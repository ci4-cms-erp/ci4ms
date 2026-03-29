<?php

namespace Modules\Install\Config;

class InstallConfig extends \CodeIgniter\Config\BaseConfig
{
    public $csrfExcept = [
        'install',
        'install/*'
    ];

    public $filters = [
        'installFilter' => ['before' => [
            'install',
            'install/*'
        ]]
    ];

    public $moduleInfo = [
        'icon' => 'fas fa-download',
    ];

    public $menus = [
    ];
}