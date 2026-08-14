<?php

declare(strict_types=1);

namespace Modules\Fileeditor\Controllers;

/**
 * Namespace-scoped stand-in for Shield's global auth() helper, loaded only by
 * FileeditorWriteAllowlistTest.
 *
 * PHP resolves an unqualified function call against the *calling* file's own
 * namespace first, falling back to the global namespace only if no such
 * function exists there. Fileeditor.php is declared under
 * Modules\Fileeditor\Controllers, so once this function has been required,
 * every unqualified auth() call inside that file resolves here instead of to
 * Shield's real auth() (vendor/codeigniter4/shield/src/Helpers/auth_helper.php,
 * global namespace, backed by service('auth')).
 *
 * This exists because Fileeditor::triggerFileevent() calls
 * auth()->user()->username unconditionally on every successful write
 * (Fileeditor.php:89), and this test suite drives the controller directly
 * (no router/filter chain, no session, no database) — the real Shield
 * Auth service has nothing to authenticate against in that setup.
 */
function auth(): object
{
    return new class {
        public function user(): object
        {
            return (object) ['username' => 'ci4ms-qa-fileeditor-test'];
        }
    };
}
