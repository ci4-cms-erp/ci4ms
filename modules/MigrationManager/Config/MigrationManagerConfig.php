<?php

namespace Modules\MigrationManager\Config;

/**
 * MigrationManager module configuration (identical to the Backup/Notifications pattern).
 * - csrfExcept: EMPTY — AJAX endpoints do not get a global CSRF exemption; the
 *   `X-CSRF-TOKEN` header is used instead (`Modules\Backend\Filters\CsrfTokenRefreshFilter`,
 *   automatically appended to backendGuard's `after`, see app/Config/Filters.php:311-318).
 * - filters: puts every endpoint under backend/migration-manager behind backendGuard.
 * - moduleInfo: icon shown in the module list.
 * - menus: navigation entry shown in the left sidebar (label lang('MigrationManager.migrationManager')).
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
