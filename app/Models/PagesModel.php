<?php

namespace App\Models;

use CodeIgniter\Model;

class PagesModel extends Model
{
    protected $table = 'pages';
    protected $allowedFields = ['title', 'seflink', 'seo', 'content', 'isActive', 'inMenu'];

    public static function sitemapItems(): array
    {
        $pages = model(self::class)->where(['isActive' => true])->orderBy('seflink ASC')->findAll();
        $items = [];

        foreach ($pages as $page) {
            $items[] = [
                'loc'        => site_url($page['seflink']),
                'lastmod'    => $page['updated_at'] ?? $page['creationDate'],
                'changefreq' => $page['changefreq'] ?? 'weekly',
                'priority'   => $page['priority'] ?? 0.8,
            ];
        }

        return $items;
    }
}
