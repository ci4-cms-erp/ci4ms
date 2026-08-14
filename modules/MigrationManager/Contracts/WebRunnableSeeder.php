<?php

declare(strict_types=1);

namespace Modules\MigrationManager\Contracts;

/**
 * Marker interface for seeders that can be triggered from the web.
 *
 * `vendor/codeigniter4/framework/system/Database/Seeder.php:154`'s
 * `Seeder::call()` does `new $class(...)` without ever validating the seeder
 * class name. Implementing this interface is the PREREQUISITE for entering
 * the structural allowlist used by `SeederScanner::discover()` — seeders
 * that don't implement it (e.g. `Ci4msDefaultsSeeder`) never show up in the
 * scan results, so no separate denylist is needed.
 */
interface WebRunnableSeeder
{
    /**
     * Returns the `lang()` key for the label shown in the backend UI.
     *
     * Returns the key INSIDE `Language/{en,tr}/MigrationManager.php`, NOT
     * the translated text — the translation happens at the call site
     * (view/controller) via `lang('MigrationManager.' . $key)`.
     *
     * @return string lang() key (no namespace/dot).
     */
    public static function seederLabel(): string;

    /**
     * States whether the seeder can safely be run more than once against
     * the same data.
     *
     * If it returns `false`, the UI should hide/block the re-run action
     * once the seeder already has a `migration_runs` record; the actual
     * idempotency guarantee still belongs to the seeder's own `run()`
     * implementation (e.g. a skip-gate) — this flag is only a UI/policy
     * signal.
     *
     * @return bool `true` if it's safe to run again.
     */
    public static function isRepeatable(): bool;
}
