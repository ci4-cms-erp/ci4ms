<?php

namespace App\Models;

use CodeIgniter\Model;

class BlogModel extends Model
{
    protected $table            = 'blog';
    protected $allowedFields = ['title', 'seflink', 'seo'];

    public static function sitemapItems(): array
    {
        $blogs = model(self::class)->where(['isActive' => true, 'inXML' => true])->findAll();
        $items = [];

        foreach ($blogs as $blog) {
            $items[] = [
                'loc'        => site_url('blog/' . $blog['seflink']),
                'lastmod'    => $blog['updated_at'] ?? $blog['created_at'],
                'changefreq' => 'weekly',
                'priority'   => 1.0,
            ];
        }

        return $items;
    }
}
