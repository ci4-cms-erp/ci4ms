<?php

declare(strict_types=1);

namespace Tests\Modules\Auth;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Validation\ValidationRules;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Modules\Auth\Controllers\LoginController;

/**
 * Regression lock for the login captcha bypass.
 *
 * Before the fix, `LoginController::loginAction()` compared
 * `getPost('captcha') == getFlashdata('cap')`. The expected value is written
 * only by `loginView()` (GET) and lives for one request, so a client that
 * never GETs /backend/login has `cap` = null; omitting the captcha field
 * makes `getPost('captcha')` null too, and `null == null` is true. The
 * session CSRF token is not bound to the login page, so it can be taken from
 * any other page first.
 *
 * Two independent gates now close it, and each is locked separately:
 * - `getValidationRules()` makes `captcha` `required` (rejection shows up as
 *   the `errors` flash, never as `error`).
 * - `loginAction()` is fail-closed (`$expected` must be non-empty) and uses
 *   `hash_equals()` instead of `==` (rejection shows up as the `error` flash
 *   `Auth.badCaptcha`).
 *
 * Telling the two apart is what makes each mutation observable:
 * - removing only the rule: scenario (a)/(b) get `error` instead of `errors`.
 * - restoring only the loose `==`: `testNumericStringCaptchaIsNotLooselyEqual`
 *   (`'1e1' == '10'`) logs in, and `testControllerFailsClosedWhenValidationIsBypassed`
 *   (null == null, validation bypassed) logs in.
 *
 * The development bypass depends on the `ENVIRONMENT` constant, which
 * `system/Test/bootstrap.php` hard-defines as `testing` and which cannot be
 * redefined in-process. Those scenarios run in a child PHPUnit process whose
 * bootstrap defines `development` first (see `runInDevelopmentChild()`).
 *
 * @internal
 */
final class LoginCaptchaRegressionTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $refresh = false;

    protected $migrateOnce = true;

    protected $namespace = null;

    private const ROUTE = 'backend/login';

    private const CAPTCHA = 'test-captcha-token';

    private const KNOWN_PASSWORD = 'SuperSecret123!';

    private const CHILD_ENV = 'CI4MS_CAPTCHA_DEV_CHILD';

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Refusing to run: the active connection is not pointed at the test database.',
        );

        service('routes')->loadRoutes();

        // The shared `auth` service keeps the previous test's logged-in user.
        \Config\Services::resetSingle('auth');

        // auth-rates (10 req/60s per IP, `file` cache) outlives the process.
        cache()->clean();
        // The throttler binds the cache instance that existed when it was first built; a
        // suite-wide earlier test can leave it pointing at a different (still-filling) store.
        \Config\Services::resetSingle('throttler');

        $this->db->transStart();
    }

    protected function tearDown(): void
    {
        setting()->forget('Auth.captchaBypassInDevelopment');
        $this->db->transRollback();
        cache()->clean();

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Core scenarios (ENVIRONMENT === 'testing')
    // ---------------------------------------------------------------

    /**
     * (a) The original exploit: valid session + CSRF, no `cap` flashdata,
     * captcha field absent, valid credentials.
     */
    public function testPostWithoutCaptchaFieldIsRejectedByValidation(): void
    {
        $email = $this->createActiveUser('cap_a');

        $response = $this->postLogin($email, self::KNOWN_PASSWORD, false, null);

        $this->assertValidationRejectedCaptcha($response);
        $this->assertNotLoggedIn();
    }

    /**
     * (b) No `cap` flashdata, captcha present but empty.
     */
    public function testEmptyCaptchaIsRejectedByValidation(): void
    {
        $email = $this->createActiveUser('cap_b');

        $response = $this->postLogin($email, self::KNOWN_PASSWORD, false, '');

        $this->assertValidationRejectedCaptcha($response);
        $this->assertNotLoggedIn();
    }

    /**
     * (c) No `cap` flashdata (the client never GET the login page), but a
     * plausible captcha value: validation passes, the fail-closed comparison
     * must still say no.
     */
    public function testPlausibleCaptchaWithoutExpectedFlashdataIsRejected(): void
    {
        $email = $this->createActiveUser('cap_c');

        $response = $this->postLogin($email, self::KNOWN_PASSWORD, false, 'abc123');

        $this->assertBadCaptcha($response);
        $this->assertNotLoggedIn();
    }

    /**
     * (d) Happy path: right captcha, right credentials. Also the plumbing
     * guard that keeps (a)-(c), (e) from passing for the wrong reason.
     */
    public function testCorrectCaptchaAndCredentialsLogIn(): void
    {
        $email = $this->createActiveUser('cap_d');

        $response = $this->postLogin($email, self::KNOWN_PASSWORD, true, self::CAPTCHA);

        $response->assertRedirect();
        $this->assertSame(
            strtolower((string) config('Auth')->loginRedirect()),
            strtolower((string) $response->getRedirectUrl()),
            'A successful login must redirect to Auth::loginRedirect().',
        );
        $this->assertArrayNotHasKey('error', $_SESSION);
        $this->assertArrayNotHasKey('errors', $_SESSION);
        $this->assertLoggedIn();
    }

    /**
     * (e) Expected captcha exists, the given one is wrong.
     */
    public function testWrongCaptchaWithExpectedFlashdataIsRejected(): void
    {
        $email = $this->createActiveUser('cap_e');

        $response = $this->postLogin($email, self::KNOWN_PASSWORD, true, 'definitely-wrong');

        $this->assertBadCaptcha($response);
        $this->assertNotLoggedIn();
    }

    /**
     * `==` juggles numeric strings: `'1e1' == '10'` is true. A captcha phrase
     * must be compared byte for byte (`hash_equals`).
     */
    public function testNumericStringCaptchaIsNotLooselyEqual(): void
    {
        $email = $this->createActiveUser('cap_loose');

        $this->assertTrue('1e1' == '10', 'Precondition: PHP loose comparison treats these as equal.');

        $response = $this->postLogin($email, self::KNOWN_PASSWORD, true, '10', '1e1');

        $this->assertBadCaptcha($response);
        $this->assertNotLoggedIn();
    }

    /**
     * The development bypass needs BOTH the setting and ENVIRONMENT ===
     * 'development'. Under `testing` the setting alone must do nothing.
     */
    public function testBypassSettingAloneDoesNothingOutsideDevelopment(): void
    {
        $this->assertSame('testing', ENVIRONMENT, 'Precondition: this process runs as testing.');

        $email = $this->createActiveUser('cap_nodev');
        setting()->set('Auth.captchaBypassInDevelopment', true);
        $this->assertTrue((bool) setting('Auth.captchaBypassInDevelopment'), 'Precondition: bypass setting is on.');

        $response = $this->postLogin($email, self::KNOWN_PASSWORD, false, 'x');

        $this->assertBadCaptcha($response);
        $this->assertNotLoggedIn();
    }

    /**
     * Second gate on its own: validation is bypassed on purpose (a subclass
     * that returns Shield's login rules only, i.e. the pre-fix rule set), so
     * `getPost('captcha')` and `getFlashdata('cap')` are both null. Only the
     * fail-closed comparison stands between this request and a login.
     */
    public function testControllerFailsClosedWhenValidationIsBypassed(): void
    {
        $email = $this->createActiveUser('cap_direct');

        $_SESSION = [];
        $request  = new IncomingRequest(config('App'), new URI('http://example.com/backend/login'), null, new UserAgent());
        $request->setGlobal('post', ['email' => $email, 'password' => self::KNOWN_PASSWORD]);

        $controller = new class () extends LoginController {
            protected function getValidationRules(): array
            {
                return (new ValidationRules())->getLoginRules();
            }
        };
        $controller->initController($request, service('response'), service('logger'));

        $this->assertNull(service('session')->getFlashdata('cap'), 'Precondition: no expected captcha.');
        $this->assertNull($request->getPost('captcha'), 'Precondition: no captcha submitted.');

        $response = $controller->loginAction();

        $this->assertSame(lang('Auth.badCaptcha'), $_SESSION['error'] ?? null);
        $this->assertArrayNotHasKey('errors', $_SESSION);
        $this->assertStringEndsWith(self::ROUTE, strtolower((string) $response->getHeaderLine('Location')));
        $this->assertNotLoggedIn();
    }

    // ---------------------------------------------------------------
    // Development bypass (child process, ENVIRONMENT === 'development')
    // ---------------------------------------------------------------

    /**
     * Parent-side driver: reruns this class's `testDev*` methods in a child
     * PHPUnit process that has ENVIRONMENT defined as `development`.
     */
    public function testBypassScenariosPassUnderDevelopmentEnvironment(): void
    {
        if (getenv(self::CHILD_ENV) === '1') {
            $this->markTestSkipped('Child process: driver not applicable.');
        }

        [$exit, $output] = $this->runInDevelopmentChild();

        $this->assertSame(0, $exit, "Development child process failed:\n" . $output);
        $this->assertMatchesRegularExpression('/OK \(4 tests?, /', $output, "Child must have really executed the 4 dev scenarios:\n" . $output);
    }

    /**
     * (f) Bypass on + development + wrong captcha + no expected -> login.
     */
    public function testDevBypassOnLetsWrongCaptchaThrough(): void
    {
        $this->requireDevelopmentChild();

        $email = $this->createActiveUser('cap_f');
        setting()->set('Auth.captchaBypassInDevelopment', true);

        $response = $this->postLogin($email, self::KNOWN_PASSWORD, false, 'x');

        $response->assertRedirect();
        $this->assertArrayNotHasKey('error', $_SESSION);
        $this->assertLoggedIn();
    }

    /**
     * OPEN RISK (documented, not endorsed): `captcha` is `required`, so even
     * with the bypass on in development a POST WITHOUT the field is refused
     * by validation. Whether "login without captcha in development" must
     * work for clients that omit the field is the lead's call (acceptance
     * criterion 4).
     */
    public function testDevBypassOnStillRequiresCaptchaFieldPresence(): void
    {
        $this->requireDevelopmentChild();

        $email = $this->createActiveUser('cap_f2');
        setting()->set('Auth.captchaBypassInDevelopment', true);

        $response = $this->postLogin($email, self::KNOWN_PASSWORD, false, null);

        $this->assertValidationRejectedCaptcha($response);
        $this->assertNotLoggedIn();
    }

    /**
     * Bypass off in development: nothing is bypassed, wrong captcha fails.
     */
    public function testDevBypassOffStillRejectsWrongCaptcha(): void
    {
        $this->requireDevelopmentChild();

        $email = $this->createActiveUser('cap_f3');
        setting()->set('Auth.captchaBypassInDevelopment', false);

        $response = $this->postLogin($email, self::KNOWN_PASSWORD, false, 'x');

        $this->assertBadCaptcha($response);
        $this->assertNotLoggedIn();
    }

    /**
     * Development, bypass off, correct captcha: the normal path still works.
     */
    public function testDevCorrectCaptchaStillLogsIn(): void
    {
        $this->requireDevelopmentChild();

        $email = $this->createActiveUser('cap_f4');

        $response = $this->postLogin($email, self::KNOWN_PASSWORD, true, self::CAPTCHA);

        $response->assertRedirect();
        $this->assertArrayNotHasKey('error', $_SESSION);
        $this->assertLoggedIn();
    }

    private function requireDevelopmentChild(): void
    {
        if (getenv(self::CHILD_ENV) !== '1') {
            $this->markTestSkipped('Runs only inside the development child process.');
        }

        $this->assertSame('development', ENVIRONMENT, 'Child bootstrap failed to define ENVIRONMENT=development.');

        // DevGateFilter puts every request behind HTTP Basic auth in development.
        $users = config('DevGate')->users;
        $this->assertNotEmpty($users, 'Precondition: DevGate has a user to authenticate as.');
        service('superglobals')->setServer('PHP_AUTH_USER', (string) array_key_first($users));
        service('superglobals')->setServer('PHP_AUTH_PW', (string) reset($users));
    }

    /**
     * @return array{0: int, 1: string}
     */
    private function runInDevelopmentChild(): array
    {
        $bootstrap = tempnam(sys_get_temp_dir(), 'ci4ms-dev-bootstrap-');
        $this->assertNotFalse($bootstrap);
        $real = $bootstrap . '.php';
        rename($bootstrap, $real);

        // system/Test/bootstrap.php unconditionally runs define('ENVIRONMENT', 'testing');
        // defining it first and swallowing that single redefinition warning is the only way
        // to get a different value without editing vendor/.
        file_put_contents($real, <<<'PHP'
            <?php
            define('ENVIRONMENT', 'development');
            // Database::$defaultGroup is 'tests' only when ENVIRONMENT === 'testing'; in
            // development the 'default' group is the LIVE database. Config reads $_ENV/$_SERVER
            // before .env (DotEnv never overwrites a non-empty value); setUp() asserts it again.
            $_SERVER['database.default.database'] = $_ENV['database.default.database'] = DB_PLACEHOLDER;
            set_error_handler(static fn (int $no, string $str): bool => str_contains($str, 'ENVIRONMENT'), E_WARNING);
            require ROOTPATH_PLACEHOLDER . '/vendor/codeigniter4/framework/system/Test/bootstrap.php';
            restore_error_handler();
            PHP);
        file_put_contents($real, strtr((string) file_get_contents($real), [
            'ROOTPATH_PLACEHOLDER' => var_export(rtrim(ROOTPATH, '/'), true),
            'DB_PLACEHOLDER'       => var_export((string) \Config\Database::connect()->database, true),
        ]));

        $cmd = [
            PHP_BINARY,
            rtrim(ROOTPATH, '/') . '/vendor/bin/phpunit',
            '--bootstrap', $real,
            '--no-logging',
            '--no-coverage',
            '--filter', '/::testDev/',
            __FILE__,
        ];

        $proc = proc_open(
            $cmd,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            rtrim(ROOTPATH, '/'),
            array_merge(getenv(), [self::CHILD_ENV => '1']),
        );
        $this->assertIsResource($proc);

        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exit = proc_close($proc);
        @unlink($real);

        return [$exit, $stdout . $stderr];
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Valid session + valid CSRF pair. `$withExpected` decides whether the
     * `cap` flashdata exists (it does only after a GET of the login page).
     * `$captcha === null` omits the field from the POST altogether.
     */
    private function postLogin(string $email, string $password, bool $withExpected, ?string $captcha, string $expected = self::CAPTCHA): TestResponse
    {
        $security  = \Config\Services::security();
        $tokenName = $security->getTokenName();
        $rawHash   = $security->generateHash();
        $token     = $security->getHash();

        $session = [$tokenName => $rawHash];
        if ($withExpected) {
            $session['cap']       = $expected;
            $session['__ci_vars'] = ['cap' => 'old'];
        }

        $post = [
            'email'    => $email,
            'password' => $password,
            $tokenName => $token,
        ];
        if ($captcha !== null) {
            $post['captcha'] = $captcha;
        }

        return $this->withSession($session)->post(self::ROUTE, $post);
    }

    private function assertValidationRejectedCaptcha(TestResponse $response): void
    {
        $response->assertRedirect();
        $this->assertArrayHasKey('errors', $_SESSION, 'Rejection must come from validation (errors flash).');
        $this->assertArrayHasKey('captcha', $_SESSION['errors'], 'The validation error must name the captcha field.');
        $this->assertArrayNotHasKey('error', $_SESSION, 'badCaptcha branch must not be what rejected this request.');
    }

    private function assertBadCaptcha(TestResponse $response): void
    {
        $response->assertRedirect();
        $this->assertSame(lang('Auth.badCaptcha'), $_SESSION['error'] ?? null);
        $this->assertArrayNotHasKey('errors', $_SESSION, 'Validation must have passed; rejection must come from the captcha comparison.');
        $this->assertSame(
            strtolower(site_url(self::ROUTE)),
            strtolower((string) $response->getRedirectUrl()),
            'A rejected captcha must send the user back to the login page.',
        );
    }

    private function sessionUserField(): string
    {
        return (string) setting('Auth.sessionConfig')['field'];
    }

    private function assertNotLoggedIn(): void
    {
        $this->assertEmpty($_SESSION[$this->sessionUserField()]['id'] ?? null, 'No user may be stored in the session.');
        $this->assertFalse(auth()->loggedIn(), 'auth()->loggedIn() must be false.');
    }

    private function assertLoggedIn(): void
    {
        $this->assertNotEmpty($_SESSION[$this->sessionUserField()]['id'] ?? null, 'The user must be stored in the session.');
        $this->assertTrue(auth()->loggedIn(), 'auth()->loggedIn() must be true.');
    }

    private function createActiveUser(string $label): string
    {
        $suffix = bin2hex(random_bytes(4));
        $email  = $label . '_' . $suffix . '@example.test';

        $users = auth()->getProvider();
        $users->save(new User([
            'firstname' => 'Test',
            'surname'   => 'User',
            'username'  => $label . '_' . $suffix,
            'email'     => $email,
            'password'  => self::KNOWN_PASSWORD,
            'active'    => 1,
        ]));

        return $email;
    }
}
