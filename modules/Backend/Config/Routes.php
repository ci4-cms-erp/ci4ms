<?php

/**
 * Define Users Routes
 */

$routes->group('backend', ['namespace' => 'Modules\Backend\Controllers'], function ($routes) {
    $routes->get('403', 'Errors::error_403', ["as" => "403"]);

    // Login/out
    $routes->get('login', 'Auth\AuthController::login', ['as' => 'login']);
    $routes->post('login', 'Auth\AuthController::attemptLogin');
    $routes->get('logout', 'Auth\AuthController::logout', ['as' => 'logout']);

    // Activation
    $routes->get('activate-account/(:any)', 'Auth\AuthController::activateAccount/$1', ['as' => 'activate-account']);
    $routes->get('activate-email/(:any)', 'Auth\AuthController::activateEmail/$1', ['as' => 'activate-email']);

    // Forgot/Resets
    $routes->get('forgot', 'Auth\AuthController::forgotPassword', ['as' => 'forgot']);
    $routes->post('forgot', 'Auth\AuthController::attemptForgot', []);
    $routes->get('reset-password/(:any)', 'Auth\AuthController::resetPassword/$1', ['as' => 'reset-password']);
    $routes->post('reset-password/(:any)', 'Auth\AuthController::attemptReset/$1', []);

    $routes->get('/', 'Backend::index', []);

    // Users Module
    $routes->group('officeWorker', function ($routes) {
        $routes->get('(:num)', 'UserController::officeWorker/$1', ['as' => 'officeWorker']);
        $routes->get('create_user', 'UserController::create_user', ['as' => 'create_user']);
        $routes->post('create_user', 'UserController::create_user_post', []);
        $routes->get('update_user/(:any)', 'UserController::update_user/$1', ['as' => 'update_user']);
        $routes->post('update_user/(:any)', 'UserController::update_user_post/$1', []);
        $routes->get('user_del/(:any)', 'UserController::user_del/$1', ['as' => 'user_del']);
        $routes->post('blackList', 'UserController::ajax_blackList_post', ['as' => 'blackList']);
        $routes->post('removeFromBlacklist', 'UserController::ajax_remove_from_blackList_post', []);
        $routes->post('forceResetPassword', 'UserController::ajax_force_reset_password', ['as' => 'forceResetPassword']);
        $routes->get('user_perms/(:any)', 'PermgroupController::user_perms/$1', ['as' => 'user_perms']);
        $routes->post('user_perms/(:any)', 'PermgroupController::user_perms_post/$1', []);

        $routes->get('groupList/(:num)', 'PermgroupController::groupList/$1', ['as' => 'groupList']);
        $routes->get('group_create', 'PermgroupController::group_create', ['as' => 'group_create']);
        $routes->post('group_create', 'PermgroupController::group_create_post', []);
        $routes->get('group_update/(:any)', 'PermgroupController::group_update/$1', ['as' => 'group_update']);
        $routes->post('group_update/(:any)', 'PermgroupController::group_update_post/$1', ['as' => 'group_update']);
    });

    //Pages Module
    $routes->group('pages', function ($routes) {
        $routes->get('(:num)', 'Pages::index/$1', ['as' => 'pages']);
        $routes->match(['GET', 'POST'], 'create', 'Pages::create', ['as' => 'pageCreate']);
        $routes->match(['GET', 'POST'], 'pageUpdate/(:any)', 'Pages::update/$1', ['as' => 'pageUpdate']);
        $routes->get('pageDelete/(:any)', 'Pages::delete_post/$1', ['as' => 'pageDelete']);
    });

    $routes->match(['GET', 'POST'], 'profile', 'UserController::profile', ['as' => 'profile']);

    //setting module
    $routes->group('settings', function ($routes) {
        $routes->get('/', 'Settings::index', ['as' => 'settings']);
        $routes->post('compInfos', 'Settings::compInfosPost', ['as' => 'compInfosPost']);
        $routes->post('socialMedia', 'Settings::socialMediaPost', ['as' => 'socialMediaPost']);
        $routes->post('mailSettings', 'Settings::mailSettingsPost', ['as' => 'mailSettingsPost']);
        $routes->post('loginSettings', 'Settings::loginSettingsPost', ['as' => 'loginSettingsPost']);
        $routes->post('setTemplate', 'Settings::templateSelectPost', ['as' => 'setTemplate']);
        $routes->post('saveAllowedFiles', 'Settings::saveAllowedFiles', ['as' => 'saveAllowedFiles']);
        $routes->get('templateSettings', 'Settings::templateSettings', ['as' => 'templateSettings']);
        $routes->post('templateSettings_post', 'Settings::templateSettings_post', ['as' => 'templateSettings_post']);
        $routes->post('elfinderConvertWebp', 'AJAX::elfinderConvertWebp', ['as' => 'elfinderConvertWebp']);
    });

    //menu module
    $routes->group('menu', function ($routes) {
        $routes->get('/', 'Menu::index', ['as' => 'menu']);
        $routes->post('createMenu', 'Menu::create', ['as' => 'createMenu']);
        $routes->post('deleteMenuAjax', 'Menu::delete_ajax', ['as' => 'deleteMenuAjax']);
        $routes->post('queueMenuAjax', 'Menu::queue_ajax', ['as' => 'queueMenuAjax']);
        $routes->post('menuList', 'Menu::listURLs', ['as' => 'menuList']);
        $routes->post('addMultipleMenu', 'Menu::addMultipleMenu', ['as' => 'addMultipleMenu']);
    });

    //blog module
    $routes->group('blogs', function ($routes) {
        $routes->get('(:num)', 'Blog::index/$1', ['as' => 'blogs']);
        $routes->match(['GET', 'POST'], 'create', 'Blog::new', ['as' => 'blogCreate']);
        $routes->match(['GET', 'POST'], 'update/(:any)', 'Blog::edit/$1', ['as' => 'blogUpdate']);
        $routes->get('delete/(:any)', 'Blog::delete/$1', ['as' => 'blogDelete']);

        //categories
        $routes->group('categories', function ($routes) {
            $routes->get('(:num)', 'Categories::index/$1', ['as' => 'categories']);
            $routes->match(['GET', 'POST'], 'Categories::new', ['as' => 'categoryCreate']);
            $routes->match(['GET', 'POST'], 'update/(:any)', 'Categories::edit/$1', ['as' => 'categoryUpdate']);
            $routes->get('delete/(:any)', 'Categories::delete/$1', ['as' => 'categoryDelete']);
        });

        //tags
        $routes->group('tags', function ($routes) {
            $routes->get('(:num)', 'Tags::index/$1', ['as' => 'tags']);
            $routes->post('create', 'Tags::create', ['as' => 'tagCreate']);
            $routes->match(['GET', 'POST'], 'update/(:any)', 'Tags::edit/$1', ['as' => 'tagUpdate']);
            $routes->get('delete/(:any)', 'Tags::delete/$1', ['as' => 'tagDelete']);
        });

        $routes->group('comments', function ($routes) {
            $routes->get('/', 'Blog::commentList', ['as' => 'comments']);
            $routes->post('commentResponse', 'Blog::commentResponse/$1', ['as' => 'commentResponse']);
            $routes->get('commentRemove/(:num)', 'Blog::commentRemove/$1', ['as' => 'commentRemove']);
            $routes->get('displayComment/(:num)', 'Blog::displayComment/$1', ['as' => 'displayComment']);
            $routes->post('confirmComment/(:num)', 'Blog::confirmComment/$1', ['as' => 'confirmComment']);
            $routes->get('badwords', 'Blog::badwordList', ['as' => 'badwords']);
            $routes->post('badwordsAdd', 'Blog::badwordsAdd', ['as' => 'badwordsAdd']);
        });
    });

    // Other Pages
    $routes->post('tagify', 'AJAX::limitTags_ajax', ['as' => 'tagify']);
    $routes->post('checkSeflink', 'AJAX::autoLookSeflinks', ['as' => 'checkSeflink']);
    $routes->post('isActive', 'AJAX::isActive', ['as' => 'isActive']);
    $routes->post('maintenance', 'AJAX::maintenance', ['as' => 'maintenance']);
    $routes->group('media', function ($routes) {
        $routes->get('/', 'Media::index', ['as' => 'media', 'filter' => 'backendAfterLoginFilter']);
        $routes->get('elfinderConnection', 'Media::elfinderConnection', ['as' => 'elfinderConnection', 'filter' => 'backendAfterLoginFilter']);
        $routes->post('elfinderConnection', 'Media::elfinderConnection', ['as' => 'elfinderConnection', 'filter' => 'backendAfterLoginFilter']);
    });

    //log module
    $routes->group('locked', function ($routes) {
        $routes->get('(:num)', 'Locked::index/$1', ['as' => 'locked']);
    });
});
