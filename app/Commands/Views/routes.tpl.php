<@php

namespace Config;

use CodeIgniter\Router\RouteCollection;

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
}
else $settings=(object)cache('settings');

/**
 * @var RouteCollection $routes
 */

/*
 * --------------------------------------------------------------------
 * Router Setup
 * --------------------------------------------------------------------
 */
$routes->setDefaultNamespace('App\Controllers');
$routes->setDefaultController('Home');
$routes->setDefaultMethod('index');
$routes->setTranslateURIDashes(false);
$routes->set404Override('App\Controllers\Errors::error404');
/* The Auto Routing (Legacy) is very dangerous. It is easy to create vulnerable apps
where controller filters or CSRF protection are bypassed.
If you don't want to define all routes, please use the Auto Routing (Improved).
Set `$autoRoutesImproved` to true in `app/Config/Feature.php` and set the following to true.
$routes->setAutoRoute(false); */

/*
 * --------------------------------------------------------------------
 * Route Definitions
 * --------------------------------------------------------------------
 */

// We get a performance increase by specifying the default
// route since we don't have to scan directories.
$routes->get('/', 'Home::index',['filter'=>'ci4ms']);
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

/*
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
if (is_dir(APPPATH.'Config')) {
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

$routes->get('/(:any)', 'Home::index/$1',['filter'=>'ci4ms']);