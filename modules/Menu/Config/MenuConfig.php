<?php namespace Modules\Menu\Config;

class MenuConfig extends \CodeIgniter\Config\BaseConfig
{
    public $csrfExcept = [
        'backend/Menu',
        'backend/Menu/*',
        'backend/menu/deleteMenuAjax',
        'backend/menu/queueMenuAjax',
    ];

    public $filters = [
        'backendAfterLoginFilter' => ['before' => [
            'backend/Menu', 'backend/Menu/*'
        ]]
    ];
}
