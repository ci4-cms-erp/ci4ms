<?php
$routes->group('backend/media', ['namespace' => 'Modules\Media\Controllers'], function ($routes) {
        $routes->get('/', 'Media::index', ['as' => 'media','role'=>'create']);
        $routes->match(['GET', 'POST'], 'elfinderConnection', 'Media::elfinderConnection', ['as' => 'elfinderConnection','role'=>'read']);
});
