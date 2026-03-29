<?php

$routes->group('backend/language-manager', ['namespace' => 'Modules\LanguageManager\Controllers'], function ($routes) {
    // Languages
    $routes->match(['GET', 'POST'], 'languages', 'Languages::index', ['as' => 'languages', 'role' => 'read']);
    $routes->match(['GET', 'POST'], 'languages/create', 'Languages::create', ['as' => 'languageCreate', 'role' => 'create']);
    $routes->match(['GET', 'POST'], 'languages/update/(:num)', 'Languages::update/$1', ['as' => 'languageUpdate', 'role' => 'update']);
    $routes->post('languages/delete/(:num)', 'Languages::delete/$1', ['as' => 'languageDelete', 'role' => 'delete']);
    $routes->post('languages/toggle/(:num)', 'Languages::toggle/$1', ['as' => 'languageToggle', 'role' => 'update']);
    $routes->post('languages/set-default/(:num)', 'Languages::setDefault/$1', ['as' => 'languageSetDefault', 'role' => 'update']);

    // Translations
    $routes->match(['GET', 'POST'], 'translations', 'Translations::index', ['as' => 'translations', 'role' => 'read']);
    $routes->post('translations/save', 'Translations::save', ['as' => 'translationSave', 'role' => 'update']);
    $routes->post('translations/add-key', 'Translations::addKey', ['as' => 'translationAddKey', 'role' => 'create']);
    $routes->post('translations/delete-key/(:num)', 'Translations::deleteKey/$1', ['as' => 'translationDeleteKey', 'role' => 'delete']);
    $routes->get('translations/export/(:segment)', 'Translations::export/$1', ['as' => 'translationExport', 'role' => 'read']);
    $routes->post('translations/import', 'Translations::import', ['as' => 'translationImport', 'role' => 'create']);
});
