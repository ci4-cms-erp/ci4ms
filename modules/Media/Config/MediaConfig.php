<?php namespace Modules\Media\Config;

class MediaConfig extends \CodeIgniter\Config\BaseConfig
{
    public $csrfExcept = [
        'backend/media/elfinderConnection'
    ];

    public $filters = [
        'backendAfterLoginFilter' => ['before' => [
            'backend/media','backend/media/*'
        ]]
    ];
}
