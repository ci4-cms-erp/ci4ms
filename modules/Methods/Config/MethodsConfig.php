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
}
