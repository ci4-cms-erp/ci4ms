<?php

namespace Modules\Backend\Libraries;

/**
 * Backend bakım modunun saf (DB/HTTP bağımsız) karar mantığı.
 * Filtre ve Settings controller'ı bu sınıfı kullanır; bu sayede
 * çekirdek mantık birim testlerle izole edilebilir.
 */
class BackendMaintenance
{
    /** Bakım listesinde gösterilmeyen / kilitlenemeyen altyapı modülleri. */
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
     * (auth_permissions_pages.className formatı)
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
     * Ayar deposundan / cache'ten gelen backendMaintenance değerini kanonik
     * yapıya çevirir: `{all: bool, until: ?int, modules: array<modulAdi, ?int>}`.
     *
     * - stdClass (settings cache) ve associative array girişlerini kabul eder.
     * - Geriye dönük uyumluluk: eski düz liste formatı (`["Blog"]`)
     *   `["Blog" => null]` map'ine dönüştürülür.
     * - `until` değerleri unix timestamp'tir; boş/0/negatif değerler null olur.
     *
     * @return array{all: bool, until: ?int, modules: array<string, ?int>}
     */
    public static function normalize(mixed $bmSetting): array
    {
        if ($bmSetting instanceof \stdClass) {
            // İç içe stdClass'ları da (modules map'i) diziye çevir.
            $bmSetting = json_decode(json_encode($bmSetting), true);
        }
        if (! is_array($bmSetting)) {
            $bmSetting = [];
        }

        $modules = [];
        foreach ((array) ($bmSetting['modules'] ?? []) as $key => $value) {
            if (is_int($key)) {
                // Eski format: ["Blog"] -> ["Blog" => null]
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

    /** Boş/0/negatif until değerlerini null'a, gerisini int'e çevirir. */
    private static function normalizeUntil(mixed $until): ?int
    {
        if ($until === null || $until === '') {
            return null;
        }
        $until = (int) $until;

        return $until > 0 ? $until : null;
    }

    /**
     * `modules` hem eski düz liste (`["Blog"]`) hem yeni map
     * (`["Blog" => ?until]`) formatını kabul eder.
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
            // Eski format: düz modül adı listesi.
            return in_array($module, $modules, true);
        }
        // Yeni format: map (modulAdi => ?until); varlık kontrolü yeterli.
        return array_key_exists($module, $modules);
    }

    /**
     * auth_permissions_pages.className formatındaki (`-Modules-Blog-Controllers-Blog`)
     * bir kaydın modülünün bakım haritasında olup olmadığını söyler.
     * Modül adı türetilemiyorsa (App controller'ları vb.) false döner.
     * Sidebar'daki bakım rozetinin saf karar mantığıdır.
     *
     * @param array{modules?: array<string, int|null>} $maintenance normalize() çıktısı
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
     * Engelleyen kapsamın bitiş timestamp'ini döner: modül bakımdaysa modülün
     * kendi `until` değeri (null olabilir), değilse `all=true` iken global
     * `until`, hiçbiri değilse null.
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
     * Bakım bitişine kalan saniye. `until` (unix ts) yoksa/0/negatifse null,
     * geçmişse 0, gelecekse kalan saniye döner.
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
     * auth_permissions_pages className'lerinden, bakıma alınabilecek
     * distinct + sıralı modül adlarını üretir (altyapı modülleri hariç).
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
