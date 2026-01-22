<?php

namespace App\Controllers;

use ci4commonmodel\Models\CommonModel;
use ci4seopro\Config\Seo;
use CodeIgniter\Controller;
use CodeIgniter\HTTP\CLIRequest;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Psr\Log\LoggerInterface;
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
    protected $helpers = [];

    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */
    // protected $session;

    /*Ci4ms*/
    public $defData;
    public $commonModel;
    protected $seosearchService;
    protected $seoConfigLoaded = false;

    /**
     * @return void
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);
        // Preload any models, libraries, etc, here.

        // E.g.: $this->session = \Config\Services::session();
        $this->commonModel = new CommonModel();
        $this->defData = $this->getDefaultData();
    }

    protected function getDefaultData(): array
    {
        if (empty(cache('menus'))) $menus = $this->commonModel->lists('menu', '*', [], 'queue ASC');
        else $menus = (object)cache('menus');
        $defData = [
            'settings' => (object)cache('settings'),
            'menus' => $menus,
            'agent' => $this->request->getUserAgent(),
            'seoConfig' => new Seo()
        ];
        $defData['seoConfig']->siteName = $defData['settings']->siteName;

        return $defData;
    }

    protected function seo()
    {
        if (!$this->seosearchService) {
            $this->seosearchService = service('seosearch', $this->defData['seoConfig']);
        }
        return $this->seosearchService;
    }
}
