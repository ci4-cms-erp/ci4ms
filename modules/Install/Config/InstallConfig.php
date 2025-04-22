<?php namespace Modules\Install\Config;

class InstallConfig extends \CodeIgniter\Config\BaseConfig{
    public $csrfExcept = [
        'install',
    ];

    public $filters=[
        'installFilter' => ['before' => [
            'install','install/*'
        ]]
    ];
}
