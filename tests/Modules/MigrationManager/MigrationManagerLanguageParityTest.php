<?php

declare(strict_types=1);

namespace Tests\Modules\MigrationManager;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * BUG-3 doğrulaması: `deletedUser` ve `searchPlaceholder` anahtarları hem
 * `en` hem `tr` dosyasında var ve her dosya içinde tekil (PHP dizi
 * literal'inde bir anahtarın ikinci tanımı sessizce ilkini ezer — bu yüzden
 * sayım, `require` edilmiş dizi yerine dosyanın KAYNAĞINDAN yapılır, aksi
 * halde bir duplicate hiçbir zaman görünmez).
 *
 * `modules/Backend/Views/base.php`'nin `window.CI4MS_LOCALE` düzeltmesi
 * `ci4msDtLanguage()` kullanan diğer modülleri (Backup, Blog,
 * DashboardWidgets, LanguageManager, Logs, Pages) de etkiliyor ama onların
 * hiçbirinde mevcut bir view-render/assertSee testi yok
 * (`grep -rl "ViewRenderTest\|assertSee" tests/` bu modüllerde eşleşme
 * vermedi) — bu dosya yalnızca MigrationManager'ın kendi iki yeni anahtarını
 * kapsar; diğer modüller için ayrı bir regresyon testi bu görevin kapsamı
 * dışıdır.
 *
 * @internal
 */
final class MigrationManagerLanguageParityTest extends CIUnitTestCase
{
    /** Locale directories the module ships. */
    private const LOCALES = ['en', 'tr'];

    /** BUG-2/BUG-3 tarafından eklenen anahtarlar. */
    private const NEW_KEYS = ['deletedUser', 'searchPlaceholder'];

    /**
     * @param string $locale Locale directory name.
     *
     * @return array<string, string> Key => translation.
     */
    private static function lines(string $locale): array
    {
        /** @var array<string, string> $lines */
        $lines = require ROOTPATH . 'modules/MigrationManager/Language/' . $locale . '/MigrationManager.php';

        return $lines;
    }

    /**
     * The keys a language file DECLARES, duplicates included, read from its source.
     *
     * @param string $locale Locale directory name.
     *
     * @return list<string> Declared keys in file order, one entry per declaration.
     */
    private static function declaredKeys(string $locale): array
    {
        $source = (string) file_get_contents(ROOTPATH . 'modules/MigrationManager/Language/' . $locale . '/MigrationManager.php');

        preg_match_all("/^\s*'([^']+)'\s*=>/m", $source, $matches);

        return $matches[1];
    }

    /**
     * `en` ve `tr` aynı anahtar kümesini tanımlar (eksik bir anahtar diğer
     * locale'de sessizce kendi adını gösterir, CI4'ün varsayılan davranışı).
     */
    public function testBothLocalesDefineTheSameKeys(): void
    {
        $english = array_keys(self::lines('en'));
        $turkish = array_keys(self::lines('tr'));

        sort($english);
        sort($turkish);

        $this->assertSame($english, $turkish, 'a key that exists in one locale only is rendered as its own name in the other');
    }

    /**
     * Ne `en` ne `tr` aynı anahtarı iki kez tanımlar (PHP dizi literal'i
     * sessizce ikinciyi tutar, ilk tanım kaybolur).
     */
    public function testNeitherLocaleDeclaresAKeyTwice(): void
    {
        foreach (self::LOCALES as $locale) {
            $declared = self::declaredKeys($locale);
            $counts   = array_filter(array_count_values($declared), static fn (int $count): bool => $count > 1);

            $this->assertSame([], $counts, "the {$locale} file silently overwrites: " . implode(', ', array_keys($counts)));
            $this->assertSame(
                count(self::lines($locale)),
                count($declared),
                "every declaration in the {$locale} file survived into the parsed array",
            );
        }
    }

    /**
     * BUG-2 (`deletedUser`) ve BUG-3 (`searchPlaceholder`) anahtarları her
     * iki locale'de de mevcut ve boş değil.
     */
    public function testTheNewKeysArePresentAndNonEmptyInBothLocales(): void
    {
        foreach (self::LOCALES as $locale) {
            $lines = self::lines($locale);

            foreach (self::NEW_KEYS as $key) {
                $this->assertArrayHasKey($key, $lines, "{$locale}.{$key}");
                $this->assertNotSame('', trim($lines[$key]), "{$locale}.{$key} is declared but empty");
            }
        }
    }
}
