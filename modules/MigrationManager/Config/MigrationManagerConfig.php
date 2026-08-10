<?php

namespace Modules\MigrationManager\Config;

/**
 * MigrationManager modül yapılandırması (Backup/Notifications kalıbıyla birebir).
 * - csrfExcept: BOŞ — AJAX uçları global CSRF muafiyeti almaz, `X-CSRF-TOKEN` header'ı
 *   kullanılır (`Modules\Backend\Filters\CsrfTokenRefreshFilter`, backendGuard'ın
 *   `after`'ına otomatik eklenir, bkz. app/Config/Filters.php:311-318).
 * - filters: backend/migration-manager altındaki tüm uçları backendGuard arkasına alır.
 * - moduleInfo: modül listesindeki ikon.
 * - menus: sol menüde görünecek gezinme kaydı (etiket lang('MigrationManager.migrationManager')).
 */
class MigrationManagerConfig
{
    public $csrfExcept = [];

    public $filters = [
        'backendGuard' => [
            'before' => [
                'backend/migration-manager',
                'backend/migration-manager/*'
            ]
        ]
    ];

    public $moduleInfo = [
        'icon' => 'fas fa-database',
    ];

    public $menus = [
        'MigrationManager.migrationManager' => [
            'icon'         => 'fas fa-database',
            'inNavigation' => true,
            'hasChild'     => false,
            'pageSort'     => 14,
            'parent_pk'    => null
        ]
    ];
}
