<?php

declare(strict_types=1);

namespace Tests\Modules\Backend;

use CodeIgniter\Config\Services;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use Config\App;
use Modules\Auth\Config\Auth;
use Modules\Backend\Commands\Ci4msSetup;
use Modules\Install\Controllers\Install;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Regression suite for F7 (installer environment + captcha bypass flag) and
 * its GHSA-x7rh-7w4r-xf6m / GHSA-wpxm-393r-vvx9 hardening follow-up.
 *
 * F7 baseline: both the web installer (Modules\Install\Controllers\Install)
 * and the CLI installer (Modules\Backend\Commands\Ci4msSetup) used to write
 * `CI_ENVIRONMENT=development` into the generated .env unconditionally, and
 * the login/comment-form captcha bypass was gated solely on
 * `ENVIRONMENT === 'development'`.
 *
 * GHSA hardening follow-up (this class's second revision): both installers
 * now resolve the operator's chosen environment through a fail-safe
 * allowlist -- anything other than an exact 'production'/'development' match
 * (including a missing field, an out-of-list string, or an injection-shaped
 * value) resolves to 'production'. Install.php additionally validates the
 * posted `environment` field with `required|in_list[production,development]`
 * (Install.php:43) and returns *before* the .env-writing block
 * (Install.php:47,58-98) whenever validation fails. The two tests that used
 * to assert a hardcoded `'CI_ENVIRONMENT' => 'production'` string went red
 * the moment the literal became an allowlist ternary/pass-through; this
 * revision replaces them with behavioral coverage of the allowlist itself
 * (K3/K4 below) instead of weakening or deleting them.
 *
 * Placement note: F7 test infrastructure was added under
 * tests/Modules/Backend/ rather than tests/Modules/Install/ -- .gitignore's
 * tests/Modules/Backend/ whitelist block already existed (Ci4msSetup.php
 * itself lives in Modules\Backend\Commands), so covering both installers
 * here avoids adding a brand-new tests/Modules/Install/ whitelist block for
 * a single-purpose regression file. See context.md for the full rationale.
 *
 * K3 (Install.php) is driven directly through the controller, bypassing
 * routing/filters entirely: this checkout already has a real .env and
 * writable/install.lock on disk, so InstallFilter::before()
 * (modules/Install/Filters/InstallFilter.php:22-30) 404s any HTTP request to
 * /install before Install::index() is ever reached -- FeatureTestTrait::post()
 * cannot exercise this controller in this environment. Instead the controller
 * is instantiated directly and given a request/response pair via Reflection,
 * mirroring tests/Modules/Settings/UpdateRollbackControllerTest.php::dispatch().
 * This bypasses InstallFilter, the global Ci4ms filter and CSRF -- deliberately,
 * since what is under test is Install::index()'s own logic (validate(), the
 * allowlist, the early return), not the routing/filter layer in front of it.
 * None of the K3 tests ever supply an environment value that survives
 * validate() while also driving the controller end to end: the allowlist
 * positive control (`testAllowlistedEnvironmentValuesPassValidationInIsolation`)
 * exercises the real Validation service directly instead of the controller, so
 * dbsetup()/copyEnvFile()/updateEnvSettings() -- and the migration they would
 * trigger -- are never reached anywhere in this file. See
 * .ci4ms/knowledge/pitfalls.md -- `php spark migrate` (and any transitive
 * migration path) must never run against a live-DB-adjacent environment from
 * an agent session.
 *
 * @internal
 */
final class InstallerHardeningTest extends CIUnitTestCase
{
    /** Arbitrary shared value for both the install_nonce cookie and POST field. */
    private const INSTALL_NONCE = 'installer-hardening-test-nonce';

    protected function setUp(): void
    {
        parent::setUp();

        // Install::index() calls Controller::validate(), which resolves the
        // process-shared Config\Services::validation() singleton (getShared
        // = true). CodeIgniter\Validation\Validation::run() never clears
        // $this->errors on its own -- only its own reset() does. If an
        // earlier test in the same PHPUnit process left that singleton with
        // stale errors, dispatchInstall()/testAllowlistedEnvironment... below
        // would fail for reasons unrelated to the 'environment' field under
        // test. See .ci4ms/knowledge/pitfalls.md, "Paylaşımlı validation
        // servisi testler arası hata sızdırıyor".
        Services::resetSingle('validation');
    }

    // -----------------------------------------------------------------
    // K3 -- Modules\Install\Controllers\Install
    // -----------------------------------------------------------------

    /**
     * An out-of-allowlist `environment` value must be rejected by
     * `in_list[production,development]` (Install.php:43) and must never
     * reach the .env-writing block: Install.php:47's early `return` sits
     * structurally before the $updates array (:58) and copyEnvFile()/
     * updateEnvSettings() (:98), so within the same function invocation a
     * failed validate() makes reaching those lines impossible. The .env
     * mtime check below is a passive, read-only secondary alarm for that
     * same guarantee.
     */
    public function testInvalidEnvironmentValueIsRejectedAndEnvFileIsUntouched(): void
    {
        $envPath = ROOTPATH . '.env';
        $before  = filemtime($envPath);
        $this->assertNotFalse($before);

        $result = $this->dispatchInstall(array_merge($this->validInstallPost(), [
            'environment' => 'malicious-value',
        ]));

        $this->assertTrue($result->hasHeader('Location'), 'index() must return a redirect when validate() fails.');

        $errors = session()->getFlashdata('errors');
        $this->assertIsArray($errors);
        $this->assertArrayHasKey(
            'environment',
            $errors,
            'in_list[production,development] must reject an out-of-allowlist value.',
        );

        clearstatcache(true, $envPath);
        $this->assertSame(
            $before,
            filemtime($envPath),
            '.env must not be touched when validate() fails.',
        );
    }

    /**
     * A missing `environment` field must be rejected by the `required` rule,
     * for the same reasons and with the same .env-untouched guarantee as
     * above.
     */
    public function testMissingEnvironmentFieldIsRejectedByRequiredRule(): void
    {
        $envPath = ROOTPATH . '.env';
        $before  = filemtime($envPath);
        $this->assertNotFalse($before);

        $post = $this->validInstallPost();
        unset($post['environment']);

        $result = $this->dispatchInstall($post);

        $this->assertTrue($result->hasHeader('Location'), 'index() must return a redirect when validate() fails.');

        $errors = session()->getFlashdata('errors');
        $this->assertIsArray($errors);
        $this->assertArrayHasKey(
            'environment',
            $errors,
            'the required rule must reject a missing environment field.',
        );

        clearstatcache(true, $envPath);
        $this->assertSame($before, filemtime($envPath), '.env must not be touched when validate() fails.');
    }

