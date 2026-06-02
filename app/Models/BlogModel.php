<?php

namespace App\Models;

use CodeIgniter\Model;

class BlogModel extends Model
{
    protected $table            = 'blog';
    protected $allowedFields = ['title', 'seflink', 'seo'];

    public static function sitemapItems(): array
    {
        $where=['isActive' => true,'inXML' => true];
        if(setting()->get('App.siteLanguageMode')==='single'){
            $where['blog_langs.lang']=setting()->get('App.defaultLocale');
        }
        $blogs = model(self::class)->join('blog_langs','blog_langs.id = blog.id','left')->where($where)->findAll();
        $items = [];

        foreach ($blogs as $blog) {
            $items[] = [
                'loc'        => '/blog/' . ltrim($blog['seflink'], '/'),
                'lastmod'    => $blog['updated_at'] ?? $blog['created_at'],
                'changefreq' => 'weekly',
                'priority'   => 1.0,
            ];
        }

        return $items;
    }
}
