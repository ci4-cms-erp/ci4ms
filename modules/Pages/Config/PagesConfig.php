<?php namespace Modules\Pages\Config;

class PagesConfig extends \CodeIgniter\Config\BaseConfig
{
    public $csrfExcept = [
        'backend/Pages',
        'backend/Pages/*',
    ];

    public $filters = [
        'backendAfterLoginFilter' => ['before' => [
            'backend/Pages', 'backend/Pages/*'
        ]]
    ];
}
