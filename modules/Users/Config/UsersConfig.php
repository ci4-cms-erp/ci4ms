<?php

namespace Modules\Users\Config;

class UsersConfig extends \CodeIgniter\Config\BaseConfig
{
    public $csrfExcept = [
        'backend/users',
        'backend/users/*'
    ];
    public $filters = [
        'backendGuard' => ['before' => [
            'backend/users',
            'backend/users/*',
        ]]
    ];

    public $moduleInfo = [
        'icon' => 'fas fa-users',
    ];

    public $menus = [
        'Users.usersCrud' => [
            'icon'         => 'fas fa-users',
            'inNavigation' => true,
            'hasChild'     => true,
            'pageSort'     => 6,
            'parent_pk'    => null
        ],
        'Users.users' => [
            'icon'         => 'fas fa-user-friends',
            'inNavigation' => true,
            'hasChild'     => false,
            'pageSort'     => 1,
            'parent_pk'    => 'Users.usersCrud'
        ],
        'Users.groupList' => [
            'icon'         => 'fas fa-sitemap',
            'inNavigation' => true,
            'hasChild'     => false,
            'pageSort'     => 2,
            'parent_pk'    => 'Users.usersCrud'
        ]
    ];
}
