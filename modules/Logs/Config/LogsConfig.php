<?php
namespace Modules\Logs\Config;

class LogsConfig {
    public $csrfExcept = [
        'backend/logs','backend/logs/*'
    ];

    public $filters=[
        'backendGuard' => ['before' => [
            'backend/logs','backend/logs/*'
            ]
        ]
    ];

    public $moduleInfo = [
        'icon' => 'fas fa-file-alt',
    ];

    public $menus = [

        'Logs.logs' => [
            'icon'         => 'fas fa-file-alt',
            'inNavigation' => true,
            'hasChild'     => false,
            'pageSort'     => 12,
            'parent_pk'    => null
        ]
        ];
}