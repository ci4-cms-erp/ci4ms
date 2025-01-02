<?php namespace Modules\Backend\Config;

use CodeIgniter\Config\BaseConfig;

class BackendConfig extends BaseConfig
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
        'backend/blogs/comments/commentResponse',
        'backend/menu/createMenu',
        'backend/menu/menuList',
        'backend/menu/deleteMenuAjax',
        'backend/menu/queueMenuAjax',
        'backend/menu/addMultipleMenu',
        'backend/summary/summary_render',
        'backend/settings/setTemplate',
        'backend/settings/elfinderConvertWebp',
        'backend/media/elfinderConnection',
        'backend/methods',
        'backend/methods/list',
        'backend/methods/read',
        'backend/methods/save',
        'backend/methods/renameFile',
        'backend/methods/createFile',
        'backend/methods/createFolder',
        'backend/methods/moveFileOrFolder',
        'backend/methods/deleteFileOrFolder',
    ];

    public $filters=['backendAuthFilter' => ['before' => ['backend/login', 'backend/activate-account', 'backend/forgot', 'backend/reset-password']],
        'backendAfterLoginFilter' => ['before' => [
            'backend','backend/officeWorker/*','backend/pages/*','backend/settings','backend/settings/*',
            'backend/menu/*','backend/blogs/*','backend/tagify','backend/checkSeflink','backend/isActive',
            'backend/maintenance','backend/menu','backend/media','backend/locked','backend/profile','backend/methods','backend/methods/*'
        ]]];
}
