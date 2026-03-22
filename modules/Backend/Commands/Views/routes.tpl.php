<@php
    if (! $set=cache('settings')) {
    $commonModel=new \ci4commonmodel\CommonModel();
    $set=[];
    foreach ($commonModel->lists('settings') as $setting) {
    $decoded = json_decode($setting->value);
    $set[$setting->key] = (json_last_error() === JSON_ERROR_NONE && (is_object($decoded) || is_array($decoded)))
    ? $decoded
    : $setting->value;
    }
    cache()->save('settings', $set, 86400);
    }
    $settings = (object) $set;
    /**
    * --------------------------------------------------------------------
    * Router Setup
    * --------------------------------------------------------------------
    */
    $routes->setDefaultNamespace('App\Controllers');
    $routes->setDefaultController('Home');
    $routes->setDefaultMethod('index');
    $routes->setTranslateURIDashes(false);
    $routes->set404Override('App\Controllers\Errors::error404');


    /**
    * --------------------------------------------------------------------
    * Additional Routing
    * --------------------------------------------------------------------
    */
    if (is_file(APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php')) {
    require APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php';
    }

    /**
    * --------------------------------------------------------------------
    * Include Templates Routes Files
    * --------------------------------------------------------------------
    */
    if (!empty($settings->templateInfos->path)) {
    $routesPath = APPPATH . 'Config/templates/' . $settings->templateInfos->path . '/Routes.php';
    if (is_file($routesPath)) {
    require($routesPath);
    }
    }

    /**
    * --------------------------------------------------------------------
    * Include Modules Routes Files
    * --------------------------------------------------------------------
    */
    if (is_dir(ROOTPATH . 'modules')) {
    $modulesPath = ROOTPATH . 'modules/';
    $modules = scandir($modulesPath);

    foreach ($modules as $module) {
    if ($module === '.' || $module === '..') continue;
    if (is_dir($modulesPath) . '/' . $module) {
    $routesPath = $modulesPath . $module . '/Config/Routes.php';
    if (is_file($routesPath)) require($routesPath);
    else continue;
    }
    }
    }


    // Robots
    $routes->get('robots.txt', '\ci4seopro\Controllers\Search\RobotsController::index');
    // Sitemap INDEX/s
    $routes->get('sitemap.xml', '\ci4seopro\Controllers\Search\SitemapController::index');
    $routes->get('sitemap-(:segment).xml', '\ci4seopro\Controllers\Search\SitemapController::chunk/$1');
    // AI
    $routes->get('.well-known/ai.txt', '\ci4seopro\Controllers\Ai\AiTxtController::index');
    $routes->get('llms.txt', '\ci4seopro\Controllers\Ai\AiTxtController::index');
    $routes->get('api/ai/context', '\ci4seopro\Controllers\Ai\AiApiController::context');
    // FEEDS
    $routes->get('feed-(:segment).xml', '\ci4seopro\Controllers\Feed\FeedController::show/$1');
    $routes->get('feed-(:segment).json', '\ci4seopro\Controllers\Feed\FeedController::show/$1');

    // For custom HTML verification files (e.g. googleXXXX.html)
    $routes->get('(:segment).html', 'Search\VerificationController::html/$1');
    // .well-known/*
    $routes->get('.well-known/(:segment)', 'Search\VerificationController::wellKnown/$1');
    $routes->get('seo/health', 'Seo\HealthController::index');

    /*
    * @var RouteCollection $routes
    */

    // ── POST / Utility routes (no locale prefix needed) ──
    $routes->post('newComment', 'Home::newComment', ['filter' => 'langfilter', 'as' => 'newComment']);
    $routes->post('repliesComment', 'Home::repliesComment', ['filter' => 'langfilter', 'as' => 'repliesComment']);
    $routes->post('loadMoreComments', 'Home::loadMoreComments', ['filter' => 'langfilter', 'as' => 'loadMoreComments']);
    $routes->post('commentCaptcha', 'Home::commentCaptcha', ['filter' => 'langfilter', 'as' => 'commentCaptcha']);
    $routes->get('maintenance-mode', 'Home::maintenanceMode', ['as' => 'maintenance-mode']);

    // ── Multi-language locale-prefixed routes (REQUIRED for all frontend) ──
    $siteLanguageMode = $settings->siteLanguageMode ?? 'single';

    if ($siteLanguageMode === 'multi') {
    $frontendLangs = cache('frontend_languages');
    if ($frontendLangs === null) {
    if (!isset($commonModel)) $commonModel = new \ci4commonmodel\CommonModel();
    $frontendLangs = $commonModel->lists('languages', 'code, flag, name as title', [
    'is_active' => 1,
    'is_frontend' => 1,
    ], 'sort_order ASC');
    cache()->save('frontend_languages', $frontendLangs, 3600);
    }

    if (!empty($frontendLangs)) {
    $frontendLangCodes = array_map(function ($l) {
    return is_object($l) ? $l->code : $l;
    }, $frontendLangs);

    $localeRegex = implode('|', $frontendLangCodes);
    $routes->group('{locale}', ['filter' => 'langfilter', 'placeholder' => ['locale' => $localeRegex]], function ($routes) {
    $routes->get('/', 'Home::index', ['as' => 'home']);
    $routes->get('blog', 'Home::blog');
    $routes->get('blog/(:num)', 'Home::blog/$1');
    $routes->get('blog/(:any)', 'Home::blogDetail/$1');
    $routes->get('tag/(:any)', 'Home::tagList/$1', ['as' => 'tag']);
    $routes->get('category/(:any)', 'Home::category/$1', ['as' => 'category']);
    $routes->post('search', 'Home::search', ['as' => 'search']);
    $routes->get('(:any)', 'Home::index/$1');
    });
    }

    // Root URL (/) redirect will be handled by LocaleFilter
    $routes->get('/', 'Home::index', ['filter' => 'langfilter', 'as' => 'home_root']);
    } else {
    // Single language mode routes (no locale prefix)
    $routes->get('/', 'Home::index', ['filter' => 'ci4ms', 'as' => 'home']);
    $routes->get('blog', 'Home::blog', ['filter' => 'ci4ms']);
    $routes->get('blog/(:num)', 'Home::blog/$1', ['filter' => 'ci4ms']);
    $routes->get('blog/(:any)', 'Home::blogDetail/$1', ['filter' => 'ci4ms']);
    $routes->get('tag/(:any)', 'Home::tagList/$1', ['filter' => 'ci4ms', 'as' => 'tag']);
    $routes->get('category/(:any)', 'Home::category/$1', ['filter' => 'ci4ms', 'as' => 'category']);
    $routes->post('search', 'Home::search', ['filter' => 'ci4ms', 'as' => 'search']);
    $routes->get('(:any)', 'Home::index/$1', ['filter' => 'ci4ms']);
    }
