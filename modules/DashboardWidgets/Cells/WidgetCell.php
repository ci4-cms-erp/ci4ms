<?php

namespace Modules\DashboardWidgets\Cells;

use Modules\DashboardWidgets\Libraries\WidgetService;

class WidgetCell
{
    /**
     * Renders all active dashboard widgets for a user.
     * Uses the optimised getUserWidgetsWithData() method with caching.
     *
     * @param array $params Contains user_id
     * @return string Rendered HTML
     */
    public function renderWidgets(array $params): string
    {
        $userId        = $params['user_id'] ?? 1;
        $widgetService = new WidgetService();
        $widgets       = $widgetService->getUserWidgetsWithData($userId);

        return view('Modules\DashboardWidgets\Views\Cells\widget_layout', ['widgets' => $widgets]);
    }
}
