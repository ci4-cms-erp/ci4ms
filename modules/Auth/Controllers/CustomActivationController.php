<?php

namespace Modules\Auth\Controllers;

use CodeIgniter\Shield\Models\UserIdentityModel;

class CustomActivationController extends BaseController
{
    public function verify()
    {
        // 1. Token'ı URL'den al
        $token = $this->request->getGet('token');

        if (! $token) {
            return show_404();
        }

        // 2. Bu token veritabanında var mı? (auth_identities tablosu)
        $identities = new UserIdentityModel();

        // Shield tokenları 'email_activate' tipiyle saklar
        $identity = $identities->where('type', 'email_activate')
                               ->where('secret', $token)
                               ->first();

        if (! $identity) {
            return show_404();
        }

        // 3. Token geçerli, kullanıcıyı bul ve aktifleştir
        $users = auth()->getProvider();
        $user  = $users->findById($identity->user_id);

        if ($user) {
            // Kullanıcıyı aktif yap
            $user->active = 1;
            $users->save($user);

            // 4. Token'ı sil (Tek kullanımlık olması için)
            $identities->delete($identity->id);

            // 5. Başarılı, login sayfasına gönder
            return redirect()->to(config('Auth')->loginRedirect())->with('message', lang('Auth.emailActivationuccess'));
        }

        return show_404();
    }
}
