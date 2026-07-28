<?php

declare(strict_types=1);

namespace Modules\Settings\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Services;
use Modules\Settings\Config\UpdateKeys;
use Modules\Settings\Libraries\ManifestVerifier;
use Throwable;

/**
 * Verifies a release manifest against the configured trusted keyring.
 *
 * Local mode checks the files produced by ci4ms:release:manifest. Remote mode
 * downloads the published assets so a publisher can confirm from the outside that
 * what GitHub serves is what they signed.
 *
 * Usage: php spark ci4ms:release:verify --remote --tag v0.35.0.0
 */
class ReleaseVerify extends BaseCommand
{
    protected $group       = 'Ci4MS';
    protected $name        = 'ci4ms:release:verify';
    protected $description = 'Verify a local or published release manifest against the trusted keyring.';
    protected $usage       = 'ci4ms:release:verify [--dir <path>] [--remote] [--tag <vX.Y.Z.W>] [--repo <owner/name>]';

    /**
     * @var array<string, string>
     */
    protected $options = [
        '--dir'    => 'Directory holding manifest.json and manifest.json.sig (default: writable/release/).',
        '--remote' => 'Download the published release assets from GitHub instead of reading local files.',
        '--tag'    => 'Release tag to verify in remote mode (default: the latest release).',
        '--repo'   => 'Repository slug (default: ci4-cms-erp/ci4ms).',
    ];

    private const MANIFEST_ASSET  = 'manifest.json';
    private const SIGNATURE_ASSET = 'manifest.json.sig';
    private const DEFAULT_REPO    = 'ci4-cms-erp/ci4ms';
    private const ASSET_TIMEOUT   = 60;

