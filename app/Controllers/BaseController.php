<?php

namespace App\Controllers;

use ci4commonmodel\CommonModel;
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

    protected function getModuleLinkMaps(): array
    {
        $map = [
            'pages'    => ['table' => 'pages_langs', 'fk' => 'pages_id', 'routePrefix' => ''],
            'blog'     => ['table' => 'blog_langs', 'fk' => 'blog_id', 'routePrefix' => 'blog/'],
            'category' => ['table' => 'categories_langs', 'fk' => 'categories_id', 'routePrefix' => 'blog/category/'],
        ];

        $modulesPath = ROOTPATH . 'modules/';
        if (is_dir($modulesPath)) {
            $modules = array_filter(scandir($modulesPath), function ($module) use ($modulesPath) {
                return !in_array($module, ['.', '..']) && is_dir($modulesPath . DIRECTORY_SEPARATOR . $module);
            });
            foreach ($modules as $module) {
                $configClass = "Modules\\{$module}\\Config\\{$module}Config";
                if (class_exists($configClass)) {
                    $configInstance = new $configClass();
                    if (property_exists($configInstance, 'linkMap') && is_array($configInstance->linkMap)) {
                        $map = array_merge($map, $configInstance->linkMap);
                    }
                }
            }
        }
        return $map;
    }

    protected function getDefaultData(): array
    {
        $settings = (object)cache('settings');
        $locale   = $this->request->getLocale();
        $cacheKey = 'menus_' . $locale;

        if (empty(cache($cacheKey))) {
            $rawMenus    = $this->commonModel->lists('menu', '*', [], 'queue ASC');
            $isMulti     = ($settings->siteLanguageMode ?? 'single') === 'multi';
            $defaultLang = cache('default_frontend_language') ?? 'tr';
            $homePageId  = setting('App.homePage');
            $linkMaps    = $this->getModuleLinkMaps();

            // Collect IDs for batch loading by dynamic urlType
            $typeIds = [];
            foreach ($rawMenus as $m) {
                if (!empty($m->pages_id) && $m->urlType !== 'url' && isset($linkMaps[$m->urlType])) {
                    $typeIds[$m->urlType][] = $m->pages_id;
                }
            }

            // Batch Load Translations
            $typeTranslations = [];
            foreach ($typeIds as $type => $ids) {
                if (empty($ids)) continue;
                $map = $linkMaps[$type];
                $trans = $this->commonModel->db->table($map['table'])
                    ->select("{$map['fk']}, title, seflink")
                    ->where('lang', $locale)
                    ->whereIn($map['fk'], $ids)
                    ->get()->getResult();

                foreach ($trans as $t) {
                    $fk = $map['fk'];
                    $typeTranslations[$type][$t->$fk] = $t;
                }
            }

            // Sync Translations & Build Seflinks
            $localePrefix = $isMulti ? $locale . '/' : '';

            foreach ($rawMenus as $key => &$m) {
                if (!empty($m->pages_id) && $m->urlType !== 'url') {
                    $map = $linkMaps[$m->urlType] ?? null;
                    if ($map && isset($typeTranslations[$m->urlType][$m->pages_id])) {
                        $transObj = $typeTranslations[$m->urlType][$m->pages_id];
                        $m->title = $transObj->title;

                        // Handle homepage exception for "pages" type
                        if ($m->urlType === 'pages' && $m->pages_id == $homePageId) {
                            $m->seflink = '';
                        } else {
                            $m->seflink = $map['routePrefix'] . $transObj->seflink;
                        }
                    } else {
                        // Inherently, if no translation or unknown type, REMOVE FROM MENU
                        unset($rawMenus[$key]);
                        continue;
                    }
                }

                // Pre-build the final site URL path
                if (!empty($m->seflink) && !str_starts_with($m->seflink, 'http://') && !str_starts_with($m->seflink, 'https://')) {
                    $m->seflink = $localePrefix . ltrim($m->seflink, '/');
                } elseif (empty($m->seflink) && $m->urlType !== 'url') {
                    // Home page case
                    $m->seflink = $localePrefix;
                }
            }

            cache()->save($cacheKey, array_values($rawMenus), 86400);
            $menus = array_values($rawMenus);
        } else {
            $menus = cache($cacheKey);
        }

        $defData = [
            'settings'       => $settings,
            'menus'          => $menus,
            'languages'      => cache('frontend_languages') ?? [],
            'alternateLinks' => [], // Default empty, filled by child controllers
            'agent'          => $this->request->getUserAgent(),
            'seoConfig'      => new Seo()
        ];

        // If languages are empty in cache, load them (fallback)
        if (empty($defData['languages'])) {
            $langs = $this->commonModel->lists('languages', 'code, flag, name as title', [
                'is_active'   => 1,
                'is_frontend' => 1,
            ], 'sort_order ASC', -1, -1);
            $defData['languages'] = $langs;
            cache()->save('frontend_languages', $langs, 86400);
        }

        $defData['seoConfig']->siteName = $defData['settings']->siteName;
        //dd($defData);
        return $defData;
    }

    /**
     * Calculates the seflink for the current content in all active languages.
     */
    protected function calculateAlternateLinks(string $type, int $id): void
    {
        $map = $this->getModuleLinkMaps();

        if (!isset($map[$type])) return;

        $info = $map[$type];
        $translations = $this->commonModel->lists($info['table'], 'lang, seflink', [
            $info['fk'] => $id
        ], 'id ASC', -1, -1);

        $defaultLang = cache('default_frontend_language') ?? 'tr';
        $isMulti = ($this->defData['settings']->siteLanguageMode ?? 'single') === 'multi';
        $homePageId = setting('App.homePage');

        foreach ($translations as $t) {
            $prefix = $isMulti ? $t->lang . '/' : '';
            $seflink = $t->seflink;

            // Home page check (only for pages module)
            if ($type === 'pages' && $id == $homePageId) {
                $seflink = '';
            }

            $this->defData['alternateLinks'][$t->lang] = site_url($prefix . $info['routePrefix'] . $seflink);
        }
    }

    protected function seo()
    {
        if (!$this->seosearchService) {
            $this->seosearchService = service('seosearch', $this->defData['seoConfig']);
        }
        return $this->seosearchService;
    }
}
