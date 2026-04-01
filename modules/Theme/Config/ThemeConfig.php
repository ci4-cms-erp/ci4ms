<?php

namespace Modules\Theme\Config;

class ThemeConfig extends \CodeIgniter\Config\BaseConfig
{
    public $csrfExcept = [];

    public $filters = ['backendGuard' => ['before' => [
        'backend/themes',
        'backend/themes/*'
    ]]];

    public $moduleInfo = [
        'icon' => 'fas fa-palette',
    ];

    public $menus = [
        'Theme.backendThemes' => [
            'icon'         => 'fas fa-palette',
            'inNavigation' => false,
            'hasChild'     => false,
            'pageSort'     => null,
            'parent_pk'    => null
        ]
    ];
}
