<?php

declare(strict_types=1);

namespace Modules\Auth\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use MaxMind\Db\Reader;
use Modules\Auth\Libraries\GeoLocator;

/**
 * Downloads/updates the free DB-IP City Lite database (MMDB) used for
 * local session geo lookup (Auth.geoLookupEnabled setting).
 *
 * DB-IP publishes a new file every month; run this monthly via cron:
 *   0 4 3 * * php /path/to/spark ci4ms:geoip-update
 *
 * The download is streamed to disk, gunzipped, validated with a test
 * lookup and only then atomically swapped in — a failed update never
 * corrupts the active database file.
 *
 * Usage: php spark ci4ms:geoip-update
 */
class GeoIpUpdate extends BaseCommand
{
    protected $group       = 'Ci4MS';
    protected $name        = 'ci4ms:geoip-update';
    protected $description = 'Download or update the local DB-IP City Lite database for session geo lookup.';
    protected $usage       = 'ci4ms:geoip-update';

    public function run(array $params): void
    {
        $targetPath = GeoLocator::dbPath();
        $targetDir  = dirname($targetPath);

        if (! is_dir($targetDir) && ! mkdir($targetDir, 0755, true)) {
            CLI::error('Could not create directory: ' . $targetDir);
            return;
        }

        $gzPath  = $targetPath . '.download.gz';
        $tmpPath = $targetPath . '.download';

        // Prevent overlapping runs (cron + manual) from corrupting the shared
        // temp files. flock is advisory but enough for this single-writer job.
        $lock = fopen($targetPath . '.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if ($lock !== false) {
                fclose($lock);
            }
            CLI::error('Another GeoIP update is already running. Aborting.');
            return;
        }

        try {
            $url = $this->downloadLatest($gzPath);
            if ($url === null) {
                CLI::error('Download failed. Check connectivity to download.db-ip.com and retry.');
                return;
            }
            CLI::write('Downloaded: ' . $url, 'green');

            $this->gunzip($gzPath, $tmpPath);

            // Sanity check before swapping in: file must open and answer a lookup.
            $reader = new Reader($tmpPath);
            $reader->get('8.8.8.8');
            $reader->close();

            if (! rename($tmpPath, $targetPath)) {
                CLI::error('Could not move database into place: ' . $targetPath);
                return;
            }

            CLI::write('GeoIP database updated: ' . $targetPath, 'green');
            CLI::write('Size: ' . number_format((float) filesize($targetPath) / 1048576, 1) . ' MB');
            CLI::write('IP Geolocation by DB-IP (https://db-ip.com) — CC BY 4.0. Attribution required.', 'yellow');
        } catch (\Throwable $e) {
            CLI::error('GeoIP update error: ' . $e->getMessage());
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            foreach ([$gzPath, $tmpPath] as $leftover) {
                if (is_file($leftover)) {
                    @unlink($leftover);
                }
            }
        }
    }

    /**
     * Tries the current month first, then up to two previous months
     * (the current month's file may not be published yet on day 1-2).
     *
     * @return string|null URL that succeeded, or null if all failed
     */
    private function downloadLatest(string $gzPath): ?string
    {
        $context = stream_context_create([
            'http' => ['timeout' => 300, 'follow_location' => 1],
            'ssl'  => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);

        for ($monthsBack = 0; $monthsBack <= 2; $monthsBack++) {
            // "first day of" anchors to the 1st before subtracting months, avoiding
            // PHP's day-of-month rollover (e.g. "Jan 31 -1 month" landing on Mar 3).
            $month = date('Y-m', strtotime("first day of -{$monthsBack} months"));
            $url   = "https://download.db-ip.com/free/dbip-city-lite-{$month}.mmdb.gz";

            CLI::write('Trying: ' . $url);
            if (@copy($url, $gzPath, $context) && filesize($gzPath) > 0) {
                return $url;
            }
        }

        return null;
    }

    /**
     * Streams the .gz archive into the target file in chunks, so the
     * ~100 MB decompressed database never has to fit in memory.
     */
    private function gunzip(string $gzPath, string $outPath): void
    {
        $gz  = gzopen($gzPath, 'rb');
        $out = fopen($outPath, 'wb');

        if ($gz === false || $out === false) {
            throw new \RuntimeException('Could not open archive for decompression.');
        }

        try {
            while (! gzeof($gz)) {
                $chunk = gzread($gz, 524288);
                if ($chunk === false) {
                    throw new \RuntimeException('Decompression read error.');
                }
                if ($chunk !== '' && fwrite($out, $chunk) === false) {
                    throw new \RuntimeException('Write error while decompressing (disk full?).');
                }
            }
        } finally {
            gzclose($gz);
            fclose($out);
        }
    }
}
