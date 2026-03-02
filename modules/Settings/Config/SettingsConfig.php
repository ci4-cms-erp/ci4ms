<?php

namespace Modules\Settings\Config;

class SettingsConfig extends \CodeIgniter\Config\BaseConfig
{

    public $csrfExcept = [
        'backend/settings/setTemplate',
        'backend/settings/elfinderConvertWebp',
        'backend/settings/testMail',
        'backend/settings/updateVersion',
    ];

    public $filters = [
        'backendGuard' => ['before' => [
            'backend/settings',
            'backend/settings/*',
        ]]
    ];
}
