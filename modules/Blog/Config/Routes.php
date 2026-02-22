<?php
$routes->group('backend/blogs', ['namespace' => 'Modules\Blog\Controllers'], function ($routes) {
    //blog module
    $routes->match(['GET', 'POST'],'/', 'Blog::index', ['as' => 'blogs','role'=>'create,update,delete,read']);
    $routes->match(['GET', 'POST'], 'create', 'Blog::new', ['as' => 'blogCreate','role'=>'create']);
    $routes->match(['GET', 'POST'], 'update/(:any)', 'Blog::edit/$1', ['as' => 'blogUpdate','role'=>'update']);
    $routes->post('delete', 'Blog::delete', ['as' => 'blogDelete','role'=>'delete']);

    //categories
    $routes->group('categories', function ($routes) {
        $routes->match(['GET', 'POST'],'/', 'Categories::index', ['as' => 'categories','role'=>'read']);
        $routes->match(['GET', 'POST'], 'new', 'Categories::new', ['as' => 'categoryCreate','role'=>'create']);
        $routes->match(['GET', 'POST'], 'update/(:any)', 'Categories::edit/$1', ['as' => 'categoryUpdate','role'=>'update']);
        $routes->post('delete', 'Categories::delete', ['as' => 'categoryDelete','role'=>'delete']);
    });

    //tags
    $routes->group('tags', function ($routes) {
        $routes->match(['GET', 'POST'],'/', 'Tags::index', ['as' => 'tags','role'=>'create,read,update,delete']);
        $routes->post('create', 'Tags::create', ['as' => 'tagCreate','role'=>'create']);
        $routes->match(['GET', 'POST'], 'update/(:any)', 'Tags::edit/$1', ['as' => 'tagUpdate','role'=>'update']);
        $routes->post('delete', 'Tags::delete', ['as' => 'tagDelete','role'=>'delete']);
    });

    $routes->group('comments', function ($routes) {
        $routes->match(['GET', 'POST'], '/', 'Blog::commentList', ['as' => 'comments','role'=>'read,create,update,delete']);
        $routes->post('commentRemove', 'Blog::commentRemove', ['as' => 'commentRemove','role'=>'delete']);
        $routes->get('displayComment/(:num)', 'Blog::displayComment/$1', ['as' => 'displayComment','role'=>'update']);
        $routes->post('confirmComment/(:num)', 'Blog::confirmComment/$1', ['as' => 'confirmComment','role'=>'update']);
        $routes->get('badwords', 'Blog::badwordList', ['as' => 'badwords','role'=>'read,create,update,delete']);
        $routes->post('badwordsAdd', 'Blog::badwordsAdd', ['as' => 'badwordsAdd','role'=>'create']);
    });
});
