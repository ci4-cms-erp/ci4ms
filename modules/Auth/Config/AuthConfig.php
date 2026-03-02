<?php

namespace Modules\Auth\Config;

use CodeIgniter\Config\BaseConfig;

class AuthConfig extends BaseConfig
{
    public $csrfExcept = [
        'backend/users/blackList',
        'backend/users/removeFromBlackList',
        'backend/users/forceResetPassword'
    ];

    public $filters = [
        'auth-rates' => [
            'before' => [
                'backend/login*',
                'backend/register',
                'backend/auth/*'
            ]
        ]
    ];
}