    /**
     * Loads the manifest pair, verifies the signature and prints the outcome.
     *
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): void
    {
        $repo = (string) (CLI::getOption('repo') ?: self::DEFAULT_REPO);

        $pair = CLI::getOption('remote') ? $this->remotePair($repo) : $this->localPair();
        if ($pair === null) {
            return;
        }

        $keyring = config(UpdateKeys::class)->keys;
        if ($keyring === []) {
            CLI::error('No trusted keys configured in modules/Settings/Config/UpdateKeys.php — nothing can be verified.');

            return;
        }

        $verifier = new ManifestVerifier($keyring);
        $result   = $verifier->verify($pair['manifest'], $pair['signature']);

        if ($result['ok'] === false) {
            CLI::error('Verification FAILED: ' . $result['code']);

            return;
        }

        $manifest    = $result['manifest'] ?? [];
        $keyId       = (string) $result['key_id'];
        $publicKey   = (string) ($keyring[$keyId]['public_key'] ?? '');
        $fingerprint = ManifestVerifier::fingerprint($publicKey);

        CLI::newLine();
        CLI::write('Signature VALID', 'green');
        CLI::write('Source      : ' . $pair['source']);
        CLI::write('Signed by   : ' . $keyId);
        CLI::write('Fingerprint : ' . $fingerprint);
        CLI::write('Repo        : ' . (string) ($manifest['repo'] ?? ''));
        CLI::write('Version     : ' . (string) ($manifest['version'] ?? ''));
        CLI::write('Generated   : ' . (string) ($manifest['generated_at'] ?? ''));
        CLI::write('Files       : ' . count((array) ($manifest['files'] ?? [])));

        if ((string) ($manifest['repo'] ?? '') !== $repo) {
            CLI::newLine();
            CLI::error('Manifest repo does not match --repo (' . $repo . ') — the updater would reject this release.');
        }
    }

    /**
     * Reads manifest.json and manifest.json.sig from a local directory.
     *
     * @return array{manifest: string, signature: string, source: string}|null
     */
    private function localPair(): ?array
    {
        $dir          = rtrim((string) (CLI::getOption('dir') ?: WRITEPATH . 'release'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $manifestPath = $dir . self::MANIFEST_ASSET;
        $sigPath      = $dir . self::SIGNATURE_ASSET;

        foreach ([$manifestPath, $sigPath] as $path) {
            if (!is_file($path) || !is_readable($path)) {
                CLI::error('Not found or unreadable: ' . $path);

                return null;
            }
        }

        return [
            'manifest'  => (string) file_get_contents($manifestPath),
            'signature' => (string) file_get_contents($sigPath),
            'source'    => $dir,
        ];
    }

    /**
     * Downloads the published manifest assets for a tag (or the latest release).
     *
     * @return array{manifest: string, signature: string, source: string}|null
     */
    private function remotePair(string $repo): ?array
    {
        $tag     = trim((string) (CLI::getOption('tag') ?: ''));
        $release = $this->fetchRelease($repo, $tag);

        if ($release === null) {
            return null;
        }

        $urls = [];
        foreach ((array) ($release['assets'] ?? []) as $asset) {
            $name = $asset['name'] ?? null;
            $url  = $asset['browser_download_url'] ?? null;
            if (is_string($name) && is_string($url) && ($name === self::MANIFEST_ASSET || $name === self::SIGNATURE_ASSET)) {
                $urls[$name] = $url;
            }
        }

        if (!isset($urls[self::MANIFEST_ASSET], $urls[self::SIGNATURE_ASSET])) {
            CLI::error('Release "' . (string) ($release['tag_name'] ?? '?') . '" does not publish both manifest assets.');

            return null;
        }

        $manifest  = $this->download($urls[self::MANIFEST_ASSET]);
        $signature = $this->download($urls[self::SIGNATURE_ASSET]);

        if ($manifest === null || $signature === null) {
            CLI::error('Could not download the manifest assets.');

            return null;
        }

        return [
            'manifest'  => $manifest,
            'signature' => $signature,
            'source'    => 'github:' . $repo . '@' . (string) ($release['tag_name'] ?? ''),
        ];
    }

    /**
     * Resolves a release payload by tag, falling back to the latest release.
     *
     * The release list endpoint is used for tags so draft releases are visible
     * when GITHUB_TOKEN is configured.
     *
     * @return array<string, mixed>|null
     */
    private function fetchRelease(string $repo, string $tag): ?array
    {
        $url = $tag === ''
            ? "https://api.github.com/repos/{$repo}/releases/latest"
            : "https://api.github.com/repos/{$repo}/releases?per_page=100";

        try {
            $response = Services::curlrequest(['timeout' => 30, 'connect_timeout' => 10])
                ->request('GET', $url, ['headers' => $this->githubHeaders(), 'http_errors' => false]);
        } catch (Throwable $e) {
            CLI::error('GitHub request failed: ' . $e->getMessage());

            return null;
        }

        if ($response->getStatusCode() !== 200) {
            CLI::error('GitHub returned HTTP ' . $response->getStatusCode() . ' for ' . $url);

            return null;
        }

        $payload = json_decode((string) $response->getBody(), true);

        if ($tag === '') {
            return is_array($payload) ? $payload : null;
        }

        foreach ((array) $payload as $release) {
            if (is_array($release) && (string) ($release['tag_name'] ?? '') === $tag) {
                return $release;
            }
        }

        CLI::error('No release found with tag ' . $tag . ' (drafts need a token in github.token).');

        return null;
    }

    /**
     * Downloads an asset body.
     *
     * browser_download_url 302-redirects to a CDN and CI4 does not follow
     * redirects unless allow_redirects is present in the options array. The
     * redirect is confined to https so a hostile hop cannot downgrade it to
     * plaintext. No Authorization header is sent so credentials never cross the
     * redirect.
     */
    private function download(string $url): ?string
    {
        try {
            $response = Services::curlrequest(['timeout' => self::ASSET_TIMEOUT, 'connect_timeout' => 10])
                ->request('GET', $url, [
                    'headers'         => ['User-Agent' => 'CI4ms-Release-Verify', 'Accept' => 'application/octet-stream'],
                    'http_errors'     => false,
                    'allow_redirects' => ['protocols' => ['https'], 'max' => 3, 'strict' => true],
                    'decode_content'  => true,
                ]);
        } catch (Throwable $e) {
            CLI::write('Asset download failed: ' . $e->getMessage(), 'yellow');

            return null;
        }

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $body = (string) $response->getBody();

        return $body === '' ? null : $body;
    }

    /**
     * @return array<string, string>
     */
    private function githubHeaders(): array
    {
        $headers = [
            'User-Agent' => 'CI4ms-Release-Verify',
            'Accept'     => 'application/vnd.github.v3+json',
        ];

        if ($token = env('github.token')) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }
}
