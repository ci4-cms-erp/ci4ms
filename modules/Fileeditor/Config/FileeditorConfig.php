<?php namespace Modules\Fileeditor\Config;

class FileeditorConfig extends \CodeIgniter\Config\BaseConfig
{
    public $csrfExcept = [
        'backend/Fileeditor',
        'backend/Fileeditor/*',
    ];

    public $filters = [
        'backendAfterLoginFilter' => ['before' => [
            'backend/Fileeditor', 'backend/Fileeditor/*'
        ]]
    ];
}
