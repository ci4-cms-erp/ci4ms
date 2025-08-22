<?php
$routes->group('backend/pages', ['namespace' => 'Modules\Pages\Controllers'], function ($routes) {
    $routes->get('(:num)', 'Pages::index/$1', ['as' => 'pages','role'=>'read,create,update,delete']);
    $routes->match(['GET', 'POST'], 'create', 'Pages::create', ['as' => 'pageCreate','role'=>'create']);
    $routes->match(['GET', 'POST'], 'pageUpdate/(:any)', 'Pages::update/$1', ['as' => 'pageUpdate','role'=>'update']);
    $routes->get('pageDelete/(:any)', 'Pages::delete_post/$1', ['as' => 'pageDelete','role'=>'delete']);
});
