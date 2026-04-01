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

    public $moduleInfo = [
        'icon' => 'fas fa-file-code',
    ];

    public $menus = [

        'Fileeditor.fileEditor' => [
            'icon'         => 'far fa-folder',
            'inNavigation' => true,
            'hasChild'     => false,
            'pageSort'     => 10,
            'parent_pk'    => null
        ]
    ];
}
