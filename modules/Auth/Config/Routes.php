<?php
$routes->group('backend', ['namespace' => 'Modules\Auth\Controllers'], function ($routes) {
    // Login/out
    $routes->get('login', 'AuthController::login', ['as' => 'login']);
    $routes->post('login', 'AuthController::attemptLogin');
    $routes->get('logout', 'AuthController::logout', ['as' => 'logout']);

    // Activation
    $routes->get('activate-account/(:any)', 'AuthController::activateAccount/$1', ['as' => 'activate-account']);
    $routes->get('activate-email/(:any)', 'AuthController::activateEmail/$1', ['as' => 'activate-email']);

    // Forgot/Resets
    $routes->get('forgot', 'AuthController::forgotPassword', ['as' => 'forgot']);
    $routes->post('forgot', 'AuthController::attemptForgot', []);
    $routes->get('reset-password/(:any)', 'AuthController::resetPassword/$1', ['as' => 'reset-password']);
    $routes->post('reset-password/(:any)', 'AuthController::attemptReset/$1', []);
});
