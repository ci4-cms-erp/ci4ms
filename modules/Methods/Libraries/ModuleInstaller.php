<?php

declare(strict_types=1);

namespace Modules\Methods\Libraries;

use CodeIgniter\Database\MigrationRunner;
use Config\Services;

/**
 * ModuleInstaller
 *
 * Handles post-install tasks for a new module:
 * - Discovers and runs pending migrations from the module's Database/Migrations directory.
 *
 * @package Modules\Methods\Libraries
 */
class ModuleInstaller
{
    /**
     * Run pending migrations for a specific module.
     *
     * @param string $moduleName The module folder name (e.g. 'Cronjobs', 'Blog')
     *
     * @return array{success: bool, migrated: int, error: string|null}
     */
    public function runModuleMigrations(string $moduleName): array
    {
        $migrationPath = ROOTPATH . 'modules/' . $moduleName . '/Database/Migrations';

        if (!is_dir($migrationPath)) {
            return ['success' => true, 'migrated' => 0, 'error' => null];
        }

        try {
            /** @var MigrationRunner $migrate */
            $migrate = Services::migrations();

            // Add the module's migration namespace so the runner can discover it
            $namespace = 'Modules\\' . $moduleName . '\\Database\\Migrations';

            // Run migrations for this specific namespace
            $migrate->setNamespace($namespace)->latest();

            // Count how many migration files exist
            $files = glob($migrationPath . '/*.php');
            $migrated = is_array($files) ? count($files) : 0;

            return ['success' => true, 'migrated' => $migrated, 'error' => null];
        } catch (\Throwable $e) {
            log_message('error', "[ModuleInstaller] Migration failed for {$moduleName}: {$e->getMessage()}");
            return ['success' => false, 'migrated' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Scan all modules and run any pending migrations.
     *
     * @return array<string, array{success: bool, migrated: int, error: string|null}>
     */
    public function runAllPendingMigrations(): array
    {
        $results = [];
        $modulesPath = ROOTPATH . 'modules/';

        if (!is_dir($modulesPath)) {
            return $results;
        }

        $dirs = array_filter(glob($modulesPath . '*'), 'is_dir');

        foreach ($dirs as $dir) {
            $moduleName = basename($dir);
            $migrationDir = $dir . '/Database/Migrations';

            if (is_dir($migrationDir)) {
                $results[$moduleName] = $this->runModuleMigrations($moduleName);
            }
        }

        return $results;
    }

    /**
     * Varsayılan veritabanı tohumlamasını (Seed) çalıştırır.
     *
     * @param string $moduleName
     * @return array{success: bool, error: string|null}
     */
    public function runModuleSeeder(string $moduleName): array
    {
        $seederName = "Modules\\{$moduleName}\\Database\\Seeds\\{$moduleName}Seeder";
        
        // Eğer böyle bir Seeder sınıfı mevcutsa çalıştır
        if (class_exists($seederName)) {
            try {
                $seeder = \Config\Database::seeder();
                $seeder->call($seederName);
                return ['success' => true, 'error' => null];
            } catch (\Throwable $e) {
                log_message('error', "[ModuleInstaller] Seeder failed for {$moduleName}: {$e->getMessage()}");
                return ['success' => false, 'error' => $e->getMessage()];
            }
        }

        // Seeder dosyası yoksa başarılı sayılır
        return ['success' => true, 'error' => null];
    }

    /**
     * Modülün migration dosyalarını parse ederek oluşturulan tablo isimlerini döndürür.
     *
     * @param string $moduleName
     * @return string[]
     */
    public function getModuleTables(string $moduleName): array
    {
        $migrationPath = ROOTPATH . 'modules/' . $moduleName . '/Database/Migrations';
        $tables = [];

        if (!is_dir($migrationPath)) {
            return $tables;
        }

        $files = glob($migrationPath . '/*.php');
        if (!is_array($files)) {
            return $tables;
        }

        $prefix = \Config\Database::connect()->getPrefix();

        foreach ($files as $file) {
            $content = file_get_contents($file);
            // createTable('table_name' kalıplarını yakala
            if (preg_match_all("/createTable\s*\(\s*['\"]([^'\"]+)['\"]/", $content, $matches)) {
                foreach ($matches[1] as $tableName) {
                    $tables[] = $prefix . $tableName;
                }
            }
        }

        return array_unique($tables);
    }

    /**
     * Her tablo için kayıt sayısını döndürür.
     *
     * @param string $moduleName
     * @return array<string, int>
     */
    public function getModuleTableStats(string $moduleName): array
    {
        $tables = $this->getModuleTables($moduleName);
        $stats = [];
        $db = \Config\Database::connect();
        $forge = \Config\Database::forge();

        foreach ($tables as $table) {
            if ($db->tableExists($table)) {
                try {
                    $stats[$table] = $db->table($table)->countAllResults();
                } catch (\Throwable $e) {
                    $stats[$table] = -1;
                }
            }
        }

        return $stats;
    }

    /**
     * Modülün migration'larını geri alarak tabloları siler.
     *
     * @param string $moduleName
     * @return array{success: bool, rolledBack: int, error: string|null}
     */
    public function rollbackModuleMigrations(string $moduleName): array
    {
        $migrationPath = ROOTPATH . 'modules/' . $moduleName . '/Database/Migrations';

        if (!is_dir($migrationPath)) {
            return ['success' => true, 'rolledBack' => 0, 'error' => null];
        }

        try {
            /** @var \CodeIgniter\Database\MigrationRunner $migrate */
            $migrate = Services::migrations();
            $namespace = 'Modules\\' . $moduleName . '\\Database\\Migrations';

            // Tüm migration'ları geri al (batch 0 = hepsini geri al)
            $migrate->setNamespace($namespace)->regress(0);

            $files = glob($migrationPath . '/*.php');
            $rolledBack = is_array($files) ? count($files) : 0;

            return ['success' => true, 'rolledBack' => $rolledBack, 'error' => null];
        } catch (\Throwable $e) {
            log_message('error', "[ModuleInstaller] Rollback failed for {$moduleName}: {$e->getMessage()}");
            return ['success' => false, 'rolledBack' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * Modül klasörünü dosya sisteminden siler.
     *
     * @param string $moduleName
     * @return array{success: bool, error: string|null}
     */
    public function removeModuleFiles(string $moduleName): array
    {
        $modulePath = ROOTPATH . 'modules/' . $moduleName;

        if (!is_dir($modulePath)) {
            return ['success' => true, 'error' => null];
        }

        try {
            helper('filesystem');
            delete_files($modulePath, true);
            // delete_files dizini silmez, sadece içindekileri siler
            if (is_dir($modulePath)) {
                $this->recursiveRemoveDir($modulePath);
            }

            return ['success' => true, 'error' => null];
        } catch (\Throwable $e) {
            log_message('error', "[ModuleInstaller] File removal failed for {$moduleName}: {$e->getMessage()}");
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Bir dizini recursive olarak siler.
     *
     * @param string $dir
     * @return void
     */
    private function recursiveRemoveDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getRealPath());
            } else {
                unlink($item->getRealPath());
            }
        }

        rmdir($dir);
    }
}
