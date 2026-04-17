<?php

declare(strict_types=1);

namespace Modules\Settings\Libraries;

use CodeIgniter\HTTP\CURLRequest;
use CodeIgniter\HTTP\ResponseInterface;
use Config\Services;

/**
 * CI4MS Update Service
 *
 * GitHub API tabanlı güncelleme, yama indirme ve otomatik uygulama işlemlerini yönetir.
 */
class UpdateService
{
    private string $repo = 'ci4-cms-erp/ci4ms';
    private CURLRequest $client;
    private string $lockFile;
    private string $backupBaseDir;

    public function __construct()
    {
        $this->client = Services::curlrequest();
        $this->lockFile = WRITEPATH . 'ci4ms_update.lock';
        $this->backupBaseDir = WRITEPATH . 'backups/';
    }

    /**
     * GitHub Releases API üzerinden en son sürümü kontrol eder.
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

            $release = json_decode($response->getBody());

            if (empty($release) || !isset($release->tag_name)) {
                return ['result' => false, 'message' => lang('Settings.noTagsFound')];
            }

            $latestVersion = ltrim($release->tag_name, 'v');

            if (version_compare($latestVersion, $currentVersion, '>')) {
                // Değişen dosyaları getir (Pagination destekli)
                $changedFiles = $this->fetchAllChangedFiles($currentVersion, $latestVersion);

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
                    'download_url'     => "https://github.com/{$this->repo}/archive/refs/tags/v{$latestVersion}.zip"
                ];
            }

            return ['result' => true, 'update_available' => false, 'message' => lang('Settings.alreadyLastVersion')];
        } catch (\Exception $e) {
            return ['result' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Değişen dosyaları raw olarak indirip bir yama dosyası hazırlar veya doğrudan uygulama için döner.
     *
     * @param string $currentVersion
     * @param string $latestVersion
     * @return array
     */
    public function downloadPatchRaw(string $currentVersion, string $latestVersion): array
    {
        $files = $this->fetchAllChangedFiles($currentVersion, $latestVersion);
        if (empty($files)) {
            return ['result' => false, 'message' => lang('Settings.noChangesFound')];
        }

        $downloaded = [];
        $failed = [];

        foreach ($files as $file) {
            if ($file['status'] === 'removed') continue;

            $url = "https://raw.githubusercontent.com/{$this->repo}/{$latestVersion}/" . ltrim($file['filename'], '/');
            try {
                $response = $this->client->request('GET', $url, [
                    'http_errors' => false
                ]);

                if ($response->getStatusCode() === 200) {
                    $downloaded[$file['filename']] = $response->getBody();
                } else {
                    $failed[] = $file['filename'];
                }
            } catch (\Exception $e) {
                $failed[] = $file['filename'];
            }
        }

        return [
            'result' => empty($failed),
            'files'  => $downloaded,
            'failed' => $failed,
            'total'  => count($files)
        ];
    }

    /**
     * Güncellemeyi atomik olarak uygular.
     *
     * @param string $latestVersion
     * @param array $filesContent [path => content]
     * @param array $allChangedFiles Raw file list with status
     * @return array
     */
    public function applyUpdate(string $latestVersion, array $filesContent, array $allChangedFiles): array
    {
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
                $targetFile = ROOTPATH . $path;
                $targetDir = dirname($targetFile);

                // Yedekleme
                if (file_exists($targetFile)) {
                    $this->ensureDirectory(dirname($backupDir . $path));
                    copy($targetFile, $backupDir . $path);
                }

                // Dizin kontrolü
                $this->ensureDirectory($targetDir);

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

            // 3. .env Güncelleme
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
     */
    public function rollback(string $backupDir, array $filesToRestore): bool
    {
        if (!is_dir($backupDir)) return false;

        foreach ($filesToRestore as $path) {
            $source = $backupDir . $path;
            $target = ROOTPATH . $path;

            if (file_exists($source)) {
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

    // --- Private Helpers ---

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

    private function fetchAllChangedFiles(string $from, string $to): array
    {
        $headers = $this->getGithubHeaders();
        $response = $this->client->request('GET', "https://api.github.com/repos/{$this->repo}/compare/{$from}...{$to}", [
            'headers'     => $headers,
            'http_errors' => false,
        ]);

        $data = json_decode($response->getBody(), true);
        if (!isset($data['files'])) return [];

        $files = [];
        foreach ($data['files'] as $f) {
            $files[$f['filename']] = [
                'filename' => $f['filename'],
                'status'   => $f['status']
            ];
        }

        // 300 dosya limiti kontrolü: Eğer commit sayısı fazlaysa commit detaylarından diğer dosyaları topla
        if (isset($data['commits']) && count($data['commits']) > 0 && count($files) >= 300) {
            foreach ($data['commits'] as $commit) {
                $cResponse = $this->client->request('GET', $commit['url'], [
                    'headers'     => $headers,
                    'http_errors' => false,
                ]);
                $cData = json_decode($cResponse->getBody(), true);
                if (isset($cData['files'])) {
                    foreach ($cData['files'] as $f) {
                        $files[$f['filename']] = [
                            'filename' => $f['filename'],
                            'status'   => $f['status']
                        ];
                    }
                }
            }
        }

        return array_values($files);
    }

    private function acquireLock(): bool
    {
        if (file_exists($this->lockFile)) {
            // 5 dakikadan eski lock'ları temizle
            if (time() - filemtime($this->lockFile) > 300) {
                @unlink($this->lockFile);
            } else {
                return false;
            }
        }
        return file_put_contents($this->lockFile, (string) time()) !== false;
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

    private function updateEnvVersion(string $version): void
    {
        $envPath = ROOTPATH . '.env';
        if (is_writable($envPath)) {
            $content = file_get_contents($envPath);
            $content = preg_replace(
                '/^app\.version\s*=\s*[\'"]?[^\'"\n]*[\'"]?/m',
                "app.version='{$version}'",
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
