<?php

namespace Modules\Backend\Controllers;

use ci4commonmodel\CommonModel;
use CodeIgniter\Controller;
use Modules\Backend\Libraries\BackendMaintenance;
use Modules\Backend\Libraries\CommonBackendLibrary;
use Modules\Backend\Config\BackendConfig;
use CodeIgniter\API\ResponseTrait;

/**
 * @property \CodeIgniter\HTTP\IncomingRequest $request
 * @property \CodeIgniter\HTTP\Response $response
 * @property \Psr\Log\LoggerInterface $logger
 * @property \CodeIgniter\Session\Session $session
 * @property \CodeIgniter\Validation\ValidationInterface $validator
 * @property \ci4commonmodel\CommonModel $commonModel
 * @property \Modules\Backend\Libraries\CommonBackendLibrary $commonBackendLibrary
 * @property \CodeIgniter\Encryption\EncrypterInterface $encrypter
 * @property \Modules\Backend\Config\BackendConfig $backConfig
 */
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
    public $commonBackendLibrary;
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
        $this->commonBackendLibrary = new CommonBackendLibrary();
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
        } else
            $uri = $this->request->getUri()->getSegment(1);
        $this->defData = [
            'config' => config('Auth'),
            'logged_in_user' => auth()->user(),
            'backConfig' => $this->backConfig,
            'navigation' => $this->generateSidebar(),
            'title' => (object) $pageInfo,
            'uri' => $uri,
            'settings' => (object) cache('settings'),
            'encrypter' => $this->encrypter
        ];

        if (!$templates = cache('templates_list')) {
            $templates = directory_map(ROOTPATH . 'public/templates');
            cache()->save('templates_list', $templates, 86400);
        }
        if (count($templates ?? []) >= 1) {
            $this->defData['templates'] = $templates;
        }
    }

    protected function generateSidebar()
    {
        if (!$menuItems = cache('sidebar_menu')) {
            $menuItems = $this->commonModel->lists(
                'auth_permissions_pages',
                '*',
                ['inNavigation' => 1, 'isBackoffice' => 1, 'isActive' => 1],
                'pageSort ASC'
            );
            cache()->save('sidebar_menu', $menuItems, 86400); // 1 day cache
        }

        $html = [];
        $user = auth()->user();

        if (!$user)
            return [];

        // Maintenance status is not written to the cached 'sidebar_menu' data;
        // it is read from current settings and attached to menu items dynamically on each request.
        $settings = cache('settings') ?: [];
        $maintenance = BackendMaintenance::normalize($settings['backendMaintenance'] ?? null);
        $isSuperadmin = $user->inGroup('superadmin');

        foreach ($menuItems as $item) {
            $permString = strtolower($item->pagename) . '.read';

            if ($isSuperadmin || $user->can($permString)) {
                $item->maintenanceBadge = BackendMaintenance::moduleInMaintenance($maintenance, (string) $item->className);
                $item->maintenanceDisabled = $item->maintenanceBadge && !$isSuperadmin;
                $html[] = $item;
            }
        }

        return $html;
    }

    /**
     * Renders a backend-specific error page.
     *
     * Use this instead of show_404() / throw PageNotFoundException:
     *   return $this->showError();
     *   return $this->showError(403);
     *   return $this->showError(500, 'Custom message');
     *
     * Since it uses CI4's view() renderer, helper functions like lang(), base_url(), etc.
     * work seamlessly inside the view.
     *
     * @param int         $statusCode HTTP status code (403, 404, 429, 500, 503 ...)
     * @param string|null $message    Custom message to pass to the page
     */
    protected function showError(int $statusCode = 404, ?string $message = null): \CodeIgniter\HTTP\ResponseInterface
    {
        $viewBase = 'Modules\\Backend\\Views\\errors\\html\\';
        $viewFile = $viewBase . 'error_' . $statusCode;

        // Fallback if there is no view for the specified status code
        if (!is_file(ROOTPATH . 'modules/Backend/Views/errors/html/error_' . $statusCode . '.php')) {
            $viewFile = (ENVIRONMENT === 'production')
                ? $viewBase . 'production'
                : $viewBase . 'error_exception';
        }

        $body = view($viewFile, ['message' => $message ?? lang('Backend.recordNotFound')]);

        return $this->response
            ->setStatusCode($statusCode)
            ->setBody($body);
    }
}
