<?php

namespace Modules\Auth\Config;

use CodeIgniter\Config\BaseConfig;

class AuthConfig extends BaseConfig
{
    public $csrfExcept = ['backend/lock', 'backend/lock/*'];

    public $filters = [
        'auth-rates' => [
            'before' => [
                'backend/login*',
                'backend/register',
                'backend/auth/*'
            ]
        ],
        'backendGuard' => [
            'except' => [
                'backend/lock',
                'backend/lock/*',
            ]
        ],
    ];

    public $moduleInfo = [
        'icon' => 'fas fa-lock',
    ];

    public $menus = [
    ];
}
