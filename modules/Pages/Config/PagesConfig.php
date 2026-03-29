<?php

namespace Modules\Pages\Config;

class PagesConfig extends \CodeIgniter\Config\BaseConfig
{
    public $csrfExcept = [
        'backend/Pages',
        'backend/Pages/*',
    ];

    public $filters = [
        'backendGuard' => ['before' => [
            'backend/Pages',
            'backend/Pages/*'
        ]]
    ];

    public $moduleInfo = [
        'icon' => 'far fa-copy',
    ];

    public $menus = [

        'Pages.pages' => [
            'icon'         => 'far fa-copy',
            'inNavigation' => true,
            'hasChild'     => false,
            'pageSort'     => 2,
            'parent_pk'    => null
        ]
        ];
}