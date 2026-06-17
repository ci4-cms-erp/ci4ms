<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use Modules\Auth\Models\UserSessionModel;

/**
 * Lock Screen Controller
 *
 * Kullanıcı hareketsizliği sonrası ekran kilitlenir ve bu controller üzerinden
 * şifre doğrulamasıyla kilit açılır. Oturum kapatılmaz, yalnızca erişim engellenir.
 */
class LockController extends BaseController
{
    /**
     * GET /backend/lock
     * Lock ekranını gösterir. Kilitli değilse dashboard'a yönlendirir.
     */
    public function lockView(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        // Oturum açık değilse login'e gönder
        if (! auth()->loggedIn()) {
            return redirect()->route('login');
        }

        $sessionId = session()->get('ci4ms_session_tracker_id');

        // session_id yoksa veya kilitli değilse dashboard'a yönlendir (direkt erişim engeli)
        if (! $sessionId) {
            return redirect()->to(config('Auth')->loginRedirect());
        }

        $model  = new UserSessionModel();
        $record = $model->where('session_id', $sessionId)->first();

        if (! $record || empty($record['locked_at'])) {
            return redirect()->to(config('Auth')->loginRedirect());
        }

        $user      = auth()->user();
        $redirect  = $this->request->getGet('redirect') ?? '';

        // Redirect URL güvenlik doğrulaması — sadece /backend/ ile başlayan URL'ler kabul edilir
        $safeRedirect = (str_starts_with($redirect, '/backend/') && ! str_contains($redirect, '..'))
            ? $redirect
            : config('Auth')->loginRedirect();

        return view('Modules\Auth\Views\lock', [
            'user'        => $user,
            'redirect'    => $safeRedirect,
            'attempts'    => session()->get('lock_attempts') ?? 0,
        ]);
    }

    /**
     * POST /backend/lock
     * Kullanıcı şifresini doğrular ve kilidi açar.
     * AJAX isteği ise JSON yanıt döner (overlay unlock), değilse redirect.
     */
    public function unlockAction(): \CodeIgniter\HTTP\RedirectResponse|\CodeIgniter\HTTP\ResponseInterface
    {
        if (! auth()->loggedIn()) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(401)->setJSON(['status' => 'unauthorized']);
            }
            return redirect()->route('login');
        }

        $sessionId = session()->get('ci4ms_session_tracker_id');
        if (! $sessionId) {
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(400)->setJSON(['status' => 'no_session']);
            }
            return redirect()->route('login');
        }

        $attempts = (int) (session()->get('lock_attempts') ?? 0);
        $password = $this->request->getPost('password');
        $redirect = $this->request->getPost('redirect') ?? '';

        // Redirect URL güvenlik doğrulaması
        $safeRedirect = (str_starts_with($redirect, '/backend/') && ! str_contains($redirect, '..'))
            ? $redirect
            : config('Auth')->loginRedirect();

        // Shield ile şifre doğrulama
        $user        = auth()->user();
        $credentials = [
            'email'    => $user->email,
            'password' => $password,
        ];

        $result = auth('session')->check($credentials);

        if (! $result->isOK()) {
            $attempts++;
            session()->set('lock_attempts', $attempts);

            // 3+ başarısız denemede oturumu sonlandır
            if ($attempts >= 3) {
                session()->remove('lock_attempts');
                auth()->logout();
                session()->destroy();

                if ($this->request->isAJAX()) {
                    return $this->response->setJSON([
                        'status'   => 'terminated',
                        'message'  => lang('Auth.tooManyUnlockAttempts'),
                        'redirect' => route_to('login'),
                    ]);
                }
                return redirect()->route('login')->with('error', lang('Auth.tooManyUnlockAttempts'));
            }

            $remaining = 3 - $attempts;
            if ($this->request->isAJAX()) {
                return $this->response->setStatusCode(401)->setJSON([
                    'status'    => 'failed',
                    'message'   => lang('Auth.unlockFailed', [$remaining]),
                    'remaining' => $remaining,
                ]);
            }
            return redirect()->to('/backend/lock?redirect=' . urlencode($redirect))
                ->with('error', lang('Auth.unlockFailed', [$remaining]));
        }

        // Başarılı — kilidi kaldır
        $model = new UserSessionModel();
        $model->unlockSession($sessionId);
        session()->remove('lock_attempts');

        if ($this->request->isAJAX()) {
            return $this->response->setJSON([
                'status'   => 'unlocked',
                'message'  => lang('Auth.unlockSuccess'),
                'redirect' => $safeRedirect,
            ]);
        }
        return redirect()->to($safeRedirect)->with('message', lang('Auth.unlockSuccess'));
    }

    /**
     * POST /backend/lock/set
     * JavaScript tarafından çağrılır; hareketsizlik sonrası DB'ye locked_at yazar.
     */
    public function setLockAction(): \CodeIgniter\HTTP\ResponseInterface
    {
        if (! auth()->loggedIn()) {
            return $this->response->setStatusCode(401)->setJSON(['status' => 'unauthorized']);
        }

        $sessionId = session()->get('ci4ms_session_tracker_id');
        if (! $sessionId) {
            return $this->response->setStatusCode(400)->setJSON(['status' => 'no_session']);
        }

        $model = new UserSessionModel();
        $model->lockSession($sessionId);

        return $this->response->setJSON(['status' => 'locked']);
    }

    /**
     * GET /backend/lock/switch
     * Mevcut oturumu kapatır ve login sayfasına yönlendirir (hesap değiştirme).
     */
    public function switchAccount(): \CodeIgniter\HTTP\RedirectResponse
    {
        auth()->logout();
        session()->destroy();
        return redirect()->route('login')->with('message', lang('Auth.successLogout'));
    }
}
