<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class MigrateContentToLangs extends Migration
{
    public function up()
    {
        $db = \Config\Database::connect();
        
        // Migrate Pages
        if ($db->tableExists('pages') && $db->tableExists('pages_langs')) {
            $pages = $db->table('pages')->get()->getResultArray();
            $pagesLangs = [];
            foreach ($pages as $page) {
                // Ensure we only migrate if the row hasn't been migrated
                $exists = $db->table('pages_langs')->where(['pages_id' => $page['id'], 'lang' => 'tr'])->countAllResults();
                if ($exists == 0 && isset($page['title'])) {
                    $pagesLangs[] = [
                        'pages_id' => $page['id'],
                        'lang'     => 'tr', // Default language
                        'title'    => $page['title'] ?? 'Page ' . $page['id'],
                        'seflink'  => $page['seflink'] ?? 'page-' . $page['id'],
                        'content'  => $page['content'] ?? '',
                        'seo'      => $page['seo'] ?? ''
                    ];
                }
            }
            if (!empty($pagesLangs)) {
                $db->table('pages_langs')->insertBatch($pagesLangs);
            }
        }
        
        // Migrate Blog
        if ($db->tableExists('blog') && $db->tableExists('blog_langs')) {
            $blogs = $db->table('blog')->get()->getResultArray();
            $blogLangs = [];
            foreach ($blogs as $blog) {
                $exists = $db->table('blog_langs')->where(['blog_id' => $blog['id'], 'lang' => 'tr'])->countAllResults();
                if ($exists == 0 && isset($blog['title'])) {
                    $blogLangs[] = [
                        'blog_id'  => $blog['id'],
                        'lang'     => 'tr',
                        'title'    => $blog['title'] ?? 'Blog ' . $blog['id'],
                        'seflink'  => $blog['seflink'] ?? 'blog-' . $blog['id'],
                        'content'  => $blog['content'] ?? '',
                        'seo'      => $blog['seo'] ?? ''
                    ];
                }
            }
            if (!empty($blogLangs)) {
                $db->table('blog_langs')->insertBatch($blogLangs);
            }
        }
        
        // Create an empty down() or reverse migration
    }

    public function down()
    {
        // One-way migration, reversing this would mean deleting tr translations,
        // which might be destructive. For safety, doing nothing here.
    }
}
