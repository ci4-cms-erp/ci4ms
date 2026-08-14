<?php

/**
 * Notification Center routes.
 *
 * Thanks to the `role` flags (read|create|update|delete), these endpoints are
 * automatically dropped into auth_permissions_pages as permission records when
 * Methods::moduleScan() runs. The permission string is derived FROM THE ROUTE NAME
 * ({@see \Modules\Methods\Libraries\ModuleScanner}: pagename = '{Module}.{route name}',
 * {@see \Modules\Auth\Filters\Ci4MsAuthFilter}: strtolower(pagename) . '.{action}'),
 * meaning each route's 'as' name determines its own permission record:
 *   notifications.notifications / notiffeed / notifstream / notifprefs → .read
 *   notifications.notifprefssave / notifread / notifreadall            → .update
 *   notifications.notifcompose / notifcomposeusers                     → .read
 *   notifications.notifcomposepreview / notifcomposesend               → .create
 *
 * PREVIEW IS UNDER THE 'create' PERMISSION (deliberately): preview() returns a
 * recipient COUNT, and that count is a membership/existence oracle — someone who adds
 * an identity to a `groups[]=superadmin` selection and watches whether the count
 * changes learns whether that identity is a superadmin and, in general, whether it
 * exists at all. Since the '.read' permission is effectively granted to every backend
 * user for the top bar bell, preview is bucketed with the send permission instead.
 *
 * After a new endpoint is added, the Methods scan must be run for permissions to be
 * recognized, and then the '{userId}_permissions' cache of the relevant users must be
 * cleared.
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
