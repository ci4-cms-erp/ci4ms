<?php

namespace Modules\Settings\Config;

class SettingsConfig extends \CodeIgniter\Config\BaseConfig
{

    public $csrfExcept = [
        'backend/settings/setTemplate',
        'backend/settings/*',
    ];

    public $filters = [
        'backendGuard' => ['before' => [
            'backend/settings',
            'backend/settings/*',
        ]]
    ];

    public $moduleInfo = [
        'icon' => 'fas fa-cogs',
    ];

    public $menus = [
        'Settings.settings' => [
            'icon'         => 'fas fa-cogs',
            'inNavigation' => true,
            'hasChild'     => false,
            'pageSort'     => 100,
            'parent_pk'    => null
        ]
    ];
}
