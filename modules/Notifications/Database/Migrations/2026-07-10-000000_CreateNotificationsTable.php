<?php

namespace Modules\Notifications\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateNotificationsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
                'null'           => false,
            ],
            // Alıcı kullanıcı. Rol hedefleri gönderim anında bu satırlara açılır.
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            // Makine-okunur olay tipi: 'update.available', 'auth.failed_login', 'comment.new' ...
            'type' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => false,
            ],
            'body' => [
                'type'    => 'TEXT',
                'null'    => true,
                'default' => null,
            ],
            // Tıklanınca gidilecek hedef. Notifier tarafında yazılırken doğrulanır
            // (yalnız site-içi '/...' veya http(s)); görüntülenirken esc() ile kaçılır.
            'url' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'default'    => null,
            ],
            // NULL = okunmamış. Ayrı bir bayrak tutulmaz; tarih hem durumu hem zamanı verir.
            'read_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'read_at']); // okunmamış rozeti + kullanıcı listesi için
        $this->forge->addKey('created_at');
        $this->forge->createTable('notifications', true);
    }

    public function down()
    {
        $this->forge->dropTable('notifications', true);
    }
}
