<?php

namespace Modules\DevGate\Config;

/**
 * This file is discovered automatically via CI4's Registrar system.
 * No manual changes to app/Config/Filters.php are required.
 *
 * CI4 scans each module's Config\Registrar class and
 * merges it into the core Config classes.
 */
class Registrar
{
    /**
     * Entries to merge into the Filters configuration
     */
    public static function Filters(): array
    {
        return [
            'aliases' => [
                'devgate' => \Modules\DevGate\Filters\DevGateFilter::class,
            ],
            // Apply globally to all requests before they are handled:
            'globals' => [
                'before' => ['devgate'],
            ],
        ];
    }
}
