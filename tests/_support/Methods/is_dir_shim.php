<?php

declare(strict_types=1);

namespace Modules\Methods\Libraries;

/**
 * Test-only function shadow for ModuleInstallerNamespaceTest.
 *
 * ModuleInstaller::runModuleMigrations()/rollbackModuleMigrations() call the
 * unqualified is_dir() from within the `Modules\Methods\Libraries` namespace
 * (ModuleInstaller.php:31,184), so PHP resolves it to a namespaced function
 * first, falling back to the global one only if none is declared here. This
 * lets the on-disk existence gate pass for the PSR-4-mapped fixture module
 * path (tests/_support/Methods/FixtureModule/...), which is never actually
 * created under modules/FixtureModule/... — writing under modules/ during
 * this test is forbidden because other PHPUnit processes may be scanning
 * that directory concurrently (see task dispatch notes). Every other path is
 * delegated to the real \is_dir(), so ModuleInstaller's other methods
 * (getModuleTables(), removeModuleFiles(), runAllPendingMigrations()) are
 * unaffected.
 *
 * @param string $path Path being checked by ModuleInstaller.
 *
 * @return bool True for the fixture's fake migration path, real is_dir() otherwise.
 */
function is_dir(string $path): bool
{
    static $fixturePath;
    $fixturePath ??= ROOTPATH . 'modules/FixtureModule/Database/Migrations';

    if ($path === $fixturePath) {
        return true;
    }

    return \is_dir($path);
}
