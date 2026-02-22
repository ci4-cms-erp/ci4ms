<?php namespace Modules\Theme\Config;

class ThemeConfig extends \CodeIgniter\Config\BaseConfig
{
    public $csrfExcept = [];

    public $filters = ['backendGuard' => ['before' => [
        'backend/themes',
        'backend/themes/*'
    ]]];
}
