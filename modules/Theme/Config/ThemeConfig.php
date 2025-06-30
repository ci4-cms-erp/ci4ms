<?php namespace Modules\Theme\Config;

class ThemeConfig extends \CodeIgniter\Config\BaseConfig
{
    public $csrfExcept = [];

    public $filters = ['backendAfterLoginFilter' => ['before' => [
        'backend/themes',
        'backend/themes/*'
    ]]];
}
