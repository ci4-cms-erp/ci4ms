<?php

namespace App\Models;

use CodeIgniter\Model;

class PagesModel extends Model
{
    protected $table = 'pages';
    protected $allowedFields = ['title', 'seflink', 'seo', 'content', 'isActive', 'inMenu'];

    public static function sitemapItems(): array
    {
        $where=['isActive' => true];
        if(setting()->get('App.siteLanguageMode')==='single'){
            $where['pages_langs.lang']=setting()->get('App.defaultLocale');
        }
        $pages = model(self::class)->join('pages_langs','pages_langs.id = pages.id','left')->where($where)->orderBy('seflink ASC')->findAll();
        $items = [];

        foreach ($pages as $page) {
            $items[] = [
                'loc'        => '/' . ltrim($page['seflink'], '/'),
                'lastmod'    => $page['updated_at'] ?? $page['creationDate'],
                'changefreq' => $page['changefreq'] ?? 'weekly',
                'priority'   => $page['priority'] ?? 0.8,
            ];
        }

        return $items;
    }
}
