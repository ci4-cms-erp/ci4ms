<?php

namespace Modules\Settings\Libraries;

/**
 * Cache-clearing registry: maps logical ids to server-fixed delete operations.
 *
 * Every `target` (exact key or glob pattern) lives here on the server and is
 * NEVER taken from client input. Real `clean()` / `cache:clear` is never
 * exposed; only targeted `delete()` / `deleteMatching()` per registered entry.
 */
class CacheRegistry
{
    /**
     * Logical cache id => resolution definition. Each entry owns one or more
     * delete operations so multi-target keys (e.g. menus: an exact key plus a
     * glob pattern) fit the same uniform shape as single-target keys.
     *
     * @var array<string, array{labelKey: string, ops: list<array{type: string, target: string}>}>
     */
    private const REGISTRY = [
        'settings' => [
            'labelKey' => 'Settings.cacheKey_settings',
            'ops'      => [
                ['type' => 'exact', 'target' => 'settings'],
            ],
        ],
        'menus' => [
            'labelKey' => 'Settings.cacheKey_menus',
            'ops'      => [
                ['type' => 'exact', 'target' => 'sidebar_menu'],
                ['type' => 'match', 'target' => 'menus_*'],
            ],
        ],
        'permissions' => [
            'labelKey' => 'Settings.cacheKey_permissions',
            'ops'      => [
                ['type' => 'match', 'target' => '*_permissions'],
            ],
        ],
        'notifications' => [
            'labelKey' => 'Settings.cacheKey_notifications',
            'ops'      => [
                ['type' => 'match', 'target' => 'notif_unread_*'],
            ],
        ],
        'templatesList' => [
            'labelKey' => 'Settings.cacheKey_templatesList',
            'ops'      => [
                ['type' => 'exact', 'target' => 'templates_list'],
            ],
        ],
        'frontendLanguages' => [
            'labelKey' => 'Settings.cacheKey_frontendLanguages',
            'ops'      => [
                ['type' => 'exact', 'target' => 'frontend_languages'],
            ],
        ],
        'defaultFrontendLanguage' => [
            'labelKey' => 'Settings.cacheKey_defaultFrontendLanguage',
            'ops'      => [
                ['type' => 'exact', 'target' => 'default_frontend_language'],
            ],
        ],
        'backendMaintenance' => [
            'labelKey' => 'Settings.cacheKey_backendMaintenance',
            'ops'      => [
                ['type' => 'exact', 'target' => 'backend_maintenance_modules'],
            ],
        ],
    ];

    /**
     * Cache keys that must never be cleared through this feature.
     * `shield_auth_dynamic_config` holds Shield's dynamic RBAC config (groups,
     * permissions and matrix) built at request bootstrap; it is deliberately kept
     * out of this general-purpose tool. Enforcement is already the allowlist
     * itself (these ids are absent from REGISTRY); this denylist is a defensive
     * guard so they can never be introduced into the clearable set by mistake.
     *
     * @var list<string>
     */
    private const PROTECTED = [
        'shield_auth_dynamic_config',
    ];

    /**
     * Clearable entries as a localized id => label map for the view select2.
     *
     * @return array<string, string> Logical id keyed to its translated label.
     */
    public static function clearable(): array
    {
        $out = [];
        foreach (self::REGISTRY as $id => $entry) {
            if (in_array($id, self::PROTECTED, true)) {
                continue;
            }
            $out[$id] = lang($entry['labelKey']);
        }

        return $out;
    }

    /**
     * Whether a client-supplied id is a known, non-protected clearable entry.
     *
     * @param string $id Logical cache id.
     *
     * @return bool True when the id is present in the registry and not protected.
     */
    public static function isAllowed(string $id): bool
    {
        return array_key_exists($id, self::REGISTRY)
            && ! in_array($id, self::PROTECTED, true);
    }

    /**
     * Resolves a logical id to its server-fixed delete operations.
     *
     * @param string $id Logical cache id.
     *
     * @return list<array{type: string, target: string}>|null Operations to run, or null when the id is unknown/protected.
     */
    public static function resolve(string $id): ?array
    {
        if (! self::isAllowed($id)) {
            return null;
        }

        return self::REGISTRY[$id]['ops'];
    }

    /**
     * All clearable (non-protected) logical ids, for the "clear all" shortcut.
     *
     * @return list<string>
     */
    public static function ids(): array
    {
        return array_values(array_filter(
            array_keys(self::REGISTRY),
            static fn(string $id): bool => ! in_array($id, self::PROTECTED, true)
        ));
    }
}
