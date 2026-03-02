<?php

$routes->group('backend', static function ($routes) {
    service('auth')->routes($routes, ['namespace' => 'Modules\Auth\Controllers']);
    $routes->get('login/verify-account', '\Modules\Auth\Controllers\CustomActivationController::verify', ['as' => 'register-verify-account']);
});
