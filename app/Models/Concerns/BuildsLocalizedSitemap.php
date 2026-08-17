<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * Shared pieces of the multi-language sitemap built by the PagesModel and
 * BlogModel sitemapItems() callbacks registered in ci4seopro\Config\Seo.
 */
trait BuildsLocalizedSitemap
{
    /**
     * Codes of the languages the frontend actually routes, in menu order.
     *
     * @return list<string>
     */
    protected static function frontendLocales(): array
    {
        $cached = cache('frontend_languages');
        if (!empty($cached)) {
            return array_map(static fn($l) => is_object($l) ? $l->code : (string) $l, (array) $cached);
        }

        $rows = db_connect()->table('languages')
            ->select('code')
            ->where(['is_active' => 1, 'is_frontend' => 1])
            ->orderBy('sort_order', 'ASC')
            ->get()
            ->getResultArray();

        return array_column($rows, 'code');
    }

    /**
     * hreflang alternates for one content item. Google expects the set to be
     * reciprocal and self-referencing, plus an x-default pointing at the site's
     * default language.
     *
     * @param array<string,string> $translations lang => loc
     * @return list<array{lang:string,loc:string}>
     */
    protected static function alternatesFor(array $translations, ?string $defaultLang): array
    {
        $alternates = [];
        foreach ($translations as $lang => $loc) {
            $alternates[] = ['lang' => $lang, 'loc' => $loc];
        }
        if ($defaultLang !== null && isset($translations[$defaultLang])) {
            $alternates[] = ['lang' => 'x-default', 'loc' => $translations[$defaultLang]];
        }

        return $alternates;
    }
}
