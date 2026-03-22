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

    public $moduleInfo = [
        'icon' => 'fas fa-images',
    ];

    public $menus = [

        'Media.media' => [
            'icon'         => 'fas fa-photo-video',
            'inNavigation' => true,
            'hasChild'     => false,
            'pageSort'     => 6,
            'parent_pk'    => null
        ]
        ];
}