<?php

$routes->group('backend', static function ($routes) {
    service('auth')->routes($routes, ['namespace' => 'Modules\Auth\Controllers']);
    $routes->get('login/verify-account', '\Modules\Auth\Controllers\CustomActivationController::verify', ['as' => 'register-verify-account']);
});

$routes->group('backend', static function ($routes) {
    $routes->get( 'lock',        '\Modules\Auth\Controllers\LockController::lockView',     ['as' => 'lock-screen']);
    $routes->post('lock',        '\Modules\Auth\Controllers\LockController::unlockAction');
    $routes->post('lock/set',    '\Modules\Auth\Controllers\LockController::setLockAction', ['as' => 'lock-set']);
    $routes->get( 'lock/switch', '\Modules\Auth\Controllers\LockController::switchAccount', ['as' => 'lock-switch']);
});
