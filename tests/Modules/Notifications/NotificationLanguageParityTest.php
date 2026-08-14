<?php

namespace Tests\Modules\Notifications;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * The two Notifications locales must stay the same file with different words in it.
 *
 * CI4 resolves a missing key by echoing the key itself, so a translation gap does not
 * fail anywhere — it ships, and an administrator reads 'Notifications.composeSubmit'
 * on a button. The FAZ 3 screen added around thirty keys at once, which is exactly the
 * kind of change that lands in one locale and not the other, so the two files are
 * compared as sets rather than reviewed by eye.
 *
 * Three properties are asserted:
 *   1. the key sets are identical in both directions;
 *   2. neither file defines a key twice — PHP keeps the LAST duplicate silently, so
 *      the earlier (usually the intended) wording disappears without a warning. This
 *      one has to be read from the file SOURCE, because the parsed array has already
 *      collapsed the duplicate;
 *   3. every key the composer screen actually asks for exists in both files, which is
 *      what catches a key renamed on one side only.
 *
 * @internal
 */
final class NotificationLanguageParityTest extends CIUnitTestCase
{
    /** Locale directories the module ships. */
    private const LOCALES = ['en', 'tr'];

    /** Sources whose `Notifications.*` references must resolve in every locale. */
    private const CONSUMERS = [
        'modules/Notifications/Controllers/ComposerController.php',
        'modules/Notifications/Views/compose.php',
    ];

    /**
     * The parsed language array of one locale.
     *
     * @param string $locale Locale directory name.
     *
     * @return array<string, string> Key => translation.
     */
    private static function lines(string $locale): array
    {
        /** @var array<string, string> $lines */
        $lines = require ROOTPATH . 'modules/Notifications/Language/' . $locale . '/Notifications.php';

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
        $source = (string) file_get_contents(ROOTPATH . 'modules/Notifications/Language/' . $locale . '/Notifications.php');

        preg_match_all("/^\s*'([^']+)'\s*=>/m", $source, $matches);

        return $matches[1];
    }

    /**
     * Every `Notifications.*` key referenced by the composer screen.
     *
     * @return list<string> Distinct key names, without the file prefix.
     */
    private static function referencedKeys(): array
    {
        $keys = [];

        foreach (self::CONSUMERS as $path) {
            preg_match_all('/Notifications\.([A-Za-z0-9_]+)/', (string) file_get_contents(ROOTPATH . $path), $matches);
            $keys = array_merge($keys, $matches[1]);
        }

        return array_values(array_unique($keys));
    }

    /**
     * Both locales define exactly the same set of keys.
     *
     * @return void
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
     * Neither locale declares the same key twice.
     *
     * @return void
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
                "every declaration in the {$locale} file survived into the parsed array"
            );
        }
    }

    /**
     * Every key the composer screen asks for exists in both locales.
     *
     * @return void
     */
    public function testEveryKeyTheComposerReferencesExistsInBothLocales(): void
    {
        $referenced = self::referencedKeys();

        $this->assertNotEmpty($referenced, 'the scan must actually find keys, or this test proves nothing');

        foreach (self::LOCALES as $locale) {
            $missing = array_values(array_diff($referenced, array_keys(self::lines($locale))));

            $this->assertSame([], $missing, "the {$locale} file is missing: " . implode(', ', $missing));
        }
    }

    /**
     * The composer keys added by FAZ 3 are present, not just consistent with each other.
     *
     * Parity alone is satisfied by two files that are equally incomplete; these are the
     * lines the new screen cannot render without.
     *
     * @return void
     */
    public function testTheComposerKeysArePresentInBothLocales(): void
    {
        $required = [
            'compose', 'composeIntro', 'composeSubmit', 'composeSent', 'composeFailed',
            'composeFieldTitle', 'composeFieldBody', 'composeFieldUrl', 'composeFieldSeverity',
            'composeFieldMode', 'composeFieldUsers', 'composeFieldGroups', 'composeFieldExclude',
            'composeModeBroadcast', 'composeModeTargeted', 'composePreview', 'composeRecipients',
            'composeNoTarget', 'composeUnknownGroup', 'composeUnknownUser',
            'composeInvalidText', 'composeTitleRequired', 'composeInvalidSeverity', 'composeInvalidMode',
            'severityInfo', 'severityWarning', 'severityCritical',
        ];

        foreach (self::LOCALES as $locale) {
            $lines = self::lines($locale);

            foreach ($required as $key) {
                $this->assertArrayHasKey($key, $lines, "{$locale}.{$key}");
                $this->assertNotSame('', trim($lines[$key]), "{$locale}.{$key} is declared but empty");
            }
        }
    }

    /**
     * A translated line keeps the placeholders of its English original.
     *
     * `composeSent` and `composeRecipients` interpolate the recipient count; a
     * translation that drops `{0}` turns the only number the sender is given into
     * nothing at all, and no test of the count itself would notice.
     *
     * @return void
     */
    public function testTranslationsKeepThePlaceholdersOfTheirOriginals(): void
    {
        $english = self::lines('en');
        $turkish = self::lines('tr');

        foreach ($english as $key => $line) {
            preg_match_all('/\{\d+\}/', $line, $expected);
            preg_match_all('/\{\d+\}/', $turkish[$key] ?? '', $actual);

            sort($expected[0]);
            sort($actual[0]);

            $this->assertSame($expected[0], $actual[0], "the tr translation of '{$key}' does not interpolate the same values");
        }
    }
}
