<?php
$routes->group('backend/fileeditor', ['namespace' => 'Modules\Fileeditor\Controllers'], function ($routes) {
    $routes->get('/', 'Fileeditor::index', ['as' => 'fileEditor', 'role' => 'create,read,update,delete']);
    $routes->get('list', 'Fileeditor::listFiles', ['as' => 'listfiles', 'role' => 'read']);
    $routes->get('read', 'Fileeditor::readFile', ['as' => 'readFile', 'role' => 'read']);
    $routes->post('save', 'Fileeditor::saveFile', ['as' => 'saveFile', 'role' => 'read,update']);
    $routes->post('renameFile', 'Fileeditor::renameFile', ['as' => 'renameFile', 'role' => 'update']);
    $routes->post('createFile', 'Fileeditor::createFile', ['as' => 'createFile', 'role' => 'create']);
    $routes->post('createFolder', 'Fileeditor::createFolder', ['as' => 'createFolder', 'role' => 'create']);
    $routes->post('deleteFileOrFolder', 'Fileeditor::deleteFileOrFolder', ['as' => 'deleteFileOrFolder', 'role' => 'delete']);
});
