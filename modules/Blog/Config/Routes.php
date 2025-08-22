<?php
$routes->group('backend/blogs', ['namespace' => 'Modules\Blog\Controllers'], function ($routes) {
    //blog module
    $routes->get('(:num)', 'Blog::index/$1', ['as' => 'blogs','role'=>'create,update,delete,read']);
    $routes->match(['GET', 'POST'], 'create', 'Blog::new', ['as' => 'blogCreate','role'=>'create']);
    $routes->match(['GET', 'POST'], 'update/(:any)', 'Blog::edit/$1', ['as' => 'blogUpdate','role'=>'update']);
    $routes->get('delete/(:any)', 'Blog::delete/$1', ['as' => 'blogDelete','role'=>'delete']);

    //categories
    $routes->group('categories', function ($routes) {
        $routes->get('(:num)', 'Categories::index/$1', ['as' => 'categories','role'=>'read']);
        $routes->match(['GET', 'POST'], 'new', 'Categories::new', ['as' => 'categoryCreate','role'=>'create']);
        $routes->match(['GET', 'POST'], 'update/(:any)', 'Categories::edit/$1', ['as' => 'categoryUpdate','role'=>'update']);
        $routes->get('delete/(:any)', 'Categories::delete/$1', ['as' => 'categoryDelete','role'=>'delete']);
    });

    //tags
    $routes->group('tags', function ($routes) {
        $routes->get('(:num)', 'Tags::index/$1', ['as' => 'tags','role'=>'create,read,update,delete']);
        $routes->post('create', 'Tags::create', ['as' => 'tagCreate','role'=>'create']);
        $routes->match(['GET', 'POST'], 'update/(:any)', 'Tags::edit/$1', ['as' => 'tagUpdate','role'=>'update']);
        $routes->get('delete/(:any)', 'Tags::delete/$1', ['as' => 'tagDelete','role'=>'delete']);
    });

    $routes->group('comments', function ($routes) {
        $routes->get('/', 'Blog::commentList', ['as' => 'comments','role'=>'read,create,update,delete']);
        $routes->post('commentResponse', 'Blog::commentResponse/$1', ['as' => 'commentResponse','role'=>'read']);
        $routes->get('commentRemove/(:num)', 'Blog::commentRemove/$1', ['as' => 'commentRemove','role'=>'delete']);
        $routes->get('displayComment/(:num)', 'Blog::displayComment/$1', ['as' => 'displayComment','role'=>'update']);
        $routes->post('confirmComment/(:num)', 'Blog::confirmComment/$1', ['as' => 'confirmComment','role'=>'update']);
        $routes->get('badwords', 'Blog::badwordList', ['as' => 'badwords','role'=>'read,create,update,delete']);
        $routes->post('badwordsAdd', 'Blog::badwordsAdd', ['as' => 'badwordsAdd','role'=>'create']);
    });
});
