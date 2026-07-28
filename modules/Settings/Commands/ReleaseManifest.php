<?php

declare(strict_types=1);

namespace Modules\Settings\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Modules\Settings\Libraries\ManifestVerifier;
use Modules\Settings\Libraries\ReleaseSigner;
use Throwable;

/**
 * Builds and Ed25519-signs the release integrity manifest.
 *
 * Covers every git tracked file, including public/be-assets, because those assets
 * are served straight into the admin browser. Refuses to run on a dirty working
 * tree so the manifest always describes the exact committed tree.
 *
 * Usage: php spark ci4ms:release:manifest --keyfile ~/.ci4ms/release.key --version 0.35.0.0
 */
class ReleaseManifest extends BaseCommand
{
    use SecretPromptTrait;

    protected $group       = 'Ci4MS';
    protected $name        = 'ci4ms:release:manifest';
    protected $description = 'Build and sign the release integrity manifest for the current git tree.';
    protected $usage       = 'ci4ms:release:manifest [--keyfile <path>] [--version <x.y.z.w>] [--repo <owner/name>] [--out <dir>]';

    /**
     * @var array<string, string>
     */
    protected $options = [
        '--keyfile' => 'Path of the sealed signing keyfile (outside the project root).',
        '--version' => 'Release version written into the manifest (default: app.version from .env).',
        '--repo'    => 'Repository slug the manifest is bound to (default: ci4-cms-erp/ci4ms).',
        '--out'     => 'Output directory (default: writable/release/).',
    ];

    private const MANIFEST_SCHEMA = 1;
    private const HASH_ALGO       = 'sha256';
    private const DEFAULT_REPO    = 'ci4-cms-erp/ci4ms';

