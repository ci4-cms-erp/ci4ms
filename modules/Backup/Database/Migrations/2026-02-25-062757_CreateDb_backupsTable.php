<?php

namespace Modules\Backup\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateDb_backupsTable extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type' => 'INT',
                'constraint' => '11',
                'unsigned' => true,
                'auto_increment' => true,
                'null' => false,
            ],
            'filename' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => false,
            ],
            'file_size' => [
                'type' => 'INT',
                'constraint' => '11',
                'unsigned' => true,
                'null' => false,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => false,
                'default' => new RawSql('CURRENT_TIMESTAMP'),
            ],
            'created_by' => [
                'type' => 'INT',
                'constraint' => '11',
                'unsigned' => true,
                'null' => true,
                'default' => null,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('db_backups', true);
    }

    public function down()
    {
        $this->forge->dropTable('db_backups', true);
    }
}
