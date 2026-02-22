<?php

namespace Modules\Fileeditor\Config;

class FileeditorConfig extends \CodeIgniter\Config\BaseConfig
{
    public $csrfExcept = [
        'backend/fileeditor',
        'backend/fileeditor/*',
    ];

    public $filters = [
        'backendGuard' => ['before' => [
            'backend/fileeditor',
            'backend/fileeditor/*'
        ]]
    ];
}
