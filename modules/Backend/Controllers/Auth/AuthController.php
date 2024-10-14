<?php namespace Modules\Backend\Controllers\Auth;

use App\Libraries\CommonLibrary;
use CodeIgniter\I18n\Time;
use Gregwar\Captcha\CaptchaBuilder;
use Modules\Backend\Models\UserModel;

class AuthController extends BaseController
{
    protected $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function login()
    {
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
        return view($this->config->views['login'], ['config' => $this->config, 'cap' => $cap]);
    }

    /**
     * Attempts to verify the user's credentials
     * through a POST request.
     */
    public function attemptLogin()
    {
        $rules = [
            'email' => 'required|valid_email',
            'password' => 'required',
            'captcha' => 'required'
        ];

        if (!$this->validate($rules)) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());

        $captchaCheck=($this->request->getPost('captcha') == $this->session->getFlashdata('cap'))?true:false;
        if (ENVIRONMENT === 'development') $captchaCheck=true;

        if ($captchaCheck===true) {
            $login = $this->request->getPost('email');
            $password = $this->request->getPost('password');
            $remember = (bool)$this->request->getPost('remember');

            // Check is blocked ip
            if ($this->authLib->isBlockedAttempt($login)) return redirect()->back()->withInput()->with('error', $this->authLib->error() ?? lang('Auth.loginBlock'));

            // Try to log them in...
            if (!$this->authLib->attempt(['email' => $login, 'password' => $password], $remember)) return redirect()->back()->withInput()->with('error', $this->authLib->error() ?? lang('Auth.badAttempt'));
            $redirectURL = session('redirect_url') ?? redirect()->route('logout');
            unset($_SESSION['redirect_url']);
            return redirect()->route($redirectURL)->withCookies()->with('message', lang('Auth.loginSuccess'));
        }
        return redirect()->route('login')->withInput()->with('error', $this->authLib->error() ?? lang('Auth.badCaptcha'));
    }

    /**
     * Log the user out.
     */
    public function logout()
    {
        if ($this->authLib->check()) $this->authLib->logout();
        return redirect()->route('login');
    }

    /**
     * Displays the forgot password form.
     */
    public function forgotPassword()
    {
        if ($this->config->activeResetter === false)
            return redirect()->route('login')->with('error', lang('Auth.forgotDisabled'));

        return view($this->config->views['forgot'], ['config' => $this->config]);
    }

    /**
     * Attempts to find a user account with that password
     * and send password reset instructions to them.
     */
    public function attemptForgot()
    {
        helper('debug');
        $rules = [
            'email' => 'required|valid_email'
        ];
        if (!$this->validate($rules)) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());

        if ($this->config->activeResetter === false) return redirect()->route('login')->with('error', lang('Auth.forgotDisabled'));

        $user = $this->commonModel->getOne('users', ['email' => $this->request->getPost('email')]);

        if (is_null($user)) return redirect()->back()->with('error', lang('Auth.forgotNoUser'));

        // Save the reset hash /
        $this->commonModel->updateOne('users', ['id' => $user->_id], ['reset_hash' => $this->authLib->generateActivateHash(), 'reset_expires' => date('Y-m-d H:i:s', time() + $this->config->resetTime)]);
        $user = $this->commonModel->getOne('users', ['id' => $user->_id]);
        $commonLibrary = new CommonLibrary();
        $mailResult = $commonLibrary->phpMailer('noreply@ci4ms.com', 'noreply@ci4ms.com',
            ['mail' => $user->email],
            'noreply@ci4ms.com', 'Information',
            'Üyelik Şifre Sıfırlama',
            'Üyeliğiniz şifre sıfırlaması gerçekleştirildi. Şifre yenileme isteğiniz ' . date('d-m-Y H:i:s', strtotime($user->reset_expires)) . ' tarihine kadar geçerlidir. Lütfen yeni şifrenizi belirlemek için <a href="' . site_url('backend/reset-password/' . $user->reset_hash) . '"><b>buraya</b></a> tıklayınız.'
        );
        if ($mailResult === true) return redirect()->route('login')->with('message', lang('Auth.forgotEmailSent'));
        else return redirect()->back()->withInput()->with('error', $mailResult ?? lang('Auth.unknownError'));
    }

    /**
     * Displays the Reset Password form.
     */
    public function resetPassword($token)
    {
        if ($this->config->activeResetter === false) return redirect()->route('login')->with('error', lang('Auth.forgotDisabled'));

        return view($this->config->views['reset'], ['config' => $this->config, 'token' => $token]);
    }

    /**
     * Verifies the code with the email and saves the new password,
     * if they all pass validation.
     *
     * @return mixed
     */
    public function attemptReset($token)
    {
        if ($this->config->activeResetter === false) return redirect()->route('login')->with('error', lang('Auth.forgotDisabled'));

        // First things first - log the reset attempt.
        $this->commonModel->create('auth_reset_password_attempts', [
                'email' => $this->request->getPost('email'),
                'ip_address' => $this->request->getIPAddress(),
                'user_agent' => (string)$this->request->getUserAgent(),
                'token' => $token,
                'created_at' => date('Y-m-d H:i:s')
            ]
        );

        $rules = [
            'email' => 'required|valid_email',
            'password' => 'required|min_length[8]',
            'pass_confirm' => 'required|matches[password]',
        ];

        if (!$this->validate($rules)) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());

        $user = $this->commonModel->selectOne('users', ['email' => $this->request->getPost('email'), 'reset_hash' => $token]);
        if (is_null($user)) return redirect()->back()->with('error', lang('Auth.forgotNoUser'));

        // Reset token still valid?
        $time = Time::parse($user->reset_expires);
        if (!empty($user->reset_expires) && time() > $time->getTimestamp()) return redirect()->back()->withInput()->with('error', lang('Auth.resetTokenExpired'));

        // Success! Save the new password, and cleanup the reset hash.
        $this->commonModel->edit('users', ['password_hash' => $this->authLib->setPassword($this->request->getPost('password')),
            'reset_hash' => null,
            'reset_expires' => null,
            'force_pass_reset' => false,
            'reset_at' => new Time('now'),
        ],['id' => $user->id]);

        return redirect()->route('login')->with('message', lang('Auth.resetSuccess'));
    }

    /**
     * Activate account.
     *
     * @return mixed
     */
    public function activateAccount($token)
    {
        // First things first - log the activation attempt.
        $this->commonModel->create('auth_email_activation_attempts',
            [
                'ip_address' => $this->request->getIPAddress(),
                'user_agent' => (string)$this->request->getUserAgent(),
                'token' => $token,
                'created_at' => date('Y-m-d H:i:s')
            ]
        );

        $throttler = service('throttler');

        if ($throttler->check($this->request->getIPAddress(), 2, MINUTE) === false) return $this->response->setStatusCode(429)->setBody(lang('Auth.tooManyRequests', [$throttler->getTokentime()]));

        $user = $this->commonModel->getOne('users', ['activate_hash' => $token, 'status' => 'deactive']);

        if (is_null($user)) return redirect()->route('login')->with('error', lang('Auth.activationNoUser'));

        $this->commonModel->update('users',  ['status' => 'active', 'activate_hash' => null],['id' => $user->id]);

        return redirect()->route('login')->with('message', lang('Auth.registerSuccess'));
    }

    public function activateEmail($token)
    {
        // First things first - log the activation attempt.
        $this->commonModel->createOne('auth_email_activation_attempts', [
                'ip_address' => $this->request->getIPAddress(),
                'user_agent' => (string)$this->request->getUserAgent(),
                'token' => $token,
                'created_at' => date('Y-m-d H:i:s')
            ]
        );

        $throttler = service('throttler');

        if ($throttler->check($this->request->getIPAddress(), 2, MINUTE) === false) return $this->response->setStatusCode(429)->setBody(lang('Auth.tooManyRequests', [$throttler->getTokentime()]));

        $user = $this->commonModel->getOne('users', ['activate_hash' => $token, 'status' => 'deactive']);

        if (is_null($user)) return redirect()->route('login')->with('error', lang('Auth.activationNoUser'));

        $this->commonModel->update('users', ['status' => 'active', 'activate_hash' => null], ['id' => $user->id]);

        return redirect()->route('login')->with('message', lang('Auth.emailActivationuccess'));
    }
}
