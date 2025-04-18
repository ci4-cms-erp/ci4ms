<?php
$routes->group('backend/blogs', ['namespace' => 'Modules\Blog\Controllers'], function ($routes) {
    //blog module
    $routes->get('(:num)', 'Blog::index/$1', ['as' => 'blogs']);
    $routes->match(['GET', 'POST'], 'create', 'Blog::new', ['as' => 'blogCreate']);
    $routes->match(['GET', 'POST'], 'update/(:any)', 'Blog::edit/$1', ['as' => 'blogUpdate']);
    $routes->get('delete/(:any)', 'Blog::delete/$1', ['as' => 'blogDelete']);

    //categories
    $routes->group('categories', function ($routes) {
        $routes->get('(:num)', 'Categories::index/$1', ['as' => 'categories']);
        $routes->match(['GET', 'POST'], 'new', 'Categories::new', ['as' => 'categoryCreate']);
        $routes->match(['GET', 'POST'], 'update/(:any)', 'Categories::edit/$1', ['as' => 'categoryUpdate']);
        $routes->get('delete/(:any)', 'Categories::delete/$1', ['as' => 'categoryDelete']);
    });

    //tags
    $routes->group('tags', function ($routes) {
        $routes->get('(:num)', 'Tags::index/$1', ['as' => 'tags']);
        $routes->post('create', 'Tags::create', ['as' => 'tagCreate']);
        $routes->match(['GET', 'POST'], 'update/(:any)', 'Tags::edit/$1', ['as' => 'tagUpdate']);
        $routes->get('delete/(:any)', 'Tags::delete/$1', ['as' => 'tagDelete']);
    });

    $routes->group('comments', function ($routes) {
        $routes->get('/', 'Blog::commentList', ['as' => 'comments']);
        $routes->post('commentResponse', 'Blog::commentResponse/$1', ['as' => 'commentResponse']);
        $routes->get('commentRemove/(:num)', 'Blog::commentRemove/$1', ['as' => 'commentRemove']);
        $routes->get('displayComment/(:num)', 'Blog::displayComment/$1', ['as' => 'displayComment']);
        $routes->post('confirmComment/(:num)', 'Blog::confirmComment/$1', ['as' => 'confirmComment']);
        $routes->get('badwords', 'Blog::badwordList', ['as' => 'badwords']);
        $routes->post('badwordsAdd', 'Blog::badwordsAdd', ['as' => 'badwordsAdd']);
    });
});
