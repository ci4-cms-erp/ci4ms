<?php

namespace Modules\Pages\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePagesLangsTable extends Migration
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
            'pages_id' => [
                'type' => 'INT',
                'constraint' => '11',
                'unsigned' => true,
                'null' => false,
            ],
            'lang' => [
                'type' => 'VARCHAR',
                'constraint' => '5',
                'null' => false,
            ],
            'title' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => false,
            ],
            'seflink' => [
                'type' => 'VARCHAR',
                'constraint' => '255',
                'null' => false,
            ],
            'content' => [
                'type' => 'LONGTEXT',
                'null' => false,
            ],
            'seo' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('pages_langs', true);

        // Add foreign key constraint
        try {
            $this->db->query('ALTER TABLE `pages_langs` ADD CONSTRAINT `fk_pages_langs_pages_id` FOREIGN KEY (`pages_id`) REFERENCES `pages`(`id`) ON DELETE CASCADE ON UPDATE CASCADE');
        } catch (\Exception $e) {}

        // Migrate existing data assuming 'tr' as default language
        if ($this->db->tableExists('pages') && $this->db->fieldExists('title', 'pages')) {
            $pages = $this->db->table('pages')->get()->getResult();
            $insertData = [];
            foreach ($pages as $page) {
                $insertData[] = [
                    'pages_id' => $page->id,
                    'lang'     => 'tr',
                    'title'    => $page->title,
                    'seflink'  => $page->seflink,
                    'content'  => $page->content,
                    'seo'      => $page->seo
                ];
            }
            if (!empty($insertData)) {
                $this->db->table('pages_langs')->insertBatch($insertData);
            }

            // Drop translatable columns from main table
            $this->forge->dropColumn('pages', ['title', 'seflink', 'content', 'seo']);
        }
    }

    public function down()
    {
        // Re-add dropped columns
        if (!$this->db->fieldExists('title', 'pages')) {
            $this->forge->addColumn('pages', [
                'title' => [
                    'type' => 'VARCHAR',
                    'constraint' => '255',
                    'null' => false,
                    'default' => '',
                ],
                'seflink' => [
                    'type' => 'VARCHAR',
                    'constraint' => '255',
                    'null' => false,
                    'default' => '',
                ],
                'content' => [
                    'type' => 'LONGTEXT',
                    'null' => false,
                ],
                'seo' => [
                    'type' => 'LONGTEXT',
                    'null' => false,
                ],
            ]);
        }

        // Migrate data back for default 'tr' lang
        if ($this->db->tableExists('pages_langs')) {
            $langs = $this->db->table('pages_langs')->where('lang', 'tr')->get()->getResult();
            foreach ($langs as $langRow) {
                $this->db->table('pages')->where('id', $langRow->pages_id)->update([
                    'title'   => $langRow->title,
                    'seflink' => $langRow->seflink,
                    'content' => $langRow->content,
                    'seo'     => $langRow->seo
                ]);
            }
        }

        // Drop foreign key and table
        try {
            $this->db->query("ALTER TABLE `pages_langs` DROP FOREIGN KEY `fk_pages_langs_pages_id`");
        } catch (\Exception $e) {}

        $this->forge->dropTable('pages_langs', true);
    }
}
