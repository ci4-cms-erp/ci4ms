<?php

namespace Config\templates\default;

use CodeIgniter\Config\BaseConfig;

class DefaultConfig extends BaseConfig
{
    public $csrfExcept = [
        'forms/searchForm',
        'commentCaptcha',
        'newComment',
        'repliesComment',
        'loadMoreComments'
    ];
    public $filters = [];
}
