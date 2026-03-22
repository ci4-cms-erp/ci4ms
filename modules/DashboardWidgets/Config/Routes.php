<?php

$routes->group('backend/dashboard-widgets', ['namespace' => 'Modules\DashboardWidgets\Controllers'], function ($routes) {
    $routes->match(['GET', 'POST'], '/', 'DashboardWidgets::index', ['as' => 'dashboardWidgets', 'role' => 'read']);
    $routes->match(['GET', 'POST'], 'create', 'DashboardWidgets::create', ['as' => 'dashboardWidgetCreate', 'role' => 'create']);
    $routes->match(['GET', 'POST'], 'update/(:num)', 'DashboardWidgets::update/$1', ['as' => 'dashboardWidgetUpdate', 'role' => 'update']);
    $routes->post('delete/(:num)', 'DashboardWidgets::delete/$1', ['as' => 'dashboardWidgetDelete', 'role' => 'delete']);
    $routes->post('toggle/(:num)', 'DashboardWidgets::toggle/$1', ['as' => 'dashboardWidgetToggle', 'role' => 'update']);
    $routes->post('save-layout', 'DashboardWidgets::saveLayout', ['as' => 'dashboardWidgetSaveLayout', 'role' => 'update']);
    $routes->get('data/(:segment)', 'DashboardWidgets::widgetData/$1', ['as' => 'dashboardWidgetData', 'role' => 'read']);
    $routes->get('seed', 'DashboardWidgets::seed', ['as' => 'dashboardWidgetSeed', 'role' => 'create']);
    $routes->post('toggle-visibility/(:num)', 'DashboardWidgets::toggleVisibility/$1', ['as' => 'dashboardWidgetToggleVisibility', 'role' => 'update']);
    $routes->get('available-widgets', 'DashboardWidgets::availableWidgets', ['as' => 'dashboardWidgetAvailable', 'role' => 'read']);
});
