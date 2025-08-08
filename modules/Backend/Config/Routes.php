<?php

/**
 * Define Users Routes
 */

$routes->group('backend', ['namespace' => 'Modules\Backend\Controllers'], function ($routes) {
    $routes->get('403', 'Errors::error_403', ["as" => "403"]);

    $routes->get('/', 'Backend::index', []);

    // Users Module
    $routes->group('officeWorker', function ($routes) {
        $routes->get('(:num)', 'UserController::officeWorker/$1', ['as' => 'officeWorker']);
        $routes->match(['GET','POST'],'create_user', 'UserController::create_user', ['as' => 'create_user']);
        $routes->match(['GET','POST'],'update_user/(:any)', 'UserController::update_user/$1', ['as' => 'update_user']);
        $routes->get('user_del/(:any)', 'UserController::user_del/$1', ['as' => 'user_del']);
        $routes->post('blackList', 'UserController::ajax_blackList_post', ['as' => 'blackList']);
        $routes->post('removeFromBlacklist', 'UserController::ajax_remove_from_blackList_post', []);
        $routes->post('forceResetPassword', 'UserController::ajax_force_reset_password', ['as' => 'forceResetPassword']);
        $routes->match(['GET', 'POST'], 'user_perms/(:any)', 'PermgroupController::user_perms/$1', ['as' => 'user_perms']);

        $routes->get('groupList/(:num)', 'PermgroupController::groupList/$1', ['as' => 'groupList']);
        $routes->match(['GET', 'POST'], 'group_create', 'PermgroupController::group_create', ['as' => 'group_create']);
        $routes->match(['GET', 'POST'], 'group_update/(:any)', 'PermgroupController::group_update/$1', ['as' => 'group_update']);
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
        $routes->post('testMail', 'Settings::testMail', ['as' => 'testMail']);
    });

    // Other Pages
    $routes->post('tagify', 'AJAX::limitTags_ajax', ['as' => 'tagify']);
    $routes->post('checkSeflink', 'AJAX::autoLookSeflinks', ['as' => 'checkSeflink']);
    $routes->post('isActive', 'AJAX::isActive', ['as' => 'isActive']);
    $routes->post('maintenance', 'AJAX::maintenance', ['as' => 'maintenance']);

    //log module
    $routes->group('locked', function ($routes) {
        $routes->get('(:num)', 'Locked::index/$1', ['as' => 'locked']);
    });
});
