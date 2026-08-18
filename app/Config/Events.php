<?php

namespace Config;

use CodeIgniter\Events\Events;
use CodeIgniter\Exceptions\FrameworkException;
use CodeIgniter\HotReloader\HotReloader;

/*
 * --------------------------------------------------------------------
 * Application Events
 * --------------------------------------------------------------------
 * Events allow you to tap into the execution of the program without
 * modifying or extending core files. This file provides a central
 * location to define your events, though they can always be added
 * at run-time, also, if needed.
 *
 * You create code that can execute by subscribing to events with
 * the 'on()' method. This accepts any form of callable, including
 * Closures, that will be executed when the event is triggered.
 *
 * Example:
 *      Events::on('create', [$myInstance, 'myMethod']);
 */

Events::on('pre_system', static function (): void {
    if (ENVIRONMENT !== 'testing') {
        $value = ini_get('zlib.output_compression');

        if (filter_var($value, FILTER_VALIDATE_BOOLEAN) || (int) $value > 0) {
            throw FrameworkException::forEnabledZlibOutputCompression();
        }

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        ob_start(static fn ($buffer) => $buffer);
    }

    /*
     * --------------------------------------------------------------------
     * Debug Toolbar Listeners.
     * --------------------------------------------------------------------
     * If you delete, they will no longer be collected.
     */
    if (CI_DEBUG && ! is_cli()) {
        Events::on('DBQuery', 'CodeIgniter\Debug\Toolbar\Collectors\Database::collect');
        service('toolbar')->respond();
        // Hot Reload route - for framework use on the hot reloader.
        if (ENVIRONMENT === 'development') {
            service('routes')->get('__hot-reload', static function (): void {
                (new HotReloader())->run();
            });
        }
    }
});

Events::on('ci4ms.audit', static function (array $e) {
    $severity = $e['severity'] ?? '';

    // No reference to Notifications\Config\NotificationMessage::SEVERITIES
    // here on purpose: this listener must not fatal in an install with the
    // Notifications module removed. 'warning' and 'critical' are spelled out
    // literally, matching the string-FQCN + null-safe + try/catch idiom
    // below.
    if (!is_string($severity) || !in_array($severity, ['warning', 'critical'], true)) {
        return;
    }

    $group = config('Modules\Notifications\Config\NotificationsConfig')->auditTargetGroup ?? 'superadmin';

    try {
        service('notifier')?->notify('audit.' . ($e['action'] ?? 'event'))
            ->severity($severity)
            ->title($e['message'] ?? '')
            ->url($e['url'] ?? null)
            ->toGroup($group)
            ->dispatch();
    } catch (\Throwable $ex) {
        log_message('error', 'ci4ms.audit notifier dispatch failed: ' . $ex->getMessage());
    }
});
