<?php namespace Modules\Methods\Config;

class MethodsConfig extends \CodeIgniter\Config\BaseConfig{
    public $csrfExcept = [
        'backend/methods',
        'backend/methods/list',
        'backend/methods/read',
        'backend/methods/save',
        'backend/methods/renameFile',
        'backend/methods/createFile',
        'backend/methods/createFolder',
        'backend/methods/moveFileOrFolder',
        'backend/methods/deleteFileOrFolder',
        'backend/methods/moduleScan',
    ];

    public $filters=[
        'backendGuard' => ['before' => [
            'backend/methods','backend/methods/*'
        ]]
    ];
}
