<?php

/**
 * Bildirim Merkezi rotaları.
 *
 * `role` bayrakları (read|create|update|delete) sayesinde bu uçlar
 * Methods::moduleScan() çalıştırıldığında otomatik olarak auth_permissions_pages'e
 * izin kaydı olarak düşer. İzin dizesi ROTA ADINDAN türetilir
 * ({@see \Modules\Methods\Libraries\ModuleScanner}: pagename = '{Modül}.{rota adı}',
 * {@see \Modules\Auth\Filters\Ci4MsAuthFilter}: strtolower(pagename) . '.{eylem}'),
 * yani her rotanın 'as' adı kendi izin kaydını belirler:
 *   notifications.notifications / notiffeed / notifstream / notifprefs → .read
 *   notifications.notifprefssave / notifread / notifreadall            → .update
 *   notifications.notifcompose / notifcomposeusers                     → .read
 *   notifications.notifcomposepreview / notifcomposesend               → .create
 *
 * ÖNİZLEME 'create' İZNİNDEDİR (bilerek): preview() bir alıcı SAYISI döndürür ve o sayı
 * bir üyelik/varlık oracle'ıdır — `groups[]=superadmin` seçimine bir kimlik ekleyip
 * sayının değişip değişmediğine bakan biri, o kimliğin superadmin olup olmadığını ve
 * genel olarak var olup olmadığını öğrenir. '.read' izni üst çubuk çanı için pratikte
 * her backend kullanıcısına verildiğinden, önizleme gönderme yetkisiyle aynı kovadadır.
 *
 * Yeni uç eklendikten sonra izinlerin tanınması için Methods taraması çalıştırılmalı,
 * ardından ilgili kullanıcıların '{userId}_permissions' cache'i düşürülmelidir.
 */
$routes->group('backend/notifications', ['namespace' => 'Modules\Notifications\Controllers'], function ($routes) {
    $routes->get('/', 'NotificationController::index', ['as' => 'notifications', 'role' => 'read']);
    $routes->get('feed', 'NotificationController::feed', ['as' => 'notifFeed', 'role' => 'read']);
    $routes->get('stream', 'RealtimeController::stream', ['as' => 'notifStream', 'role' => 'read']);
    $routes->get('preferences', 'PreferenceController::index', ['as' => 'notifPrefs', 'role' => 'read']);
    $routes->post('preferences', 'PreferenceController::save', ['as' => 'notifPrefsSave', 'role' => 'update']);
    $routes->get('compose', 'ComposerController::index', ['as' => 'notifCompose', 'role' => 'read']);
    $routes->get('compose/users', 'ComposerController::users', ['as' => 'notifComposeUsers', 'role' => 'read']);
    $routes->post('compose/preview', 'ComposerController::preview', ['as' => 'notifComposePreview', 'role' => 'create']);
    $routes->post('compose', 'ComposerController::send', ['as' => 'notifComposeSend', 'role' => 'create']);
    $routes->post('read/(:num)', 'NotificationController::markRead/$1', ['as' => 'notifRead', 'role' => 'update']);
    $routes->post('readAll', 'NotificationController::markAllRead', ['as' => 'notifReadAll', 'role' => 'update']);
});
