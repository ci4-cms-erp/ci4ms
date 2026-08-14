<?php

declare(strict_types=1);

namespace Tests\Modules\Backend;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Auth\Config\Auth;

/**
 * Regression suite for F7 (installer environment + captcha bypass flag).
 *
 * Both the web installer (Modules\Install\Controllers\Install) and the CLI
 * installer (Modules\Backend\Commands\Ci4msSetup) used to write
 * `CI_ENVIRONMENT=development` into the generated .env, and the login/
 * comment-form captcha bypass was gated solely on
 * `ENVIRONMENT === 'development'`. Any operator who left ENVIRONMENT at its
 * installer default therefore shipped with debug output AND captcha bypass
 * both enabled in what they believed was a production deployment.
 *
 * Placement note: F7 test infrastructure was added under
 * tests/Modules/Backend/ rather than tests/Modules/Install/ -- .gitignore's
 * tests/Modules/Backend/ whitelist block already existed (Ci4msSetup.php
 * itself lives in Modules\Backend\Commands), so covering both installers
 * here avoids adding a brand-new tests/Modules/Install/ whitelist block for
 * a single-purpose regression file. See context.md for the full rationale.
 *
 * The .env-writing assertions are deliberately file-content checks, not a
 * full installer run: actually exercising Install::dbsetup()/
 * Ci4msSetup::updateEnvSettings() requires a real migration/DB-setup pass,
 * which this suite avoids per ci4ms-protocol's migration safety rules (see
 * .ci4ms/knowledge/pitfalls.md -- `php spark migrate` must never run against
 * a live-DB-adjacent environment from an agent session).
 *
 * @internal
 */
final class InstallerHardeningTest extends CIUnitTestCase
{
    /**
     * The web installer's $updates array must set CI_ENVIRONMENT to
     * 'production', not 'development'.
     */
    public function testInstallControllerWritesProductionEnvironment(): void
    {
        $path = ROOTPATH . 'modules/Install/Controllers/Install.php';
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertMatchesRegularExpression(
            "/'CI_ENVIRONMENT'\\s*=>\\s*'production'/",
            $content,
            'Install.php must write CI_ENVIRONMENT => production.',
        );
        $this->assertDoesNotMatchRegularExpression(
            "/'CI_ENVIRONMENT'\\s*=>\\s*'development'/",
            $content,
            'Install.php must not write CI_ENVIRONMENT => development.',
        );
    }

    /**
     * The CLI installer's $updates array must set CI_ENVIRONMENT to
     * 'production', not 'development'.
     */
    public function testCi4msSetupCommandWritesProductionEnvironment(): void
    {
        $path = ROOTPATH . 'modules/Backend/Commands/Ci4msSetup.php';
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertMatchesRegularExpression(
            "/'CI_ENVIRONMENT'\\s*=>\\s*'production'/",
            $content,
            'Ci4msSetup.php must write CI_ENVIRONMENT => production.',
        );
        $this->assertDoesNotMatchRegularExpression(
            "/'CI_ENVIRONMENT'\\s*=>\\s*'development'/",
            $content,
            'Ci4msSetup.php must not write CI_ENVIRONMENT => development.',
        );
    }

    /**
     * Auth::$captchaBypassInDevelopment must default to false -- the login/
     * comment-form captcha bypass requires this setting to be explicitly
     * turned on, ENVIRONMENT === 'development' alone must not be enough.
     * This is a direct config-property check, independent of the settings
     * table/cache -- deterministic regardless of test-DB state (see
     * .ci4ms/knowledge/pitfalls.md "Test veritabanı kırılganlığı").
     */
    public function testCaptchaBypassInDevelopmentConfigDefaultsToFalse(): void
    {
        $config = new Auth();

        $this->assertFalse($config->captchaBypassInDevelopment);
    }

    /**
     * Cross-check via the real setting() helper: with no settings-table row
     * for Auth.captchaBypassInDevelopment (the seed row Group E added to
     * Ci4msReferenceDataSeeder.php has not been run against ci4ms_test in
     * this session -- seeding requires `php spark db:seed`, which this
     * suite deliberately avoids, same rationale as the migration note
     * above), CodeIgniter\Settings\Settings::get() falls through to the
     * config class default (vendor/codeigniter4/settings/src/Settings.php:
     * 53-70) -- the exact mechanism modules/Auth/Models/UserSessionModel.php
     * already relies on for the sibling geoLookupEnabled setting.
     *
     * The fixture-assumption guard makes this self-documenting: if a future
     * session seeds ci4ms_test (or a previous one already did), this test
     * fails loudly instead of silently asserting against a DB-backed value
     * it no longer controls.
     */
    public function testCaptchaBypassInDevelopmentSettingFallsBackToConfigDefault(): void
    {
        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Refusing to run: the active connection is not pointed at the test database.',
        );

        $existingRow = \Config\Database::connect()
            ->table('settings')
            ->where(['class' => Auth::class, 'key' => 'captchaBypassInDevelopment'])
            ->countAllResults();

        $this->assertSame(
            0,
            $existingRow,
            'fixture assumption violated: a settings-table row for Auth.captchaBypassInDevelopment already exists -- this test no longer exercises the config-default fallback path.',
        );

        $this->assertFalse(setting('Auth.captchaBypassInDevelopment'));
    }
}
