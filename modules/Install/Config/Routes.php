<?php
$routes->group('install', ['namespace' => 'Modules\Install\Controllers'], function ($routes) {
        $routes->match(['GET', 'POST'], '/', 'Install::index', ['as' => 'install','role'=>'create']);
        $routes->post('dbsetup', 'Install::dbSetup', ['as' => 'install_dbsetup','role'=>'create']);
});
