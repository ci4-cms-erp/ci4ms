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
}
