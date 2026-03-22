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
}
