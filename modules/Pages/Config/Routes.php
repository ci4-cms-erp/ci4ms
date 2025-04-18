<?php
$routes->group('backend/pages', ['namespace' => 'Modules\Pages\Controllers'], function ($routes) {
    $routes->get('(:num)', 'Pages::index/$1', ['as' => 'pages']);
    $routes->match(['GET', 'POST'], 'create', 'Pages::create', ['as' => 'pageCreate']);
    $routes->match(['GET', 'POST'], 'pageUpdate/(:any)', 'Pages::update/$1', ['as' => 'pageUpdate']);
    $routes->get('pageDelete/(:any)', 'Pages::delete_post/$1', ['as' => 'pageDelete']);
});
