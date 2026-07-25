<?php

declare(strict_types=1);

namespace Modules\Notifications\Commands;

use ci4commonmodel\CommonModel;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Eski, okunmuş bildirimleri budayan bakım komutu.
 *
 * Aday: `created_at` verilen gün eşiğinden eski VE en az bir `notification_reads`
 * satırı olan (okunmuş proxy) bildirimler. `--force` VERİLMEDİKÇE hiçbir şey silmez,
 * yalnız aday sayısını basar (dry-run). Silme, FK CASCADE ile ilgili okundu
 * kayıtlarını da temizler. Bu komut Events/cron'a BAĞLANMAZ; elle çalıştırılır.
 */
class NotificationsPurge extends BaseCommand
{
    /** --days verilmezse kullanılan varsayılan eşik (gün). */
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
     * Aday okunmuş bildirimleri raporlar (dry-run) veya --force ile siler.
     *
     * @param array<int|string, string|null> $params CLI argümanları (kullanılmaz).
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

        // Okunmuş proxy: en az bir notification_reads satırı olan, eşikten eski bildirimler.
        // TODO: "tam-okundu" (tüm ilgili kullanıcılarca okunmuş) hesabı relevans genişlemesi
        //       gerektirdiğinden ileriye bırakıldı; şimdilik "en az bir okundu" ölçütü kullanılıyor.
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

        // CommonModel::remove() IN ifadesi üretemediğinden tek sorguluk toplu silme için
        // ham query builder kullanılır (N+1'den kaçınmak için). FK CASCADE okundu kayıtlarını temizler.
        $model->db->table('notifications')->whereIn('id', $ids)->delete();

        CLI::write(CLI::color("{$count} okunmuş bildirim silindi (FK CASCADE ile okundu kayıtları temizlendi).", 'green'));
    }
}
