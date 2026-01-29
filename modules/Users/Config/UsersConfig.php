<?php

namespace Modules\Users\Config;

class UsersConfig extends \CodeIgniter\Config\BaseConfig
{
    public $csrfExcept = [
        'backend/users',
        'backend/users/*'
    ];
    public $filters = [
        'backendAfterLoginFilter' => ['before' => [
            'backend/users','backend/users/*',
        ]]
    ];
}
