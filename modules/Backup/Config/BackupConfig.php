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
}
