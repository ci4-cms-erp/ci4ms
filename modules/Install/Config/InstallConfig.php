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
            'install'
        ]]
    ];
}
