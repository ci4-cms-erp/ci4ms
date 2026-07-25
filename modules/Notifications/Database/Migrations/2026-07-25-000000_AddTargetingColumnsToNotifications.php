<?php

namespace Modules\Notifications\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Zengin hedefleme için `notifications` tablosuna additive (DROP'suz) `exclude_users`
 * kolonunu ekler.
 *
 * FORMAT — SENTINEL-SARMALI CSV: değer daima baştan ve sondan virgüllüdür (`,5,12,`),
 * hariç tutulan kullanıcı yoksa NULL'dur. Sarmalayan virgüller sayesinde okuma
 * yolundaki `exclude_users NOT LIKE '%,{userId},%'` kontrolü `,1,` ile `,12,` arasında
 * ASLA karışmaz. JSON fonksiyonu KULLANILMAZ: MySQL/MariaDB sürüm farklarından
 * bağımsız olsun diye kasıtlı olarak düz metin + LIKE tercih edilmiştir.
 * Yazan: InAppChannel::buildRow(); okuyan TEK yer: Notifier::applyRelevance().
 *
 * up() fieldExists guard'lıdır (tekrar çalıştırılabilir). down() yalnız dosya
 * bütünlüğü içindir: kolon veri taşır, otomatik DROP veri kaybı olurdu.
 */
class AddTargetingColumnsToNotifications extends Migration
{
    public function up()
    {
        $table = 'notifications';

        if (! $this->db->fieldExists('exclude_users', $table)) {
            $this->forge->addColumn($table, [
                'exclude_users' => [
                    'type'    => 'TEXT',
                    'null'    => true,
                    'default' => null,
                    'after'   => 'target_value',
                ],
            ]);
        }
    }

    public function down()
    {
        // Geri alma ELLE yapılır — bu kolon veri taşır, otomatik DROP veri kaybıdır.
        // Manuel adım (gerekirse):
        //   ALTER TABLE `{prefix}notifications` DROP COLUMN `exclude_users`;
    }
}
