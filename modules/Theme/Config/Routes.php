<?php

$routes->group('backend/themes', ['namespace' => 'Modules\Theme\Controllers'], function ($routes) {
    $routes->get('/', 'Theme::index', ['as' => 'backendThemes','role'=>'read']);
    $routes->post('themesUpload', 'Theme::upload', ['as' => 'themesUpload','role'=>'create']);
});
