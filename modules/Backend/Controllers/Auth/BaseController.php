<?php namespace Modules\Backend\Controllers\Auth;

/**
 * Class BaseController
 *
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 * Extend this class in any new controllers:
 *     class Home extends BaseController
 *
 * For security be sure to declare any new methods as protected or private.
 *
 * @package CodeIgniter
 */

use ci4commonModel\Models\CommonModel;
use CodeIgniter\Controller;
use Modules\Backend\Config\Auth;
use Modules\Backend\Libraries\AuthLibrary;

class BaseController extends Controller
{
    protected $session;
    protected $config;
    protected $authLib;
    public $commonModel;
    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var array
     */
    protected $helpers = ['html'];

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

        $this->session = service('session');
        $this->config = new Auth();
        $this->authLib = new AuthLibrary();
        $this->commonModel = new CommonModel();
        if(empty(cache('settings'))){
            $settings=$this->commonModel->lists('settings');
            cache()->save('settings',$settings,86400);
        }
        else $settings=cache()->get('settings');
        $mailSettings = (object)json_decode(array_reduce($settings, fn($carry, $item) => $carry ?? ('mail' == $item->option ? $item : null))->content, true);
        $this->config->mailConfig=['protocol' => $mailSettings->protocol,
            'SMTPHost' => $mailSettings->server,
            'SMTPPort' => $mailSettings->port,
            'SMTPUser' => $mailSettings->address,
            'SMTPPass' => $mailSettings->password,
            'charset' => 'UTF-8',
            'mailtype' => 'html',
            'wordWrap' => 'true',
            'TLS'=>$mailSettings->tls,
            'newline' => "\r\n"];
        if($mailSettings->protocol==='smtp')
            $this->config->mailConfig['SMTPCrypto']='PHPMailer::ENCRYPTION_STARTTLS';
    }

}
