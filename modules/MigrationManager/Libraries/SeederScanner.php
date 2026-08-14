<?php

declare(strict_types=1);

namespace Modules\MigrationManager\Libraries;

use CodeIgniter\Database\Seeder;
use Modules\MigrationManager\Contracts\WebRunnableSeeder;

/**
 * Scans the seed files under `app/Database/Seeds/` and each module's
 * `Database/Seeds/` directory, and returns only the classes that implement
 * `WebRunnableSeeder` and are `Seeder` subclasses.
 *
 * This allowlist structurally closes the unvalidated `new $class(...)` sink
 * in `Seeder::call()` (see `WebRunnableSeeder`'s docblock): a seeder that
 * doesn't implement the interface (e.g. `Ci4msDefaultsSeeder`) never enters
 * the scan results — no separate denylist is needed.
 *
 * FQCN derivation is done through a pure filesystem scan with no user
 * input: it maps exactly onto the `Modules\{Folder}` PSR-4 prefix that
 * `app/Config/Autoload.php` auto-registers for every module directory.
 */
class SeederScanner
{
    /**
     * Discovers the valid seed classes that implement `WebRunnableSeeder`.
     *
     * Since `app/Database/Seeds/Ci4msDefaultsSeeder.php` doesn't implement
     * this interface in production, the returned array is **empty** as
     * long as no seeder implementing `WebRunnableSeeder` has been added yet
     * — this is expected behavior, not a bug.
     *
     * @return list<array{class: class-string<Seeder>, label: string, repeatable: bool}>
     */
    public function discover(): array
    {
        $discovered = [];

        foreach ($this->candidateClasses() as $fqcn) {
            if (!class_exists($fqcn) || !is_subclass_of($fqcn, Seeder::class)) {
                continue;
            }

            if (!in_array(WebRunnableSeeder::class, class_implements($fqcn) ?: [], true)) {
                continue;
            }

            /** @var class-string<Seeder&WebRunnableSeeder> $fqcn */
            $discovered[] = [
                'class'      => $fqcn,
                'label'      => $fqcn::seederLabel(),
                'repeatable' => $fqcn::isRepeatable(),
            ];
        }

        return $discovered;
    }

    /**
     * Derives the candidate FQCN list, before the allowlist check, from the
     * seed files on disk.
     *
     * `app/Database/Seeds/{Basename}.php` -> `App\Database\Seeds\{Basename}`
     * `modules/{Module}/Database/Seeds/{Basename}.php` -> `Modules\{Module}\Database\Seeds\{Basename}`
     *
     * @return list<string> Candidate class names not guaranteed to exist.
     */
    private function candidateClasses(): array
    {
        $candidates = [];

        foreach (glob(ROOTPATH . 'app/Database/Seeds/*.php') ?: [] as $file) {
            $candidates[] = 'App\\Database\\Seeds\\' . pathinfo($file, PATHINFO_FILENAME);
        }

        foreach (glob(ROOTPATH . 'modules/*/Database/Seeds/*.php') ?: [] as $file) {
            $moduleName    = basename(dirname($file, 3));
            $candidates[] = 'Modules\\' . $moduleName . '\\Database\\Seeds\\' . pathinfo($file, PATHINFO_FILENAME);
        }

        return $candidates;
    }
}
