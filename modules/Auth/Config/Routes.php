<?php
$routes->group('backend', ['namespace' => 'Modules\Auth\Controllers'], function ($routes) {
    // Login/out
    $routes->match(['GET', 'POST'], 'login', 'AuthController::login', ['as' => 'login']);
    $routes->get('logout', 'AuthController::logout', ['as' => 'logout']);

    // Activation
    $routes->get('activate-account/(:any)', 'AuthController::activateAccount/$1', ['as' => 'activate-account']);
    $routes->get('activate-email/(:any)', 'AuthController::activateEmail/$1', ['as' => 'activate-email']);

    // Forgot/Resets
    $routes->match(['GET', 'POST'], 'forgot', 'AuthController::forgotPassword', ['as' => 'forgot']);
    $routes->match(['GET', 'POST'], 'reset-password/(:any)', 'AuthController::resetPassword/$1', ['as' => 'reset-password']);
    $routes->post('reset-password/(:any)', 'AuthController::attemptReset/$1', []);
});
