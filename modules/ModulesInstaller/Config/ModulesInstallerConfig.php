<?php namespace Modules\ModulesInstaller\Config;

class ModulesInstallerConfig extends \CodeIgniter\Config\BaseConfig
{
    public $csrfExcept = [
        'backend/modulesInstaller',
        'backend/modulesInstaller/*',
    ];

    public $filters = [
        'backendGuard' => ['before' => [
            'backend/modulesInstaller', 'backend/modulesInstaller/*'
        ]]
    ];
}
