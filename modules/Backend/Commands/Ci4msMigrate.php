<?php

declare(strict_types=1);

namespace Modules\Backend\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Applies all pending migrations across every namespace (App + Modules\*).
 *
 * Bare `php spark migrate` only runs the App namespace and silently skips
 * module migrations. This command uses Services::migrations()->setNamespace(null)
 * to apply ALL PSR-4 namespaces in global timestamp order (a standalone,
 * re-runnable version of the migrate step in ci4ms:setup). Idempotent.
 *
 * Usage: php spark ci4ms:migrate
 */
class Ci4msMigrate extends BaseCommand
{
    protected $group       = 'Ci4MS';
    protected $name        = 'ci4ms:migrate';
    protected $description  = 'Apply all pending migrations across every namespace (App + Modules).';
    protected $usage        = 'ci4ms:migrate';

    public function run(array $params): void
    {
        try {
            $runner = \Config\Services::migrations();
            $runner->setNamespace(null)->latest();
            CLI::write('All namespace migrations applied (App + Modules).', 'green');
        } catch (\Throwable $e) {
            CLI::error('Migration error: ' . $e->getMessage());
        }
    }
}
