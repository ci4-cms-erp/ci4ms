<?php

namespace Config\templates\default;

use CodeIgniter\Config\BaseConfig;

class ThemeConfig extends BaseConfig
{
    public $csrfExcept = ['forms/searchForm'];
    public $filters = [];
}
