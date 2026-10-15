<?php

declare(strict_types=1);

/**
 * This file is part of CodeIgniter Shield.
 *
 * (c) CodeIgniter Foundation <admin@codeigniter.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Modules\Auth\Controllers;

use CodeIgniter\Shield\Authentication\Authenticators\Session;
use CodeIgniter\Shield\Traits\Viewable;
use CodeIgniter\Shield\Validation\ValidationRules;
use Gregwar\Captcha\CaptchaBuilder;

class LoginController extends BaseController
{
    use Viewable;

    /**
     * Displays the form the login to the site.
     *
     * @return RedirectResponse|string
     */
    public function loginView()
    {
        if (auth()->loggedIn()) {
            // withCookies() is required: restoring the session from a remember-me
            // cookie rotates the token and queues the replacement cookie, which a
            // bare redirect drops — leaving the new token in the DB and the old one
            // in the browser, silently invalidating "remember me".
            return redirect()->to(config('Auth')->loginRedirect())->withCookies();
        }

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        // If an action has been defined, start it up.
        if ($authenticator->hasAction()) {
            return redirect()->route('auth-action-show');
        }
        $cap = new CaptchaBuilder();
        $cap->setBackgroundColor(139, 203, 183);
        $cap->setIgnoreAllEffects(false);
        $cap->setMaxFrontLines(0);
        $cap->setMaxBehindLines(0);
        $cap->setMaxAngle(1);
        $cap->setTextColor(18, 58, 73);
        $cap->setLineColor(18, 58, 73);
        $cap->build();
        $this->session->setFlashdata('cap', $cap->getPhrase());
        return $this->view(setting('Auth.views')['login'], ['cap' => $cap]);
    }

    /**
     * Attempts to log the user in.
     */
    public function loginAction()
    {
        // Validate here first, since some things,
        // like the password, can only be validated properly here.
        $rules = $this->getValidationRules();

        if (! $this->validateData($this->request->getPost(), $rules, [], config('Auth')->DBGroup)) {
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        }

        $expected     = $this->session->getFlashdata('cap');
        $given        = $this->request->getPost('captcha');
        $captchaCheck = ($expected !== null && $expected !== '') && hash_equals((string) $expected, (string) $given);
        if (ENVIRONMENT === 'development' && setting('Auth.captchaBypassInDevelopment')) $captchaCheck = true;
        if (!$captchaCheck) return redirect()->route('login')->withInput()->with('error', lang('Auth.badCaptcha'));

        /** @var array $credentials */
        $credentials             = $this->request->getPost(setting('Auth.validFields')) ?? [];
        $credentials             = array_filter($credentials);
        $credentials['password'] = $this->request->getPost('password');
        $remember                = (bool) $this->request->getPost('remember');
        unset($credentials['captcha']);

        /** @var Session $authenticator */
        $authenticator = auth('session')->getAuthenticator();

        // Attempt to login
        $result = $authenticator->remember($remember)->attempt($credentials);
        if (! $result->isOK()) {
            // Only the real reason is logged for the operator; the user always gets
            // the same generic message (login-enumeration defense, GHSA-ppvr-272j-ggvv).
            log_message(
                'warning',
                'Login failed for ' . $this->maskIdentifier($credentials) . ' from ' . $this->request->getIPAddress() . ': ' . $result->reason()
            );

            return redirect()->route('login')->withInput()->with('error', lang('Auth.loginFailed'));
        }

        // If an action has been defined for login, start it up.
        if ($authenticator->hasAction()) {
            return redirect()->route('auth-action-show')->withCookies();
        }

        return redirect()->to(config('Auth')->loginRedirect())->withCookies();
    }

    /**
     * Returns the rules that should be used for validation.
     *
     * @return array<string, array<string, list<string>|string>>
     */
    protected function getValidationRules(): array
    {
        $rules = new ValidationRules();

        return $rules->getLoginRules() + [
            'captcha' => [
                'label' => lang('Auth.captchaLabel'),
                'rules' => 'required|regex_match[/^[^<>{}]*$/u]',
            ],
        ];
    }

    /**
     * Masks a failed login attempt's identifier for safe logging.
     *
     * Keeps a failed-login log entry useful for an operator (which account was
     * targeted, roughly) without writing a directly reusable email/username into
     * the log file in plaintext.
     *
     * @param array<string, mixed> $credentials Raw login credentials (password included).
     *
     * @return string Masked identifier, or '(unknown)' when no identifier field was submitted.
     */
    private function maskIdentifier(array $credentials): string
    {
        unset($credentials['password']);
        $identifier = (string) (reset($credentials) ?: '');

        if ($identifier === '') {
            return '(unknown)';
        }

        if (str_contains($identifier, '@')) {
            [$local, $domain] = explode('@', $identifier, 2);

            return mb_substr($local, 0, 2) . '***@' . $domain;
        }

        return mb_substr($identifier, 0, 2) . '***';
    }

    /**
     * Logs the current user out.
     */
    public function logoutAction()
    {
        // Capture logout redirect URL before auth logout,
        // otherwise you cannot check the user in `logoutRedirect()`.
        $url = config('Auth')->logoutRedirect();

        $userId    = auth()->id();
        $sessionId = session()->get('ci4ms_session_tracker_id');

        auth()->logout();

        if ($userId && $sessionId) {
            $model = new \Modules\Auth\Models\UserSessionModel();
            $model->terminateSession((int) $userId, $sessionId, true);
        }

        session()->destroy();

        return redirect()->to($url)->with('message', lang('Auth.successLogout'));
    }
}
