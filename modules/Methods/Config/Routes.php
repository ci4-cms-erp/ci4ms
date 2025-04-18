<?php
$routes->group('backend/methods', ['namespace' => 'Modules\Methods\Controllers'], function ($routes) {
    $routes->match(['GET', 'POST'], '/', 'Methods::index', ['as' => 'list']);
    $routes->match(['GET', 'POST'], 'create', 'Methods::create', ['as' => 'methodCreate']);
    $routes->match(['GET', 'POST'], 'update/(:num)', 'Methods::update/$1', ['as' => 'methodUpdate']);
    $routes->get('delete/(:num)', 'Methods::delete/$1', ['as' => 'methodDelete']);
    $routes->get('updateRouteFile', 'Methods::updateRouteFile', ['as' => 'updateRouteFile']);
    $routes->get('list', 'Methods::listFiles', ['as' => 'listfiles']);
    $routes->get('read', 'Methods::readFile', ['as' => 'readFile']);
    $routes->post('save', 'Methods::saveFile', ['as' => 'saveFile']);
    $routes->post('renameFile', 'Methods::renameFile', ['as' => 'renameFile']);
    $routes->post('createFile', 'Methods::createFile', ['as' => 'createFile']);
    $routes->post('createFolder', 'Methods::createFolder', ['as' => 'createFolder']);
    $routes->post('moveFileOrFolder', 'Methods::moveFileOrFolder', ['as' => 'moveFileOrFolder']);
    $routes->post('deleteFileOrFolder', 'Methods::deleteFileOrFolder', ['as' => 'deleteFileOrFolder']);
});
