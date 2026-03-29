<?php

namespace Modules\DashboardWidgets\Config;

class DashboardWidgetsConfig
{
    public $csrfExcept = [
        'backend/dashboard-widgets',
        'backend/dashboard-widgets/*'
    ];

    public $filters = [
        'backendGuard' => ['before' => [
            'backend/dashboard-widgets',
            'backend/dashboard-widgets/*'
        ]]
    ];

    public $moduleInfo = [
        'icon' => 'fas fa-th-large',
    ];

    public $menus = [

        'DashboardWidgets.dashboardWidgets' => [
            'icon'         => 'fas fa-th-large',
            'inNavigation' => true,
            'hasChild'     => false,
            'pageSort'     => 14,
            'parent_pk'    => null
        ]
        ];
}