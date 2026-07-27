<?php

declare(strict_types=1);

namespace Modules\Notifications\Libraries;

/**
 * Builder hedef direktiflerini normalize edilmiş satır-tanımlarına çevirir.
 *
 * Model B'de fan-out YOKTUR: her direktif tek bir küresel satıra karşılık gelir ve
 * grup üyeliği okuma-zamanında çözülür (bu sınıf `auth_groups_users`'a DOKUNMAZ).
 * `broadcast` diğer tüm direktiflere baskındır (tek bir 'broadcast' satırı üretir).
 */
final class TargetResolver
{
    /**
     * Hedef direktiflerini `[[target_type, target_value], ...]` satır-tanımlarına indirger.
     *
     * @param array<int, array{0:string, 1:?string}> $targets   Toplanmış [tip, değer] direktifleri.
     * @param bool                                    $broadcast broadcast() çağrıldı mı.
     *
     * @return array<int, array{0:string, 1:?string}> Yinelenenler ayıklanmış satır-tanımları.
     */
    public function resolve(array $targets, bool $broadcast): array
    {
        if ($broadcast) {
            return [['broadcast', null]];
        }

        $seen = [];
        $rows = [];
        foreach ($targets as [$type, $value]) {
            $key = $type . '|' . ($value ?? '');
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $rows[]     = [$type, $value];
        }

        return $rows;
    }
}
