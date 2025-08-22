<?php

namespace Modules\Users\Config;

class UsersConfig extends \CodeIgniter\Config\BaseConfig
{
    public $filters = [
        'backendAfterLoginFilter' => ['before' => [
            'backend/users/*',
        ]]
    ];
}
