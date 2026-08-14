<?php

declare(strict_types=1);

namespace Modules\Notifications\Libraries;

/**
 * Converts builder target directives into normalized row definitions.
 *
 * There is NO fan-out in Model B: each directive corresponds to a single
 * global row, and group membership is resolved at read time (this class
 * DOES NOT TOUCH `auth_groups_users`). `broadcast` overrides all other
 * directives (produces a single 'broadcast' row).
 */
final class TargetResolver
{
    /**
     * Reduces target directives to `[[target_type, target_value], ...]` row definitions.
     *
     * @param array<int, array{0:string, 1:?string}> $targets   Collected [type, value] directives.
     * @param bool                                    $broadcast Whether broadcast() was called.
     *
     * @return array<int, array{0:string, 1:?string}> Row definitions with duplicates removed.
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
