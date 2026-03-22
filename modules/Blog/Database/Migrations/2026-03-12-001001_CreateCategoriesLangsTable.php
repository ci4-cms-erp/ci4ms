<?php

namespace Modules\Blog\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateCategoriesLangsTable extends Migration
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
            'categories_id' => [
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
            'seo' => [
                'type' => 'LONGTEXT',
                'null' => true,
            ],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('categories_langs', true);

        // Add foreign key constraint
        try {
            $this->db->query('ALTER TABLE `categories_langs` ADD CONSTRAINT `fk_categories_langs_categories_id` FOREIGN KEY (`categories_id`) REFERENCES `categories`(`id`) ON DELETE CASCADE ON UPDATE CASCADE');
        } catch (\Exception $e) {}

        // Migrate existing data assuming 'tr' as default language
        if ($this->db->tableExists('categories') && $this->db->fieldExists('title', 'categories')) {
            $categories = $this->db->table('categories')->get()->getResult();
            $insertData = [];
            foreach ($categories as $category) {
                $insertData[] = [
                    'categories_id' => $category->id,
                    'lang'          => 'tr',
                    'title'         => $category->title,
                    'seflink'       => $category->seflink,
                    'seo'           => $category->seo
                ];
            }
            if (!empty($insertData)) {
                $this->db->table('categories_langs')->insertBatch($insertData);
            }

            // Drop translatable columns from main table
            $this->forge->dropColumn('categories', ['title', 'seflink', 'seo']);
        }
    }

    public function down()
    {
        // Re-add dropped columns
        if (!$this->db->fieldExists('title', 'categories')) {
            $this->forge->addColumn('categories', [
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
                'seo' => [
                    'type' => 'LONGTEXT',
                    'null' => false,
                ],
            ]);
        }

        // Migrate data back for default 'tr' lang
        if ($this->db->tableExists('categories_langs')) {
            $langs = $this->db->table('categories_langs')->where('lang', 'tr')->get()->getResult();
            foreach ($langs as $langRow) {
                $this->db->table('categories')->where('id', $langRow->categories_id)->update([
                    'title'   => $langRow->title,
                    'seflink' => $langRow->seflink,
                    'seo'     => $langRow->seo
                ]);
            }
        }

        // Drop foreign key and table
        try {
            $this->db->query("ALTER TABLE `categories_langs` DROP FOREIGN KEY `fk_categories_langs_categories_id`");
        } catch (\Exception $e) {}

        $this->forge->dropTable('categories_langs', true);
    }
}
