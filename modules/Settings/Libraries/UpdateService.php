<?php

declare(strict_types=1);

namespace Modules\Settings\Libraries;

use CodeIgniter\HTTP\CURLRequest;
use Config\Services;
use Modules\Backend\Libraries\RunLock;
use Modules\Settings\Config\UpdateKeys;

/**
 * CI4MS Update Service
 *
 * Manages GitHub API based updates, patch downloading, and automatic apply
 * operations. Every update is verified against a release manifest signed by
 * the publisher with Ed25519; not a single file is downloaded or written
 * before the signature is verified.
 */
class UpdateService
{
    private const MANIFEST_ASSET  = 'manifest.json';
    private const SIGNATURE_ASSET = 'manifest.json.sig';
    private const ASSET_TIMEOUT   = 60;

    /** Largest release asset or single source file the updater will hold in memory. */
    private const MAX_BODY_BYTES = 8388608;

    /** GitHub tag names the updater accepts; anything else is attacker controlled text. */
    private const TAG_PATTERN = '/^v?\d+(?:\.\d+){1,3}$/';

    /** Repository relative paths the updater accepts from the compare API. */
    private const PATH_PATTERN = '#^[A-Za-z0-9._/-]+$#';

    /** Host that is allowed to serve commit detail payloads. */
    private const API_HOST = 'api.github.com';

    /** Redirect policy for asset downloads: followed, but never downgraded to plaintext. */
    private const REDIRECT_POLICY = ['protocols' => ['https'], 'max' => 3, 'strict' => true];

    private string $repo = 'ci4-cms-erp/ci4ms';
    private CURLRequest $client;
    private string $backupBaseDir;
    private ManifestVerifier $verifier;

    /**
     * @var array<array-key, mixed> Trusted signing keys (key_id => entry)
     */
    private array $keyring;

    /**
     * @param CURLRequest|null $client Test injection; produced from the CI4 service if null
     */
    public function __construct(?CURLRequest $client = null)
    {
        $this->client = $client ?? Services::curlrequest([
            // CI4 default: connect 150s, transfer unlimited — bounded so a
            // GitHub outage doesn't hang the backend AJAX and update worker.
            'timeout'         => 15,
            'connect_timeout' => 5,
        ]);
        $this->backupBaseDir = WRITEPATH . 'backups/';
        $this->keyring = config(UpdateKeys::class)->keys;
        $this->verifier = new ManifestVerifier($this->keyring);
    }