    /**
     * Generates manifest.json plus manifest.json.sig for the committed tree.
     *
     * @param array<int|string, string|null> $params
     */
    public function run(array $params): void
    {
        $root = rtrim(ROOTPATH, DIRECTORY_SEPARATOR);

        if (!$this->assertCleanTree($root)) {
            return;
        }

        $tracked = $this->trackedFiles($root);
        if ($tracked === null) {
            return;
        }

        $version = (string) (CLI::getOption('version') ?: env('app.version'));
        if (preg_match('/^\d+\.\d+\.\d+\.\d+$/', $version) !== 1) {
            CLI::error('Invalid version "' . $version . '". Expected x.y.z.w — pass --version explicitly.');

            return;
        }

        $repo   = (string) (CLI::getOption('repo') ?: self::DEFAULT_REPO);
        $outDir = rtrim((string) (CLI::getOption('out') ?: WRITEPATH . 'release'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        [$hashes, $skipped] = $this->hashTrackedFiles($root, $tracked);

        if ($hashes === []) {
            CLI::error('No hashable tracked files found.');

            return;
        }

        $manifest = $this->encodeManifest($repo, $version, gmdate('c'), $hashes);

        $signature = $this->sign($manifest);
        if ($signature === null) {
            return;
        }

        if (!is_dir($outDir) && !mkdir($outDir, 0755, true) && !is_dir($outDir)) {
            CLI::error('Could not create output directory: ' . $outDir);

            return;
        }

        $manifestPath = $outDir . 'manifest.json';
        $sigPath      = $manifestPath . '.sig';

        $sigFile = ReleaseSigner::buildSignatureFile($signature['key_id'], $signature['signature']);

        if (file_put_contents($manifestPath, $manifest, LOCK_EX) === false
            || file_put_contents($sigPath, $sigFile, LOCK_EX) === false) {
            CLI::error('Could not write the manifest files into ' . $outDir);

            return;
        }

        if (!$this->selfCheck($manifest, $sigFile, $signature)) {
            @unlink($manifestPath);
            @unlink($sigPath);
            CLI::error('Self-check FAILED: the signature this command produced does not verify. Nothing was published.');

            return;
        }

        $this->report($manifestPath, $sigPath, $manifest, $hashes, $skipped, $signature);
    }

    /**
     * Serialises the manifest document into the exact bytes that get signed.
     *
     * The file map is sorted here rather than by the caller: the signature covers
     * these bytes, so ordering must not depend on git ls-files output order.
     *
     * @param string                $repo        Repository slug the manifest binds to
     * @param string                $version     Release version
     * @param string                $generatedAt RFC 3339 build timestamp
     * @param array<string, string> $hashes      Path => sha256 hex
     *
     * @return string Raw manifest bytes
     */
    private function encodeManifest(string $repo, string $version, string $generatedAt, array $hashes): string
    {
        ksort($hashes, SORT_STRING);

        return (string) json_encode([
            'schema'       => self::MANIFEST_SCHEMA,
            'repo'         => $repo,
            'version'      => $version,
            'generated_at' => $generatedAt,
            'algo'         => self::HASH_ALGO,
            'files'        => $hashes,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Re-verifies the freshly written pair with the shipped verifier.
     *
     * Catches the "signed it, but nothing can verify it" class before the assets
     * are uploaded: the keyring is built from the public half of the signing key,
     * so this proves the bytes on disk satisfy the updater's own gate.
     *
     * @param string                                                                            $manifest  Raw manifest bytes
     * @param string                                                                            $sigFile   Raw signature carrier bytes
     * @param array{key_id: string, public_key: string, fingerprint: string, signature: string} $signature Signing result
     *
     * @return bool
     */
    private function selfCheck(string $manifest, string $sigFile, array $signature): bool
    {
        $verifier = new ManifestVerifier([
            $signature['key_id'] => [
                'public_key'  => $signature['public_key'],
                'status'      => 'active',
                'added'       => gmdate('Y-m-d'),
                'fingerprint' => $signature['fingerprint'],
            ],
        ]);

        $result = $verifier->verify($manifest, $sigFile);

        if ($result['ok'] === false) {
            CLI::error('Verifier rejected the generated pair: ' . $result['code']);

            return false;
        }

        return true;
    }

    /**
     * Aborts unless `git status --porcelain` reports a clean tree.
     *
     * stderr is kept out of the parsed output: a git warning printed alongside a
     * successful run used to be read back as a dirty path.
     */
    private function assertCleanTree(string $root): bool
    {
        [$status, $output, $stderr] = $this->git($root, ['status', '--porcelain']);

        if ($status !== 0) {
            CLI::error('git status failed: ' . ($stderr !== '' ? $stderr : 'exit code ' . $status));

            return false;
        }

        if ($output !== []) {
            CLI::error('Working tree is dirty — commit or stash before building a release manifest.');
            foreach (array_slice($output, 0, 15) as $line) {
                CLI::write('  ' . $line, 'yellow');
            }
            if (count($output) > 15) {
                CLI::write('  ... ' . (count($output) - 15) . ' more', 'yellow');
            }

            return false;
        }

        return true;
    }

    /**
     * Lists every git tracked path.
     *
     * @return list<string>|null Null when git could not be queried
     */
    private function trackedFiles(string $root): ?array
    {
        [$status, $output, $stderr] = $this->git($root, ['-c', 'core.quotePath=false', 'ls-files']);

        if ($status !== 0 || $output === []) {
            CLI::error('git ls-files failed or returned nothing.' . ($stderr === '' ? '' : ' ' . $stderr));

            return null;
        }

        return $output;
    }

    /**
     * Runs a git command in the project root, keeping stdout and stderr apart.
     *
     * @param string       $root Repository root passed to `git -C`
     * @param list<string> $args Arguments, each escaped individually
     *
     * @return array{0: int, 1: list<string>, 2: string} Exit code, non-empty stdout lines, trimmed stderr
     */
    private function git(string $root, array $args): array
    {
        $command = 'git -C ' . escapeshellarg($root) . ' ' . implode(' ', array_map('escapeshellarg', $args));
        $pipes   = [];
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (!is_resource($process)) {
            return [-1, [], 'git could not be started'];
        }

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        $status = proc_close($process);
        $lines  = array_values(array_filter(
            array_map('trim', explode("\n", $stdout)),
            static fn (string $line): bool => $line !== ''
        ));

        return [$status, $lines, trim($stderr)];
    }

    /**
     * Hashes every tracked path that is a readable regular file.
     *
     * @param list<string> $tracked
     *
     * @return array{0: array<string, string>, 1: list<string>} Hash map plus the paths that were skipped
     */
    private function hashTrackedFiles(string $root, array $tracked): array
    {
        $hashes  = [];
        $skipped = [];

        foreach ($tracked as $path) {
            $absolute = $root . DIRECTORY_SEPARATOR . $path;

            if (!is_file($absolute) || !is_readable($absolute)) {
                $skipped[] = $path;
                continue;
            }

            $hash = hash_file(self::HASH_ALGO, $absolute);
            if ($hash === false) {
                $skipped[] = $path;
                continue;
            }

            $hashes[$path] = $hash;
        }

        return [$hashes, $skipped];
    }

    /**
     * Prompts for the keyfile passphrase and signs the manifest bytes.
     *
     * @return array{key_id: string, public_key: string, fingerprint: string, signature: string}|null
     */
    private function sign(string $manifest): ?array
    {
        $keyfile = (string) (CLI::getOption('keyfile') ?: CLI::prompt('Signing keyfile path', null, ['required']));

        try {
            $signer = new ReleaseSigner($keyfile);
        } catch (Throwable $e) {
            CLI::error($e->getMessage());

            return null;
        }

        $password = $this->promptSecret('Keyfile passphrase');

        try {
            return $signer->sign($password, $manifest);
        } catch (Throwable $e) {
            CLI::error($e->getMessage());

            return null;
        } finally {
            sodium_memzero($password);
        }
    }

    /**
     * Prints the manifest size, coverage and signer details.
     *
     * @param array<string, string>                                                        $hashes
     * @param list<string>                                                                 $skipped
     * @param array{key_id: string, public_key: string, fingerprint: string, signature: string} $signature
     */
    private function report(string $manifestPath, string $sigPath, string $manifest, array $hashes, array $skipped, array $signature): void
    {
        CLI::newLine();
        CLI::write('Manifest written : ' . $manifestPath, 'green');
        CLI::write('Signature written: ' . $sigPath, 'green');
        CLI::write('Files covered    : ' . count($hashes));
        CLI::write('Manifest size    : ' . number_format(strlen($manifest) / 1024, 1) . ' KB');
        CLI::write('Manifest sha256  : ' . hash(self::HASH_ALGO, $manifest));
        CLI::write('Signed by        : ' . $signature['key_id'] . ' (' . $signature['fingerprint'] . ')');

        if ($skipped !== []) {
            CLI::newLine();
            CLI::write('Skipped ' . count($skipped) . ' tracked path(s) that are not readable regular files:', 'yellow');
            foreach (array_slice($skipped, 0, 10) as $path) {
                CLI::write('  ' . $path, 'yellow');
            }
        }

        CLI::newLine();
        CLI::write('Upload both files as release assets named manifest.json and manifest.json.sig.', 'light_gray');
    }
}
