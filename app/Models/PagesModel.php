<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Concerns\BuildsLocalizedSitemap;
use CodeIgniter\Model;

class PagesModel extends Model
{
    use BuildsLocalizedSitemap;

    protected $table = 'pages';
    protected $allowedFields = ['title', 'seflink', 'seo', 'content', 'isActive', 'inMenu'];

    public static function sitemapItems(): array
    {
        $isMulti     = setting()->get('App.siteLanguageMode') === 'multi';
        $defaultLang = cache('default_frontend_language') ?? setting()->get('App.defaultLocale');
        $homePageId  = setting('App.homePage');

        $where = ['isActive' => true];
        if (!$isMulti) {
            $where['pages_langs.lang'] = $defaultLang;
        }

        $rows = model(self::class)
            ->select('pages.*, pages_langs.lang, pages_langs.seflink')
            ->join('pages_langs', 'pages_langs.pages_id = pages.id', 'left')
            ->where($where)
            ->orderBy('pages_langs.seflink', 'ASC')
            ->findAll();

        $locales = $isMulti ? self::frontendLocales() : [];

        // Collect every translation of a page first: in multi-language mode each
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
            if ((int) $row['id'] === (int) $homePageId) {
                // The home page is canonical at the locale root, not under its slug.
                $seflink = '';
            } elseif ($seflink === '') {
                continue;
            }

            $translations[$row['id']][$lang] = $isMulti
                ? '/' . $lang . '/' . $seflink
                : '/' . $seflink;
        }

        $items = [];
        foreach ($rows as $row) {
            $lang = (string) ($row['lang'] ?? '');
            if (!isset($translations[$row['id']][$lang])) {
                continue;
            }

            $item = [
                'loc'        => $translations[$row['id']][$lang],
                'lastmod'    => $row['updated_at'] ?? $row['creationDate'],
                'changefreq' => $row['changefreq'] ?? 'weekly',
                'priority'   => $row['priority'] ?? 0.8,
            ];

            if ($isMulti) {
                $item['alternates'] = self::alternatesFor($translations[$row['id']], $defaultLang);
            }

            $items[] = $item;
        }

        return $items;
    }
}
