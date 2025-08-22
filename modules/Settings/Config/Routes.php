<?php

$routes->group('backend/settings', ['namespace' => 'Modules\Settings\Controllers'], function ($routes) {
    $routes->get('/', 'Settings::index', ['as' => 'settings','role'=>'read,update']);
    $routes->post('compInfos', 'Settings::compInfosPost', ['as' => 'compInfosPost','role'=>'update']);
    $routes->post('socialMedia', 'Settings::socialMediaPost', ['as' => 'socialMediaPost','role'=>'update']);
    $routes->post('mailSettings', 'Settings::mailSettingsPost', ['as' => 'mailSettingsPost','role'=>'update']);
    $routes->post('loginSettings', 'Settings::loginSettingsPost', ['as' => 'loginSettingsPost','role'=>'delete']);
    $routes->post('setTemplate', 'Settings::templateSelectPost', ['as' => 'setTemplate','role'=>'update']);
    $routes->post('saveAllowedFiles', 'Settings::saveAllowedFiles', ['as' => 'saveAllowedFiles','role'=>'update']);
    $routes->get('templateSettings', 'Settings::templateSettings', ['as' => 'templateSettings','role'=>'update']);
    $routes->post('templateSettings_post', 'Settings::templateSettings_post', ['as' => 'templateSettings_post','role'=>'update']);
    $routes->post('elfinderConvertWebp', 'AJAX::elfinderConvertWebp', ['as' => 'elfinderConvertWebp','role'=>'update']);
    $routes->post('testMail', 'Settings::testMail', ['as' => 'testMail','role'=>'read']);
});
