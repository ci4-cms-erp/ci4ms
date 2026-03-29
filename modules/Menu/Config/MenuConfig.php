<?php namespace Modules\Menu\Config;

class MenuConfig extends \CodeIgniter\Config\BaseConfig
{
    public $csrfExcept = [
        'backend/Menu',
        'backend/Menu/*',
        'backend/menu/deleteMenuAjax',
        'backend/menu/queueMenuAjax',
    ];

    public $filters = [
        'backendGuard' => ['before' => [
            'backend/Menu', 'backend/Menu/*'
        ]]
    ];

    public $moduleInfo = [
        'icon' => 'fas fa-bars',
    ];

    public $menus = [

        'Menu.menu' => [
            'icon'         => 'fas fa-bars',
            'inNavigation' => true,
            'hasChild'     => false,
            'pageSort'     => 4,
            'parent_pk'    => null
        ]
        ];
}