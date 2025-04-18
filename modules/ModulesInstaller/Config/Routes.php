<?php
$routes->group('backend/modulesInstaller', ['namespace' => 'Modules\ModulesInstaller\Controllers'], function ($routes) {
    $routes->get('/', 'ModulesInstaller::index', ['as' => 'modulesInstaller']);
    $routes->post('moduleUpload', 'ModulesInstaller::moduleUpload', ['as' => 'moduleUpload']);
});
