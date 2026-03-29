<?php

namespace Modules\Blog\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBlogLangsTable extends Migration
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
            'blog_id' => [
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
        $this->forge->createTable('blog_langs', true);

        // Add foreign key constraint
        try {
            $this->db->query('ALTER TABLE `blog_langs` ADD CONSTRAINT `fk_blog_langs_blog_id` FOREIGN KEY (`blog_id`) REFERENCES `blog`(`id`) ON DELETE CASCADE ON UPDATE CASCADE');
        } catch (\Exception $e) {}

        // Migrate existing data assuming 'tr' as default language
        if ($this->db->tableExists('blog') && $this->db->fieldExists('title', 'blog')) {
            $blogs = $this->db->table('blog')->get()->getResult();
            $insertData = [];
            foreach ($blogs as $blog) {
                $insertData[] = [
                    'blog_id' => $blog->id,
                    'lang'    => 'tr',
                    'title'   => $blog->title,
                    'seflink' => $blog->seflink,
                    'content' => $blog->content,
                    'seo'     => $blog->seo
                ];
            }
            if (!empty($insertData)) {
                $this->db->table('blog_langs')->insertBatch($insertData);
            }

            // Drop translatable columns from main table
            $this->forge->dropColumn('blog', ['title', 'seflink', 'content', 'seo']);
        }
    }

    public function down()
    {
        // Re-add dropped columns
        if (!$this->db->fieldExists('title', 'blog')) {
            $this->forge->addColumn('blog', [
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
        if ($this->db->tableExists('blog_langs')) {
            $langs = $this->db->table('blog_langs')->where('lang', 'tr')->get()->getResult();
            foreach ($langs as $langRow) {
                $this->db->table('blog')->where('id', $langRow->blog_id)->update([
                    'title'   => $langRow->title,
                    'seflink' => $langRow->seflink,
                    'content' => $langRow->content,
                    'seo'     => $langRow->seo
                ]);
            }
        }

        // Drop foreign key and table
        try {
            $this->db->query("ALTER TABLE `blog_langs` DROP FOREIGN KEY `fk_blog_langs_blog_id`");
        } catch (\Exception $e) {}

        $this->forge->dropTable('blog_langs', true);
    }
}
