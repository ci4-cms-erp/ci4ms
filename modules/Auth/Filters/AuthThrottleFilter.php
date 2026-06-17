<?php

namespace Modules\Auth\Filters;

use CodeIgniter\HTTP\RequestInterface;

/**
 * Shield auth route'ları (login/register/forgot vb.) için rate-limit.
 *
 * `auth-rates` alias'ı bu sınıfa yönlendirilir (bkz. Config\Filters).
 * Shield'in çıplak 429'u yerine, her zaman 'auth' profili ile çalışıp
 * markalı error_429 sayfasını GERÇEK geri sayımla gösterir.
 */
class AuthThrottleFilter extends \App\Filters\ThrottleFilter
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Argüman ne olursa olsun 'auth' profilini zorla.
        return parent::before($request, ['auth']);
    }
}
