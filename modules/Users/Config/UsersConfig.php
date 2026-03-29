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
            'backend/users','backend/users/*',
        ]]
    ];

    public $moduleInfo = [
        'icon' => 'fas fa-users',
    ];

    public $menus = [
        'Users.userList' => [
            'icon'         => 'fas fa-user-friends',
            'inNavigation' => true,
            'hasChild'     => false,
            'pageSort'     => 1,
            'parent_pk'    => 'Users.usersCrud' // Sadece gruplandırma amacıyla elle girilen/varolan parent kaydına bağla
        ],
        'Users.permGroupList' => [
            'icon'         => 'fas fa-sitemap',
            'inNavigation' => true,
            'hasChild'     => false,
            'pageSort'     => 3,
            'parent_pk'    => 'Users.usersCrud'
        ]
    ];
}