    /**
     * Checks the latest version via the GitHub Releases API.
     *
     * No field in the response is treated as trusted: the tag name is not
     * used before it passes a strict version allowlist, because this value
     * is shown to the admin on the backend and runs before the signature gate.
     *
     * @return array
     */
    public function checkVersion(): array
    {
        $currentVersion = (string) env('app.version');
        $headers = $this->getGithubHeaders();

        try {
            $response = $this->client->request('GET', "https://api.github.com/repos/{$this->repo}/releases/latest", [
                'headers'     => $headers,
                'http_errors' => false,
            ]);

            $release = json_decode((string) $response->getBody());

            if (empty($release) || !isset($release->tag_name)) {
                return ['result' => false, 'message' => lang('Settings.noTagsFound')];
            }

            $tagName = is_string($release->tag_name) ? $release->tag_name : '';
            if (preg_match(self::TAG_PATTERN, $tagName) !== 1) {
                log_message('warning', 'Update check aborted: release tag is not a plain version string.');

                return ['result' => false, 'message' => lang('Settings.invalidVersionFormat')];
            }

            $latestVersion = ltrim($tagName, 'v');

            if (version_compare($latestVersion, $currentVersion, '>')) {
                // Fetch the changed files (with pagination support)
                $changedFiles = $this->fetchAllChangedFiles($currentVersion, $latestVersion);
                $assets = $this->assetUrls($release->assets ?? []);

                return [
                    'result'           => true,
                    'update_available' => true,
                    'latest_version'   => $latestVersion, // Backward compatibility
                    'new_version'      => $latestVersion, // What the JS expects
                    'current_version'  => $currentVersion,
                    'release_notes'    => $release->body ?? '',
                    'changed_files'    => $changedFiles,
                    'changed_count'    => count($changedFiles),
                    'compare_url'      => "https://github.com/{$this->repo}/compare/{$currentVersion}...{$latestVersion}",
                    'download_url'     => "https://github.com/{$this->repo}/archive/refs/tags/v{$latestVersion}.zip",
                    // For the badge: asset presence; the real verification happens in fetchManifest().
                    'signed'           => $assets[self::MANIFEST_ASSET] !== null && $assets[self::SIGNATURE_ASSET] !== null
                ];
            }

            return ['result' => true, 'update_available' => false, 'message' => lang('Settings.alreadyLastVersion')];
        } catch (\Exception $e) {
            return ['result' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Downloads and verifies the signed release manifest for the requested version.
     *
     * /releases/latest is called once; the manifest and signature asset URLs
     * are taken from the browser_download_url fields in that response (no
     * extra asset API call is made). Signature verification is performed on
     * the raw HTTP body; the JSON is only decoded after verification passes.
     *
     * @param string $expectedVersion The version requested for installation (without the v prefix)
     *
     * @return array{result: bool, message?: string, code?: string, manifest?: array, hashes?: array<string, string>, key_id?: string, fingerprint?: string}
     */
    public function fetchManifest(string $expectedVersion): array
    {
        try {
            $response = $this->client->request('GET', "https://api.github.com/repos/{$this->repo}/releases/latest", [
                'headers'     => $this->getGithubHeaders(),
                'http_errors' => false,
            ]);
        } catch (\Exception $e) {
            return $this->manifestUnreachable('release lookup failed: ' . $e->getMessage());
        }

        if ($response->getStatusCode() !== 200) {
            return $this->manifestUnreachable('release lookup returned HTTP ' . $response->getStatusCode());
        }

        $release = json_decode((string) $response->getBody(), true);
        if (!is_array($release) || !isset($release['tag_name'])) {
            return $this->manifestUnreachable('release payload has no tag_name');
        }

        if (ltrim((string) $release['tag_name'], 'v') !== $expectedVersion) {
            return $this->signatureInvalid('tag_mismatch', 'latest release tag does not match the requested version');
        }

        $assets = $this->assetUrls($release['assets'] ?? []);
        if ($assets[self::MANIFEST_ASSET] === null || $assets[self::SIGNATURE_ASSET] === null) {
            return $this->manifestUnreachable('release does not publish both manifest assets');
        }

        $manifestFailure  = null;
        $signatureFailure = null;

        $rawManifest  = $this->downloadAsset($assets[self::MANIFEST_ASSET], $manifestFailure);
        $rawSignature = $this->downloadAsset($assets[self::SIGNATURE_ASSET], $signatureFailure);

        if ($manifestFailure === 'oversize' || $signatureFailure === 'oversize') {
            return $this->assetTooLarge('a manifest asset exceeded ' . self::MAX_BODY_BYTES . ' bytes');
        }

        if ($rawManifest === null || $rawSignature === null) {
            return $this->manifestUnreachable('manifest or signature asset could not be downloaded');
        }

        $verified = $this->verifier->verify($rawManifest, $rawSignature);
        if ($verified['ok'] === false) {
            return $verified['code'] === 'no_trusted_keys'
                ? $this->noTrustedKeys()
                : $this->signatureInvalid($verified['code'], 'manifest signature rejected');
        }

        $manifest = $verified['manifest'] ?? [];
        $binding  = $this->verifier->checkBinding($manifest, $this->repo, $expectedVersion, (string) env('app.version'));
        if ($binding['ok'] === false) {
            return $this->signatureInvalid($binding['code'], 'manifest binding rejected');
        }

        $keyId = (string) $verified['key_id'];

        return [
            'result'      => true,
            'manifest'    => $manifest,
            'hashes'      => (array) ($manifest['files'] ?? []),
            'key_id'      => $keyId,
            'fingerprint' => ManifestVerifier::fingerprint((string) ($this->keyring[$keyId]['public_key'] ?? '')),
        ];
    }

    /**
     * Downloads the changed files raw and either prepares a patch file or
     * returns them for direct application.
     *
     * The signed manifest gate runs BEFORE any file is downloaded: if the
     * gate is closed, zero files are downloaded. Even a single file that is
     * absent from the manifest or whose SHA-256 doesn't match aborts the
     * entire operation; there is no partial apply.
     *
     * The unsigned compare response cannot contradict the signed manifest:
     * showing a file the manifest still publishes as "removed" is a
     * suppression attack and aborts the whole update. An empty set of files
     * to apply is also not a valid outcome.
     *
     * @param string $currentVersion
     * @param string $latestVersion
     * @return array
     */
    public function downloadPatchRaw(string $currentVersion, string $latestVersion): array
    {
        $gate = $this->fetchManifest($latestVersion);
        if ($gate['result'] === false) {
            return $gate;
        }

        $manifest = $gate['manifest'];

        try {
            $files = $this->fetchAllChangedFiles($currentVersion, $latestVersion);
        } catch (\Exception $e) {
            return ['result' => false, 'message' => $e->getMessage()];
        }

        if (empty($files)) {
            return ['result' => false, 'message' => lang('Settings.noChangesFound')];
        }

        $downloaded = [];
        $failed = [];

        foreach ($files as $file) {
            $signedHash = $this->verifier->fileHash($manifest, $file['filename']);

            if ($file['status'] === 'removed') {
                // If the signed manifest still publishes the file, the "removed" claim is a contradiction.
                if ($signedHash !== null) {
                    return $this->signatureInvalid('removed_but_signed', 'compare claims a file was removed while the signed manifest still ships it: ' . $file['filename']);
                }

                continue;
            }

            // A file outside the manifest scope is a manifest-exclusion attack.
            if ($signedHash === null) {
                return $this->signatureInvalid('file_not_in_manifest', 'changed file is absent from the signed manifest: ' . $file['filename']);
            }

            // The sha in the compare response is only metadata; hash_equals() throws a TypeError if it's not a string.
            $blobSha = $file['sha'] ?? null;
            if ($blobSha !== null && !is_string($blobSha)) {
                log_message('warning', 'Update download rejected a changed file whose compare sha is not a string: ' . $file['filename']);
                $failed[] = $file['filename'];
                continue;
            }

            $url = "https://raw.githubusercontent.com/{$this->repo}/{$latestVersion}/" . ltrim($file['filename'], '/');
            try {
                $response = $this->client->request('GET', $url, [
                    'http_errors'     => false,
                    'allow_redirects' => self::REDIRECT_POLICY,
                ]);

                if ($response->getStatusCode() === 200) {
                    if ($this->declaredSizeExceeds($response->getHeaderLine('Content-Length'), self::MAX_BODY_BYTES)) {
                        return $this->assetTooLarge('source file declares more than ' . self::MAX_BODY_BYTES . ' bytes: ' . $file['filename']);
                    }

                    $body = (string) $response->getBody();

                    if (strlen($body) > self::MAX_BODY_BYTES) {
                        return $this->assetTooLarge('source file body exceeds ' . self::MAX_BODY_BYTES . ' bytes: ' . $file['filename']);
                    }

                    // The SHA-1 blob match is only an unauthenticated pre-filter; the authoritative gate is the manifest SHA-256.
                    if (is_string($blobSha) && !hash_equals($blobSha, $this->gitBlobSha($body))) {
                        $failed[] = $file['filename'];
                        continue;
                    }
                    if (!$this->verifier->verifyFile($manifest, $file['filename'], $body)) {
                        return $this->signatureInvalid('file_hash_mismatch', 'downloaded file does not match the signed hash: ' . $file['filename']);
                    }
                    $downloaded[$file['filename']] = $body;
                } else {
                    $failed[] = $file['filename'];
                }
            } catch (\Exception $e) {
                log_message('warning', 'Update download failed for ' . $file['filename'] . ': ' . $e->getMessage());
                $failed[] = $file['filename'];
            }
        }

        // If zero files were applied, the version would still bump; that's the case where a suppression attack wins.
        if ($downloaded === [] && $failed === []) {
            return $this->signatureInvalid('empty_apply_set', 'the signed release resolved to zero applicable files');
        }

        $result = [
            'result'        => empty($failed),
            'files'         => $downloaded,
            'all_files'     => $files,
            'failed'        => $failed,
            'total'         => count($files),
            'signed_hashes' => $gate['hashes'],
            'key_id'        => $gate['key_id'],
            'fingerprint'   => $gate['fingerprint']
        ];

        if (!empty($failed)) {
            $result['message'] = lang('Settings.updateDownloadFailed', [count($failed)]);
        }

        return $result;
    }

    /**
     * Applies the update atomically.
     *
     * A signed hash map is mandatory: every file's content is re-verified
     * before the write loop, even before the backup directory is created.
     * No code path can enter this method with an unverified file set. An
     * empty file set is also rejected: otherwise the .env version would bump
     * without anything being written, and the installation would never pull
     * that update again.
     *
     * Concurrency: guarded by `Modules\Backend\Libraries\RunLock` at
     * `WRITEPATH.'locks/updater.lock'` — its own path, never shared with
     * `Modules\MigrationManager`'s or `Ci4msMigrate`'s lock file. `acquire()`
     * is checked before any write; `release()` runs in a `finally` covering
     * both the success path and the `catch (\Exception)` rollback path, so
     * an uncaught `\Error`/`TypeError` also releases the lock instead of
     * leaving it held.
     *
     * Do not drop that `finally` on the grounds that the lock frees itself
     * anyway. It does today, but only by accident of shape: `$lock` is a local,
     * so unwinding the stack drops its refcount to zero, the file handle closes
     * and flock lets go. Assign `$lock` to a property, capture it in a closure,
     * or bind it to a reference and that implicit release disappears — the lock
     * file just stays held until the process dies. `finally` ties the release to
     * control flow rather than to object lifetime, which is the only version of
     * it that survives refactoring; deleting it turns
     * `UpdateServiceRunLockTest::testTheLockIsFreeBeforeApplyUpdateDestroysItsLocals`
     * red.
     *
     * @param string                $latestVersion
     * @param array                 $filesContent    [path => content]
     * @param array                 $allChangedFiles Raw file list with status
     * @param array<string, string> $signedHashes    Signed manifest hash map [path => sha256]
     * @return array
     */
    public function applyUpdate(string $latestVersion, array $filesContent, array $allChangedFiles, array $signedHashes): array
    {
        if ($filesContent === []) {
            log_message('critical', 'Update aborted: applyUpdate() called with an empty file set.');

            return ['result' => false, 'message' => lang('Settings.updateSignatureInvalid')];
        }

        if (!$this->assertSignedContent($filesContent, $signedHashes)) {
            return ['result' => false, 'message' => lang('Settings.updateSignatureInvalid')];
        }

        $lock = new RunLock(WRITEPATH . 'locks/updater.lock');
        if (!$lock->acquire()) {
            return ['result' => false, 'message' => lang('Settings.updateInProgress')];
        }

        $currentVersion = (string) env('app.version');
        $backupDir = $this->backupBaseDir . "v{$currentVersion}_to_v{$latestVersion}_" . date('Ymd_His') . '/';
        $appliedFiles = [];
        $removedFiles = [];

        try {
            if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

            // 1. Detect the files to delete
            foreach ($allChangedFiles as $f) {
                if ($f['status'] === 'removed') {
                    $removedFiles[] = $f['filename'];
                }
            }

            // 2. Apply the files (Atomic Write)
            foreach ($filesContent as $path => $content) {
                $targetFile = $this->safeTargetPath((string) $path);
                $targetDir = dirname($targetFile);

                // Backup
                if (file_exists($targetFile)) {
                    $this->ensureDirectory(dirname($backupDir . $path));
                    copy($targetFile, $backupDir . $path);
                }

                // Directory check
                $this->ensureDirectory($targetDir);

                if (!$this->directoryInsideRoot($targetDir)) {
                    throw new \RuntimeException("Hedef dizin proje kökünün dışına çıkıyor: {$path}");
                }

                // Atomic Write: create a temp file and rename it
                $tmpFile = $targetFile . '.update_tmp';
                if (file_put_contents($tmpFile, $content) === false) {
                    throw new \Exception("Dosya yazılamadı: {$path}");
                }

                if (!rename($tmpFile, $targetFile)) {
                    @unlink($tmpFile);
                    throw new \Exception("Dizin taşıma/yeniden adlandırma hatası: {$path}");
                }

                $appliedFiles[] = $path;
            }

            // 3. .env update — only if the entire verified set was written
            if (count($appliedFiles) !== count($filesContent)) {
                throw new \RuntimeException('Applied file count does not match the verified file set.');
            }

            $this->updateEnvVersion($latestVersion);

            // 4. Cleanup and SQL Migrations
            $this->runMigrations();
            cache()->clean();

            return [
                'result'        => true,
                'applied_count' => count($appliedFiles),
                'removed_files' => $removedFiles,
                'backup_dir'    => $backupDir
            ];

        } catch (\Exception $e) {
            $this->rollback($backupDir, $appliedFiles);
            return ['result' => false, 'message' => $e->getMessage()];
        } finally {
            $lock->release();
        }
    }

    /**
     * Restores from a specific backup.
     *
     * Every path coming from the backup directory is verified to stay inside
     * the project root before being written to its target; an invalid path
     * stops the entire restore.
     */
    public function rollback(string $backupDir, array $filesToRestore): bool
    {
        if (!is_dir($backupDir)) return false;

        foreach ($filesToRestore as $path) {
            $source = $backupDir . $path;
            $target = $this->safeTargetPath((string) $path);

            if (file_exists($source)) {
                if (!$this->directoryInsideRoot(dirname($target))) {
                    log_message('warning', 'Rollback skipped a path that does not resolve inside the project root: ' . $path);
                    continue;
                }

                @copy($source, $target);
            }
        }

        return true;
    }

    /**
     * Lists the recorded backups.
     */
    public function listBackups(): array
    {
        if (!is_dir($this->backupBaseDir)) return [];

        $dirs = glob($this->backupBaseDir . '*', GLOB_ONLYDIR);
        $backups = [];

        foreach ($dirs as $dir) {
            $backups[] = [
                'name' => basename($dir),
                'path' => $dir,
                'date' => date('Y-m-d H:i:s', filemtime($dir))
            ];
        }

        // Newest first
        usort($backups, fn($a, $b) => $b['date'] <=> $a['date']);

        return $backups;
    }

    /**
     * Resolves a backup name coming from the user against the real backup
     * list on disk.
     *
     * The name never enters path concatenation at any point: it's narrowed
     * with basename, matched against listBackups() output, and the directory
     * path is taken from the matched entry's own path. The realpath check
     * after matching prevents a symlink placed in the backups directory from
     * escaping to a source directory outside it.
     *
     * @param string $name Raw POST value
     *
     * @return string|null Absolute directory path ending in '/'; null if no match
     */
    public function resolveBackupDir(string $name): ?string
    {
        $name = basename(trim($name));
        if ($name === '' || $name === '.' || $name === '..') {
            return null;
        }

        $base = realpath($this->backupBaseDir);
        if ($base === false) {
            return null;
        }

        foreach ($this->listBackups() as $item) {
            if (!hash_equals($item['name'], $name)) {
                continue;
            }

            $real = realpath($item['path']);
            if ($real === false || !is_dir($real) || dirname($real) !== $base) {
                log_message('warning', 'Backup entry resolved outside the backup directory: ' . $item['path']);

                return null;
            }

            return $real . '/';
        }

        return null;
    }

    // --- Private Helpers ---

    /**
     * Re-verifies every file to be applied against the signed hash map.
     *
     * @param array<string, string> $filesContent
     * @param array<string, string> $signedHashes
     */
    private function assertSignedContent(array $filesContent, array $signedHashes): bool
    {
        if ($signedHashes === []) {
            log_message('critical', 'Update aborted: applyUpdate() called without a signed manifest hash map.');

            return false;
        }

        foreach ($filesContent as $path => $content) {
            $expected = $signedHashes[$path] ?? null;

            if (!is_string($expected) || !hash_equals($expected, hash('sha256', (string) $content))) {
                log_message('critical', 'Update aborted: unsigned or tampered file reached applyUpdate(): ' . $path);

                return false;
            }
        }

        return true;
    }

    /**
     * Extracts the manifest and signature download URLs from the release asset list.
     *
     * @param mixed $assets GitHub release assets field (array or list of stdClass)
     *
     * @return array{"manifest.json": string|null, "manifest.json.sig": string|null}
     */
    private function assetUrls(mixed $assets): array
    {
        $urls = [self::MANIFEST_ASSET => null, self::SIGNATURE_ASSET => null];

        foreach ((array) $assets as $asset) {
            $asset = (array) $asset;
            $name  = $asset['name'] ?? null;
            $url   = $asset['browser_download_url'] ?? null;

            if (is_string($name) && is_string($url) && array_key_exists($name, $urls)) {
                $urls[$name] = $url;
            }
        }

        return $urls;
    }

    /**
     * Downloads a release asset.
     *
     * browser_download_url redirects to the CDN with a 302, and CI4's
     * CURLRequest never sets CURLOPT_FOLLOWLOCATION unless allow_redirects is
     * in the options array. decode_content lets curl handle decompression
     * itself; sending Accept-Encoding manually would corrupt the signature
     * with raw gzip bytes. The Authorization header is deliberately not
     * sent: credentials must not leak to the CDN after the redirect. The
     * redirect is only followed over https, otherwise an attacker could
     * downgrade it to plaintext with a 302.
     *
     * @param string      $url     Asset address to download
     * @param string|null $failure Output parameter: 'transport', 'http', 'oversize', or 'empty'
     *
     * @return string|null Body; null on failure
     */
    private function downloadAsset(string $url, ?string &$failure = null): ?string
    {
        $failure = null;

        try {
            $response = $this->client->request('GET', $url, [
                'headers'         => ['User-Agent' => 'CI4ms-Auto-Updater', 'Accept' => 'application/octet-stream'],
                'http_errors'     => false,
                'timeout'         => self::ASSET_TIMEOUT,
                'allow_redirects' => self::REDIRECT_POLICY,
                'decode_content'  => true,
            ]);
        } catch (\Exception $e) {
            log_message('warning', 'Update asset download failed for ' . $url . ': ' . $e->getMessage());
            $failure = 'transport';

            return null;
        }

        if ($response->getStatusCode() !== 200) {
            $failure = 'http';

            return null;
        }

        if ($this->declaredSizeExceeds($response->getHeaderLine('Content-Length'), self::MAX_BODY_BYTES)) {
            $failure = 'oversize';

            return null;
        }

        $body = (string) $response->getBody();

        if (strlen($body) > self::MAX_BODY_BYTES) {
            $failure = 'oversize';

            return null;
        }

        if ($body === '') {
            $failure = 'empty';

            return null;
        }

        return $body;
    }

    /**
     * Tells whether the Content-Length header exceeds the cap.
     *
     * @param string $contentLength Raw header value ('' means no info)
     * @param int    $limit         Cap in bytes
     */
    private function declaredSizeExceeds(string $contentLength, int $limit): bool
    {
        return $contentLength !== '' && ctype_digit($contentLength) && (int) $contentLength > $limit;
    }

    /**
     * Converts a relative path into an absolute target under the project root.
     *
     * Syntactic gate before writing: a null byte, an absolute path, a drive
     * letter, or a ".." segment is not accepted.
     *
     * @param string $relativePath Repository-relative path
     *
     * @return string Absolute target file path
     *
     * @throws \RuntimeException If the path is not safe
     */
    private function safeTargetPath(string $relativePath): string
    {
        if ($relativePath === '' || str_contains($relativePath, "\0")) {
            throw new \RuntimeException('Geçersiz güncelleme yolu.');
        }

        if (str_starts_with($relativePath, '/') || str_starts_with($relativePath, '\\') || preg_match('/^[A-Za-z]:/', $relativePath) === 1) {
            throw new \RuntimeException("Mutlak güncelleme yolu kabul edilmiyor: {$relativePath}");
        }

        if (in_array('..', preg_split('#[\\\\/]+#', $relativePath) ?: [], true)) {
            throw new \RuntimeException("Güncelleme yolu üst dizine çıkıyor: {$relativePath}");
        }

        return ROOTPATH . $relativePath;
    }

    /**
     * Tells whether a resolved directory stays under the project root.
     *
     * @param string $directory Absolute directory expected to exist
     */
    private function directoryInsideRoot(string $directory): bool
    {
        $resolved = realpath($directory);
        $root     = realpath(ROOTPATH);

        if ($resolved === false || $root === false) {
            return false;
        }

        $root = rtrim($root, DIRECTORY_SEPARATOR);

        return $resolved === $root || str_starts_with($resolved, $root . DIRECTORY_SEPARATOR);
    }

    /**
     * @return array{result: false, message: string, code: string}
     */
    private function manifestUnreachable(string $reason): array
    {
        log_message('warning', 'Update manifest unreachable: ' . $reason);

        return ['result' => false, 'message' => lang('Settings.updateManifestUnreachable'), 'code' => 'manifest_unreachable'];
    }

    /**
     * @return array{result: false, message: string, code: string}
     */
    private function signatureInvalid(string $code, string $reason): array
    {
        log_message('critical', 'Update signature verification failed (' . $code . '): ' . $reason);

        return ['result' => false, 'message' => lang('Settings.updateSignatureInvalid'), 'code' => $code];
    }

    /**
     * @return array{result: false, message: string, code: string}
     */
    private function assetTooLarge(string $reason): array
    {
        log_message('critical', 'Update aborted, size limit exceeded: ' . $reason);

        return ['result' => false, 'message' => lang('Settings.updateAssetTooLarge'), 'code' => 'asset_too_large'];
    }

    /**
     * @return array{result: false, message: string, code: string}
     */
    private function noTrustedKeys(): array
    {
        log_message('critical', 'Update aborted: no active release signing key is configured in Modules\Settings\Config\UpdateKeys.');

        return ['result' => false, 'message' => lang('Settings.updateNoTrustedKeys'), 'code' => 'no_trusted_keys'];
    }

    private function getGithubHeaders(): array
    {
        $headers = [
            'User-Agent' => 'CI4ms-Auto-Updater',
            'Accept'     => 'application/vnd.github.v3+json',
        ];

        if ($token = env('github.token')) {
            $headers['Authorization'] = 'Bearer ' . $token;
        }

        return $headers;
    }

    /**
     * Collects the files changed between two versions from the compare API.
     *
     * No field in the response is trusted: file names pass through an
     * allowlist, and commit detail addresses are only requested if they are
     * this repo's commit endpoint on api.github.com (otherwise the
     * Authorization header would go to a third party).
     *
     * @return list<array{filename: string, status: string, sha: mixed}>
     */
    private function fetchAllChangedFiles(string $from, string $to): array
    {
        $headers = $this->getGithubHeaders();
        $response = $this->client->request('GET', "https://api.github.com/repos/{$this->repo}/compare/{$from}...{$to}", [
            'headers'     => $headers,
            'http_errors' => false,
        ]);

        $data = json_decode((string) $response->getBody(), true);
        if (!is_array($data) || !isset($data['files'])) return [];

        $files = [];
        $this->collectChangedFiles($data['files'], $files);

        // 300 file limit check: if there are more commits, collect the remaining files from commit details
        $commits = $data['commits'] ?? null;
        if (is_array($commits) && $commits !== [] && count($files) >= 300) {
            foreach ($commits as $commit) {
                $url = is_array($commit) ? ($commit['url'] ?? null) : null;

                if (!is_string($url) || !$this->isRepoCommitUrl($url)) {
                    log_message('warning', 'Update check skipped a commit entry whose url is not a GitHub API commit url.');
                    continue;
                }

                $cResponse = $this->client->request('GET', $url, [
                    'headers'     => $headers,
                    'http_errors' => false,
                ]);
                $cData = json_decode((string) $cResponse->getBody(), true);
                if (is_array($cData) && isset($cData['files'])) {
                    $this->collectChangedFiles($cData['files'], $files);
                }
            }
        }

        return array_values($files);
    }

    /**
     * Collects file entries from the compare/commit response, passing them
     * through the allowlist.
     *
     * @param mixed                                                 $entries The files array from the API
     * @param array<string, array{filename: string, status: string, sha: mixed}> $files   Collected files (by reference)
     */
    private function collectChangedFiles(mixed $entries, array &$files): void
    {
        if (!is_array($entries)) {
            return;
        }

        foreach ($entries as $entry) {
            $entry    = (array) $entry;
            $filename = $entry['filename'] ?? null;

            if (!is_string($filename) || preg_match(self::PATH_PATTERN, $filename) !== 1) {
                log_message('warning', 'Update check skipped a changed file whose path is not a plain repository path.');
                continue;
            }

            $files[$filename] = [
                'filename' => $filename,
                'status'   => is_string($entry['status'] ?? null) ? $entry['status'] : '',
                'sha'      => $entry['sha'] ?? null,
            ];
        }
    }

    /**
     * Tells whether a URL is this repo's GitHub API commit endpoint.
     *
     * @param string $url Raw URL taken from the commit entry
     */
    private function isRepoCommitUrl(string $url): bool
    {
        $parts = parse_url($url);

        if (!is_array($parts)) {
            return false;
        }

        $scheme = $parts['scheme'] ?? '';
        $host   = $parts['host'] ?? '';
        $path   = $parts['path'] ?? '';

        return $scheme === 'https'
            && strcasecmp($host, self::API_HOST) === 0
            && str_starts_with($path, "/repos/{$this->repo}/commits/");
    }

    private function gitBlobSha(string $content): string
    {
        return sha1('blob ' . strlen($content) . "\0" . $content);
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Updates the app.version line inside .env.
     *
     * $version comes from the signed manifest and is coerced into version
     * format by ManifestVerifier; replacement escaping is still applied
     * anyway, because preg_replace interprets "$1" / "\1" in the replacement
     * as a backreference.
     */
    private function updateEnvVersion(string $version): void
    {
        $envPath = ROOTPATH . '.env';
        if (is_writable($envPath)) {
            $content = file_get_contents($envPath);
            $content = preg_replace(
                '/^app\.version\s*=\s*[\'"]?[^\'"\n]*[\'"]?/m',
                "app.version='" . addcslashes($version, '\\$') . "'",
                $content
            );
            file_put_contents($envPath, $content);
        }
    }

    private function runMigrations(): void
    {
        $migrate = Services::migrations();
        $migrate->setNamespace('App');
        try {
            $migrate->latest();
        } catch (\Exception $e) {
            log_message('error', 'Update migration error: ' . $e->getMessage());
        }
    }
}
