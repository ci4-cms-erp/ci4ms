<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BuildsLocalizedSitemap;
use CodeIgniter\Model;

class BlogModel extends Model
{
    use BuildsLocalizedSitemap;

    protected $table            = 'blog';
    protected $allowedFields = ['title', 'seflink', 'seo'];

    public static function sitemapItems(): array
    {
        $isMulti     = setting()->get('App.siteLanguageMode') === 'multi';
        $defaultLang = cache('default_frontend_language') ?? setting()->get('App.defaultLocale');

        $where = ['isActive' => true, 'inXML' => true];
        if (!$isMulti) {
            $where['blog_langs.lang'] = $defaultLang;
        }

        $rows = model(self::class)
            ->select('blog.*, blog_langs.lang, blog_langs.seflink')
            ->join('blog_langs', 'blog_langs.blog_id = blog.id', 'left')
            ->where($where)
            ->findAll();

        $locales = $isMulti ? self::frontendLocales() : [];

        // Collect every translation of a post first: in multi-language mode each
        // entry has to advertise the others as hreflang alternates, which is only
        // knowable once the whole set is in hand.
        $translations = [];
        foreach ($rows as $row) {
            $lang = (string) ($row['lang'] ?? '');
            if ($lang === '') {
                continue;
            }
            // A language that is not an active frontend language has no route,
            // so listing it would advertise a URL that 404s.
            if ($isMulti && !in_array($lang, $locales, true)) {
                continue;
            }

            $seflink = ltrim((string) ($row['seflink'] ?? ''), '/');
            if ($seflink === '') {
                continue;
            }

            $translations[$row['id']][$lang] = $isMulti
                ? '/' . $lang . '/blog/' . $seflink
                : '/blog/' . $seflink;
        }

        $items = [];
        foreach ($rows as $row) {
            $lang = (string) ($row['lang'] ?? '');
            if (!isset($translations[$row['id']][$lang])) {
                continue;
            }

            $item = [
                'loc'        => $translations[$row['id']][$lang],
                'lastmod'    => $row['updated_at'] ?? $row['created_at'],
                'changefreq' => 'weekly',
                'priority'   => 1.0,
            ];

            if ($isMulti) {
                $item['alternates'] = self::alternatesFor($translations[$row['id']], $defaultLang);
            }

            $items[] = $item;
        }

        return $items;
    }
}
