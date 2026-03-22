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
}
