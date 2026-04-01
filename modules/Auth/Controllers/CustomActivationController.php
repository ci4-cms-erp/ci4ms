<?php

namespace Modules\Auth\Controllers;

use CodeIgniter\Shield\Models\UserIdentityModel;

class CustomActivationController extends BaseController
{
    public function verify()
    {
        // 1. Get token from URL
        $token = $this->request->getGet('token');

        if (! $token) {
            return show_404();
        }

        // 2. Does this token exist in the database? (auth_identities table)
        $identities = new UserIdentityModel();

        // Shield stores tokens with 'email_activate' type
        $identity = $identities->where('type', 'email_activate')
                               ->where('secret', $token)
                               ->first();

        if (! $identity) {
            return show_404();
        }

        // 3. Token is valid, find and activate the user
        $users = auth()->getProvider();
        $user  = $users->findById($identity->user_id);

        if ($user) {
            // Activate the user
            $user->active = 1;
            $users->save($user);

            // 4. Delete the token (to make it single-use)
            $identities->delete($identity->id);

            // 5. Success, redirect to login page
            return redirect()->to(config('Auth')->loginRedirect())->with('message', lang('Auth.emailActivationuccess'));
        }

        return show_404();
    }
}
