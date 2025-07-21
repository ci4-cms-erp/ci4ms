<?php namespace Modules\Menu\Config;

class MenuConfig extends \CodeIgniter\Config\BaseConfig
{
    public $csrfExcept = [
        'backend/Menu',
        'backend/Menu/*',
    ];

    public $filters = [
        'backendAfterLoginFilter' => ['before' => [
            'backend/Menu', 'backend/Menu/*'
        ]]
    ];
}
