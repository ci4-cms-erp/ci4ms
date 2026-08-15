<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Backend\Libraries\CommonBackendLibrary;

/**
 * buildSeoData() regresyon testleri.
 *
 * Daha önce crash'e neden olan yollar:
 * - Dizi-olmayan keywords JSON (object dönüşü)
 * - Geçersiz UTF-8 içeren data
 *
 * @internal
 */
final class CommonBackendLibraryTest extends CIUnitTestCase
{
    private CommonBackendLibrary $lib;

    protected function setUp(): void
    {
        parent::setUp();
        $this->lib = new CommonBackendLibrary();
    }

    // ──────────────────────────────────────────────
    // buildSeoData — Normal akış
    // ──────────────────────────────────────────────

    public function testBuildSeoDataReturnsNullForEmptyData(): void
    {
        $result = $this->lib->buildSeoData([]);
        $this->assertNull($result);
    }

    public function testBuildSeoDataIncludesDescriptionWhenProvided(): void
    {
        $result = $this->lib->buildSeoData([
            'description' => 'Test açıklama',
        ]);

        $this->assertNotNull($result);
        $decoded = json_decode($result, true);
        $this->assertSame('Test açıklama', $decoded['description']);
    }

    public function testBuildSeoDataIncludesCoverImage(): void
    {
        $result = $this->lib->buildSeoData([
            'pageimg'       => 'uploads/test.webp',
            'pageIMGWidth'  => '1200',
            'pageIMGHeight' => '630',
        ]);

        $this->assertNotNull($result);
        $decoded = json_decode($result, true);
        $this->assertSame('uploads/test.webp', $decoded['coverImage']);
        $this->assertSame('1200', $decoded['IMGWidth']);
        $this->assertSame('630', $decoded['IMGHeight']);
    }

    public function testBuildSeoDataParsesValidKeywordsArray(): void
    {
        $keywords = json_encode([
            (object) ['value' => 'php'],
            (object) ['value' => 'codeigniter'],
        ]);

        $result = $this->lib->buildSeoData(['keywords' => $keywords]);

        $this->assertNotNull($result);
        $decoded = json_decode($result, true);
        $this->assertCount(2, $decoded['keywords']);
    }

    // ──────────────────────────────────────────────
    // buildSeoData — Regresyon: keywords edge case
    // ──────────────────────────────────────────────

    /**
     * Regresyon: keywords alanı JSON object dönerse (dizi değil),
     * is_array() kontrolü sayesinde crash olmamalı.
     */
    public function testBuildSeoDataHandlesObjectKeywordsGracefully(): void
    {
        // json_decode('{"value":"test"}') → object, dizi değil
        $keywords = '{"value":"test"}';
        $result = $this->lib->buildSeoData(['keywords' => $keywords]);

        // keywords object olduğu için is_array() false → keywords eklenmemeli
        if ($result !== null) {
            $decoded = json_decode($result, true);
            $this->assertArrayNotHasKey('keywords', $decoded);
        } else {
            // Başka SEO verisi yoksa null dönmesi de kabul
            $this->assertNull($result);
        }
    }

    /**
     * Regresyon: keywords alanı geçersiz JSON ise crash olmamalı.
     */
    public function testBuildSeoDataHandlesInvalidJsonKeywords(): void
    {
        $result = $this->lib->buildSeoData(['keywords' => '{invalid json!!!']);

        // json_decode null döner, is_array(null) false → keywords eklenmemeli
        if ($result !== null) {
            $decoded = json_decode($result, true);
            $this->assertArrayNotHasKey('keywords', $decoded);
        } else {
            $this->assertNull($result);
        }
    }

    /**
     * Regresyon: keywords dizisinde boş value'lar filtrelenmeli.
     */
    public function testBuildSeoDataFiltersEmptyKeywordValues(): void
    {
        $keywords = json_encode([
            (object) ['value' => 'php'],
            (object) ['value' => ''],
            (object) ['value' => '   '],
            (object) ['value' => 'ci4'],
        ]);

        $result = $this->lib->buildSeoData(['keywords' => $keywords]);

        $this->assertNotNull($result);
        $decoded = json_decode($result, true);
        // Boş value'lar filtrelenmiş, 2 kalmalı
        $this->assertCount(2, $decoded['keywords']);
    }

    // ──────────────────────────────────────────────
    // buildSeoData — Regresyon: geçersiz UTF-8
    // ──────────────────────────────────────────────

    /**
     * Regresyon: geçersiz UTF-8 byte dizileri crash veya
     * json_encode hatası üretmemeli.
     */
    public function testBuildSeoDataHandlesInvalidUtf8Description(): void
    {
        // 0xC0 0xAF geçersiz UTF-8 overlong encoding
        $invalidUtf8 = "Test \xC0\xAF açıklama";

        $result = $this->lib->buildSeoData([
            'description' => $invalidUtf8,
        ]);

        // strip_tags + trim çalışmalı, json_encode başarısız olursa null dönmeli
        // Hiçbir durumda exception fırlatmamalı
        $this->assertTrue($result === null || is_string($result));
    }

    /**
     * Regresyon: XSS tag'li description temizlenmeli.
     */
    public function testBuildSeoDataStripsHtmlTags(): void
    {
        $result = $this->lib->buildSeoData([
            'description' => '<script>alert("xss")</script>Temiz metin',
        ]);

        $this->assertNotNull($result);
        $decoded = json_decode($result, true);
        $this->assertStringNotContainsString('<script>', $decoded['description']);
        $this->assertStringContainsString('Temiz metin', $decoded['description']);
    }

    /**
     * Regresyon: Tüm alanlar boş string ise null dönmeli.
     */
    public function testBuildSeoDataReturnsNullWhenAllFieldsEmpty(): void
    {
        $result = $this->lib->buildSeoData([
            'pageimg'     => '',
            'description' => '',
            'keywords'    => '',
        ]);

        $this->assertNull($result);
    }

    /**
     * Regresyon: Sadece whitespace içeren alanlar boş sayılmalı.
     */
    public function testBuildSeoDataReturnsNullForWhitespaceOnlyFields(): void
    {
        $result = $this->lib->buildSeoData([
            'pageimg'     => '   ',
            'description' => '   ',
            'keywords'    => '   ',
        ]);

        // trim sonrası boş → null
        $this->assertNull($result);
    }

    // ──────────────────────────────────────────────
    // getDatatablesPagination — Temel doğruluk
    // ──────────────────────────────────────────────

    public function testGetDatatablesPaginationDefaults(): void
    {
        $result = $this->lib->getDatatablesPagination([
            'search' => ['value' => ''],
        ]);

        $this->assertSame(10, $result['length']);
        $this->assertSame(0, $result['start']);
        $this->assertSame('', $result['searchString']);
        $this->assertSame(1, $result['draw']);
    }

    public function testGetDatatablesPaginationMinusOneLength(): void
    {
        $result = $this->lib->getDatatablesPagination([
            'search' => ['value' => ''],
            'length' => '-1',
            'start'  => '0',
            'draw'   => '3',
        ]);

        // length -1 = "tümünü göster" → 0 dönmeli
        $this->assertSame(0, $result['length']);
        $this->assertSame(0, $result['start']);
        $this->assertSame(3, $result['draw']);
    }

    public function testGetDatatablesPaginationStripsHtmlFromSearch(): void
    {
        $result = $this->lib->getDatatablesPagination([
            'search' => ['value' => '<b>test</b>'],
        ]);

        $this->assertSame('test', $result['searchString']);
    }
}
