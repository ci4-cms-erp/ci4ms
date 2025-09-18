<?php
namespace Modules\Logs\Config;

class LogsConfig {
    public $csrfExcept = [
        'backend/logs','backend/logs/*'
    ];

    public $filters=[
        'backendAfterLoginFilter' => ['before' => [
            'backend/logs','backend/logs/*'
            ]
        ]
    ];
}