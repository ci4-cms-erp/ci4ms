<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regression tests for the cache('settings') object-canon.
 *
 * Four request-entry fillers (app/Config/Filters.php, app/Config/Routes.php,
 * modules/Backend/Commands/Views/routes.tpl.php, modules/Auth/Controllers/
 * BaseController.php) build the settings cache with an identical decode loop:
 * the container is a plain PHP array (key => value), and each value is a
 * JSON-object -> nested stdClass, a JSON-array -> PHP indexed array of stdClass,
 * or (when the raw value is not a JSON object/array) the raw string untouched.
 *
 * Frontend/backend views then read that shape with object access
 * ($settings->templateInfos->fonts->googleFont, $sn->link, ...). These tests
 * exercise the pure decode logic and the two representative consumer paths
 * (templateInfos nested + socialNetwork) with no database or cache access, so
 * they pin the canonical shape the fillers must keep producing.
 *
 * @internal
 */
final class SettingsCacheCanonTest extends CIUnitTestCase
{
    /**
     * Decodes settings rows exactly as the four request-entry fillers do.
     *
     * This is a byte-for-byte copy of the filler loop; if the fillers change
     * their decode logic this helper must change with them, and any drift
     * between them is what the byte-identity check in the QA harness guards.
     *
     * @param list<object{key: string, value: string}> $rows Rows shaped like CommonModel::lists('settings') output.
     *
     * @return array<string, mixed> The container stored into cache('settings').
     */
    private function decodeCanon(array $rows): array
    {
        $set = [];
        foreach ($rows as $setting) {
            $decoded = json_decode($setting->value);
            $set[$setting->key] = (json_last_error() === JSON_ERROR_NONE && (is_object($decoded) || is_array($decoded)))
                ? $decoded
                : $setting->value;
        }

        return $set;
    }

    /**
     * Builds a single settings row (stdClass with ->key and ->value).
     *
     * @param string $key   Setting key.
     * @param string $value Raw stored value (JSON or plain string).
     *
     * @return object{key: string, value: string} The row.
     */
    private function row(string $key, string $value): object
    {
        return (object) ['key' => $key, 'value' => $value];
    }

    /**
     * Returns a representative, fully populated settings container.
     *
     * @return array<string, mixed> Decoded container.
     */
    private function fullContainer(): array
    {
        $templateInfos = (string) json_encode([
            'path'         => 'default',
            'fonts'        => ['googleFont' => 'Roboto', 'weights' => '400,600,700'],
            'theme_assets' => [
                'styles'  => ['/templates/default/assets/ci4ms.css', 'https://cdn.example.com/x.css'],
                'scripts' => ['/templates/default/assets/ci4ms.js'],
            ],
            'widgets'      => ['sidebar' => ['searchWidget' => true, 'categoriesWidget' => false, 'archiveWidget' => true]],
            'footer'       => [
                'copyright' => '© 2026 ACME',
                'links'     => [
                    ['label' => 'Privacy', 'url' => '/privacy'],
                    ['label' => 'Terms', 'url' => '/terms'],
                ],
            ],
            'display'      => ['breadcrumbs' => true, 'backToTop' => true, 'darkModeToggle' => false],
        ], JSON_UNESCAPED_UNICODE);

        $social = (string) json_encode([
            ['smName' => 'twitter', 'link' => 'https://twitter.com/acme'],
            ['smName' => 'github', 'link' => 'https://github.com/acme'],
        ], JSON_UNESCAPED_UNICODE);

        return $this->decodeCanon([
            $this->row('templateInfos', $templateInfos),
            $this->row('socialNetwork', $social),
            $this->row('siteName', 'My Site'),
            $this->row('maintenanceMode', '1'),
        ]);
    }

