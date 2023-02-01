<?php namespace Modules\Backend\Controllers;

use ci4commonModel\Models\CommonModel;
use Modules\Backend\Libraries\AuthLibrary;
use Modules\Backend\Config\Auth;
use CodeIgniter\Controller;
use Modules\Backend\Config\BackendConfig;
use Modules\Backend\Models\UserscrudModel;
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
    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var array
     */
    protected $helpers = ['text','filesystem','html'];

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

        $this->config = new Auth();
        $this->backConfig = new BackendConfig();
        $this->authLib = new AuthLibrary();
        $this->commonModel = new CommonModel();
        $userModel = new UserscrudModel();
        $this->logged_in_user = $userModel->loggedUser(0, '*', ['users.id' => session()->get($this->config->logged_in)]);
        $this->logged_in_user = $this->logged_in_user[0];
        $uri='';
        if($this->request->uri->getTotalSegments()>1){
            $segs=$this->request->uri->getSegments();
            unset($segs[0]);
            foreach ($segs as $totalSegment) {
                $uri.='/'.$totalSegment;
            }
            $uri=substr($uri,1);
        }
        else $uri=$this->request->uri->getSegment(1);
        $router = service('router');
        $navigation=$this->commonModel->lists('auth_permissions_pages','*', ['inNavigation' => true, 'isBackoffice'=>true],'pageSort');
        $nav=[];
        foreach ($navigation as $item) {
            $result=$this->authLib->has_perm($item->id);
            if($result===true) $nav[]=$item;
        }
        $settings = $this->commonModel->selectOne('settings');
        $this->config->mailConfig=['protocol' => $settings->mailProtocol,
            'SMTPHost' => $settings->mailServer,
            'SMTPPort' => $settings->mailPort,
            'SMTPUser' => $settings->mailAddress,
            'SMTPPass' => $settings->mailPassword,
            'charset' => 'UTF-8',
            'mailtype' => 'html',
            'wordWrap' => 'true',
            'TLS'=>$settings->mailTLS,
            'newline' => "\r\n"];
        if($settings->mailProtocol==='smtp') $this->config->mailConfig['SMTPCrypto']='PHPMailer::ENCRYPTION_STARTTLS';

        $templates=directory_map(ROOTPATH.'public/templates');
        foreach($templates as $key=>$template){
                if(is_file(ROOTPATH.'public/templates/'.$key.'info.xml')===false && is_file(ROOTPATH.'public/templates/'.$key.'screenshot.png')===false)
                {
                    session()->setFlashdata('warning', ROOTPATH.'public/templates/'.$key.' klasöründe "info.xml" ve/veya "screenshot.png" dosyaları bulunmuyor. Kontrolü sağlayınız.');
                    break;
                }
        }
        $this->defData = ['config' => $this->config,
            'logged_in_user' => $this->logged_in_user,
            'backConfig' => $this->backConfig,
            'navigation' => $nav,
            'title'=>$this->commonModel->selectOne('auth_permissions_pages', ['className' => str_replace('\\', '-',$router->controllerName()), 'methodName' => $router->methodName()], 'pagename'),
            'uri' => $uri,
            'settings'=>$settings];
        $this->defData['settings']->templateInfos=json_decode($this->defData['settings']->templateInfos);
        $this->defData['settings']->templateInfos=(object)$this->defData['settings']->templateInfos;
        $this->defData['settings']->socialNetwork=json_decode($this->defData['settings']->socialNetwork);
        $this->defData['settings']->socialNetwork=(object)$this->defData['settings']->socialNetwork;
        $this->defData['settings']->allowedFiles=json_decode($this->defData['settings']->allowedFiles);
        $this->defData['settings']->allowedFiles=(object)$this->defData['settings']->allowedFiles;
        if(count($templates)>=1) $this->defData['templates']=$templates;
    }
}
