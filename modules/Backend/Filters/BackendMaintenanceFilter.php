<?php

namespace Modules\Backend\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Modules\Backend\Libraries\BackendMaintenance;

/**
 * backendGuard zincirinde Ci4MsAuthFilter'dan sonra çalışır.
 * Bu noktada kullanıcı kimliği doğrulanmış ve izinleri kontrol edilmiştir.
 * Superadmin dışındaki kullanıcıları, bakımdaki alanlarda 503 ile durdurur.
 */
class BackendMaintenanceFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // backendGuard zincirinde Ci4MsAuthFilter'dan SONRA çalıştığımız için kullanıcı
        // normalde kimliği doğrulanmış olur; yine de auth() helper'ı ve inGroup() yokken
        // fatal olmamak adına savunmacı kontrol yapıyoruz.
        $user         = function_exists('auth') ? auth()->user() : null;
        $isSuperadmin = $user && method_exists($user, 'inGroup') && $user->inGroup('superadmin');

        // settings cache'inde değer object (stdClass) olarak tutulur; normalize()
        // stdClass / array / eski düz liste formatlarının hepsini kanonik
        // {all, until, modules: map} yapısına çevirir.
        $settings    = cache('settings') ?: [];
        $maintenance = BackendMaintenance::normalize($settings['backendMaintenance'] ?? null);

        $controllerName = service('router')->controllerName();
        $controllerName = is_string($controllerName) ? $controllerName : '';

        if (BackendMaintenance::isBlocked($maintenance, $controllerName, (bool) $isSuperadmin)) {
            $response = service('response')->setStatusCode(503);

            // Geri sayım, engelleyen kapsamın kendi until'ini kullanır:
            // modül engeliyse modülün süresi, global engelse global süre.
            $until = BackendMaintenance::untilFor($maintenance, $controllerName);
            $sec   = BackendMaintenance::secondsUntilEnd(['until' => $until]);
            $data  = [];
            if ($sec !== null) {
                // until geçmiş (0) ise kısa bir değerle auto-reload yeniden kontrol etsin
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
