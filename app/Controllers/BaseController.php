<?php

namespace App\Controllers;

use ci4commonmodel\Models\CommonModel;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
use Melbahja\Seo\Schema;
use Melbahja\Seo\Schema\Thing;
use CodeIgniter\API\ResponseTrait;

/**
 * Class BaseController
 *
 * BaseController provides a convenient place for loading components
 * and performing functions that are needed by all your controllers.
 * Extend this class in any new controllers:
 *     class Home extends BaseController
 *
 * For security be sure to declare any new methods as protected or private.
 */
abstract class BaseController extends Controller
{
    use ResponseTrait;
    /**
     * Instance of the main Request object.
     *
     * @var CLIRequest|IncomingRequest
     */
    protected $request;

    /**
     * An array of helpers to be loaded automatically upon
     * class instantiation. These helpers will be available
     * to all other controllers that extend BaseController.
     *
     * @var array
     */
    protected $helpers = ['html'];

    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */
    // protected $session;

    /*Ci4ms*/
    public $defData;
    public $commonModel;

    /**
     * Constructor.
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.

        // E.g.: $this->session = \Config\Services::session();

        $this->commonModel=new CommonModel();
        $settings=$this->commonModel->selectOne('settings');
        $this->defData = ['logo' => $settings,'settings'=>$settings,
            'menus' =>$this->commonModel->lists('menu','*',[],'queue ASC')];
        $this->defData['settings']->templateInfos=json_decode($this->defData['settings']->templateInfos);
        $this->defData['settings']->templateInfos=(object)$this->defData['settings']->templateInfos;
        $this->defData['settings']->socialNetwork=json_decode($this->defData['settings']->socialNetwork);
        $this->defData['settings']->socialNetwork=(object)$this->defData['settings']->socialNetwork;
        $this->defData['schema']=new Schema(
            new Thing('Organization', [
                'url'          => site_url(), //TODO: burada site linkleri mi olması gerekiyor araştır.
                'logo'         => $this->defData['logo']->logo,
                'contactPoint' => new Thing('ContactPoint', [
                    'telephone' => $this->defData['settings']->companyPhone,
                    'contactType' => 'customer service'
                ])
            ])
        );
    }
}
