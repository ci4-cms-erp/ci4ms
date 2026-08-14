<?php

declare(strict_types=1);

namespace Modules\Backend\Libraries;

/**
 * Pure (DB/HTTP independent) decision logic for the Backend maintenance mode.
 * Used by the filter and the Settings controller; this lets the
 * core logic be isolated with unit tests.
 */
class BackendMaintenance
{
    /** Infrastructure modules not shown in / not lockable from the maintenance list. */
    public const EXCLUDED = ['Auth', 'Backend', 'Install'];

    /**
     * `Modules\Blog\Controllers\Tags` -> `Blog`
     */
    public static function moduleFromController(string $controllerName): ?string
    {
        $parts = explode('\\', ltrim($controllerName, '\\'));
        if (count($parts) >= 2 && $parts[0] === 'Modules') {
            return $parts[1];
        }
        return null;
    }

    /**
     * `-Modules-Blog-Controllers-Blog` -> `Blog`
     * (auth_permissions_pages.className format)
     */
    public static function moduleFromDbClassName(string $dbClassName): ?string
    {
        $parts = explode('-', ltrim($dbClassName, '-'));
        if (count($parts) >= 2 && $parts[0] === 'Modules') {
            return $parts[1];
        }
        return null;
    }

    /**
     * Converts the backendMaintenance value coming from the settings store /
     * cache into the canonical structure: `{all: bool, until: ?int, modules: array<moduleName, ?int>}`.
     *
     * - Accepts stdClass (settings cache) and associative array inputs.
     * - Backward compatibility: the old flat-list format (`["Blog"]`)
     *   is converted to the `["Blog" => null]` map.
     * - `until` values are unix timestamps; empty/0/negative values become null.
     *
     * @return array{all: bool, until: ?int, modules: array<string, ?int>}
     */
    public static function normalize(mixed $bmSetting): array
    {
        if ($bmSetting instanceof \stdClass) {
            // Also convert nested stdClass instances (the modules map) into arrays.
            $bmSetting = json_decode((string) json_encode($bmSetting), true);
        }
        if (! is_array($bmSetting)) {
            $bmSetting = [];
        }

        $modules = [];
        foreach ((array) ($bmSetting['modules'] ?? []) as $key => $value) {
            if (is_int($key)) {
                // Old format: ["Blog"] -> ["Blog" => null]
                $modules[(string) $value] = null;
                continue;
            }
            $modules[(string) $key] = self::normalizeUntil($value);
        }

        return [
            'all'     => (bool) ($bmSetting['all'] ?? false),
            'until'   => self::normalizeUntil($bmSetting['until'] ?? null),
            'modules' => $modules,
        ];
    }

    /** Converts empty/0/negative until values to null, the rest to int. */
    private static function normalizeUntil(mixed $until): ?int
    {
        if ($until === null || $until === '') {
            return null;
        }
        $until = (int) $until;

        return $until > 0 ? $until : null;
    }

    /**
     * `modules` accepts both the old flat-list (`["Blog"]`) and the new map
     * (`["Blog" => ?until]`) format.
     *
     * @param array{all?: bool, modules?: array<int|string, int|string|null>} $maintenance
     */
    public static function isBlocked(array $maintenance, string $controllerName, bool $isSuperadmin): bool
    {
        if ($isSuperadmin) {
            return false;
        }
        if (! empty($maintenance['all'])) {
            return true;
        }
        $module = self::moduleFromController($controllerName);
        if ($module === null) {
            return false;
        }
        $modules = (array) ($maintenance['modules'] ?? []);
        if (array_is_list($modules)) {
            // Old format: flat list of module names.
            return in_array($module, $modules, true);
        }
        // New format: map (moduleName => ?until); an existence check is enough.
        return array_key_exists($module, $modules);
    }

    /**
     * Tells whether the module of a record in the auth_permissions_pages.className
     * format (`-Modules-Blog-Controllers-Blog`) is in the maintenance map.
     * Returns false if the module name cannot be derived (App controllers, etc.).
     * This is the pure decision logic for the maintenance badge in the sidebar.
     *
     * @param array{modules?: array<string, int|null>} $maintenance output of normalize()
     */
    public static function moduleInMaintenance(array $maintenance, string $dbClassName): bool
    {
        $module = self::moduleFromDbClassName($dbClassName);
        if ($module === null) {
            return false;
        }

        return array_key_exists($module, (array) ($maintenance['modules'] ?? []));
    }

    /**
     * Returns the blocking scope's end timestamp: the module's own `until`
     * value (may be null) if the module is under maintenance, otherwise the
     * global `until` while `all=true`, otherwise null.
     *
     * @param array{all?: bool, until?: int|null, modules?: array<string, int|null>} $maintenance
     */
    public static function untilFor(array $maintenance, string $controllerName): ?int
    {
        $module = self::moduleFromController($controllerName);
        if ($module !== null && array_key_exists($module, $maintenance['modules'] ?? [])) {
            return self::normalizeUntil($maintenance['modules'][$module]);
        }
        if (! empty($maintenance['all'])) {
            return self::normalizeUntil($maintenance['until'] ?? null);
        }

        return null;
    }

    /**
     * Seconds remaining until maintenance ends. Returns null if `until`
     * (unix ts) is missing/0/negative, 0 if it's in the past, or the
     * remaining seconds if it's in the future.
     *
     * @param array{until?: int|string|null} $maintenance
     */
    public static function secondsUntilEnd(array $maintenance): ?int
    {
        $until = $maintenance['until'] ?? null;

        if ($until === null || $until === '') {
            return null;
        }

        $until = (int) $until;
        if ($until <= 0) {
            return null;
        }

        return max(0, $until - time());
    }

    /**
     * Produces distinct, sorted module names eligible for maintenance mode
     * from auth_permissions_pages className values (excluding infrastructure modules).
     *
     * @param string[] $dbClassNames
     * @param string[] $excluded
     * @return string[]
     */
    public static function selectableModules(array $dbClassNames, array $excluded = self::EXCLUDED): array
    {
        $modules = [];
        foreach ($dbClassNames as $className) {
            $module = self::moduleFromDbClassName($className);
            if ($module !== null && ! in_array($module, $excluded, true)) {
                $modules[$module] = true;
            }
        }
        $list = array_keys($modules);
        sort($list);
        return $list;
    }
}
