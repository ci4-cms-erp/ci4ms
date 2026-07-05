<?php

declare(strict_types=1);

namespace Modules\Auth\Libraries;

use MaxMind\Db\Reader;

/**
 * Resolves approximate city/country/region info for an IP address using the
 * local DB-IP Lite database (MMDB format). No external service is called;
 * the IP never leaves the server.
 *
 * Geo data is cosmetic: any failure (missing/corrupt database file, invalid
 * IP, unreadable record) returns null and must never break the caller
 * (login flow).
 */
class GeoLocator
{
    /**
     * Absolute path of the MMDB database file. Downloaded/updated via
     * `php spark ci4ms:geoip-update`.
     */
    public static function dbPath(): string
    {
        return WRITEPATH . 'geoip/dbip-city-lite.mmdb';
    }

    /**
     * @param string $ip IPv4/IPv6 address
     * @return array{city: string|null, country: string|null, region: string|null}|null
     */
    public function lookup(string $ip): ?array
    {
        try {
            $dbPath = self::dbPath();
            if (! is_file($dbPath)) {
                return null;
            }

            $reader = new Reader($dbPath);

            try {
                $record = $reader->get($ip);
            } finally {
                $reader->close();
            }

            if (! is_array($record)) {
                return null;
            }

            $clean = static function (mixed $value): ?string {
                return is_string($value) ? (trim(strip_tags($value)) ?: null) : null;
            };

            // MMDB kaydında beklenen yapı: node['names']['en']; bozuk/eksik
            // düğümler null'a düşer.
            $pick = static function (mixed $node) use ($clean): ?string {
                if (! is_array($node) || ! isset($node['names']) || ! is_array($node['names'])) {
                    return null;
                }

                return $clean($node['names']['en'] ?? null);
            };

            $subdivisions = $record['subdivisions'] ?? null;

            return [
                'city'    => $pick($record['city'] ?? null),
                'country' => $pick($record['country'] ?? null),
                'region'  => $pick(is_array($subdivisions) ? ($subdivisions[0] ?? null) : null),
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
