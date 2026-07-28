<?php

declare(strict_types=1);

namespace Modules\Settings\Libraries;

use CodeIgniter\HTTP\CURLRequest;
use Config\Services;
use Modules\Settings\Config\UpdateKeys;

/**
 * CI4MS Update Service
 *
 * GitHub API tabanlı güncelleme, yama indirme ve otomatik uygulama işlemlerini yönetir.
 * Her güncelleme, yayıncının Ed25519 ile imzaladığı release manifest'ine karşı
 * doğrulanır; imza doğrulanmadan tek bir dosya bile indirilmez veya yazılmaz.
 */
class UpdateService
{
    private const MANIFEST_ASSET  = 'manifest.json';
    private const SIGNATURE_ASSET = 'manifest.json.sig';
    private const ASSET_TIMEOUT   = 60;

    /** Largest release asset or single source file the updater will hold in memory. */
    private const MAX_BODY_BYTES = 8388608;

    /** Seconds after which an update lock is considered stale. */
    private const LOCK_TTL = 300;

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
    private string $lockFile;
    private string $backupBaseDir;
    private ManifestVerifier $verifier;

    /**
     * @var array<array-key, mixed> Güvenilen imzalama anahtarları (key_id => entry)
     */
    private array $keyring;

    /**
     * @param CURLRequest|null $client Test enjeksiyonu; null ise CI4 servisinden üretilir
     */
    public function __construct(?CURLRequest $client = null)
    {
        $this->client = $client ?? Services::curlrequest([
            // CI4 default: connect 150 sn, transfer sınırsız — GitHub erişilemezken
            // backend AJAX'ı ve update worker'ı asmaması için sınırlandı.
            'timeout'         => 15,
            'connect_timeout' => 5,
        ]);
        $this->lockFile = WRITEPATH . 'ci4ms_update.lock';
        $this->backupBaseDir = WRITEPATH . 'backups/';
        $this->keyring = config(UpdateKeys::class)->keys;
        $this->verifier = new ManifestVerifier($this->keyring);
    }

