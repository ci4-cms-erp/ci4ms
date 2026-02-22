<?php

namespace Modules\Backend\Controllers;

use ci4commonmodel\Models\CommonModel;
use CodeIgniter\Controller;
use Modules\Backend\Config\BackendConfig;
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
        $this->backConfig = new BackendConfig();
        $this->commonModel = new CommonModel();
        $router = service('router');
        $dbClassName = str_replace('\\', '-', $router->controllerName());
        if (substr($dbClassName, 0, 1) !== '-') {
            $dbClassName = '-' . $dbClassName;
        }
        $pageInfo = cache('backend_page_info_' . md5($dbClassName . $router->methodName()));
        $uri = '';
        if ($this->request->getUri()->getTotalSegments() > 1) {
            $segs = $this->request->getUri()->getSegments();
            unset($segs[0]);
            foreach ($segs as $totalSegment) {
                $uri .= '/' . $totalSegment;
            }
            $uri = substr($uri, 1);
        } else $uri = $this->request->getUri()->getSegment(1);
        $this->defData = [
            'config' => config('Auth'),
            'logged_in_user' => auth()->user(),
            'backConfig' => $this->backConfig,
            'navigation' => $this->generateSidebar(),
            'title' => (object)$pageInfo,
            'uri' => $uri,
            'settings' => (object)cache('settings'),
            'encrypter' => $this->encrypter
        ];

        if (count(directory_map(ROOTPATH . 'public/templates')) >= 1) $this->defData['templates'] = directory_map(ROOTPATH . 'public/templates');
    }

    protected function generateSidebar()
    {
        if (! $menuItems = cache('sidebar_menu')) {
            $menuItems = $this->commonModel->lists(
                'auth_permissions_pages',
                '*',
                ['inNavigation' => 1],
                'pageSort ASC'
            );
            cache()->save('sidebar_menu', $menuItems, 86400); // 1 gün cache
        }

        $html = [];
        $user = auth()->user();

        if (!$user) return [];

        foreach ($menuItems as $item) {
            $permString = $item->pagename . '.read';

            if ($user->inGroup('superadmin') || $user->can($permString)) {
                $html[] = $item;
            }
        }

        return $html;
    }
}
