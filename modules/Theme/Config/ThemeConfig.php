<?php namespace Modules\Theme\Config;

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
            'inNavigation' => true,
            'hasChild'     => false,
            'pageSort'     => 11,
            'parent_pk'    => null
        ]
        ];
}