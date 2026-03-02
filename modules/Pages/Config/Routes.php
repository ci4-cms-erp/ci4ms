<?php
$routes->group('backend/pages', ['namespace' => 'Modules\Pages\Controllers'], function ($routes) {
    $routes->match(['GET', 'POST'], '/', 'Pages::index', ['as' => 'pages', 'role' => 'read,create,update,delete']);
    $routes->match(['GET', 'POST'], 'create', 'Pages::create', ['as' => 'pageCreate', 'role' => 'create,read']);
    $routes->match(['GET', 'POST'], 'pageUpdate/(:any)', 'Pages::update/$1', ['as' => 'pageUpdate', 'role' => 'update,read']);
    $routes->post('pageDelete', 'Pages::delete_post', ['as' => 'pageDelete', 'role' => 'delete']);
});
