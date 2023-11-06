<?php

namespace App\Controllers;

use App\Libraries\Ci4msseoLibrary;
use ci4commonmodel\Models\CommonModel;
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
    protected $helpers = ['html'];

    /**
     * Be sure to declare properties for any property fetch you initialized.
     * The creation of dynamic property is deprecated in PHP 8.2.
     */
    // protected $session;

    /*Ci4ms*/
    public $defData;
    public $commonModel;
    public $ci4msseoLibrary;

    /**
     * Constructor.
     */
    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        // Do Not Edit This Line
        parent::initController($request, $response, $logger);

        // Preload any models, libraries, etc, here.

        // E.g.: $this->session = \Config\Services::session();
        $this->commonModel = new CommonModel();
        $this->ci4msseoLibrary = new Ci4msseoLibrary();
        $settings = cache('settings');
        $this->defData = ['settings' => (object)[
            'templateInfos' => (object)json_decode(array_reduce($settings, fn($carry, $item) => $carry ?? ('templateInfos' == $item->option ? $item : null))->content, true),
            'companyInfos' => (object)json_decode(array_reduce($settings, fn($carry, $item) => $carry ?? ('company' == $item->option ? $item : null))->content, true),
            'socialNetwork' => json_decode(array_reduce($settings, fn($carry, $item) => $carry ?? ('socialNetwork' == $item->option ? $item : null))->content, true),
            'siteName' => array_reduce($settings, fn($carry, $item) => $carry ?? ('siteName' == $item->option ? $item : null))->content,
            'logo' => array_reduce($settings, fn($carry, $item) => $carry ?? ('logo' == $item->option ? $item : null))->content,
            'maintenanceMode' => array_reduce($settings, fn($carry, $item) => $carry ?? ('maintenanceMode' == $item->option ? $item : null))->content
        ],
            'menus' => $this->commonModel->lists('menu', '*', [], 'queue ASC')];
        //dd($this->defData['settings']->socialNetwork);
    }
}