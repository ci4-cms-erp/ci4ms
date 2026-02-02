<?php namespace Modules\Fileeditor\Config;

class FileeditorConfig extends \CodeIgniter\Config\BaseConfig
{
    public $csrfExcept = [
        'backend/fileeditor',
        'backend/fileeditor/*',
    ];

    public $filters = [
        'backendAfterLoginFilter' => ['before' => [
            'backend/fileeditor', 'backend/fileeditor/*'
        ]]
    ];
}
