<?php

namespace Modules\LanguageManager\Config;

class LanguageManagerConfig
{
    public $csrfExcept = [
        'backend/language-manager',
        'backend/language-manager/*'
    ];

    public $filters = [
        'backendGuard' => ['before' => [
            'backend/language-manager',
            'backend/language-manager/*'
        ]],
    ];

    public $moduleInfo = [
        'icon' => 'fas fa-language',
    ];

    public $menus = [

        'LanguageManager.languageManagerList' => [
            'icon'         => 'fas fa-language',
            'inNavigation' => true,
            'hasChild'     => false,
            'pageSort'     => 13,
            'parent_pk'    => null
        ]
        ];
}