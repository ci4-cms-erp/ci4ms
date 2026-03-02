<?php namespace Modules\Media\Config;

class MediaConfig extends \CodeIgniter\Config\BaseConfig
{
    public $csrfExcept = [
        'backend/media/elfinderConnection'
    ];

    public $filters = [
        'backendGuard' => ['before' => [
            'backend/media','backend/media/*'
        ]]
    ];
}
