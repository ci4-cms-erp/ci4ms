<?php

namespace Modules\Notifications\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Model B okundu-durumu tablosu: (notification_id, user_id) başına en fazla bir satır.
 *
 * Bir bildirim, ilgili bir kullanıcı tarafından okunduğunda buraya bir satır yazılır;
 * satırın yokluğu "okunmamış" anlamına gelir. UNIQUE(notification_id,user_id) çift
 * okundu kaydını engeller (markAll INSERT IGNORE ile idempotenttir). FK'ler CASCADE'dir:
 * bildirim ya da kullanıcı silinince ilgili okundu kayıtları da silinir.
 */
class CreateNotificationReadsTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('notification_reads')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
                'null'           => false,
            ],
            'notification_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            'read_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['notification_id', 'user_id'], false, true); // UNIQUE
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('notification_id', 'notifications', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('notification_reads', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        $this->forge->dropTable('notification_reads', true);
    }
}
