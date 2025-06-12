<?php
$routes->group('backend/fileeditor', ['namespace' => 'Modules\Fileeditor\Controllers'], function ($routes) {
    $routes->get('/', 'Fileeditor::index', ['as' => 'fileEditor']);
    $routes->get('list', 'Fileeditor::listFiles', ['as' => 'listfiles']);
    $routes->get('read', 'Fileeditor::readFile', ['as' => 'readFile']);
    $routes->post('save', 'Fileeditor::saveFile', ['as' => 'saveFile']);
    $routes->post('renameFile', 'Fileeditor::renameFile', ['as' => 'renameFile']);
    $routes->post('createFile', 'Fileeditor::createFile', ['as' => 'createFile']);
    $routes->post('createFolder', 'Fileeditor::createFolder', ['as' => 'createFolder']);
    $routes->post('moveFileOrFolder', 'Fileeditor::moveFileOrFolder', ['as' => 'moveFileOrFolder']);
    $routes->post('deleteFileOrFolder', 'Fileeditor::deleteFileOrFolder', ['as' => 'deleteFileOrFolder']);
});
