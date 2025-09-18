<?php
$routes->group('backend/methods', ['namespace' => 'Modules\Methods\Controllers'], function ($routes) {
    $routes->match(['GET', 'POST'], '/', 'Methods::index', ['as' => 'list', 'role' => 'read']);
    $routes->match(['GET', 'POST'], 'create', 'Methods::create', ['as' => 'methodCreate', 'role' => 'create']);
    $routes->match(['GET', 'POST'], 'update/(:num)', 'Methods::update/$1', ['as' => 'methodUpdate', 'role' => 'update']);
    $routes->get('delete/(:num)', 'Methods::delete/$1', ['as' => 'methodDelete', 'role' => 'delete']);
    $routes->post('moduleScan', 'Methods::moduleScan', ['as' => 'moduleScan', 'role' => 'read,create']);
});
