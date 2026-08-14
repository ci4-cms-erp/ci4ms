<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * NOW A NO-OP: this seeder used to prompt for name/e-mail/password via
 * `CLI::prompt()` and create a superadmin account by calling
 * `InstallService::createDefaultData()`. There is no STDIN in a web context
 * (`vendor/codeigniter4/framework/system/CLI/CLI.php` -- `fgets(STDIN)`
 * returns `false` when it can't be read, and `CLI::prompt()` turns that into
 * `''`), so triggering `php spark db:seed Ci4msDefaultsSeeder` from the web
 * (e.g. from the `MigrationManager` seed screen added by this task) used to
 * create a superadmin account with an empty name/e-mail/password -- a silent
 * data corruption. Account creation no longer exists in any web-runnable
 * seeder: this class does NOT implement
 * `Modules\MigrationManager\Contracts\WebRunnableSeeder`, so it is never
 * discovered by `Modules\MigrationManager\Libraries\SeederScanner::discover()`
 * and never shows up in the backend UI.
 *
 * The class was NOT deleted, due to the risk of third-party docs/muscle
 * memory; `run()` is a no-op with an empty body, so
 * `php spark db:seed Ci4msDefaultsSeeder` still returns without erroring or
 * hanging, it just does nothing.
 */
class Ci4msDefaultsSeeder extends Seeder
{
    /**
     * No-op. The old CLI-prompt + account creation behavior was removed
     * (see the class docblock).
     *
     * @return void
     */
    public function run()
    {
    }
}
