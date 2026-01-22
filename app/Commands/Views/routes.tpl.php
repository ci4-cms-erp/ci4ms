<@php

if(empty(cache('settings'))){
    $commonModel = new \ci4commonmodel\Models\CommonModel();
    $settings=$commonModel->lists('settings');
    $set=[];
    $formatRules=new \CodeIgniter\Validation\FormatRules();
    foreach ($settings as $setting) {
        if($formatRules->valid_json($setting->content)===true)
            $set[$setting->option]=(object)json_decode($setting->content,JSON_UNESCAPED_UNICODE);
        else
            $set[$setting->option] = $setting->content;
    }
    cache()->save('settings',$set,86400);
    $settings=(object)$set;
}
else $settings=(object)cache('settings');

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
 *
 * There will often be times that you need additional routing and you
 * need it to be able to override any defaults in this file. Environment
 * based routes is one such time. require() additional route files here
 * to make that happen.
 *
 * You will have access to the $routes object within that file without
 * needing to reload it.
 */
if (is_file(APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php')) {
    require APPPATH . 'Config/' . ENVIRONMENT . '/Routes.php';
}

/**
 * --------------------------------------------------------------------
 * Include Templates Routes Files
 * --------------------------------------------------------------------
 */
if (!empty($settings->templateInfos->path) && is_dir(APPPATH.'Config')) {
    $modulesPath = APPPATH.'Config';
    $modules = scandir($modulesPath.'/templates');
    foreach ($modules as $module) {
        if ($module === '.' || $module === '..') continue;
        if (is_dir($modulesPath) . '/' . $module) {
            $routesPath = $modulesPath . '/templates/'.$settings->templateInfos->path.'/Routes.php';
            if (is_file($routesPath)) require($routesPath);
            else continue;
        }
    }
}

/**
 * --------------------------------------------------------------------
 * Include Modules Routes Files
 * --------------------------------------------------------------------
 */
if (is_dir(ROOTPATH.'modules')) {
    $modulesPath = ROOTPATH.'modules/';
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

/*
 * @var RouteCollection $routes
 */

// We get a performance increase by specifying the default
// route since we don't have to scan directories.
$routes->get('/', 'Home::index',['filter'=>'ci4ms','as'=>'home']);
$routes->get('maintenance-mode','Home::maintenanceMode',['as'=>'maintenance-mode']);
$routes->get('blog','Home::blog',['filter'=>'ci4ms']);
$routes->get('blog/(:num)','Home::blog/$1',['filter'=>'ci4ms']);
$routes->get('blog/(:any)','Home::blogDetail/$1',['filter'=>'ci4ms']);
$routes->get('tag/(:any)','Home::tagList/$1',['filter'=>'ci4ms','as'=>'tag']);
$routes->get('category/(:any)','Home::category/$1',['filter'=>'ci4ms','as'=>'category']);
$routes->post('newComment','Home::newComment',['filter'=>'ci4ms','as'=>'newComment']);
$routes->post('repliesComment','Home::repliesComment',['filter'=>'ci4ms','as'=>'repliesComment']);
$routes->post('loadMoreComments','Home::loadMoreComments',['filter'=>'ci4ms','as'=>'loadMoreComments']);
$routes->post('commentCaptcha','Home::commentCaptcha',['filter'=>'ci4ms','as'=>'commentCaptcha']);
$routes->post('search','Home::search',['filter'=>'ci4ms','as'=>'search']);
// Robots
$routes->get('robots.txt', '\ci4seopro\Controllers\Search\RobotsController::index');
// Sitemap INDEX/s
$routes->get('sitemap.xml', '\ci4seopro\Controllers\Search\SitemapController::index');
$routes->get('sitemap-(:segment).xml', '\ci4seopro\Controllers\Search\SitemapController::chunk/$1');
// AI
$routes->get('.well-known/ai.txt', '\ci4seopro\Controllers\Ai\AiTxtController::index');
$routes->get('llms.txt', '\ci4seopro\Controllers\Ai\AiTxtController::index'); // alias
$routes->get('api/ai/context', '\ci4seopro\Controllers\Ai\AiApiController::context');
// FEEDS
$routes->get('feed-(:segment).xml', '\ci4seopro\Controllers\Feed\FeedController::show/$1'); // rss2/atom
$routes->get('feed-(:segment).json', '\ci4seopro\Controllers\Feed\FeedController::show/$1'); // jsonfeed/llm-changefeed

// For custom HTML verification files (e.g. googleXXXX.html)
$routes->get('(:segment).html', 'Search\VerificationController::html/$1');
// .well-known/*
$routes->get('.well-known/(:segment)', 'Search\VerificationController::wellKnown/$1');
$routes->get('seo/health', 'Seo\HealthController::index');

$routes->get('/(:any)', 'Home::index/$1',['filter'=>'ci4ms']);
