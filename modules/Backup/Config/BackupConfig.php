<?php
namespace Modules\Backup\Config;

class BackupConfig {
    public $csrfExcept = [
        'backend/backup','backend/backup/*'
    ];

    public $filters=[
        'backendGuard' => ['before' => [
            'backend/backup','backend/backup/*'
            ]
        ]
    ];

    public $moduleInfo = [
        'icon' => 'fas fa-hdd',
    ];

    public $menus = [

        'Backup.backups' => [
            'icon'         => 'fas fa-hdd',
            'inNavigation' => true,
            'hasChild'     => false,
            'pageSort'     => 15,
            'parent_pk'    => null
        ]
        ];
}