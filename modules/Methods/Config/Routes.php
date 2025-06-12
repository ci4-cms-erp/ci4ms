<?php
$routes->group('backend/methods', ['namespace' => 'Modules\Methods\Controllers'], function ($routes) {
    $routes->match(['GET', 'POST'], '/', 'Methods::index', ['as' => 'list']);
    $routes->match(['GET', 'POST'], 'create', 'Methods::create', ['as' => 'methodCreate']);
    $routes->match(['GET', 'POST'], 'update/(:num)', 'Methods::update/$1', ['as' => 'methodUpdate']);
    $routes->get('delete/(:num)', 'Methods::delete/$1', ['as' => 'methodDelete']);
});
