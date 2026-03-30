<?php

$routes->group('backend/themes', ['namespace' => 'Modules\Theme\Controllers'], function ($routes) {
    $routes->get('/', 'Theme::index', ['as' => 'backendThemes','role'=>'read']);
    $routes->get('download-starter', 'Theme::downloadStarter', ['as' => 'downloadStarterTheme','role'=>'read']);
    $routes->post('themesUpload', 'Theme::upload', ['as' => 'themesUpload','role'=>'create']);
    $routes->get('delete-confirm/(:segment)', 'Theme::deleteConfirm/$1', ['as' => 'deleteThemeConfirm', 'role' => 'delete']);
    $routes->post('delete-process/(:segment)', 'Theme::deleteProcess/$1', ['as' => 'deleteThemeProcess', 'role' => 'delete']);
});
