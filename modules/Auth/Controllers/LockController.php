<?php

declare(strict_types=1);

namespace Modules\Auth\Controllers;

use Modules\Auth\Models\UserSessionModel;

/**
 * Lock Screen Controller
 *
 * The screen locks after user inactivity and is unlocked through this
 * controller with password verification. The session isn't closed, only
 * access is blocked.
 */
class LockController extends BaseController
{
    /**
     * GET /backend/lock
     * Shows the lock screen. Redirects to the dashboard if not locked.
     */
    public function lockView(): string|\CodeIgniter\HTTP\RedirectResponse
    {
        // Send to login if there's no active session
        if (! auth()->loggedIn()) {
            return redirect()->route('login');
        }

        $sessionId = session()->get('ci4ms_session_tracker_id');

        // Redirect to the dashboard if there's no session_id or it's not locked (blocks direct access)
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

        // Redirect URL security validation — only URLs starting with /backend/ are accepted
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
     * Verifies the user's password and unlocks the screen.
     * Returns a JSON response for an AJAX request (overlay unlock), otherwise redirects.
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

        // Redirect URL security validation
        $safeRedirect = (str_starts_with($redirect, '/backend/') && ! str_contains($redirect, '..'))
            ? $redirect
            : config('Auth')->loginRedirect();

        // Password verification via Shield
        $user        = auth()->user();
        $credentials = [
            'email'    => $user->email,
            'password' => $password,
        ];

        $result = auth('session')->check($credentials);

        if (! $result->isOK()) {
            $attempts++;
            session()->set('lock_attempts', $attempts);

            // Terminate the session after 3+ failed attempts
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

        // Success — remove the lock
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
     * Called from JavaScript; writes locked_at to the DB after inactivity.
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
     * Closes the current session and redirects to the login page (account switch).
     */
    public function switchAccount(): \CodeIgniter\HTTP\RedirectResponse
    {
        auth()->logout();
        session()->destroy();
        return redirect()->route('login')->with('message', lang('Auth.successLogout'));
    }
}
