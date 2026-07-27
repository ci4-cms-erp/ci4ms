<?php

declare(strict_types=1);

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
        $pages = model(self::class)->join('pages_langs','pages_langs.pages_id = pages.id','left')->where($where)->orderBy('seflink ASC')->findAll();
        $items = [];

        foreach ($pages as $page) {
            $seflink = ltrim((string) ($page['seflink'] ?? ''), '/');
            if ($seflink === '') {
                continue;
            }

            $items[] = [
                'loc'        => '/' . $seflink,
                'lastmod'    => $page['updated_at'] ?? $page['creationDate'],
                'changefreq' => $page['changefreq'] ?? 'weekly',
                'priority'   => $page['priority'] ?? 0.8,
            ];
        }

        return $items;
    }
}
