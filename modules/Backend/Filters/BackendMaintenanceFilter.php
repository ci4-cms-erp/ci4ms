<?php

declare(strict_types=1);

namespace Modules\Backend\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Backend\Libraries\BackendMaintenance;

/**
 * Runs after Ci4MsAuthFilter in the backendGuard chain.
 * At this point the user's identity has been verified and their permissions checked.
 * Stops non-superadmin users with a 503 in areas under maintenance.
 */
class BackendMaintenanceFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Because we run AFTER Ci4MsAuthFilter in the backendGuard chain, the user's
        // identity is normally already verified; still, we do a defensive check so we
        // don't fatal if the auth() helper or inGroup() isn't available.
        $user         = function_exists('auth') ? auth()->user() : null;
        $isSuperadmin = $user && method_exists($user, 'inGroup') && $user->inGroup('superadmin');

        // The value is kept as an object (stdClass) in the settings cache; normalize()
        // converts stdClass / array / legacy flat-list formats all into the
        // canonical {all, until, modules: map} structure.
        $settings    = cache('settings') ?: [];
        $maintenance = BackendMaintenance::normalize($settings['backendMaintenance'] ?? null);

        $controllerName = service('router')->controllerName();
        $controllerName = is_string($controllerName) ? $controllerName : '';

        if (BackendMaintenance::isBlocked($maintenance, $controllerName, (bool) $isSuperadmin)) {
            $response = service('response')->setStatusCode(503);

            // The countdown uses the blocking scope's own until value:
            // the module's duration if it's a module block, the global duration if it's a global block.
            $until = BackendMaintenance::untilFor($maintenance, $controllerName);
            $sec   = BackendMaintenance::secondsUntilEnd(['until' => $until]);
            $data  = [];
            if ($sec !== null) {
                // If until has passed (0), let auto-reload re-check with a short value
                $retryAfter         = $sec > 0 ? $sec : 15;
                $data['retryAfter'] = $retryAfter;
                $response->setHeader('Retry-After', (string) $retryAfter);
            }

            return $response->setBody(
                view('Modules\Backend\Views\errors\html\error_503', $data)
            );
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
