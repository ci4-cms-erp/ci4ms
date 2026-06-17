<?php

namespace Modules\DashboardWidgets\Config;

class DashboardWidgetsConfig
{
    public $csrfExcept = [];

    public $filters = [
        'backendGuard' => ['before' => [
            'backend/dashboard-widgets',
            'backend/dashboard-widgets/*'
        ]]
    ];

    /**
     * Allowed custom widget data provider classes.
     * Each class must implement WidgetDataProviderInterface; only the FQCNs
     * listed here are accepted as a data_source. Empty by default = disabled.
     *
     * @var class-string<\Modules\DashboardWidgets\Libraries\WidgetDataProviderInterface>[]
     */
    public array $dataProviders = [];

    public $moduleInfo = [
        'icon' => 'fas fa-th-large',
    ];

    public $menus = [
        'DashboardWidgets.dashboardWidgets' => [
            'icon'         => 'fas fa-th-large',
            'inNavigation' => true,
            'hasChild'     => false,
            'pageSort'     => 9,
            'parent_pk'    => null
        ]
    ];
}
