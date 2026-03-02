<?php
$routes->group('backend/logs', ['namespace' => 'Modules\Logs\Controllers'], function ($routes) {
    $routes->get('/', 'Logs::index', ['as' => 'logs', 'role' => 'read']);
    $routes->post('delete', 'Logs::delete_post', ['as' => 'logDelete', 'role' => 'delete']);
});