    /**
     * Positive control: 'production' and 'development' must both pass the
     * `environment` rule. This deliberately never drives the controller --
     * it runs the real Validation service directly against a byte-for-byte
     * copy of Install.php:28-44's $valData (see installValData() below), so
     * dbsetup() and the migration it would trigger are never reached.
     *
     * @dataProvider allowlistedEnvironmentProvider
     */
    public function testAllowlistedEnvironmentValuesPassValidationInIsolation(string $environment): void
    {
        $post                 = $this->validInstallPost();
        $post['environment']  = $environment;

        $validation = Services::validation();
        $validation->setRules($this->installValData());
        $validation->run($post);

        $this->assertArrayNotHasKey(
            'environment',
            $validation->getErrors(),
            "in_list[production,development] must accept '{$environment}'.",
        );
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function allowlistedEnvironmentProvider(): iterable
    {
        yield 'production' => ['production'];
        yield 'development' => ['development'];
    }

    // -----------------------------------------------------------------
    // K4 -- Modules\Backend\Commands\Ci4msSetup
    // -----------------------------------------------------------------

    /**
     * `resolveEnvironmentChoice()` is pure (no I/O, no side effects), so it
     * is invoked directly via Reflection on an uninstantiated-constructor
     * instance -- the same technique this project already uses for private
     * command methods (see Ci4msMigrateCommandTest::
     * testRecordRunViaReflectionWritesFailureAuditRow()). The matrix proves
     * the fail-safe allowlist in both directions: exact 'production'/
     * 'development' matches (case/whitespace-normalized) pass through, and
     * every other input -- including an injection-shaped string -- falls
     * back to 'production'. No input other than an exact 'development' match
     * may ever produce 'development'.
     *
     * @dataProvider environmentChoiceProvider
     */
    public function testResolveEnvironmentChoiceIsAFailSafeAllowlist(string $raw, string $expected): void
    {
        $command = (new ReflectionClass(Ci4msSetup::class))->newInstanceWithoutConstructor();
        $method  = new ReflectionMethod(Ci4msSetup::class, 'resolveEnvironmentChoice');
        $method->setAccessible(true);

        $this->assertSame($expected, $method->invoke($command, $raw));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function environmentChoiceProvider(): iterable
    {
        yield 'empty string (blank Enter) -> production' => ['', 'production'];
        yield 'production -> production' => ['production', 'production'];
        yield 'development -> development' => ['development', 'development'];
        yield 'mixed case Development -> development' => ['Development', 'development'];
        yield 'whitespace-padded DEVELOPMENT -> development' => [' DEVELOPMENT ', 'development'];
        yield 'arbitrary string -> production' => ['anything-else', 'production'];
        yield 'injection-shaped string -> production' => ['DROP TABLE', 'production'];
    }

    /**
     * Structural invariant, not a changing-literal regex (contrast with the
     * two assertions this class replaced, which pinned the literal
     * 'CI_ENVIRONMENT' => 'production' string and broke the moment that
     * became an allowlist expression): the 'development' branch must still
     * pair with a red CLI::write() warning. This is deliberately narrow --
     * it only proves the pairing exists in source, not that CLI::write()
     * fires at runtime through run(); see the class-level "Doğrulanamayanlar"
     * note in the QA report for why the interactive path cannot be exercised
     * here (CLI::prompt()'s STDIN cannot be mocked in this environment --
     * vendor/codeigniter4/framework/system/Test/StreamFilterTrait.php only
     * intercepts STDOUT/STDERR -- and calling run() end to end would write
     * .env and trigger migration/seed, which this suite must not do).
     */
    public function testCliSetupWarnsOnlyWhenResolvedEnvironmentIsDevelopment(): void
    {
        $path = ROOTPATH . 'modules/Backend/Commands/Ci4msSetup.php';
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $this->assertMatchesRegularExpression(
            '/if \(\$environment === \'development\'\) \{.*?CLI::write\(.*?\'red\'\)/s',
            $content,
            "The 'development' branch must still pair with a red CLI::write() warning.",
        );
    }

    // -----------------------------------------------------------------
    // K5 -- development-mode banner condition (modules/Backend/Views/base.php)
    // -----------------------------------------------------------------

    /**
     * ENVIRONMENT is a process-wide PHP constant, already fixed to 'testing'
     * for this entire PHPUnit run (the same pre-existing constraint applies
     * to BaseController.php:157's `ENVIRONMENT === 'production'` check) --
     * there is no way to render base.php in this process under
     * ENVIRONMENT === 'development' or 'production' to exercise the real
     * banner condition. Instead the exact condition expression is extracted
     * (not retyped/guessed) from base.php via regex and evaluated in an
     * isolated `php -r` subprocess, once per ENVIRONMENT x superadmin-group
     * combination, with a stub $logged_in_user standing in for
     * inGroup('superadmin'). No migration, DB, or .env access is involved.
     */
    public function testDevelopmentBannerShowsOnlyForDevelopmentEnvironmentAndSuperadminGroup(): void
    {
        $path = ROOTPATH . 'modules/Backend/Views/base.php';
        $this->assertFileExists($path);
        $content = (string) file_get_contents($path);

        $matched = preg_match(
            '/if \((ENVIRONMENT.*?inGroup\(\'superadmin\'\))\)/s',
            $content,
            $matches,
        );
        $this->assertSame(1, $matched, 'Could not locate the development-mode banner condition in base.php.');
        $condition = $matches[1];

        $this->assertTrue(
            $this->evaluateBannerCondition($condition, 'development', true),
            'development env + superadmin group must show the banner.',
        );
        $this->assertFalse(
            $this->evaluateBannerCondition($condition, 'development', false),
            'development env + non-superadmin group must not show the banner.',
        );
        $this->assertFalse(
            $this->evaluateBannerCondition($condition, 'production', true),
            'production env + superadmin group must not show the banner.',
        );
        $this->assertFalse(
            $this->evaluateBannerCondition($condition, 'production', false),
            'production env + non-superadmin group must not show the banner.',
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

    // -----------------------------------------------------------------
    // .env permission hardening (re-audit F2 follow-up)
    // -----------------------------------------------------------------

    /**
     * Install::restrictEnvPermissions() hardcodes `ROOTPATH . '.env'`, so it
     * cannot be invoked directly via Reflection without chmod-ing the real
     * `.env` this checkout is running on -- forbidden (see class docblock's
     * .ci4ms/knowledge/pitfalls.md reference and the dispatch task's "GERÇEK
     * .env'e asla yazma/chmod'lama" constraint). Instead the method's exact
     * body is extracted fresh from source (never retyped/guessed) via
     * extractMethodBody() and executed against a disposable scratch `.env`
     * under a fake ROOTPATH, in an isolated `php -r` subprocess -- the same
     * source-extraction technique evaluateBannerCondition() already uses
     * above, generalized to a full method body instead of a single
     * expression.
     */
    public function testInstallControllerRestrictEnvPermissionsChmods0600InIsolatedSubprocess(): void
    {
        $body = $this->extractMethodBody(
            ROOTPATH . 'modules/Install/Controllers/Install.php',
            'private function restrictEnvPermissions(): void',
        );

        $scratch = $this->makeScratchDir();
        $envPath = $scratch . '.env';
        file_put_contents($envPath, "CI_ENVIRONMENT=production\n");
        chmod($envPath, 0644);

        try {
            $script = 'define("ROOTPATH", ' . var_export($scratch, true) . ');' . PHP_EOL
                . 'function log_message($level, $message) {}' . PHP_EOL
                . $body . PHP_EOL
                . 'echo sprintf("%o", fileperms(ROOTPATH . ".env") & 0777);';

            $result = $this->runIsolatedPhp($script);

            $this->assertSame(0, $result['exitCode'], "Subprocess must exit cleanly. Output: {$result['output']}");
            $this->assertSame('600', trim($result['output']), 'restrictEnvPermissions() must narrow .env to 0600.');
        } finally {
            $this->removeScratchDir($scratch);
        }
    }

    /**
     * Fail-safe: a chmod() failure (here simulated by pointing ROOTPATH at a
     * directory that was never created, so the target .env does not exist)
     * must not throw or fatal -- restrictEnvPermissions() is called from
     * Install::index() only inside a best-effort `if (...) { ... }` block
     * that is not allowed to turn an otherwise-successful install into a
     * failed one. The subprocess reaching its own trailing echo with exit
     * code 0 is the proof; the docblock on restrictEnvPermissions() itself
     * documents this as deliberate (return type void, no throw).
     */
    public function testInstallControllerRestrictEnvPermissionsDoesNotFatalWhenEnvFileIsMissing(): void
    {
        $body = $this->extractMethodBody(
            ROOTPATH . 'modules/Install/Controllers/Install.php',
            'private function restrictEnvPermissions(): void',
        );

        $scratch     = $this->makeScratchDir();
        $missingRoot = $scratch . 'nonexistent/'; // deliberately never created

        try {
            $script = 'define("ROOTPATH", ' . var_export($missingRoot, true) . ');' . PHP_EOL
                . 'function log_message($level, $message) {}' . PHP_EOL
                . $body . PHP_EOL
                . 'echo "SUBPROCESS_COMPLETED";';

            $result = $this->runIsolatedPhp($script);

            $this->assertSame(
                0,
                $result['exitCode'],
                "A missing target .env must not turn into a fatal error. Output: {$result['output']}",
            );
            $this->assertStringContainsString('SUBPROCESS_COMPLETED', $result['output']);
        } finally {
            $this->removeScratchDir($scratch);
        }
    }

    /**
     * Same technique as the Install.php pair above, for
     * Ci4msSetup::restrictEnvPermissions() (Ci4msSetup.php:577-595), which
     * additionally touches WRITEPATH (for filegroup()) and CLI::write() --
     * this subprocess therefore also requires the real Composer autoloader
     * and CodeIgniter\CLI\CLI's own dependency, system/Common.php (for
     * is_cli()/is_windows(), guarded by function_exists() so it is safe to
     * require standalone without a full framework bootstrap), plus an
     * ENVIRONMENT constant (CLI::hasColorSupport() -> streamSupports()
     * reads it). WRITEPATH is pointed at the same real, disposable scratch
     * directory as ROOTPATH -- filegroup() needs an existing directory to
     * read a gid from, and reusing one keeps this test to a single temp
     * tree; the real project's writable/ is never touched. `CLI::` calls in
     * the extracted body are rewritten to the fully-qualified
     * `\CodeIgniter\CLI\CLI::` since the isolated script has no `use
     * CodeIgniter\CLI\CLI;` import (Install.php's own `use` statement does
     * not carry into a `php -r` subprocess).
     */
    public function testCi4msSetupRestrictEnvPermissionsChmods0640InIsolatedSubprocess(): void
    {
        $body = $this->extractMethodBody(
            ROOTPATH . 'modules/Backend/Commands/Ci4msSetup.php',
            'private function restrictEnvPermissions(): void',
        );
        $body = str_replace('CLI::', '\\CodeIgniter\\CLI\\CLI::', $body);

        $scratch = $this->makeScratchDir();
        $envPath = $scratch . '.env';
        file_put_contents($envPath, "CI_ENVIRONMENT=production\n");
        chmod($envPath, 0644);

        try {
            $script = 'define("ENVIRONMENT", "testing");' . PHP_EOL
                . 'require ' . var_export(ROOTPATH . 'vendor/autoload.php', true) . ';' . PHP_EOL
                . 'require ' . var_export(ROOTPATH . 'vendor/codeigniter4/framework/system/Common.php', true) . ';' . PHP_EOL
                . 'define("ROOTPATH", ' . var_export($scratch, true) . ');' . PHP_EOL
                . 'define("WRITEPATH", ' . var_export($scratch, true) . ');' . PHP_EOL
                . $body . PHP_EOL
                . 'echo "MODE_MARKER:" . sprintf("%o", fileperms(ROOTPATH . ".env") & 0777);';

            $result = $this->runIsolatedPhp($script);

            $this->assertSame(0, $result['exitCode'], "Subprocess must exit cleanly. Output: {$result['output']}");
            $this->assertStringContainsString(
                'MODE_MARKER:640',
                $result['output'],
                'restrictEnvPermissions() must narrow .env to 0640. The chgrp() call may be a no-op on this machine ' .
                '(WRITEPATH and .env can already share a group) -- that is expected and not asserted here; only that ' .
                'chgrp() does not error and chmod(0640) takes effect.',
            );
        } finally {
            $this->removeScratchDir($scratch);
        }
    }

    /**
     * Fail-safe counterpart to the success case above: ROOTPATH points at a
     * directory that was never created (so the target .env cannot exist),
     * while WRITEPATH stays real so filegroup() itself succeeds -- this
     * isolates the failure to chgrp()/chmod() against a missing file, the
     * same shape of failure the docblock on
     * Ci4msSetup::restrictEnvPermissions() calls out (open_basedir,
     * read-only filesystem, etc.). Both branches degrade to a `CLI::write()`
     * warning, never a thrown exception, so the subprocess must still reach
     * its own trailing echo with exit code 0.
     */
    public function testCi4msSetupRestrictEnvPermissionsDoesNotFatalWhenEnvFileIsMissing(): void
    {
        $body = $this->extractMethodBody(
            ROOTPATH . 'modules/Backend/Commands/Ci4msSetup.php',
            'private function restrictEnvPermissions(): void',
        );
        $body = str_replace('CLI::', '\\CodeIgniter\\CLI\\CLI::', $body);

        $scratch     = $this->makeScratchDir();
        $missingRoot = $scratch . 'nonexistent/'; // deliberately never created

        try {
            $script = 'define("ENVIRONMENT", "testing");' . PHP_EOL
                . 'require ' . var_export(ROOTPATH . 'vendor/autoload.php', true) . ';' . PHP_EOL
                . 'require ' . var_export(ROOTPATH . 'vendor/codeigniter4/framework/system/Common.php', true) . ';' . PHP_EOL
                . 'define("ROOTPATH", ' . var_export($missingRoot, true) . ');' . PHP_EOL
                . 'define("WRITEPATH", ' . var_export($scratch, true) . ');' . PHP_EOL
                . $body . PHP_EOL
                . 'echo "SUBPROCESS_COMPLETED";';

            $result = $this->runIsolatedPhp($script);

            $this->assertSame(
                0,
                $result['exitCode'],
                "A missing target .env must not turn into a fatal error. Output: {$result['output']}",
            );
            $this->assertStringContainsString('SUBPROCESS_COMPLETED', $result['output']);
        } finally {
            $this->removeScratchDir($scratch);
        }
    }

    // -----------------------------------------------------------------
    // Install.php:60 environment fail-safe gate regression (re-audit F1 follow-up)
    // -----------------------------------------------------------------

    /**
     * Behavioral regression for re-audit finding F1: `Controller::validate()`
     * resolves CodeIgniter's `Validation::withRequest()` -> `getVar()`, which
     * reads the merged `$_REQUEST` (GET+POST+COOKIE), while Install.php:58
     * reads `$_POST` only via `getPost('environment')`. Those two sources are
     * independent -- if `environment=production` is sent exclusively as a
     * query-string parameter, `validate()` (Install.php:47) PASSES because
     * `in_list[production,development]` sees it through `$_REQUEST`, while
     * `getPost('environment')` (Install.php:58) returns exactly `null`. The
     * `null` case in the data provider below is that GET/POST-desync
     * scenario's direct equivalent, not a synthetic edge case -- the exact
     * value `getPost()` returns when the field only travelled over GET.
     * Install.php:60's `in_array(..., true)` ternary is the only gate
     * standing between that desync and an empty `CI_ENVIRONMENT=` line
     * landing in `.env` (see updateEnvSettings(), Install.php:144-158, which
     * writes `(string) $updates['CI_ENVIRONMENT']` verbatim with no further
     * validation) -- `(string) null === ''`, which breaks the framework on
     * next boot. See the class-level "Doğrulanamayanlar" note in the QA
     * report for why the full HTTP GET/POST-desync path itself cannot be
     * exercised end to end through dispatchInstall() (doing so would require
     * validate() to pass, which routes into dbsetup() and a real migration
     * call -- forbidden by .ci4ms/knowledge/pitfalls.md).
     *
     * The ternary expression itself is extracted fresh from Install.php on
     * every run (never retyped/guessed) via extractCiEnvironmentExpression()
     * and evaluated in an isolated `php -r` subprocess against each
     * candidate `$environment` value -- no ROOTPATH/ENVIRONMENT constant is
     * needed since the expression only touches a local `$environment`
     * variable, unlike the .env-hardening subprocess tests above.
     *
     * @dataProvider ciEnvironmentInputProvider
     */
    public function testCiEnvironmentResolutionIsAFailSafeAllowlist(mixed $environment, string $expected): void
    {
        $expression = $this->extractCiEnvironmentExpression();

        $script = '$environment = ' . var_export($environment, true) . ';' . PHP_EOL
            . 'echo (' . $expression . ');';

        $result = $this->runIsolatedPhp($script);

        $this->assertSame(0, $result['exitCode'], "Subprocess must exit cleanly. Output: {$result['output']}");
        $this->assertSame($expected, trim($result['output']));
    }

    /**
     * @return iterable<string, array{0: mixed, 1: string}>
     */
    public static function ciEnvironmentInputProvider(): iterable
    {
        yield 'production -> production' => ['production', 'production'];
        yield 'development -> development' => ['development', 'development'];
        yield 'null (GET/POST desync: getPost() returns null) -> production' => [null, 'production'];
        yield 'empty string -> production' => ['', 'production'];
        yield 'injection-shaped string -> production' => ['DROP TABLE', 'production'];
        yield 'array (getPost() can theoretically return an array) -> production' => [['production'], 'production'];
        yield 'case mismatch Production -> production' => ['Production', 'production'];
        yield 'whitespace-padded  production  -> production' => [' production ', 'production'];
    }

    // -----------------------------------------------------------------
    // environment=development confirm-checkbox gate (Kalem A dispatch
    // follow-up: Install.php:78-91)
    // -----------------------------------------------------------------

    /**
     * `development` (in any case/whitespace variant that
     * normalizeEnvironmentInput() folds to the exact string 'development')
     * must be rejected solely on the new `environmentDevelopmentConfirm`
     * field (Install.php:89-91) when the confirm checkbox was not submitted
     * -- validInstallPost() never includes that key, so it is deliberately
     * absent here, not merely unset. No other field may fail: every other
     * value in validInstallPost() is valid, and `environment` itself must
     * NOT error (normalizeEnvironmentInput() already folded it to a value
     * `in_list[production,development]` accepts). This never drives
     * validate() to a full pass -- the confirm rule always fires -- so
     * dbsetup() is never reached, same guarantee as the class docblock's K3
     * note.
     *
     * @dataProvider developmentWithoutConfirmProvider
     */
    public function testDevelopmentEnvironmentWithoutConfirmIsRejectedOnlyOnConfirmField(string $environmentInput): void
    {
        $post                 = $this->validInstallPost();
        $post['environment']  = $environmentInput;

        $result = $this->dispatchInstall($post);

        $this->assertTrue($result->hasHeader('Location'), 'index() must return a redirect when validate() fails.');

        $errors = session()->getFlashdata('errors');
        $this->assertIsArray($errors);
        $this->assertSame(
            ['environmentDevelopmentConfirm'],
            array_keys($errors),
            'A missing confirm checkbox must be the ONLY validation error -- not baseUrl/host/environment/etc.',
        );
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function developmentWithoutConfirmProvider(): iterable
    {
        yield 'exact lowercase development' => ['development'];
        yield 'uppercase DEVELOPMENT (proves normalization feeds Kalem A\'s condition)' => ['DEVELOPMENT'];
        yield 'whitespace-padded  development ' => [' development '];
    }

    /**
     * Byte-for-byte mirror of Install.php:89-91's conditional rule addition,
     * layered on top of installValData() (itself a byte-for-byte copy of
     * Install.php:59-75/93). Used ONLY to drive the real Validation service
     * directly (never the controller), so the confirm-gate's positive path
     * (development + confirm=1 -> passes) can be exercised without ever
     * routing through validate() === true in the controller -- which would
     * fall into dbsetup()/migration, forbidden by
     * .ci4ms/knowledge/pitfalls.md.
     *
     * @param array<string, mixed> $post
     *
     * @return array<string, array{label: string|list<string>, rules: string}>
     */
    private function installValDataWithConditionalConfirm(array $post): array
    {
        $valData = $this->installValData();
        if (($post['environment'] ?? null) === 'development') {
            $valData['environmentDevelopmentConfirm'] = ['label' => lang('Install.environmentDevelopmentConfirm'), 'rules' => 'required'];
        }

        return $valData;
    }

    /**
     * Positive control for the confirm gate, plus the two negative shapes
     * that must NOT add or fail the rule: `production` never gets the rule
     * added at all (Install.php:89's condition itself is false), and
     * `development` with confirm=1 passes it outright.
     *
     * @dataProvider developmentConfirmGateProvider
     */
    public function testEnvironmentDevelopmentConfirmGateBehavesCorrectlyInIsolation(
        string $environment,
        ?string $confirm,
        bool $expectConfirmError,
    ): void {
        $post                 = $this->validInstallPost();
        $post['environment']  = $environment;
        if ($confirm !== null) {
            $post['environmentDevelopmentConfirm'] = $confirm;
        }

        $validation = Services::validation();
        $validation->setRules($this->installValDataWithConditionalConfirm($post));
        $validation->run($post);

        if ($expectConfirmError) {
            $this->assertArrayHasKey('environmentDevelopmentConfirm', $validation->getErrors());
        } else {
            $this->assertArrayNotHasKey('environmentDevelopmentConfirm', $validation->getErrors());
        }
        $this->assertArrayNotHasKey('environment', $validation->getErrors());
    }

    /**
     * @return iterable<string, array{0: string, 1: string|null, 2: bool}>
     */
    public static function developmentConfirmGateProvider(): iterable
    {
        yield 'development + confirm=1 -> no error' => ['development', '1', false];
        yield 'development + confirm missing -> error' => ['development', null, true];
        yield 'production + confirm missing -> no error, rule never added' => ['production', null, false];
    }

    // -----------------------------------------------------------------
    // install.php view: confirm checkbox visible without JS (Kalem A view)
    // -----------------------------------------------------------------

    /**
     * With JavaScript disabled, toggleEnvironmentConfirm() (install.php's
     * inline <script> block) never runs, so the confirm checkbox must be
     * visible in the raw server-rendered HTML regardless of which radio is
     * selected -- otherwise an operator who picks "development" with JS off
     * can never see, let alone tick, the required confirm box. The regex
     * below deliberately captures ONLY the wrapping div's own opening tag
     * (up to its first '>'), not the whole file: a naive substring search
     * for "display:none"/"hidden" would also match the unrelated
     * .hide()/.show() calls in the JS block below it.
     */
    public function testDevelopmentConfirmCheckboxIsVisibleInMarkupWithoutJavascript(): void
    {
        $html = (string) view('Modules\Install\Views\install', ['installNonce' => self::INSTALL_NONCE]);

        $matched = preg_match(
            '/<div class="form-group" id="environment-development-confirm-wrap"[^>]*>/',
            $html,
            $matches,
        );
        $this->assertSame(1, $matched, 'Could not locate the environment-development-confirm-wrap div opening tag.');
        $openingTag = $matches[0];

        $this->assertDoesNotMatchRegularExpression(
            '/display\s*:\s*none/i',
            $openingTag,
            'The confirm-wrap div must not carry an inline display:none -- JS-disabled operators must see it.',
        );
        $this->assertDoesNotMatchRegularExpression(
            '/\bhidden\b/i',
            $openingTag,
            'The confirm-wrap div must not carry a "hidden" attribute/class.',
        );

        $this->assertStringContainsString(
            'id="environmentDevelopmentConfirm"',
            $html,
            'The confirm checkbox input itself must be present in the rendered markup.',
        );
    }

    // -----------------------------------------------------------------
    // normalizeEnvironmentInput() pure function + glue integration (Kalem B)
    // -----------------------------------------------------------------

    /**
     * normalizeEnvironmentInput() is pure (no I/O, no side effects), invoked
     * directly via Reflection -- same technique as
     * testResolveEnvironmentChoiceIsAFailSafeAllowlist() above for the CLI
     * installer's sibling function. It only normalizes case/whitespace; it
     * does NOT enforce the allowlist -- the last case proves that by passing
     * an out-of-allowlist string through unchanged.
     *
     * @dataProvider normalizeEnvironmentInputProvider
     */
    public function testNormalizeEnvironmentInputIsAPureCaseAndWhitespaceNormalizer(string $raw, string $expected): void
    {
        $controller = (new ReflectionClass(Install::class))->newInstanceWithoutConstructor();
        $method     = new ReflectionMethod(Install::class, 'normalizeEnvironmentInput');
        $method->setAccessible(true);

        $this->assertSame($expected, $method->invoke($controller, $raw));
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function normalizeEnvironmentInputProvider(): iterable
    {
        yield 'uppercase DEVELOPMENT -> development' => ['DEVELOPMENT', 'development'];
        yield 'whitespace-padded  development  -> development' => ['  development  ', 'development'];
        yield 'already-lowercase production -> production' => ['production', 'production'];
        yield 'out-of-allowlist string passes through unfiltered (no allowlist here)' => ['Anything-Else', 'anything-else'];
    }

    /**
     * Glue proof tying the Reflection-tested pure function above to the
     * controller's actual runtime path in a single assertion: sending
     * `environment=DEVELOPMENT` (mixed case) and omitting the confirm
     * checkbox must fail on `environmentDevelopmentConfirm` alone. That
     * single outcome is only possible if BOTH (a) normalizeEnvironmentInput()'s
     * output reached `in_list[production,development]` and passed it (else
     * `environment` itself would also error) AND (b) that same normalized
     * value reached Install.php:89's `=== 'development'` comparison (else the
     * confirm rule would never have been added and validate() would pass
     * outright, routing into dbsetup() -- which none of these tests may do).
     */
    public function testMixedCaseDevelopmentNormalizesBeforeReachingBothGates(): void
    {
        $post                = $this->validInstallPost();
        $post['environment'] = 'DEVELOPMENT';

        $result = $this->dispatchInstall($post);

        $this->assertTrue($result->hasHeader('Location'), 'index() must return a redirect when validate() fails.');

        $errors = session()->getFlashdata('errors');
        $this->assertIsArray($errors);
        $this->assertSame(
            ['environmentDevelopmentConfirm'],
            array_keys($errors),
            'Mixed-case "DEVELOPMENT" must normalize to a value that (a) passes in_list and (b) satisfies Kalem A\'s === \'development\' check.',
        );
    }

    // -----------------------------------------------------------------
    // Install.php .env write ordering gate: generateEncryptionKey()/
    // restrictEnvPermissions() (Kalem C, Install.php:144-168)
    // -----------------------------------------------------------------

    /**
     * Extracts the exact copyEnvFile()/updateEnvSettings()/
     * generateEncryptionKey()/restrictEnvPermissions() gate from Install.php
     * via the fixed regex the QA dispatch specifies -- fresh on every run,
     * never retyped/guessed. Generalizes extractMethodBody()'s
     * source-extraction technique to a non-method code fragment.
     */
    private function extractInstallEnvWriteGate(): string
    {
        $path    = ROOTPATH . 'modules/Install/Controllers/Install.php';
        $content = (string) file_get_contents($path);

        $matched = preg_match(
            '/if \(\$this->copyEnvFile\(\) === true && \$this->updateEnvSettings\(\$updates\) === true\) \{.*?\}\s*\$this->restrictEnvPermissions\(\);/s',
            $content,
            $matches,
        );
        $this->assertSame(
            1,
            $matched,
            'Could not locate the copyEnvFile()/updateEnvSettings()/generateEncryptionKey()/restrictEnvPermissions() gate in Install.php.',
        );

        return $matches[0];
    }

    /**
     * The extracted gate snippet is spliced verbatim into a throwaway stub
     * class's method body in an isolated `php -r` subprocess -- the stub's
     * copyEnvFile()/updateEnvSettings() return the scenario's fixture value,
     * and generateEncryptionKey()/restrictEnvPermissions() record whether
     * they were invoked. This proves Kalem C's `=== true` strict-comparison
     * fix behaviorally (a truthy-but-non-`true` `['error' => ...]` array must
     * NOT satisfy the gate) without ever touching the real .env, copyEnvFile()
     * or updateEnvSettings() implementations.
     *
     * @dataProvider envWriteGateProvider
     */
    public function testEnvWriteGateOnlyGeneratesEncryptionKeyWhenBothWritesStrictlySucceed(
        mixed $copyEnvFileResult,
        mixed $updateEnvSettingsResult,
        bool $expectGenerateEncryptionKeyCalled,
    ): void {
        $gate = $this->extractInstallEnvWriteGate();

        $template = <<<'PHP'
class Ci4msEnvWriteGateStub
{
    public bool $generateEncryptionKeyCalled = false;
    public bool $restrictEnvPermissionsCalled = false;
    private $copyEnvFileResult;
    private $updateEnvSettingsResult;

    public function __construct($copyEnvFileResult, $updateEnvSettingsResult)
    {
        $this->copyEnvFileResult = $copyEnvFileResult;
        $this->updateEnvSettingsResult = $updateEnvSettingsResult;
    }

    private function copyEnvFile() { return $this->copyEnvFileResult; }
    private function updateEnvSettings($updates) { return $this->updateEnvSettingsResult; }
    private function generateEncryptionKey() { $this->generateEncryptionKeyCalled = true; }
    private function restrictEnvPermissions() { $this->restrictEnvPermissionsCalled = true; }

    public function run()
    {
        $updates = [];
        __GATE__
    }
}
PHP;
        $script = str_replace('__GATE__', $gate, $template)
            . PHP_EOL . '$stub = new Ci4msEnvWriteGateStub(' . var_export($copyEnvFileResult, true) . ', ' . var_export($updateEnvSettingsResult, true) . ');'
            . PHP_EOL . '$stub->run();'
            . PHP_EOL . 'echo json_encode(["generateEncryptionKeyCalled" => $stub->generateEncryptionKeyCalled, "restrictEnvPermissionsCalled" => $stub->restrictEnvPermissionsCalled]);';

        $result = $this->runIsolatedPhp($script);

        $this->assertSame(0, $result['exitCode'], "Subprocess must exit cleanly. Output: {$result['output']}");
        $decoded = json_decode($result['output'], true);
        $this->assertIsArray($decoded, "Subprocess did not emit valid JSON. Output: {$result['output']}");
        $this->assertSame($expectGenerateEncryptionKeyCalled, $decoded['generateEncryptionKeyCalled']);
        $this->assertTrue(
            $decoded['restrictEnvPermissionsCalled'],
            'restrictEnvPermissions() must run unconditionally, regardless of the write outcome.',
        );
    }

    /**
     * @return iterable<string, array{0: mixed, 1: mixed, 2: bool}>
     */
    public static function envWriteGateProvider(): iterable
    {
        yield 'both writes succeed -> key generated' => [true, true, true];
        yield 'copyEnvFile() fails (truthy array) -> key not generated' => [['error' => 'x'], true, false];
        yield 'updateEnvSettings() fails (truthy array) -> key not generated' => [true, ['error' => 'x'], false];
    }

    // -----------------------------------------------------------------
    // installer app.version literal drift regression (Kalem D)
    // -----------------------------------------------------------------

    /**
     * Regression for Kalem D's app.version bump: both installers' literal
     * `'app.version' => '...'` write must always match the latest PUBLISHED
     * CHANGELOG.md release heading.
     *
     * CHANGELOG.md -- not the live `.env` -- is the single source of truth
     * this test compares against, for three reasons: (1) CHANGELOG.md is
     * committed and therefore present on every checkout, unlike this
     * checkout's gitignored `.env`; (2) it is the file
     * `/ci4ms:ci4ms-bump-version` already updates as part of a release, so it
     * stays current without inventing a new process; and (3), decisively,
     * `.env`'s `app.version` value is itself WRITTEN by these same two
     * installers' literals (Install.php:142, Ci4msSetup.php:273) -- using it
     * as the reference would be circular and could never catch the exact
     * drift class this test exists for (one installer's literal falling out
     * of sync with the other, or with the latest release).
     */
    public function testInstallerAppVersionLiteralsMatchLatestPublishedChangelogRelease(): void
    {
        $changelog = (string) file_get_contents(ROOTPATH . 'CHANGELOG.md');
        $matched   = preg_match('/^## \[(\d+\.\d+\.\d+\.\d+)\]/m', $changelog, $m);
        $this->assertSame(1, $matched, 'Could not find a published version heading in CHANGELOG.md.');
        $latest = $m[1];

        $installContent = (string) file_get_contents(ROOTPATH . 'modules/Install/Controllers/Install.php');
        preg_match("/'app\.version'\s*=>\s*'([\d.]+)'/", $installContent, $mi);
        $this->assertSame($latest, $mi[1] ?? null, "Install.php's app.version literal drifted from CHANGELOG.md's latest published release.");

        $setupContent = (string) file_get_contents(ROOTPATH . 'modules/Backend/Commands/Ci4msSetup.php');
        preg_match("/'app\.version'\s*=>\s*'([\d.]+)'/", $setupContent, $ms);
        $this->assertSame($latest, $ms[1] ?? null, "Ci4msSetup.php's app.version literal drifted from CHANGELOG.md's latest published release.");
    }

    // -----------------------------------------------------------------
    // shared helpers for the two new sections above (source extraction +
    // isolated-subprocess execution)
    // -----------------------------------------------------------------

    /**
     * Extracts a method's body (opening/closing braces excluded) from a PHP
     * source file via brace-depth counting rather than a line-anchored
     * regex: Ci4msSetup::restrictEnvPermissions() contains nested if/else
     * blocks, so a naive non-greedy `\{(.*?)\}` would stop at the first
     * inner closing brace instead of the method's own.
     */
    private function extractMethodBody(string $path, string $signature): string
    {
        $content = (string) file_get_contents($path);
        $sigPos  = strpos($content, $signature);
        $this->assertNotFalse($sigPos, "Could not locate '{$signature}' in {$path}.");

        $bracePos = strpos($content, '{', $sigPos);
        $this->assertNotFalse($bracePos, "Could not locate the opening brace for '{$signature}' in {$path}.");

        $length = strlen($content);
        $depth  = 0;
        $pos    = $bracePos;
        do {
            if ($content[$pos] === '{') {
                $depth++;
            } elseif ($content[$pos] === '}') {
                $depth--;
            }
            $pos++;
        } while ($depth > 0 && $pos < $length);

        $this->assertSame(0, $depth, "Unbalanced braces while extracting '{$signature}' body from {$path}.");

        return substr($content, $bracePos + 1, $pos - 1 - ($bracePos + 1));
    }

    /**
     * Extracts the `'CI_ENVIRONMENT' => ...` value expression from
     * Install.php's $updates array (Install.php:60) via regex -- fresh on
     * every call, never retyped/guessed.
     */
    private function extractCiEnvironmentExpression(): string
    {
        $path    = ROOTPATH . 'modules/Install/Controllers/Install.php';
        $content = (string) file_get_contents($path);

        $matched = preg_match("/'CI_ENVIRONMENT'\s*=>\s*(.+?),\r?\n/", $content, $matches);
        $this->assertSame(1, $matched, 'Could not locate the CI_ENVIRONMENT resolution expression in Install.php.');

        return $matches[1];
    }

    /**
     * Runs a standalone PHP snippet via `php -r` in a fresh subprocess and
     * returns both its stdout and exit code. stderr is dropped for the same
     * reason as evaluateBannerCondition() below (this environment's php CLI
     * prints Xdebug connection-attempt noise to stderr on every invocation).
     *
     * @return array{output: string, exitCode: int}
     */
    private function runIsolatedPhp(string $script): array
    {
        $command = escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>/dev/null';
        exec($command, $outputLines, $exitCode);

        return ['output' => implode(PHP_EOL, $outputLines), 'exitCode' => $exitCode];
    }

    /**
     * Creates a fresh, disposable scratch directory under
     * sys_get_temp_dir() to stand in for ROOTPATH/WRITEPATH in the isolated
     * subprocess tests above -- never the real project ROOTPATH, so the
     * real .env can never be touched by this file.
     */
    private function makeScratchDir(): string
    {
        $dir = sys_get_temp_dir() . '/ci4ms-envtest-' . bin2hex(random_bytes(8)) . '/';
        $this->assertTrue(mkdir($dir, 0777, true), "Could not create scratch dir {$dir}.");

        return $dir;
    }

    /**
     * Recursively removes a scratch directory created by makeScratchDir().
     */
    private function removeScratchDir(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) ?: [] as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . $item;
            if (is_dir($path)) {
                $this->removeScratchDir($path . '/');
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }

    // -----------------------------------------------------------------
    // shared helpers
    // -----------------------------------------------------------------

    /**
     * A full set of Install::index() POST fields that pass every rule,
     * including a matching install_nonce cookie/POST pair so the
     * pre-validation nonce check (Install.php:22-26) never short-circuits
     * before validate() runs -- that check is a plain hash_equals()
     * equality, not a format check, so any identical arbitrary string works.
     * Callers override/unset 'environment' as needed.
     *
     * @return array<string, string>
     */
    private function validInstallPost(): array
    {
        return [
            'install_nonce' => self::INSTALL_NONCE,
            'baseUrl'       => 'https://example.test',
            'host'          => 'localhost',
            'dbname'        => 'ci4ms_install_test',
            'dbusername'    => 'ci4ms_install_test',
            'dbpassword'    => '',
            'dbdriver'      => 'MySQLi',
            'dbpre'         => 'ci4ms_',
            'dbport'        => '3306',
            'name'          => 'Test',
            'surname'       => 'User',
            'username'      => 'testadmin',
            'password'      => 'Sup3rSecret!',
            'email'         => 'test@example.test',
            'siteName'      => 'Test Site',
            'environment'   => 'production',
        ];
    }

    /**
     * Byte-for-byte copy of Install.php:28-44's $valData. Must be kept in
     * sync with that array by hand if Install.php's rules ever change --
     * used ONLY to drive the Validation service directly (never the
     * controller), so the allowlist's permissive side can be proven without
     * ever risking a dbsetup()/migration call.
     *
     * @return array<string, array{label: string|list<string>, rules: string}>
     */
    private function installValData(): array
    {
        return [
            'baseUrl' => ['label' => lang('Install.baseUrl'), 'rules' => 'required|valid_url'],
            'host' => ['label' => lang('Install.databaseHost'), 'rules' => 'required|max_length[255]|regex_match[/^[a-zA-Z0-9._-]+$/]'],
            'dbname' => ['label' => lang('Install.databaseName'), 'rules' => 'required|alpha_dash|max_length[100]'],
            'dbusername' => ['label' => lang('Install.databaseUsername'), 'rules' => 'required|alpha_dash|max_length[100]'],
            'dbpassword' => ['label' => lang('Install.databasePassword'), 'rules' => 'permit_empty|max_length[255]|regex_match[/^[^\r\n]*$/]'],
            'dbdriver' => ['label' => lang('Install.databaseDriver'), 'rules' => 'required|in_list[MySQLi]'],
            'dbpre' => ['label' => lang('Install.databasePrefix'), 'rules' => 'permit_empty|alpha_dash|max_length[20]'],
            'dbport' => ['label' => lang('Install.databasePort'), 'rules' => 'required|is_natural_no_zero|less_than[65536]'],
            'name' => ['label' => lang('Install.firstName'), 'rules' => 'required|max_length[100]|regex_match[/^[^<>{}=]+$/u]'],
            'surname' => ['label' => lang('Install.lastName'), 'rules' => 'required|max_length[100]|regex_match[/^[^<>{}=]+$/u]'],
            'username' => ['label' => lang('Backend.username'), 'rules' => 'required|alpha_numeric|min_length[3]|max_length[50]'],
            'password' => ['label' => lang('Install.password'), 'rules' => 'required|min_length[8]'],
            'email' => ['label' => lang('Install.email'), 'rules' => 'required|valid_email|max_length[255]'],
            'siteName' => ['label' => lang('Backend.siteName'), 'rules' => 'required|alpha_numeric_space|max_length[255]|regex_match[/^[^<>{}=]+$/u]'],
            'environment' => ['label' => lang('Install.environment'), 'rules' => 'required|in_list[production,development]'],
        ];
    }

    /**
     * Drives Install::index() directly, bypassing InstallFilter/the global
     * Ci4ms filter/CSRF/routing entirely (route dispatch 404s in this
     * already-installed checkout -- see class docblock). Mirrors the pattern
     * in tests/Modules/Settings/UpdateRollbackControllerTest.php::dispatch().
     *
     * @param array<string, string> $post
     */
    private function dispatchInstall(array $post): ResponseInterface
    {
        $config  = config(App::class);
        $request = new IncomingRequest($config, new SiteURI($config), null, new UserAgent());
        // Request::getMethod() returns the stored value verbatim (no
        // uppercasing despite its docblock claim) while
        // IncomingRequest::is() uppercases only its own argument before
        // comparing -- withMethod() must therefore be given 'POST', not
        // 'post', or Install::index()'s `$this->request->is('post')` guard
        // silently evaluates to false and the GET branch (which returns a
        // rendered view string, not a ResponseInterface) runs instead.
        $request = $request->withMethod('POST');
        // getPost() reads the 'post' bucket, but Validation::withRequest()
        // reads getVar() -> fetchGlobal('request', ...) -- a *separate*
        // bucket that setGlobal('post', ...) does not populate. Both must be
        // set or every rule in $this->validate() fails as "required" even
        // though the field was submitted (same fix as
        // tests/Modules/Users/PermgroupMalformedPermsPayloadTest.php's
        // primeRequest()).
        $request->setGlobal('post', $post);
        $request->setGlobal('request', $post);
        $request->setGlobal('cookie', ['install_nonce' => self::INSTALL_NONCE]);

        $controller = new Install();

        (new ReflectionProperty(\CodeIgniter\Controller::class, 'request'))->setValue($controller, $request);
        (new ReflectionProperty(\CodeIgniter\Controller::class, 'response'))->setValue($controller, service('response', $config, false));

        $result = $controller->index();

        $this->assertInstanceOf(ResponseInterface::class, $result);

        return $result;
    }

    /**
     * Evaluates the exact banner condition extracted from base.php in an
     * isolated PHP subprocess (ENVIRONMENT is otherwise immutable for the
     * current process). Uses a nowdoc template so the condition/placeholders
     * are substituted via str_replace rather than PHP string interpolation
     * -- the condition text itself contains `$logged_in_user`, which must
     * reach the subprocess script literally, not be interpolated here.
     */
    private function evaluateBannerCondition(string $condition, string $environment, bool $isSuperadmin): bool
    {
        $template = <<<'PHP'
define("ENVIRONMENT", __ENVIRONMENT__);
class Ci4msTestGroupGate {
    private $isSuperadmin;
    public function __construct($isSuperadmin) { $this->isSuperadmin = $isSuperadmin; }
    public function inGroup($name) { return $name === 'superadmin' && $this->isSuperadmin; }
}
$logged_in_user = new Ci4msTestGroupGate(__IS_SUPERADMIN__);
var_dump((bool) (__CONDITION__));
PHP;

        $script = str_replace(
            ['__ENVIRONMENT__', '__IS_SUPERADMIN__', '__CONDITION__'],
            [var_export($environment, true), $isSuperadmin ? 'true' : 'false', $condition],
            $template,
        );

        // stderr is dropped, not merged: this environment's php CLI prints
        // Xdebug connection-attempt noise to stderr on every invocation,
        // which would otherwise corrupt the exact-match comparison below.
        $output = shell_exec(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg($script) . ' 2>/dev/null');
        $this->assertNotNull($output, 'php -r subprocess produced no output.');

        return trim((string) $output) === 'bool(true)';
    }
}
