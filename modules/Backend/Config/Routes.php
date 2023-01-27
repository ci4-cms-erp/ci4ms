<?php

/**
 * Define Users Routes
 */

$routes->group('backend', ['namespace' => 'Modules\Backend\Controllers'], function ($routes) {
    $routes->get('403', 'Errors::error_403', ["as" => "403", 'filter' => 'backendAfterLoginFilter']);

    // Login/out
    $routes->get('login', 'Auth\AuthController::login', ['filter' => 'backendAuthFilter', 'as' => 'login']);
    $routes->post('login', 'Auth\AuthController::attemptLogin', ['filter' => 'backendAuthFilter']);
    $routes->get('logout', 'Auth\AuthController::logout', ['as' => 'logout']);

    // Activation
    $routes->get('activate-account/(:any)', 'Auth\AuthController::activateAccount/$1', ['filter' => 'backendAuthFilter', 'as' => 'activate-account']);
    $routes->get('activate-email/(:any)', 'Auth\AuthController::activateEmail/$1', ['filter' => 'backendAuthFilter', 'as' => 'activate-email']);

    // Forgot/Resets
    $routes->get('forgot', 'Auth\AuthController::forgotPassword', ['filter' => 'backendAuthFilter', 'as' => 'forgot']);
    $routes->post('forgot', 'Auth\AuthController::attemptForgot', ['filter' => 'backendAuthFilter']);
    $routes->get('reset-password/(:any)', 'Auth\AuthController::resetPassword/$1', ['filter' => 'backendAuthFilter', 'as' => 'reset-password']);
    $routes->post('reset-password/(:any)', 'Auth\AuthController::attemptReset/$1', ['filter' => 'backendAfterLoginFilter']);

    $routes->get('/', 'Backend::index', ['filter' => 'backendAfterLoginFilter']);

    // Users Module
    $routes->group('officeWorker', function ($routes) {
        $routes->get('(:num)', 'UserController::officeWorker/$1', ['as' => 'officeWorker', 'filter' => 'backendAfterLoginFilter']);
        $routes->get('create_user', 'UserController::create_user', ['as' => 'create_user', 'filter' => 'backendAfterLoginFilter']);
        $routes->post('create_user', 'UserController::create_user_post', ['filter' => 'backendAfterLoginFilter']);
        $routes->get('update_user/(:any)', 'UserController::update_user/$1', ['filter' => 'backendAfterLoginFilter', 'as' => 'update_user']);
        $routes->post('update_user/(:any)', 'UserController::update_user_post/$1', ['filter' => 'backendAfterLoginFilter']);
        $routes->get('user_del/(:any)', 'UserController::user_del/$1', ['filter' => 'backendAfterLoginFilter', 'as' => 'user_del']);
        $routes->post('blackList', 'UserController::ajax_blackList_post', ['filter' => 'backendAfterLoginFilter']);
        $routes->post('removeFromBlacklist', 'UserController::ajax_remove_from_blackList_post', ['filter' => 'backendAfterLoginFilter']);
        $routes->post('forceResetPassword', 'UserController::ajax_force_reset_password', ['filter' => 'backendAfterLoginFilter','as'=>'forceResetPassword']);
        $routes->get('user_perms/(:any)', 'PermgroupController::user_perms/$1', ['filter' => 'backendAfterLoginFilter', 'as' => 'user_perms']);
        $routes->post('user_perms/(:any)', 'PermgroupController::user_perms_post/$1', ['filter' => 'backendAfterLoginFilter']);

        $routes->get('groupList/(:num)', 'PermgroupController::groupList/$1', ['as' => 'groupList', 'filter' => 'backendAfterLoginFilter']);
        $routes->get('group_create', 'PermgroupController::group_create', ['as' => 'group_create', 'filter' => 'backendAfterLoginFilter']);
        $routes->post('group_create', 'PermgroupController::group_create_post', ['filter' => 'backendAfterLoginFilter']);
        $routes->get('group_update/(:any)', 'PermgroupController::group_update/$1', ['filter' => 'backendAfterLoginFilter', 'as' => 'group_update']);
        $routes->post('group_update/(:any)', 'PermgroupController::group_update_post/$1', ['filter' => 'backendAfterLoginFilter', 'as' => 'group_update']);
    });

    //Pages Module
    $routes->group('pages', function ($routes) {
        $routes->get('(:num)', 'Pages::index/$1', ['as' => 'pages', 'filter' => 'backendAfterLoginFilter']);
        $routes->get('create', 'Pages::create', ['as' => 'pageCreate', 'filter' => 'backendAfterLoginFilter']);
        $routes->post('create', 'Pages::create_post', ['filter' => 'backendAfterLoginFilter']);
        $routes->get('pageUpdate/(:any)', 'Pages::update/$1', ['as' => 'pageUpdate', 'filter' => 'backendAfterLoginFilter']);
        $routes->post('pageUpdate/(:any)', 'Pages::update_post/$1', ['filter' => 'backendAfterLoginFilter']);
        $routes->get('pageDelete/(:any)', 'Pages::delete_post/$1', ['as' => 'pageDelete', 'filter' => 'backendAfterLoginFilter']);
    });

    $routes->get('profile', 'UserController::profile', ['filter' => 'backendAfterLoginFilter', 'as' => 'profile']);
    $routes->post('profile', 'UserController::profile_post', ['filter' => 'backendAfterLoginFilter']);

    //setting module
    $routes->group('settings', function ($routes) {
        $routes->get('/', 'Settings::index', ['as' => 'settings', 'filter' => 'backendAfterLoginFilter']);
        $routes->post('compInfos', 'Settings::compInfosPost', ['as' => 'compInfosPost', 'filter' => 'backendAfterLoginFilter']);
        $routes->post('socialMedia', 'Settings::socialMediaPost', ['as' => 'socialMediaPost', 'filter' => 'backendAfterLoginFilter']);
        $routes->post('mailSettings', 'Settings::mailSettingsPost', ['as' => 'mailSettingsPost', 'filter' => 'backendAfterLoginFilter']);
        $routes->post('loginSettings', 'Settings::loginSettingsPost', ['as' => 'loginSettingsPost', 'filter' => 'backendAfterLoginFilter']);
        $routes->post('setTemplate', 'Settings::templateSelectPost', ['as' => 'setTemplate', 'filter' => 'backendAfterLoginFilter']);
        $routes->post('saveAllowedFiles', 'Settings::saveAllowedFiles', ['as' => 'saveAllowedFiles', 'filter' => 'backendAfterLoginFilter']);
        $routes->get('templateSettings', 'Settings::templateSettings', ['as' => 'templateSettings', 'filter' => 'backendAfterLoginFilter']);
        $routes->post('templateSettings_post', 'Settings::templateSettings_post', ['as' => 'templateSettings_post', 'filter' => 'backendAfterLoginFilter']);
    });

    //menu module
    $routes->group('menu', function ($routes) {
        $routes->get('/', 'Menu::index', ['as' => 'menu', 'filter' => 'backendAfterLoginFilter']);
        $routes->post('createMenu', 'Menu::create', ['as' => 'createMenu', 'filter' => 'backendAfterLoginFilter']);
        $routes->post('deleteMenuAjax', 'Menu::delete_ajax', ['as' => 'deleteMenuAjax', 'filter' => 'backendAfterLoginFilter']);
        $routes->post('queueMenuAjax', 'Menu::queue_ajax', ['as' => 'queueMenuAjax', 'filter' => 'backendAfterLoginFilter']);
        $routes->post('menuList', 'Menu::listURLs', ['as' => 'menuList', 'filter' => 'backendAfterLoginFilter']);
        $routes->post('addMultipleMenu', 'Menu::addMultipleMenu', ['as' => 'addMultipleMenu', 'filter' => 'backendAfterLoginFilter']);
    });

    //blog module
    $routes->group('blogs', function ($routes) {
        $routes->get('(:num)', 'Blog::index/$1', ['as' => 'blogs', 'filter' => 'backendAfterLoginFilter']);
        $routes->get('create', 'Blog::new', ['as' => 'blogCreate', 'filter' => 'backendAfterLoginFilter']);
        $routes->post('create', 'Blog::create', ['as' => 'blogCreate', 'filter' => 'backendAfterLoginFilter']);
        $routes->get('update/(:any)', 'Blog::edit/$1', ['as' => 'blogUpdate', 'filter' => 'backendAfterLoginFilter']);
        $routes->post('update/(:any)', 'Blog::update/$1', ['as' => 'blogUpdate', 'filter' => 'backendAfterLoginFilter']);
        $routes->get('delete/(:any)', 'Blog::delete/$1', ['as' => 'blogDelete', 'filter' => 'backendAfterLoginFilter']);

        //categories
        $routes->group('categories', function ($routes) {
            $routes->get('(:num)', 'Categories::index/$1', ['as' => 'categories', 'filter' => 'backendAfterLoginFilter']);
            $routes->get('create', 'Categories::new', ['as' => 'categoryCreate', 'filter' => 'backendAfterLoginFilter']);
            $routes->post('create', 'Categories::create', ['as' => 'categoryCreate', 'filter' => 'backendAfterLoginFilter']);
            $routes->get('update/(:any)', 'Categories::edit/$1', ['as' => 'categoryUpdate', 'filter' => 'backendAfterLoginFilter']);
            $routes->post('update/(:any)', 'Categories::update/$1', ['as' => 'categoryUpdate', 'filter' => 'backendAfterLoginFilter']);
            $routes->get('delete/(:any)', 'Categories::delete/$1', ['as' => 'categoryDelete', 'filter' => 'backendAfterLoginFilter']);
        });

        //tags
        $routes->group('tags', function ($routes) {
            $routes->get('(:num)', 'Tags::index/$1', ['as' => 'tags', 'filter' => 'backendAfterLoginFilter']);
            $routes->post('create', 'Tags::create', ['as' => 'tagCreate', 'filter' => 'backendAfterLoginFilter']);
            $routes->get('update/(:any)', 'Tags::edit/$1', ['as' => 'tagUpdate', 'filter' => 'backendAfterLoginFilter']);
            $routes->post('update/(:any)', 'Tags::update/$1', ['as' => 'tagUpdate', 'filter' => 'backendAfterLoginFilter']);
            $routes->get('delete/(:any)', 'Tags::delete/$1', ['as' => 'tagDelete', 'filter' => 'backendAfterLoginFilter']);
        });

        $routes->group('comments',function ($routes){
           $routes->get('/','Blog::commentList',['as'=>'comments','filter'=>'backendAfterLoginFilter']);
           $routes->post('commentResponse','Blog::commentResponse/$1',['as'=>'commentResponse','filter'=>'backendAfterLoginFilter']);
           $routes->get('commentPendingApproval/(:num)','Blog::commentPendingApproval/$1',['as'=>'commentPendingApproval','filter'=>'backendAfterLoginFilter']);
           $routes->post('confirmComment/(:num)','Blog::confirmComment/$1',['as'=>'confirmComment','filter'=>'backendAfterLoginFilter']);
           $routes->get('badwords/(:num)','Blog::badwordList/$1',['as'=>'badwords','filter'=>'backendAfterLoginFilter']);
           $routes->post('badwordsAdd','Blog::badwordsAdd',['as'=>'badwordsAdd','filter'=>'backendAfterLoginFilter']);
        });
    });

    // Other Pages
    $routes->post('tagify', 'AJAX::limitTags_ajax', ['as' => 'tagify', 'filter' => 'backendAfterLoginFilter']);
    $routes->post('checkSeflink', 'AJAX::autoLookSeflinks', ['as' => 'checkSeflink', 'filter' => 'backendAfterLoginFilter']);
    $routes->post('isActive', 'AJAX::isActive', ['as' => 'isActive', 'filter' => 'backendAfterLoginFilter']);
    $routes->post('maintenance', 'AJAX::maintenance', ['as' => 'maintenance', 'filter' => 'backendAfterLoginFilter']);
    $routes->get('media', 'Media::index', ['as' => 'media', 'filter' => 'backendAfterLoginFilter']);

    //log module
    $routes->group('locked', function ($routes) {
        $routes->get('(:num)', 'Locked::index/$1', ['as' => 'locked', 'filter' => 'backendAfterLoginFilter']);
    });
});
