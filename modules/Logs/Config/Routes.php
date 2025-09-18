<?php
$routes->group('backend/logs', ['namespace' => 'Modules\Logs\Controllers'], function($routes) {
    $routes->get('/', 'Logs::index',['as' => 'logs', 'role' => 'read']);
});