    /**
     * GitHub Releases API üzerinden en son sürümü kontrol eder.
     *
     * Yanıttaki hiçbir alan güvenilir kabul edilmez: tag adı katı bir sürüm
     * allowlist'inden geçmeden kullanılmaz, çünkü bu değer backend'de admin'e
     * gösterilir ve imza kapısından önce çalışır.
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
                // Değişen dosyaları getir (Pagination destekli)
                $changedFiles = $this->fetchAllChangedFiles($currentVersion, $latestVersion);
                $assets = $this->assetUrls($release->assets ?? []);

                return [
                    'result'           => true,
                    'update_available' => true,
                    'latest_version'   => $latestVersion, // Geriye dönük uyumluluk
                    'new_version'      => $latestVersion, // JS'nin beklediği
                    'current_version'  => $currentVersion,
                    'release_notes'    => $release->body ?? '',
                    'changed_files'    => $changedFiles,
                    'changed_count'    => count($changedFiles),
                    'compare_url'      => "https://github.com/{$this->repo}/compare/{$currentVersion}...{$latestVersion}",
                    'download_url'     => "https://github.com/{$this->repo}/archive/refs/tags/v{$latestVersion}.zip",
                    // Rozet için: asset varlığı; gerçek doğrulama fetchManifest() içinde yapılır.
                    'signed'           => $assets[self::MANIFEST_ASSET] !== null && $assets[self::SIGNATURE_ASSET] !== null
                ];
            }

            return ['result' => true, 'update_available' => false, 'message' => lang('Settings.alreadyLastVersion')];
        } catch (\Exception $e) {
            return ['result' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * İstenen sürümün imzalı release manifest'ini indirir ve doğrular.
     *
     * /releases/latest bir kez çağrılır; manifest ve imza asset URL'leri o yanıttaki
     * browser_download_url alanlarından alınır (ek asset API çağrısı yapılmaz).
     * İmza doğrulaması ham HTTP gövdesi üzerinden yapılır, JSON ancak doğrulama
     * geçtikten sonra decode edilir.
     *
     * @param string $expectedVersion Kurulması istenen sürüm (v öneki olmadan)
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
     * Değişen dosyaları raw olarak indirip bir yama dosyası hazırlar veya doğrudan uygulama için döner.
     *
     * İmzalı manifest kapısı dosyalar indirilmeden ÖNCE çalışır: kapı kapalıysa
     * sıfır dosya indirilir. Manifest'te bulunmayan ya da SHA-256'sı tutmayan tek
     * bir dosya bile tüm işlemi iptal ettirir; kısmi uygulama yoktur.
     *
     * İmzasız compare yanıtı, imzalı manifest ile çelişemez: manifest'in hâlâ
     * yayınladığı bir dosyayı "removed" göstermek bastırma saldırısıdır ve tüm
     * güncellemeyi iptal ettirir. Uygulanacak dosya kümesinin boş kalması da
     * geçerli bir sonuç değildir.
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
                // İmzalı manifest dosyayı hâlâ yayınlıyorsa "removed" iddiası bir çelişkidir.
                if ($signedHash !== null) {
                    return $this->signatureInvalid('removed_but_signed', 'compare claims a file was removed while the signed manifest still ships it: ' . $file['filename']);
                }

                continue;
            }

            // Manifest kapsamı dışındaki bir dosya, manifestten çıkarma saldırısıdır.
            if ($signedHash === null) {
                return $this->signatureInvalid('file_not_in_manifest', 'changed file is absent from the signed manifest: ' . $file['filename']);
            }

            // Compare yanıtındaki sha yalnızca metadata; string değilse hash_equals() TypeError atar.
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

                    // SHA-1 blob eşleşmesi yalnızca yetkisiz ön filtre; yetkili kapı manifest SHA-256'dır.
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

        // Sıfır dosya uygulanacaksa sürüm yine de yükselirdi; bu, bastırma saldırısının kazandığı durumdur.
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
     * Güncellemeyi atomik olarak uygular.
     *
     * İmzalı hash haritası zorunludur: yazma döngüsünden, hatta yedek dizini
     * oluşturulmadan önce her dosyanın içeriği yeniden doğrulanır. Doğrulanmamış
     * bir dosya kümesiyle bu metoda hiçbir kod yolundan girilemez. Boş dosya
     * kümesi de reddedilir: aksi halde hiçbir şey yazılmadan .env sürümü yükselir
     * ve kurulum o güncellemeyi bir daha çekmez.
     *
     * @param string                $latestVersion
     * @param array                 $filesContent    [path => content]
     * @param array                 $allChangedFiles Raw file list with status
     * @param array<string, string> $signedHashes    İmzalı manifest hash haritası [path => sha256]
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

        if (!$this->acquireLock()) {
            return ['result' => false, 'message' => lang('Settings.updateInProgress')];
        }

        $currentVersion = (string) env('app.version');
        $backupDir = $this->backupBaseDir . "v{$currentVersion}_to_v{$latestVersion}_" . date('Ymd_His') . '/';
        $appliedFiles = [];
        $removedFiles = [];

        try {
            if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);

            // 1. Silinecek dosyaları tespit et
            foreach ($allChangedFiles as $f) {
                if ($f['status'] === 'removed') {
                    $removedFiles[] = $f['filename'];
                }
            }

            // 2. Dosyaları uygula (Atomic Write)
            foreach ($filesContent as $path => $content) {
                $targetFile = $this->safeTargetPath((string) $path);
                $targetDir = dirname($targetFile);

                // Yedekleme
                if (file_exists($targetFile)) {
                    $this->ensureDirectory(dirname($backupDir . $path));
                    copy($targetFile, $backupDir . $path);
                }

                // Dizin kontrolü
                $this->ensureDirectory($targetDir);

                if (!$this->directoryInsideRoot($targetDir)) {
                    throw new \RuntimeException("Hedef dizin proje kökünün dışına çıkıyor: {$path}");
                }

                // Atomic Write: Temp dosya oluştur ve rename yap
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

            // 3. .env Güncelleme — yalnızca doğrulanmış kümenin tamamı yazıldıysa
            if (count($appliedFiles) !== count($filesContent)) {
                throw new \RuntimeException('Applied file count does not match the verified file set.');
            }

            $this->updateEnvVersion($latestVersion);

            // 4. Temizlik ve SQL Migrations
            $this->runMigrations();
            cache()->clean();

            $this->releaseLock();
            return [
                'result'        => true,
                'applied_count' => count($appliedFiles),
                'removed_files' => $removedFiles,
                'backup_dir'    => $backupDir
            ];

        } catch (\Exception $e) {
            $this->rollback($backupDir, $appliedFiles);
            $this->releaseLock();
            return ['result' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Belirli bir yedekten geri yükleme yapar.
     *
     * Yedek dizininden gelen her yol, hedefe yazılmadan önce proje kökü içinde
     * kaldığı doğrulanır; geçersiz bir yol tüm geri yüklemeyi durdurur.
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
     * Kayıtlı yedekleri listeler.
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

        // En yeni en üstte
        usort($backups, fn($a, $b) => $b['date'] <=> $a['date']);

        return $backups;
    }

    /**
     * Kullanıcıdan gelen yedek adını diskteki gerçek yedek listesine karşı çözer.
     *
     * Ad hiçbir noktada yol birleştirmeye girmez: basename ile daraltılır,
     * listBackups() çıktısıyla eşleştirilir ve dizin yolu eşleşen kaydın kendi
     * path'inden alınır. Eşleşme sonrası realpath kontrolü, backups dizinine
     * yerleştirilmiş bir symlink'in kaynak dizini dışarı taşımasını engeller.
     *
     * @param string $name Ham POST değeri
     *
     * @return string|null Sonu '/' ile biten mutlak dizin yolu; eşleşme yoksa null
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
     * Uygulanacak her dosyayı imzalı hash haritasına karşı yeniden doğrular.
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
     * Release asset listesinden manifest ve imza indirme URL'lerini ayıklar.
     *
     * @param mixed $assets GitHub release assets alanı (dizi ya da stdClass listesi)
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
     * Bir release asset'ini indirir.
     *
     * browser_download_url 302 ile CDN'e yönlenir ve CI4 CURLRequest, options
     * dizisinde allow_redirects yoksa CURLOPT_FOLLOWLOCATION'ı hiç set etmez.
     * decode_content curl'ün sıkıştırmayı kendi çözmesini sağlar; elle
     * Accept-Encoding gönderilirse ham gzip byte'ları imzayı bozar.
     * Authorization header'ı bilerek gönderilmez: yönlendirme sonrası CDN'e
     * kimlik bilgisi sızmamalı. Yönlendirme yalnızca https üzerinde izlenir,
     * aksi halde saldırgan bir 302 ile düz metne düşürebilirdi.
     *
     * @param string      $url     İndirilecek asset adresi
     * @param string|null $failure Çıkış parametresi: 'transport', 'http', 'oversize' veya 'empty'
     *
     * @return string|null Gövde; başarısızlıkta null
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
     * Content-Length başlığının tavanı aşıp aşmadığını söyler.
     *
     * @param string $contentLength Ham başlık değeri ('' ise bilgi yok)
     * @param int    $limit         Byte cinsinden tavan
     */
    private function declaredSizeExceeds(string $contentLength, int $limit): bool
    {
        return $contentLength !== '' && ctype_digit($contentLength) && (int) $contentLength > $limit;
    }

