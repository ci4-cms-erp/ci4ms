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
}
