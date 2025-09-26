<?php

namespace Modules\Backend\Controllers;

use ci4commonmodel\Models\CommonModel;
use Modules\Auth\Libraries\AuthLibrary;
use Modules\Auth\Config\AuthConfig;
use CodeIgniter\Controller;
use Modules\Backend\Config\BackendConfig;
use Modules\Users\Models\UserscrudModel;
use CodeIgniter\API\ResponseTrait;

class BaseController extends Controller
{
    use ResponseTrait;

    public $logged_in_user;
    public $commonModel;
    public $perms;
    public $backConfig;
    public $defData;
    public $authLib;
    public $config;
    public $encrypter;
    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var array
     */
    protected $helpers = ['Modules\Backend\Helpers\ci4ms'];

    /**
     * Constructor.
     */
    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        //--------------------------------------------------------------------
        // Preload any models, libraries, etc, here.
        //--------------------------------------------------------------------
        // E.g.:
        // $this->session = \Config\Services::session();
        $this->encrypter = \Config\Services::encrypter();
        $this->config = new AuthConfig();
        $this->backConfig = new BackendConfig();
        $this->authLib = new AuthLibrary();
        $this->commonModel = new CommonModel();
        $userModel = new UserscrudModel();
        $this->logged_in_user = $userModel->loggedUser(0, 'users.id,firstname,name,sirname,username', ['users.id' => session()->get($this->config->logged_in)]);
        $this->logged_in_user = reset($this->logged_in_user);
        $uri = '';
        if ($this->request->getUri()->getTotalSegments() > 1) {
            $segs = $this->request->getUri()->getSegments();
            unset($segs[0]);
            foreach ($segs as $totalSegment) {
                $uri .= '/' . $totalSegment;
            }
            $uri = substr($uri, 1);
        } else $uri = $this->request->getUri()->getSegment(1);
        $router = service('router');
        $searchValues = [str_replace('\\', '-', $router->controllerName()), $router->methodName()];
        $perms = array_reduce(cache(session()->get($this->config->logged_in) . '_permissions'), fn($carry, $item) => $carry ?? ($item['className'] === $searchValues[0] && $item['methodName'] === $searchValues[1] ? $item : null));
        $this->defData = [
            'config' => $this->config,
            'logged_in_user' => $this->logged_in_user,
            'backConfig' => $this->backConfig,
            'navigation' => $this->authLib->sidebarNavigation(),
            'title' => (object)$perms,
            'uri' => $uri,
            'settings' => (object)cache('settings'),
            'encrypter' => $this->encrypter
        ];
        $this->config->mailConfig = [
            'protocol' => $this->defData['settings']->mail->protocol,
            'SMTPHost' => $this->defData['settings']->mail->server,
            'SMTPPort' => $this->defData['settings']->mail->port,
            'SMTPUser' => $this->defData['settings']->mail->address,
            //'SMTPPass' => $this->encrypter->decrypt(base64_decode($this->defData['settings']->mail->password)),
            'charset' => 'UTF-8',
            'mailtype' => 'html',
            'wordWrap' => 'true',
            'TLS' => $this->defData['settings']->mail->tls,
            'newline' => "\r\n"
        ];
        if ($this->defData['settings']->mail->protocol === 'smtp') $this->config->mailConfig['SMTPCrypto'] = 'PHPMailer::ENCRYPTION_STARTTLS';
        if (count(directory_map(ROOTPATH . 'public/templates')) >= 1) $this->defData['templates'] = directory_map(ROOTPATH . 'public/templates');
    }
}
