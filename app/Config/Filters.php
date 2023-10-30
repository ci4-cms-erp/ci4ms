<?php

namespace Config;

use App\Filters\Ci4ms;
use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\SecureHeaders;
use Modules\Backend\Filters\BackendAfterLoginFilter;
use Modules\Backend\Filters\BackendAuthFilter;

class Filters extends BaseConfig
{
    /**
     * Configures aliases for Filter classes to
     * make reading things nicer and simpler.
     */
    public array $aliases = [
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'ci4ms' => Ci4ms::class,
        'backendAuthFilter' => BackendAuthFilter::class,
        'backendAfterLoginFilter' => BackendAfterLoginFilter::class,
    ];

    /**
     * List of filter aliases that are always
     * applied before and after every request.
     */
    public array $globals = [
        'before' => [
            'honeypot',
            'csrf'=>['except' => ['newComment', 'repliesComment',
                'loadMoreComments', 'commentCaptcha', 'backend/officeWorker/blackList', 'backend/officeWorker/removeFromBlackList',
                'backend/officeWorker/forceResetPassword', 'backend/menu/deleteMenuAjax', 'backend/menu/queueMenuAjax', 'backend/tagify',
                'backend/checkSeflink', 'backend/isActive', 'backend/maintenance', 'backend/blogs/comments/commentResponse',
                'backend/menu/createMenu', 'backend/menu/menuList', 'backend/menu/deleteMenuAjax', 'backend/menu/queueMenuAjax',
                'backend/menu/addMultipleMenu', 'backend/summary/summary_render', 'backend/settings/setTemplate',
                'backend/settings/elfinderConvertWebp', 'backend/media/elfinderConnection',
            ]],
            // 'invalidchars',
        ],
        'after' => [
            'toolbar',
            // 'honeypot',
            // 'secureheaders',
        ],
    ];

    /**
     * List of filter aliases that works on a
     * particular HTTP method (GET, POST, etc.).
     *
     * Example:
     * 'post' => ['foo', 'bar']
     *
     *
     *
     *
     *
     * If you use this, you should disable auto-routing because auto-routing
     * permits any HTTP method to access a controller. Accessing the controller
     * with a method you don’t expect could bypass the filter.
     */
    public array $methods = [];

    /**
     * List of filter aliases that should run on any
     * before or after URI patterns.
     *
     * Example:
     * 'isLoggedIn' => ['before' => ['account/*', 'profiles/*']]
     */
    public array $filters = [
        'backendAuthFilter' => ['before'=>['backend/login', 'backend/activate-account', 'backend/forgot', 'backend/reset-password']],
        'backendAfterLoginFilter'=>['before' => ['backend','backend/officeWorker/*', 'backend/pages/*', 'backend/settings/*', 'backend/menu/*', 'backend/blogs/*', 'backend/tagify',
'backend/checkSeflink', 'backend/isActive', 'backend/maintenance', 'backend/media', 'backend/locked', 'backend/profile']]
    ];
}
