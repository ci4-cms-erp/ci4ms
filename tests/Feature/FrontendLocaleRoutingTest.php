<?php

namespace Tests\Feature;

use App\Controllers\BaseController;
use ci4commonmodel\CommonModel;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;

/**
 * Exposes the two protected members under test. initController() is skipped on
 * purpose: it builds the whole settings/menu cache, which this test does not need.
 */
class LocaleRoutingProbe extends BaseController
{
    public function __construct(string $mode = 'multi')
    {
        $this->commonModel = new CommonModel();
        $this->defData     = ['settings' => (object) ['siteLanguageMode' => $mode]];
    }

    public function linkMaps(): array
    {
        return $this->getModuleLinkMaps();
    }

    public function resolve(string $type, string $seflink, string $locale): ?string
    {
        return $this->resolveLocalizedUrl($type, $seflink, $locale);
    }
}

/**
 * Locks the frontend multi-language URL contract:
 *   - a seflink belonging to another language resolves to this language's seflink
 *   - content with no translation in the requested language stays a 404
 *   - hreflang/switcher URLs use the route each module is actually registered under
 *   - locale_url() prefixes only in multi-language mode
 */
class FrontendLocaleRoutingTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;

    // Same reasoning as tests/Feature/SecurityXSSCSRFTest.php: $refresh = true
    // drops every namespace's tables in the shared ci4ms_test schema.
    protected $refresh   = false;
    protected $namespace = null;

    private CommonModel $model;
    private int $translatedId;
    private int $untranslatedId;

    protected function setUp(): void
    {
        parent::setUp();
        \Config\Services::migrations()->setNamespace(null)->latest();

        $this->model = new CommonModel();

        $this->translatedId   = $this->makeBlog();
        $this->untranslatedId = $this->makeBlog();

        $this->makeTranslation($this->translatedId, 'tr', 'locale-test-tr');
        $this->makeTranslation($this->translatedId, 'en', 'locale-test-en');
        $this->makeTranslation($this->untranslatedId, 'en', 'locale-test-en-only');
    }

    protected function tearDown(): void
    {
        foreach ([$this->translatedId, $this->untranslatedId] as $id) {
            $this->model->remove('blog_langs', ['blog_id' => $id]);
            $this->model->remove('blog', ['id' => $id]);
        }
        parent::tearDown();
    }

    private function makeBlog(): int
    {
        return $this->model->create('blog', [
            'locale'     => 'tr',
            'isActive'   => 1,
            'created_at' => date('Y-m-d H:i:s'),
            'inMenu'     => 0,
            'author'     => 1,
            'inXML'      => 1,
        ]);
    }

    private function makeTranslation(int $blogId, string $lang, string $seflink): void
    {
        $this->model->create('blog_langs', [
            'blog_id' => $blogId,
            'lang'    => $lang,
            'title'   => 'Locale routing fixture ' . $lang,
            'seflink' => $seflink,
            'content' => 'fixture',
            'seo'     => '{}',
        ]);
    }

    public function testForeignSeflinkResolvesToTheRequestedLocaleSeflink(): void
    {
        $probe = new LocaleRoutingProbe();

        $this->assertSame(
            site_url('tr/blog/locale-test-tr'),
            $probe->resolve('blog', 'locale-test-en', 'tr')
        );
        $this->assertSame(
            site_url('en/blog/locale-test-en'),
            $probe->resolve('blog', 'locale-test-tr', 'en')
        );
    }

    public function testContentWithoutATranslationIsNotResolved(): void
    {
        $probe = new LocaleRoutingProbe();

        $this->assertNull($probe->resolve('blog', 'locale-test-en-only', 'tr'));
    }

    public function testUnknownSeflinkIsNotResolved(): void
    {
        $probe = new LocaleRoutingProbe();

        $this->assertNull($probe->resolve('blog', 'no-such-seflink-anywhere', 'tr'));
    }

    public function testResolvingASeflinkToItsOwnLocaleReturnsNullSoNoRedirectLoops(): void
    {
        $probe = new LocaleRoutingProbe();

        $this->assertNull($probe->resolve('blog', 'locale-test-tr', 'tr'));
    }

    public function testSingleLanguageModeResolvesWithoutALocalePrefix(): void
    {
        $probe = new LocaleRoutingProbe('single');

        $this->assertSame(
            site_url('blog/locale-test-tr'),
            $probe->resolve('blog', 'locale-test-en', 'tr')
        );
    }

    public function testCategoryLinkMapMatchesTheRegisteredRoute(): void
    {
        $maps = (new LocaleRoutingProbe())->linkMaps();

        // Routes.php registers category as {locale}/category/(:any), so a
        // 'blog/category/' prefix would build hreflang URLs that 404.
        $this->assertSame('category/', $maps['category']['routePrefix']);
        $this->assertSame('blog/', $maps['blog']['routePrefix']);
        $this->assertSame('', $maps['pages']['routePrefix']);
    }

    public function testLocaleUrlPrefixesTheActiveLocaleInMultiMode(): void
    {
        cache()->save('settings', ['siteLanguageMode' => 'multi'], 60);
        $locale = \Config\Services::request()->getLocale();

        $this->assertSame(site_url($locale . '/blog/x'), locale_url('blog/x'));

        cache()->delete('settings');
    }

    public function testLocaleUrlLeavesThePathAloneInSingleMode(): void
    {
        cache()->save('settings', ['siteLanguageMode' => 'single'], 60);

        $this->assertSame(site_url('blog/x'), locale_url('blog/x'));

        cache()->delete('settings');
    }

    /**
     * The testing environment runs in single-language mode (app/Config/Routes.php
     * blanks $settings there), so this exercises the real single-mode route.
     *
     * Config\App::$defaultLocale falls back to 'en', so getLocale() can differ from
     * the language a site's pages_langs rows actually use. Without the
     * default-language fallback in Home::index() every page on such a site 404s.
     *
     * The content language is derived from the active locale rather than hardcoded,
     * so the mismatch this covers holds however the environment resolves getLocale().
     */
    public function testSingleModeServesContentStoredUnderTheSiteDefaultLanguage(): void
    {
        (new \Modules\Install\Services\InstallService())->createDefaultData([
            'fname'    => 'Locale',
            'sname'    => 'Fixture',
            'username' => 'localefixture',
            'email'    => 'localefixture@example.com',
            'password' => 'SuperSecret123!',
            'siteName' => 'CI4MS Test System',
        ]);
        cache()->clean();

        $contentLang = service('request')->getLocale() === 'tr' ? 'en' : 'tr';
        cache()->save('default_frontend_language', $contentLang, 300);

        $pageId = $this->model->create('pages', [
            'locale'       => $contentLang,
            'creationDate' => date('Y-m-d H:i:s'),
            'isActive'     => 1,
            'inMenu'       => 0,
            'changefreq'   => 'weekly',
            'priority'     => 0.5,
        ]);
        $this->model->create('pages_langs', [
            'pages_id' => $pageId,
            'lang'     => $contentLang,
            'title'    => 'Locale routing fixture page',
            'seflink'  => 'locale-test-single-page',
            'content'  => 'fixture',
            'seo'      => '{}',
        ]);

        try {
            $result = $this->call('get', '/locale-test-single-page');
            $this->assertSame(200, $result->response()->getStatusCode());

            $this->expectException(\CodeIgniter\Exceptions\PageNotFoundException::class);
            $this->call('get', '/locale-test-no-such-page');
        } finally {
            $this->model->remove('pages_langs', ['pages_id' => $pageId]);
            $this->model->remove('pages', ['id' => $pageId]);
            cache()->delete('default_frontend_language');
        }
    }
}