    /**
     * Runs a closure with any PHP notice/warning promoted to an exception.
     *
     * Lets a test assert that a view access path emits zero notices, without
     * depending on the PHPUnit failOnWarning configuration.
     *
     * @param callable $fn Access path to execute.
     *
     * @return void
     *
     * @throws ErrorException If the closure emits any notice/warning/deprecation.
     */
    private function withStrictErrors(callable $fn): void
    {
        set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
            throw new ErrorException($message, 0, $severity, $file, $line);
        });

        try {
            $fn();
        } finally {
            restore_error_handler();
        }
    }

    /**
     * The cache container is a plain top-level PHP array.
     *
     * Consumers use both (object) cache('settings') and array access
     * cache('settings')['backendMaintenance']; the latter fatals if the
     * container is not an array, so the top level must never be an object.
     *
     * @return void
     */
    public function testContainerIsTopLevelPhpArray(): void
    {
        $container = $this->fullContainer();

        $this->assertArrayHasKey('templateInfos', $container);
        $this->assertArrayHasKey('socialNetwork', $container);
        $this->assertArrayHasKey('siteName', $container);
    }

    /**
     * A JSON object value decodes to a deeply-nested stdClass tree.
     *
     * @return void
     */
    public function testJsonObjectDecodesToNestedStdClass(): void
    {
        $ti = $this->fullContainer()['templateInfos'];

        $this->assertInstanceOf(stdClass::class, $ti);
        $this->assertInstanceOf(stdClass::class, $ti->fonts);
        $this->assertInstanceOf(stdClass::class, $ti->widgets->sidebar);
        $this->assertSame('Roboto', $ti->fonts->googleFont);
        $this->assertTrue($ti->widgets->sidebar->searchWidget);
    }

    /**
     * A JSON array value decodes to a PHP indexed array of stdClass items.
     *
     * @return void
     */
    public function testJsonArrayDecodesToIndexedArrayOfStdClass(): void
    {
        $social = $this->fullContainer()['socialNetwork'];

        $this->assertIsArray($social);
        $this->assertInstanceOf(stdClass::class, $social[0]);
        $this->assertSame('twitter', $social[0]->smName);
        $this->assertSame('https://twitter.com/acme', $social[0]->link);
    }

    /**
     * Non-object/array JSON and plain strings are kept as the raw string.
     *
     * A JSON scalar ("1") decodes to an int but is NOT an object/array, so the
     * canon keeps the raw string; a plain string is kept untouched.
     *
     * @return void
     */
    public function testScalarAndPlainValuesStayRawStrings(): void
    {
        $container = $this->fullContainer();

        $this->assertSame('1', $container['maintenanceMode']);
        $this->assertSame('My Site', $container['siteName']);
    }

    /**
     * The templateInfos consumer paths resolve with zero notices.
     *
     * Mirrors base.php / sidebar.php / temp-settings.php: fonts->googleFont,
     * theme_assets->styles foreach, widgets->sidebar->searchWidget,
     * footer->links foreach with $link->label, display->backToTop.
     *
     * @return void
     */
    public function testTemplateInfosConsumerPathsResolveWithoutNotice(): void
    {
        $settings = (object) $this->fullContainer();

        $collected = [];
        $this->withStrictErrors(function () use ($settings, &$collected): void {
            $collected['googleFont'] = $settings->templateInfos->fonts->googleFont ?? '';
            $collected['weights']    = $settings->templateInfos->fonts->weights ?? '400,600,700';

            $collected['styles'] = [];
            if (!empty($settings->templateInfos->theme_assets->styles)) {
                foreach ($settings->templateInfos->theme_assets->styles as $styleUrl) {
                    $collected['styles'][] = $styleUrl;
                }
            }

            $collected['searchWidget'] = $settings->templateInfos->widgets->sidebar->searchWidget;

            $collected['labels'] = [];
            foreach (($settings->templateInfos->footer->links ?? []) as $link) {
                $collected['labels'][] = $link->label ?? '';
            }

            $collected['backToTop'] = !empty($settings->templateInfos->display->backToTop);
        });

        $this->assertSame('Roboto', $collected['googleFont']);
        $this->assertSame('400,600,700', $collected['weights']);
        $this->assertSame(['/templates/default/assets/ci4ms.css', 'https://cdn.example.com/x.css'], $collected['styles']);
        $this->assertTrue($collected['searchWidget']);
        $this->assertSame(['Privacy', 'Terms'], $collected['labels']);
        $this->assertTrue($collected['backToTop']);
    }

    /**
     * The socialNetwork consumer paths resolve with zero notices.
     *
     * Mirrors settings.php (no defensive cast, $sn->smName / $sn->link),
     * maintenance.php ((object) cast then $sn->link) and Home.php
     * (array_map(fn($sN) => $sN->link, (array) $settings->socialNetwork)).
     *
     * @return void
     */
    public function testSocialNetworkConsumerPathsResolveWithoutNotice(): void
    {
        $settings = (object) $this->fullContainer();

        $links = [];
        $names = [];
        $this->withStrictErrors(function () use ($settings, &$links, &$names): void {
            foreach ($settings->socialNetwork as $sn) {
                $names[] = $sn->smName;
                $links[] = $sn->link;
            }
        });

        $mapped = array_map(static fn ($sN) => $sN->link, (array) $settings->socialNetwork);

        $this->assertSame(['twitter', 'github'], $names);
        $this->assertSame(['https://twitter.com/acme', 'https://github.com/acme'], $links);
        $this->assertSame($links, $mapped);
    }

    /**
     * Missing templateInfos/socialNetwork never fatals the guarded consumers.
     *
     * A fresh install has neither key; the (object) cast yields a stdClass with
     * no such properties, and every guarded access (empty() / ??) must fall
     * through to its default without emitting a notice.
     *
     * @return void
     */
    public function testMissingKeysFallThroughWithoutNotice(): void
    {
        $settings = (object) $this->decodeCanon([
            $this->row('siteName', 'Bare Site'),
            $this->row('maintenanceMode', '0'),
        ]);

        $result = [];
        $this->withStrictErrors(function () use ($settings, &$result): void {
            $result['googleFont'] = $settings->templateInfos->fonts->googleFont ?? 'DEFAULT';
            $result['styles']     = $settings->templateInfos->theme_assets->styles ?? ['fallback.css'];
            $result['sidebar']    = !empty($settings->templateInfos->widgets->sidebar);
            $result['backToTop']  = !empty($settings->templateInfos->display->backToTop);
            $result['social']     = array_map(static fn ($sN) => $sN->link, (array) ($settings->socialNetwork ?? []));
        });

        $this->assertSame('DEFAULT', $result['googleFont']);
        $this->assertSame(['fallback.css'], $result['styles']);
        $this->assertFalse($result['sidebar']);
        $this->assertFalse($result['backToTop']);
        $this->assertSame([], $result['social']);
    }

    /**
     * Home::maintenanceMode() gate reads the raw scalar maintenanceMode.
     *
     * Guards the fixed Home.php:116 expression
     * `(bool)($settings->maintenanceMode ?? false) === false` against the old
     * `->scalar` object-cast artefact. On the object-canon, maintenanceMode
     * stays a raw string ("0"/"1"), so the gate must resolve with zero notices:
     * "1" -> false (no redirect, maintenance page renders), "0" -> true
     * (redirect to home), missing property -> true (redirect, guard default).
     *
     * @return void
     */
    public function testMaintenanceModeGateReadsRawScalarWithoutNotice(): void
    {
        $on      = (object) $this->decodeCanon([$this->row('maintenanceMode', '1')]);
        $off     = (object) $this->decodeCanon([$this->row('maintenanceMode', '0')]);
        $missing = (object) $this->decodeCanon([$this->row('siteName', 'Bare Site')]);

        $redirectHome = [];
        $this->withStrictErrors(function () use ($on, $off, $missing, &$redirectHome): void {
            $redirectHome['on']      = (bool) ($on->maintenanceMode ?? false) === false;
            $redirectHome['off']     = (bool) ($off->maintenanceMode ?? false) === false;
            $redirectHome['missing'] = (bool) ($missing->maintenanceMode ?? false) === false;
        });

        $this->assertFalse($redirectHome['on']);
        $this->assertTrue($redirectHome['off']);
        $this->assertTrue($redirectHome['missing']);
    }

    /**
     * Media::store() presave hook reads the raw scalar convertWebp.
     *
     * Guards the fixed Media.php:124 expression
     * `(bool)($settings->convertWebp ?? false) === true` against the old
     * `->scalar` object-cast artefact. On the object-canon, convertWebp stays a
     * raw string ("0"/"1"), so the toggle must resolve with zero notices:
     * "1" -> true (conversion on), "0" -> false (off), missing property ->
     * false (off, guard default).
     *
     * @return void
     */
    public function testConvertWebpToggleReadsRawScalarWithoutNotice(): void
    {
        $on      = (object) $this->decodeCanon([$this->row('convertWebp', '1')]);
        $off     = (object) $this->decodeCanon([$this->row('convertWebp', '0')]);
        $missing = (object) $this->decodeCanon([$this->row('siteName', 'Bare Site')]);

        $convert = [];
        $this->withStrictErrors(function () use ($on, $off, $missing, &$convert): void {
            $convert['on']      = (bool) ($on->convertWebp ?? false) === true;
            $convert['off']     = (bool) ($off->convertWebp ?? false) === true;
            $convert['missing'] = (bool) ($missing->convertWebp ?? false) === true;
        });

        $this->assertTrue($convert['on']);
        $this->assertFalse($convert['off']);
        $this->assertFalse($convert['missing']);
    }
}
