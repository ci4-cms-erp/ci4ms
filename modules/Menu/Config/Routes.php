<?php
$routes->group('backend/menu', ['namespace' => 'Modules\Menu\Controllers'], function ($routes) {
    $routes->get('/', 'Menu::index', ['as' => 'menu']);
    $routes->post('createMenu', 'Menu::create', ['as' => 'createMenu']);
    $routes->post('deleteMenuAjax', 'Menu::delete_ajax', ['as' => 'deleteMenuAjax']);
    $routes->post('queueMenuAjax', 'Menu::queue_ajax', ['as' => 'queueMenuAjax']);
    $routes->post('menuList', 'Menu::listURLs', ['as' => 'menuList']);
    $routes->post('addMultipleMenu', 'Menu::addMultipleMenu', ['as' => 'addMultipleMenu']);
});
