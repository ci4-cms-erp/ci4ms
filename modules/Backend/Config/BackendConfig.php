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
        '403' => 'Modules\Backend\Views\errors\html\error_403',
        '404' => 'Modules\Backend\Views\errors\html\error_404'
    ];

    //--------------------------------------------------------------------
    // Layout for the views to extend
    //--------------------------------------------------------------------
    public $viewLayout = 'Modules\Backend\Views\base';

    public $csrfExcept = [
        'backend/tagify',
        'backend/checkSeflink',
        'backend/isActive',
        'backend/maintenance',
        'backend/summary/summary_render'
    ];

    public $filters=[
        'backendAfterLoginFilter' => ['before' => [
            'backend','backend/test',
            'backend/tagify','backend/checkSeflink','backend/isActive',
            'backend/maintenance','backend/locked/*','backend/profile',
        ]]];
}
