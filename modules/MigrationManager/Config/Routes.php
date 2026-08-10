<?php
$routes->group('backend/migration-manager', ['namespace' => 'Modules\MigrationManager\Controllers'], function ($routes) {
    $routes->get('/', 'MigrationManager::index', ['as' => 'migrationManager', 'role' => 'read']);
    $routes->post('run-migration', 'MigrationManager::runMigration', ['as' => 'migrationManagerRunMigration', 'role' => 'update', 'filter' => 'throttle:strict']);
    $routes->post('run-seed', 'MigrationManager::runSeed', ['as' => 'migrationManagerRunSeed', 'role' => 'update', 'filter' => 'throttle:strict']);
    $routes->match(['GET', 'POST'], 'history', 'MigrationManager::history', ['as' => 'migrationManagerHistory', 'role' => 'read']);
});