    /**
     * Göreli bir yolu proje kökü altındaki mutlak hedefe çevirir.
     *
     * Yazma öncesi sözdizimsel kapı: null byte, mutlak yol, sürücü harfi ve
     * ".." segmenti kabul edilmez.
     *
     * @param string $relativePath Depo göreli yol
     *
     * @return string Mutlak hedef dosya yolu
     *
     * @throws \RuntimeException Yol güvenli değilse
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
     * Çözümlenmiş bir dizinin proje kökü altında kalıp kalmadığını söyler.
     *
     * @param string $directory Var olması beklenen mutlak dizin
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
     * İki sürüm arasında değişen dosyaları compare API'sinden toplar.
     *
     * Yanıttaki hiçbir alan güvenilir değildir: dosya adları bir allowlist'ten
     * geçer, commit detay adresleri yalnızca api.github.com üzerindeki bu deponun
     * commit endpoint'i ise istenir (aksi halde Authorization header'ı üçüncü
     * tarafa giderdi).
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

        // 300 dosya limiti kontrolü: Eğer commit sayısı fazlaysa commit detaylarından diğer dosyaları topla
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
     * Compare/commit yanıtındaki dosya girdilerini allowlist'ten geçirerek toplar.
     *
     * @param mixed                                                 $entries API'den gelen files dizisi
     * @param array<string, array{filename: string, status: string, sha: mixed}> $files   Toplanan dosyalar (referans)
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
     * Bir adresin bu deponun GitHub API commit endpoint'i olup olmadığını söyler.
     *
     * @param string $url Commit girdisinden gelen ham adres
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

    /**
     * Güncelleme kilidini atomik olarak alır.
     *
     * fopen('xb') yarışa kapalıdır: file_exists() + file_put_contents() ikilisinde
     * iki istek aynı anda kilidi "alabiliyordu".
     */
    private function acquireLock(): bool
    {
        // 5 dakikadan eski lock'ları temizle
        if (file_exists($this->lockFile) && time() - filemtime($this->lockFile) > self::LOCK_TTL) {
            @unlink($this->lockFile);
        }

        $handle = @fopen($this->lockFile, 'xb');
        if ($handle === false) {
            return false;
        }

        fwrite($handle, (string) time());

        return fclose($handle);
    }

    private function releaseLock(): void
    {
        if (file_exists($this->lockFile)) {
            @unlink($this->lockFile);
        }
    }

    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * .env içindeki app.version satırını günceller.
     *
     * $version imzalı manifest'ten gelir ve ManifestVerifier tarafından sürüm
     * biçimine zorlanır; yine de replacement kaçışı uygulanır, çünkü preg_replace
     * replacement'ında "$1" / "\1" geri referans olarak yorumlanır.
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
