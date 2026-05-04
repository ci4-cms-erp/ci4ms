<?php

namespace Modules\Auth\Config;

use CodeIgniter\Config\BaseConfig;

class AuthConfig extends BaseConfig
{
    public $csrfExcept = [];

    public $filters = [
        'auth-rates' => [
            'before' => [
                'backend/login*',
                'backend/register',
                'backend/auth/*'
            ]
        ]
    ];

    public $moduleInfo = [
        'icon' => 'fas fa-lock',
    ];

    public $menus = [
    ];
}