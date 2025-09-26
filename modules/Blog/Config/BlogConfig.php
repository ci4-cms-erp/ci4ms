<?php namespace Modules\Blog\Config;

class BlogConfig extends \CodeIgniter\Config\BaseConfig
{
    public $csrfExcept = [
        'backend/blogs',
        'backend/blogs/*'
    ];

    public $filters = ['backendAfterLoginFilter' => ['before' => [
        'backend/blogs',
        'backend/blogs/*'
    ]]];
}

