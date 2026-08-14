<?php

declare(strict_types=1);

namespace Modules\Notifications\Commands;

use ci4commonmodel\CommonModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Maintenance command that prunes old, read notifications.
 *
 * Candidate: notifications older than the given `created_at` day threshold AND
 * having at least one `notification_reads` row (read proxy). Deletes nothing
 * UNLESS `--force` is given, otherwise only prints the candidate count (dry-run).
 * Deletion also clears the related read records via FK CASCADE. This command is
 * NOT wired to Events/cron; it's run manually.
 */
class NotificationsPurge extends BaseCommand
{
    /** Default threshold (days) used when --days is not given. */
    private const DEFAULT_DAYS = 90;

    protected $group       = 'Notifications';
    protected $name        = 'notifications:purge';
    protected $description = 'Purge old, read notifications (dry-run unless --force is given).';
    protected $usage       = 'notifications:purge [--days N] [--force]';
    protected $arguments   = [];
    protected $options     = [
        '--days'  => 'Age threshold in days (default: 90). Older-than candidates are considered.',
        '--force' => 'Actually delete. Without it the command only reports the candidate count.',
    ];

    /**
     * Reports candidate read notifications (dry-run) or deletes them with --force.
     *
     * @param array<int|string, string|null> $params CLI arguments (unused).
     */
    public function run(array $params): void
    {
        $days  = (int) (CLI::getOption('days') ?: self::DEFAULT_DAYS);
        if ($days < 1) {
            $days = self::DEFAULT_DAYS;
        }
        $force = (bool) CLI::getOption('force');

        $model = new CommonModel();
        if (! $model->db->tableExists('notifications') || ! $model->db->tableExists('notification_reads')) {
            CLI::error('notifications / notification_reads tabloları bulunamadı; budama yapılamadı.');

            return;
        }

        $threshold = date('Y-m-d H:i:s', strtotime("-{$days} days"));

        // Read proxy: notifications older than the threshold that have at least one
        // notification_reads row.
        // TODO: a "fully read" (read by all relevant users) calculation was deferred
        //       because it requires expanding relevance; for now the "at least one
        //       read" criterion is used.
        $ids = array_map(
            static fn ($row) => (int) $row->id,
            $model->db->table('notifications n')
                ->select('n.id')
                ->join('notification_reads r', 'r.notification_id = n.id', 'inner')
                ->where('n.created_at <', $threshold)
                ->groupBy('n.id')
                ->get()
                ->getResult()
        );

        $count = count($ids);

        if (! $force) {
            CLI::write("Silinecek aday okunmuş bildirim (dry-run): {$count} (created_at < {$threshold}).");
            CLI::write('Gerçekten silmek için --force ekleyin.');

            return;
        }

        if ($count === 0) {
            CLI::write('Silinecek aday yok.');

            return;
        }

        // Raw query builder is used for a single-query bulk delete (to avoid N+1),
        // since CommonModel::remove() can't produce an IN clause. FK CASCADE clears
        // the read records.
        $model->db->table('notifications')->whereIn('id', $ids)->delete();

        CLI::write(CLI::color("{$count} okunmuş bildirim silindi (FK CASCADE ile okundu kayıtları temizlendi).", 'green'));
    }
}
