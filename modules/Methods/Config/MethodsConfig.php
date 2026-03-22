<?php namespace Modules\Methods\Config;

class MethodsConfig extends \CodeIgniter\Config\BaseConfig{
    public $csrfExcept = [
        'backend/methods',
        'backend/methods/*'
    ];

    public $filters=[
        'backendGuard' => ['before' => [
            'backend/methods','backend/methods/*'
        ]]
    ];

    public $moduleInfo = [
        'icon' => 'fas fa-cube',
    ];

    public $menus = [

        'Methods.methodList' => [
            'icon'         => 'fas fa-memory',
            'inNavigation' => true,
            'hasChild'     => false,
            'pageSort'     => 8,
            'parent_pk'    => null
        ]
        ];
}