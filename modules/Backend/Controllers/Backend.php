<?php

namespace Modules\Backend\Controllers;

class Backend extends BaseController
{
    public function index()
    {
        if (class_exists(\Modules\DashboardWidgets\Libraries\WidgetService::class)) {
            $widgetService = new \Modules\DashboardWidgets\Libraries\WidgetService();
            $userId = auth()->user()->id ?? 1;
            $this->defData['widgets'] = $widgetService->getUserWidgetsWithData($userId);
        }
        return view('Modules\Backend\Views\welcome_message', $this->defData);
    }
}
