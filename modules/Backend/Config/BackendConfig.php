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

    //--------------------------------------------------------------------
    // Version for the views
    //--------------------------------------------------------------------
    public $vers = 'v0.3.2';
}
