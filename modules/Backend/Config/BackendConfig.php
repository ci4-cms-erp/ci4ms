<?php namespace Modules\Backend\Config;

class BackendConfig extends \CodeIgniter\Config\BaseConfig
{
    //--------------------------------------------------------------------
    // Default User Group
    //--------------------------------------------------------------------
    // The name of a group a user will be added to when they register
    //
    // i.e. $defaultUserGroup = 'guests';
    //
    public $defaultUserGroup;

    //--------------------------------------------------------------------
    // Views used by Auth Controllers
    //--------------------------------------------------------------------

    public $views = [
        '403' => 'Modules\Backend\Views\errors\html\error_403'
    ];

    //--------------------------------------------------------------------
    // Layout for the views to extend
    //--------------------------------------------------------------------
    public $viewLayout = 'Modules\Backend\Views\base';

    public $csrfExcept = [
        'backend/officeWorker/blackList',
        'backend/officeWorker/removeFromBlackList',
        'backend/officeWorker/forceResetPassword',
        'backend/menu/deleteMenuAjax',
        'backend/menu/queueMenuAjax',
        'backend/tagify',
        'backend/checkSeflink',
        'backend/isActive',
        'backend/maintenance',
        'backend/summary/summary_render',
        'backend/settings/setTemplate',
        'backend/settings/elfinderConvertWebp'
    ];

    public $filters=['backendAuthFilter' => ['before' => ['backend/login', 'backend/activate-account', 'backend/forgot', 'backend/reset-password']],
        'backendAfterLoginFilter' => ['before' => [
            'backend','backend/officeWorker/*','backend/settings','backend/settings/*',
            'backend/tagify','backend/checkSeflink','backend/isActive',
            'backend/maintenance','backend/locked','backend/profile'
        ]]];
}
