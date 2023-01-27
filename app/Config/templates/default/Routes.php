<?php
$routes->set404Override('App\Controllers\Errors::error404');

$routes->group('forms', ['namespace' => '\App\Controllers\templates\default'], function ($routes) {
    $routes->post('contactForm', 'Forms::contactForm_post',['as'=>'contactForm']);
    $routes->get('searchForm', 'Forms::searchForm',['as'=>'search']);
});
$routes->get('sitemaps.xml','\App\Controllers\templates\default\Seo::index',['as'=>'sitemaps.xml']);