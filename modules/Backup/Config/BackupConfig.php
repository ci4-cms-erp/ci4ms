<?php
namespace Modules\Backup\Config;

class BackupConfig {
    public $csrfExcept = [
        'backend/backup','backend/backup/*'
    ];

    public $filters=[
        'backendAfterLoginFilter' => ['before' => [
            'backend/backup','backend/backup/*'
            ]
        ]
    ];
}