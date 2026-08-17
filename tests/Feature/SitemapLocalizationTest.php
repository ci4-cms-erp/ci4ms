<?php

namespace Tests\Feature;

use App\Models\BlogModel;
use App\Models\PagesModel;
use ci4commonmodel\CommonModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Locks the sitemap URL contract.
 *
 * Before this was fixed the sitemap listed every language's seflink under a
 * locale-less URL (/about next to /hakkimda), so each entry was a redirect whose
 * destination depended on the visitor's site_locale cookie and no page was ever
 * reachable at the URL the sitemap advertised.
 */
class SitemapLocalizationTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    // Same reasoning as tests/Feature/SecurityXSSCSRFTest.php: $refresh = true
    // drops every namespace's tables in the shared ci4ms_test schema.
    protected $refresh   = false;
    protected $namespace = null;

    private CommonModel $model;
    private int $blogId;
    private int $pageId;
    private int $homeId;
    private ?string $previousMode     = null;
    private ?string $previousHomePage = null;

    protected function setUp(): void
    {
        parent::setUp();
        \Config\Services::migrations()->setNamespace(null)->latest();

        $this->model            = new CommonModel();
        $this->previousMode     = setting()->get('App.siteLanguageMode');
        $this->previousHomePage = setting()->get('App.homePage');

        cache()->save('default_frontend_language', 'tr', 300);
        cache()->save('frontend_languages', ['tr', 'en'], 300);

        $this->blogId = $this->model->create('blog', [
            'locale'     => 'tr',
            'isActive'   => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'inMenu'     => 0,
            'author'     => 1,
            'inXML'      => 1,
        ]);
        $this->makeBlogLang($this->blogId, 'tr', 'sitemap-test-tr');
        $this->makeBlogLang($this->blogId, 'en', 'sitemap-test-en');
        // A translation in a language the frontend does not route.
        $this->makeBlogLang($this->blogId, 'de', 'sitemap-test-de');

        $this->pageId = $this->makePage('sitemap-test-page-tr', 'sitemap-test-page-en');
        $this->homeId = $this->makePage('sitemap-test-home-tr', 'sitemap-test-home-en');
    }

    protected function tearDown(): void
    {
        $this->model->remove('blog_langs', ['blog_id' => $this->blogId]);
        $this->model->remove('blog', ['id' => $this->blogId]);
        foreach ([$this->pageId, $this->homeId] as $id) {
            $this->model->remove('pages_langs', ['pages_id' => $id]);
            $this->model->remove('pages', ['id' => $id]);
        }
        setting()->set('App.siteLanguageMode', $this->previousMode);
        setting()->set('App.homePage', $this->previousHomePage);
        cache()->delete('default_frontend_language');
        cache()->delete('frontend_languages');
        parent::tearDown();
    }

    private function makeBlogLang(int $blogId, string $lang, string $seflink): void
    {
        $this->model->create('blog_langs', [
            'blog_id' => $blogId,
            'lang'    => $lang,
            'title'   => 'Sitemap fixture ' . $lang,
            'seflink' => $seflink,
            'content' => 'fixture',
            'seo'     => '{}',
        ]);
    }

    private function makePage(string $trSeflink, string $enSeflink): int
    {
        $id = $this->model->create('pages', [
            'locale'       => 'tr',
            'creationDate' => date('Y-m-d H:i:s'),
            'isActive'     => 1,
            'inMenu'       => 0,
            'changefreq'   => 'weekly',
            'priority'     => 0.5,
        ]);
        foreach (['tr' => $trSeflink, 'en' => $enSeflink] as $lang => $seflink) {
            $this->model->create('pages_langs', [
                'pages_id' => $id,
                'lang'     => $lang,
                'title'    => 'Sitemap page fixture ' . $lang,
                'seflink'  => $seflink,
                'content'  => 'fixture',
                'seo'      => '{}',
            ]);
        }

        return $id;
    }

    /**
     * @return array<string,array<string,mixed>> loc => item
     */
    private function itemsByLoc(array $items): array
    {
        $byLoc = [];
        foreach ($items as $item) {
            $byLoc[$item['loc']] = $item;
        }

        return $byLoc;
    }

    private function hreflangMap(array $item): array
    {
        $map = [];
        foreach ($item['alternates'] ?? [] as $alt) {
            $map[$alt['lang']] = $alt['loc'];
        }

        return $map;
    }

    public function testMultiModeEmitsOneLocalePrefixedUrlPerTranslation(): void
    {
        setting()->set('App.siteLanguageMode', 'multi');

        $byLoc = $this->itemsByLoc(BlogModel::sitemapItems());

        $this->assertArrayHasKey('/tr/blog/sitemap-test-tr', $byLoc);
        $this->assertArrayHasKey('/en/blog/sitemap-test-en', $byLoc);
        $this->assertArrayNotHasKey('/blog/sitemap-test-tr', $byLoc);
        $this->assertArrayNotHasKey('/blog/sitemap-test-en', $byLoc);
    }

    public function testMultiModeAlternatesAreReciprocalAndSelfReferencing(): void
    {
        setting()->set('App.siteLanguageMode', 'multi');

        $byLoc = $this->itemsByLoc(BlogModel::sitemapItems());

        foreach (['/tr/blog/sitemap-test-tr', '/en/blog/sitemap-test-en'] as $loc) {
            $map = $this->hreflangMap($byLoc[$loc]);

            $this->assertSame('/tr/blog/sitemap-test-tr', $map['tr'] ?? null);
            $this->assertSame('/en/blog/sitemap-test-en', $map['en'] ?? null);
            // x-default points at the site's default frontend language.
            $this->assertSame('/tr/blog/sitemap-test-tr', $map['x-default'] ?? null);
        }
    }

    public function testMultiModeSkipsLanguagesTheFrontendDoesNotRoute(): void
    {
        setting()->set('App.siteLanguageMode', 'multi');

        $byLoc = $this->itemsByLoc(BlogModel::sitemapItems());

        // 'de' is not in frontend_languages, so it has no route and must not be
        // advertised either as a <loc> or as an hreflang alternate.
        $this->assertArrayNotHasKey('/de/blog/sitemap-test-de', $byLoc);
        $this->assertArrayNotHasKey('de', $this->hreflangMap($byLoc['/tr/blog/sitemap-test-tr']));
    }

    public function testMultiModePutsTheHomePageAtTheLocaleRoot(): void
    {
        setting()->set('App.siteLanguageMode', 'multi');
        setting()->set('App.homePage', (string) $this->homeId);

        $byLoc = $this->itemsByLoc(PagesModel::sitemapItems());

        $this->assertArrayHasKey('/tr/', $byLoc);
        $this->assertArrayHasKey('/en/', $byLoc);
        $this->assertArrayNotHasKey('/tr/sitemap-test-home-tr', $byLoc);
        $this->assertArrayNotHasKey('/en/sitemap-test-home-en', $byLoc);

        // Ordinary pages still live under their own slug.
        $this->assertArrayHasKey('/tr/sitemap-test-page-tr', $byLoc);
        $this->assertArrayHasKey('/en/sitemap-test-page-en', $byLoc);
    }

    public function testSingleModeKeepsUnprefixedUrlsForTheDefaultLanguageOnly(): void
    {
        setting()->set('App.siteLanguageMode', 'single');

        $byLoc = $this->itemsByLoc(BlogModel::sitemapItems());

        $this->assertArrayHasKey('/blog/sitemap-test-tr', $byLoc);
        $this->assertArrayNotHasKey('/blog/sitemap-test-en', $byLoc);
        $this->assertArrayNotHasKey('/tr/blog/sitemap-test-tr', $byLoc);
        // A single-language site has nothing to point hreflang at.
        $this->assertArrayNotHasKey('alternates', $byLoc['/blog/sitemap-test-tr']);
    }
}
