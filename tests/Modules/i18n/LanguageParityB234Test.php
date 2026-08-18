<?php

namespace Tests\Modules\i18n;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * The audit lines B-2/B-3/B-4 added must survive in both locales AND actually interpolate.
 *
 * Two different bugs have to be locked out here, and neither technique catches the other:
 *
 *   1. A key that exists in `en` but not in `tr`. `require`-ing both files and comparing
 *      the parsed arrays is the ONLY way to see this. `lang()` cannot: Language.php:123-128
 *      falls back to English when the active locale has no entry, so a `tr` gap renders as
 *      fluent English and a naive "lang() did not return the raw key name" assertion stays
 *      green. That is why the array assertions below were kept rather than replaced.
 *
 *   2. A `%s` written where ICU wants `{0}`. This environment has intl loaded, so CI4 always
 *      formats through MessageFormatter (Language.php:213) and sprintf syntax is never
 *      substituted — the administrator reads a literal `%s`. Reading the array cannot see
 *      this: `%s` is a perfectly valid string sitting in a perfectly valid array. Only a real
 *      `lang()` call with real arguments shows it. See .ci4ms/knowledge/pitfalls.md:47-60.
 *
 * So every key is checked three ways: it is DECLARED in both files (arrays), it SUBSTITUTES
 * its arguments when rendered (real `lang()` with sentinel arguments), and `tr` renders
 * something OTHER than `en` (real `lang()` twice). The third one turns the English fallback
 * from a masking problem into the detector: if the `tr` entry vanished, both calls would
 * return the same English sentence.
 *
 * @internal
 */
final class LanguageParityB234Test extends CIUnitTestCase
{
    /** Locale directories every module in this suite ships. */
    private const LOCALES = ['en', 'tr'];

    /**
     * How many of the keys below each module must actually declare, per locale.
     *
     * This is not a restatement of `count(self::keys($module))`: what is counted is how many
     * of the expected keys the language FILE ON DISK still has, so the number goes red both
     * when a key is dropped from `modules/*\/Language/` and when a key is quietly dropped
     * from the expectation list.
     */
    private const EXPECTED_COUNT = [
        'Users'   => 7,
        'Methods' => 2,
        'Backup'  => 2,
    ];

    /**
     * Substituted for `{0}`, `{1}`, … so the rendered line can be searched for them.
     *
     * Contains no ICU syntax character (`{`, `}`, `'`, `#`), so it passes through
     * MessageFormatter unchanged and any occurrence in the output is a real substitution.
     */
    private const ARG_SENTINEL = '@@A%d@@';

    protected function setUp(): void
    {
        parent::setUp();

        // Every assertion here goes through the shared `language` service; a mock left behind
        // by another test would make this file assert nothing. Narrow reset only — a broad
        // Services::reset() drops the shared `routes` service (.ci4ms/knowledge/pitfalls.md:125-146).
        \Config\Services::resetSingle('language');
    }

    /**
     * The keys B-2/B-3/B-4 introduced, per module.
     *
     * @return list<string> Key names without the file prefix.
     */
    private static function keys(string $module): array
    {
        $keys = [
            'Backup' => [
                'auditBackupRestored',
                'auditRestoreRbacStatementsSkipped',
            ],
            'Methods' => [
                'auditPermissionPageCreated',
                'auditPermissionPageUpdated',
            ],
            'Users' => [
                'permsMalformed',
                'auditPermsDelegationRejected',
                'auditGroupCreated',
                'auditGroupUpdated',
                'auditUserPermsUpdated',
                'auditUserUpdated',
                'auditUserUpdatedWithPasswordReset',
            ],
        ];

        return $keys[$module] ?? [];
    }

    /**
     * The parsed language array of one module/locale, read straight from the file.
     *
     * @return array<string, string> Key => translation.
     */
    private static function lines(string $module, string $locale): array
    {
        /** @var array<string, string> $lines */
        $lines = require ROOTPATH . "modules/{$module}/Language/{$locale}/{$module}.php";

        return $lines;
    }

    /**
     * Every placeholder index EITHER locale uses for one key.
     *
     * The union matters: if one locale drops `{0}`, the sentinel for index 0 is still passed
     * to both, and its absence from that locale's output is what fails.
     *
     * @return list<int> Distinct indices, ascending; empty when the line takes no arguments.
     */
    private static function sharedPlaceholderIndices(string $module, string $key): array
    {
        $indices = array_values(array_unique(array_merge(
            self::placeholderIndices(self::lines($module, 'en')[$key] ?? ''),
            self::placeholderIndices(self::lines($module, 'tr')[$key] ?? '')
        )));
        sort($indices);

        return $indices;
    }

    /**
     * Sentinel arguments covering those indices.
     *
     * @param list<int> $indices Output of sharedPlaceholderIndices().
     *
     * @return list<string> One sentinel per index, index 0 upwards, never empty.
     */
    private static function sentinelArgs(array $indices): array
    {
        // A line with no placeholders still gets one argument, because MessageFormatter is
        // skipped entirely when $args === [] (Language.php:201) and a malformed ICU pattern
        // would then never be parsed at all.
        $highest = $indices === [] ? 0 : max($indices);
        $args    = [];

        for ($i = 0; $i <= $highest; $i++) {
            $args[] = sprintf(self::ARG_SENTINEL, $i);
        }

        return $args;
    }

