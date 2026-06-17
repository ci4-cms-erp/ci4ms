<?php

namespace Modules\DashboardWidgets\Libraries;

interface WidgetDataProviderInterface
{
    /**
     * Returns the widget data.
     * E.g. ['value' => 123, 'label' => '...'] or ['rows' => [...], 'label' => '...'].
     *
     * @return array<string, mixed>
     */
    public function getData(): array;
}
