<?php

namespace Modules\LanguageManager\Database\Migrations;

use CodeIgniter\Database\Migration;
use CodeIgniter\Database\RawSql;

class CreateLanguageManagerTables extends Migration
{
    public function up()
    {
        // ── Languages ──
        $this->forge->addField([
            'id' => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'code' => ['type' => 'VARCHAR', 'constraint' => 10, 'unique' => true, 'comment' => 'ISO 639-1: tr, en, de'],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            'native_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'flag' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true, 'comment' => 'emoji or icon class'],
            'direction' => ['type' => 'ENUM', 'constraint' => ['ltr', 'rtl'], 'default' => 'ltr'],
            'is_default' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'is_active' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'sort_order' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'default' => new RawSql('CURRENT_TIMESTAMP')],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('languages', true);

        // ── Translation Keys ──
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'group_name' => ['type' => 'VARCHAR', 'constraint' => 100, 'comment' => 'e.g. backend, frontend, emails'],
            'key_name' => ['type' => 'VARCHAR', 'constraint' => 255],
            'created_at' => ['type' => 'DATETIME', 'default' => new RawSql('CURRENT_TIMESTAMP')],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['group_name', 'key_name'], false, true);
        $this->forge->createTable('translation_keys', true);

        // ── Translations ──
        $this->forge->addField([
            'id' => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'key_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'language_code' => ['type' => 'VARCHAR', 'constraint' => 10],
            'value' => ['type' => 'TEXT', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'default' => new RawSql('CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP')],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['key_id', 'language_code'], false, true);
        $this->forge->addForeignKey('key_id', 'translation_keys', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('translations', true);
    }

    public function down()
    {
        $this->forge->dropTable('translations', true);
        $this->forge->dropTable('translation_keys', true);
        $this->forge->dropTable('languages', true);
    }
}
