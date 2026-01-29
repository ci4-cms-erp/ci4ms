<?php
// Users Module
$routes->group('backend/users', ['namespace' => 'Modules\Users\Controllers'], function ($routes) {
    $routes->match(['GET', 'POST'],'/', 'UserController::users', ['as' => 'users', 'role' => 'create,read,update,delete']);
    $routes->match(['GET', 'POST'], 'create_user', 'UserController::create_user', ['as' => 'create_user', 'role' => 'create']);
    $routes->match(['GET', 'POST'], 'update_user/(:any)', 'UserController::update_user/$1', ['as' => 'update_user', 'role' => 'update']);
    $routes->get('user_del/(:any)', 'UserController::user_del/$1', ['as' => 'user_del', 'role' => 'delete']);
    $routes->post('blackList', 'UserController::ajax_blackList_post', ['as' => 'blackList', 'role' => 'update']);
    $routes->post('removeFromBlacklist', 'UserController::ajax_remove_from_blackList_post', ['as' => 'removeFromBlacklist', 'role' => 'update']);
    $routes->post('forceResetPassword', 'UserController::ajax_force_reset_password', ['as' => 'forceResetPassword', 'role' => 'update']);
    $routes->match(['GET', 'POST'], 'user_perms/(:any)', 'PermgroupController::user_perms/$1', ['as' => 'user_perms', 'role' => 'update']);

    $routes->match(['GET', 'POST'],'groupList', 'PermgroupController::groupList', ['as' => 'groupList', 'role' => 'read,create,update,delete']);
    $routes->match(['GET', 'POST'], 'group_create', 'PermgroupController::group_create', ['as' => 'group_create', 'role' => 'create']);
    $routes->match(['GET', 'POST'], 'group_update/(:any)', 'PermgroupController::group_update/$1', ['as' => 'group_update', 'role' => 'update']);
    $routes->match(['GET', 'POST'], 'profile', 'UserController::profile', ['as' => 'profile', 'role' => 'read,create']);
});