    /**
     * The ICU placeholder indices a line uses.
     *
     * @return list<int> Distinct indices, ascending.
     */
    private static function placeholderIndices(string $line): array
    {
        preg_match_all('/\{(\d+)\}/', $line, $matches);

        $indices = array_values(array_unique(array_map('intval', $matches[1])));
        sort($indices);

        return $indices;
    }

    /**
     * Every key is physically declared, and not empty, in both locale files.
     *
     * The count is asserted against what the file holds, so a key deleted from
     * `modules/*\/Language/` fails here even though `lang()` would still render it in English.
     */
    public function testEveryKeyIsDeclaredInBothLocaleFiles(): void
    {
        foreach (self::EXPECTED_COUNT as $module => $expected) {
            $expectedKeys = self::keys($module);

            foreach (self::LOCALES as $locale) {
                $path = ROOTPATH . "modules/{$module}/Language/{$locale}/{$module}.php";
                $this->assertFileExists($path, "{$module}/{$locale} language file");

                $lines = self::lines($module, $locale);

                foreach ($expectedKeys as $key) {
                    $this->assertArrayHasKey($key, $lines, "{$locale}: {$module}.{$key} is not declared");
                    $this->assertNotSame('', trim($lines[$key]), "{$locale}: {$module}.{$key} is declared but empty");
                }

                $present = array_values(array_intersect($expectedKeys, array_keys($lines)));

                $this->assertCount(
                    $expected,
                    $present,
                    "{$locale}: {$module} must carry {$expected} of the B-2/3/4 keys, found: " . implode(', ', $present)
                );
            }
        }
    }

    /**
     * A real `lang()` call substitutes every placeholder, in every locale.
     *
     * This is the assertion the array comparison above cannot make. `%s` survives a `require`
     * untouched; it only fails once MessageFormatter has run and the sentinel is missing from
     * the output.
     */
    public function testRealLangCallsSubstituteEveryPlaceholder(): void
    {
        foreach (array_keys(self::EXPECTED_COUNT) as $module) {
            foreach (self::keys($module) as $key) {
                $indices = self::sharedPlaceholderIndices($module, $key);
                $args    = self::sentinelArgs($indices);

                foreach (self::LOCALES as $locale) {
                    $source = self::lines($module, $locale)[$key] ?? '';

                    $this->assertDoesNotMatchRegularExpression(
                        '/%[sd]/',
                        $source,
                        "{$locale}: {$module}.{$key} uses sprintf syntax; intl is loaded, so it is never substituted"
                    );

                    $rendered = lang("{$module}.{$key}", $args, $locale);

                    $this->assertIsString($rendered, "{$locale}: {$module}.{$key} did not render to a string");
                    $this->assertNotSame("{$module}.{$key}", $rendered, "{$locale}: {$module}.{$key} did not resolve at all");
                    $this->assertStringNotContainsString(
                        '【Warning】',
                        $rendered,
                        "{$locale}: {$module}.{$key} is not a valid ICU pattern"
                    );

                    foreach ($indices as $index) {
                        $this->assertStringContainsString(
                            $args[$index],
                            $rendered,
                            "{$locale}: {$module}.{$key} never substituted argument {$index}; rendered: {$rendered}"
                        );
                    }

                    $this->assertDoesNotMatchRegularExpression(
                        '/\{\d+\}/',
                        $rendered,
                        "{$locale}: {$module}.{$key} still shows a raw placeholder after formatting: {$rendered}"
                    );
                    $this->assertDoesNotMatchRegularExpression(
                        '/%[sd]/',
                        $rendered,
                        "{$locale}: {$module}.{$key} still shows sprintf syntax after formatting: {$rendered}"
                    );
                }
            }
        }
    }

    /**
     * Both locales interpolate the same values.
     *
     * A translation that drops `{2}` silently loses the group list from the audit line, and
     * no assertion about the count of anything would notice.
     */
    public function testBothLocalesInterpolateTheSamePlaceholders(): void
    {
        foreach (array_keys(self::EXPECTED_COUNT) as $module) {
            $english = self::lines($module, 'en');
            $turkish = self::lines($module, 'tr');

            foreach (self::keys($module) as $key) {
                $this->assertSame(
                    self::placeholderIndices($english[$key] ?? ''),
                    self::placeholderIndices($turkish[$key] ?? ''),
                    "{$module}.{$key} does not interpolate the same values in both locales"
                );
            }
        }
    }

    /**
     * `tr` renders something other than `en`, asked through the real helper.
     *
     * This is the one assertion that carries the "go through `lang()`" rule to its conclusion.
     * Language.php:123-128 answers a missing `tr` entry with the English line, so two
     * identical renders mean either a copy-pasted translation or no translation at all —
     * the exact gap a `lang()`-only presence check would swallow.
     */
    public function testTurkishRendersDifferentTextThanEnglish(): void
    {
        foreach (array_keys(self::EXPECTED_COUNT) as $module) {
            foreach (self::keys($module) as $key) {
                $args = self::sentinelArgs(self::sharedPlaceholderIndices($module, $key));

                $this->assertNotSame(
                    lang("{$module}.{$key}", $args, 'en'),
                    lang("{$module}.{$key}", $args, 'tr'),
                    "{$module}.{$key} renders identically in both locales: the tr entry is missing or copy-pasted"
                );
            }
        }
    }
}
