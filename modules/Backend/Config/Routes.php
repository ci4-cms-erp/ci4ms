<?php

/**
 * Define Users Routes
 */

$routes->group('backend', ['namespace' => 'Modules\Backend\Controllers'], function ($routes) {
    $routes->get('/', 'Backend::index', ['as' => 'backend', 'role' => 'read']);

    // Other Pages
    $routes->post('tagify', 'AJAX::limitTags_ajax', ['as' => 'tagify', 'role' => 'delete']);
    $routes->post('checkSeflink', 'AJAX::autoLookSeflinks', ['as' => 'checkSeflink', 'role' => 'delete']);
    $routes->post('isActive', 'AJAX::isActive', ['as' => 'isActive', 'role' => 'delete']);
    $routes->post('maintenance', 'AJAX::maintenance', ['as' => 'maintenance', 'role' => 'update']);
});
