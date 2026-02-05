<?php
$routes->group('backend/backup', ['namespace' => 'Modules\Backup\Controllers'], function($routes) {
    $routes->match(['GET', 'POST'], '/', 'Backup::index',['as' => 'backup', 'role' => 'read']);
    $routes->post('restore', 'Backup::restore',['as' => 'backupRestore', 'role' => 'create']);
    $routes->post('create', 'Backup::create', ['as' => 'backupCreate', 'role' => 'create']);
    $routes->get('download/(:any)', 'Backup::download/$1', ['as' => 'backupDownload', 'role' => 'read']);
    $routes->post('delete/(:num)', 'Backup::delete/$1',['as' => 'backupDelete', 'role' => 'delete']);
});
