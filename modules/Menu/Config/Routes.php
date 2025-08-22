<?php
$routes->group('backend/menu', ['namespace' => 'Modules\Menu\Controllers'], function ($routes) {
    $routes->get('/', 'Menu::index', ['as' => 'menu','role'=>'read']);
    $routes->post('createMenu', 'Menu::create', ['as' => 'createMenu','role'=>'create']);
    $routes->post('deleteMenuAjax', 'Menu::delete_ajax', ['as' => 'deleteMenuAjax','role'=>'delete']);
    $routes->post('queueMenuAjax', 'Menu::queue_ajax', ['as' => 'queueMenuAjax','role'=>'update']);
    $routes->post('menuList', 'Menu::listURLs', ['as' => 'menuList','role'=>'read']);
    $routes->post('addMultipleMenu', 'Menu::addMultipleMenu', ['as' => 'addMultipleMenu','role'=>'create']);
});
