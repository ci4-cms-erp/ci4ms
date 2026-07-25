<?php

namespace Modules\Notifications\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Model B (küresel kayıt + per-user okundu-durumu) için `notifications` tablosunu
 * additive (DROP'suz) genişletir: severity/target_type/target_value/channel kolonları,
 * legacy user_id'nin nullable'a çekilmesi ve (target_type,target_value) indeksi.
 *
 * up() tüm adımlarda fieldExists/index guard'lıdır (mevcut veriyi ve tekrar çalıştırmayı
 * korur). down() yalnız dosya bütünlüğü içindir; rollback ELLE ÇALIŞTIRILMAZ.
 */
class AddModelBColumnsToNotifications extends Migration
{
    public function up()
    {
        $table = 'notifications';

        if (! $this->db->fieldExists('severity', $table)) {
            $this->forge->addColumn($table, [
                'severity' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 16,
                    'null'       => false,
                    'default'    => 'info',
                    'after'      => 'type',
                ],
            ]);
        }

        if (! $this->db->fieldExists('target_type', $table)) {
            $this->forge->addColumn($table, [
                'target_type' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 16,
                    'null'       => false,
                    'default'    => 'broadcast',
                    'after'      => 'severity',
                ],
            ]);
        }

        if (! $this->db->fieldExists('target_value', $table)) {
            $this->forge->addColumn($table, [
                'target_value' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 255,
                    'null'       => true,
                    'default'    => null,
                    'after'      => 'target_type',
                ],
            ]);
        }

        if (! $this->db->fieldExists('channel', $table)) {
            $this->forge->addColumn($table, [
                'channel' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 32,
                    'null'       => false,
                    'default'    => 'inapp',
                    'after'      => 'url',
                ],
            ]);
        }

        // Legacy user_id artık zorunlu değil (Model B satırları user_id=null yazar).
        $this->forge->modifyColumn($table, [
            'user_id' => [
                'name'       => 'user_id',
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
            ],
        ]);

        // (target_type, target_value) indeksi — Forge addColumn indeks eklemediğinden
        // ham ALTER ile eklenir; SHOW INDEX guard'ıyla tekrar çalıştırmaya karşı korunur.
        $prefixed = $this->db->prefixTable($table);
        $existing = $this->db->query("SHOW INDEX FROM `{$prefixed}` WHERE Key_name = 'notif_target'")->getResultArray();
        if ($existing === []) {
            $this->db->query("ALTER TABLE `{$prefixed}` ADD INDEX notif_target (target_type, target_value)");
        }
    }

    public function down()
    {
        $table = 'notifications';

        foreach (['severity', 'target_type', 'target_value', 'channel'] as $column) {
            if ($this->db->fieldExists($column, $table)) {
                $this->forge->dropColumn($table, $column);
            }
        }
    }
}
