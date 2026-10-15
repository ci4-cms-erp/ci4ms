<?php

declare(strict_types=1);

namespace Tests\Modules\Auth;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;

/**
 * Regression lock for GHSA-ppvr-272j-ggvv (login user enumeration).
 *
 * `CodeIgniter\Shield\Authentication\Authenticators\Session` can fail a
 * login attempt for three distinct reasons:
 * - `check()` (vendor/codeigniter4/shield/src/Authentication/Authenticators/Session.php:353-357,
 *   :368-372) -- no matching account, or a malformed credentials array --
 *   `Auth.badAttempt`.
 * - `check()` (:379-383) -- the account exists but the password is wrong --
 *   `Auth.invalidPassword`.
 * - `attempt()` (:157-163) -- `check()` already succeeded (right password)
 *   but `$user->isBanned()` is true -- reason is
 *   `$user->getBanMessage() ?? lang('Auth.bannedUser')`. This branch runs
 *   strictly AFTER a successful password match; driving it with the WRONG
 *   password never reaches it and silently degenerates into the
 *   invalid-password case instead.
 *
 * The fix that closes the enumeration hole ("Seçenek A", see this task's
 * context.md) does not equalize `lang()` strings across namespaces anymore.
 * That approach -- former `app/Language/{locale}/Auth.php` App-namespace
 * override files, now deleted -- depended on CI4's `Language::requireFile()`
 * namespace-merge priority, which turned out to be fragile: deleting the
 * override let `vendor/codeigniter4/shield`'s own text leak back through
 * even though `modules/Auth/Language/{locale}/Auth.php` had the same key
 * equalized, because the merge order does not guarantee the module
 * namespace beats the vendor namespace.
 *
 * Instead, `LoginController::loginAction()`
 * (modules/Auth/Controllers/LoginController.php:88-98) now discards
 * `$result->reason()` entirely and prints one constant, unconditional key --
 * `lang('Auth.loginFailed')`, defined once per locale in
 * `modules/Auth/Language/{locale}/Auth.php` -- for every `!$result->isOK()`
 * outcome, regardless of which of the three reasons above produced it. The
 * real reason is still recorded, but only in the operator-facing log line
 * (`log_message('warning', ...)`, LoginController.php:92-95), never in the
 * HTTP response. The security property this test locks in therefore no
 * longer rests on `lang()` namespace-merge priority at all -- it rests on
 * the controller having exactly one unconditional branch for every login
 * failure.
 *
 * This class drives the real `backend/login` route end to end
 * (`FeatureTestTrait`) for all three failure reasons and asserts the
 * resulting HTTP responses are indistinguishable across every surface an
 * attacker could probe: status code, redirect target, response body
 * (compared by SHA-256 hash, not raw string), flash-data key set, session
 * key set, cookie name/flag set (never raw cookie/session/flash values for
 * those -- they legitimately vary request to request, e.g. CSRF hashes),
 * AND the raw `error` flash VALUE itself. That last one is the load-bearing
 * check: unlike a CSRF hash, `$_SESSION['error']` is fully deterministic
 * text under both the broken and fixed controller, so comparing it directly
 * is safe and is what actually distinguishes `Auth.badAttempt` ("check your
 * credentials") / `Auth.invalidPassword` ("check your password") / a ban
 * message from one another -- key-set-only comparison cannot, since all
 * three scenarios flash under the same `'error'` key regardless of which
 * branch produced them.
 *
 * DB group / CSRF / captcha / throttle plumbing all mirror already-proven
 * patterns elsewhere in this suite:
 * - DB group + transaction wrap: `CustomActivationRouteTest.php:35-46`.
 * - Session-based CSRF with `tokenRandomize=true` (raw hash goes into the
 *   session, the BREACH-encoded token goes into the POST body -- posting
 *   the raw hash directly throws `SecurityException` on the very first
 *   request): `MigrationManagerCsrfRotationRouteTest.php:123-155`.
 * - Real user fixture via Shield's own provider:
 *   `UserControllerPeerPasswordResetAuthzTest.php:230-246`.
 * - `auth-rates` throttle bucket persists across process invocations via the
 *   `file` cache handler; `cache()->clean()` in `setUp()` prevents a false
 *   429 on repeated runs (same technique as `CustomActivationRouteTest.php:48-55`).
 * - Captcha gate (`LoginController::loginAction()` line 73, out of scope for
 *   this fix): flashdata `'cap'` is satisfied directly via `withSession()`
 *   (`Session::getFlashdata()`, vendor/codeigniter4/framework/system/Session/Session.php:413-434,
 *   treats any non-int `__ci_vars['cap']` value as "still valid flash data").
 *
 * @internal
 */
final class LoginEnumerationRegressionTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $refresh = false;

    protected $migrateOnce = true;

    protected $namespace = null;

    private const ROUTE = 'backend/login';

    private const LOGIN_ROUTE_NAME = 'login';

    private const CAPTCHA_TOKEN = 'test-captcha-token';

    private const KNOWN_PASSWORD = 'SuperSecret123!';

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Refusing to run: the active connection is not pointed at the test database.',
        );

        service('routes')->loadRoutes();

        // auth-rates (10 req/60s per IP, `file` cache handler) persists
        // across separate PHPUnit process invocations, not just within one
        // -- without this, repeated runs of this file (e.g. the RED/GREEN
        // control experiment) trip a false 429.
        cache()->clean();

        $this->db->transStart();
    }

    protected function tearDown(): void
    {
        $this->db->transRollback();

        parent::tearDown();
    }

    /**
     * Plumbing sanity guard, not part of the GHSA assertion itself: proves
     * the CSRF/captcha harness built by `postLogin()` actually reaches
     * `Session::check()` with valid input and that a legitimate credential
     * pair really succeeds. Without this, a broken helper (wrong token
     * name, wrong captcha value, ...) could make every failure scenario
     * below pass identically for the wrong reason -- e.g. every POST
     * bouncing off CSRF before ever reaching `Session::check()`/`attempt()`
     * -- and the indistinguishability assertion would pass vacuously
     * without ever exercising `Auth.badAttempt`, `Auth.invalidPassword` or
     * the ban branch.
     */
    public function testValidCredentialsLogInSuccessfully(): void
    {
        $email = $this->createActiveUser('enum_happy');

        $response = $this->postLogin($email, self::KNOWN_PASSWORD);

        $response->assertRedirect();
        $response->assertSessionMissing('error');
        $this->assertNotSame(
            strtolower(site_url(self::LOGIN_ROUTE_NAME)),
            strtolower((string) $response->getRedirectUrl()),
            'A successful login must not redirect back to the login page.',
        );
    }

    /**
     * Standalone precondition proof, kept separate from the
     * indistinguishability assertion below (one behavior per test): the
     * SAME account + SAME correct password logs in successfully BEFORE
     * `Bannable::ban()` runs, and fails AFTER it. This is what guarantees
     * that any later failure attributed to "the account is banned" really
     * exercises `Session::attempt()`'s ban check
     * (vendor/codeigniter4/shield/src/Authentication/Authenticators/Session.php:157-163)
     * rather than a typo'd password or an inactive account -- both of which
     * would also make the login fail, but for an unrelated reason.
     */
    public function testBanBlocksLoginThatWouldOtherwiseSucceed(): void
    {
        $email = $this->createActiveUser('enum_ban_precondition');

        $beforeBan = $this->postLogin($email, self::KNOWN_PASSWORD);
        $beforeBan->assertRedirect();
        $beforeBan->assertSessionMissing('error');
        $this->assertNotSame(
            strtolower(site_url(self::LOGIN_ROUTE_NAME)),
            strtolower((string) $beforeBan->getRedirectUrl()),
            'Precondition failed: this account/password must log in successfully before banning it, otherwise banning it proves nothing.',
        );

        $users = auth()->getProvider();
        $user  = $users->findByCredentials(['email' => $email]);
        $this->assertNotNull($user, 'Precondition failed: the just-created user must be findable by credentials.');
        $user->ban('some distinctive test ban message');
        $this->assertTrue($user->isBanned(), 'Precondition failed: Bannable::ban() must flip status to banned.');

        $afterBan = $this->postLogin($email, self::KNOWN_PASSWORD);
        $afterBan->assertSessionHas('error');
        // NB: `site_url(self::LOGIN_ROUTE_NAME)` (used for the negative checks
        // elsewhere in this file) does NOT resolve the named route -- it treats
        // 'login' as a literal URI segment, which happens to still work for a
        // not-equal comparison but would be wrong here. `self::ROUTE` is the
        // real path the 'login' named route maps to (LoginController.php's
        // `redirect()->route('login')` target).
        $this->assertSame(
            strtolower(site_url(self::ROUTE)),
            strtolower((string) $afterBan->getRedirectUrl()),
            'A banned account must be redirected back to the login page, not logged in, even with the correct password.',
        );
    }

    /**
     * The actual GHSA-ppvr-272j-ggvv regression lock: a nonexistent account
     * (`Session::check()` lines 368-372, `Auth.badAttempt`), an existing
     * account with the wrong password (`check()` lines 379-383,
     * `Auth.invalidPassword`), and a banned account with the CORRECT
     * password (`attempt()` lines 157-163, ban message) must all produce
     * responses an attacker cannot tell apart from one another -- same
     * status code, same redirect target, same body hash, same flash-data
     * key set, same session key set, same cookie name/flag set. Tests run
     * tek-dil (`en`); this compares the three live responses to each other,
     * not against a hardcoded expected string.
     */
    public function testNonexistentAccountWrongPasswordAndBannedAccountAreIndistinguishable(): void
    {
        $existingEmail    = $this->createActiveUser('enum_existing');
        $bannedEmail      = $this->createBannedUser('enum_banned');
        $nonexistentEmail = 'nonexistent_' . bin2hex(random_bytes(6)) . '@example.test';

        $responseA    = $this->postLogin($nonexistentEmail, 'SomeUnrelatedPassword123!');
        $fingerprintA = $this->fingerprint($responseA);

        $responseB    = $this->postLogin($existingEmail, 'TotallyWrongPassword456!');
        $fingerprintB = $this->fingerprint($responseB);

        $responseC    = $this->postLogin($bannedEmail, self::KNOWN_PASSWORD);
        $fingerprintC = $this->fingerprint($responseC);

        $this->assertContains('error', $fingerprintA['flashKeys'], 'Nonexistent-account login must still set an "error" flash message.');
        $this->assertContains('error', $fingerprintB['flashKeys'], 'Wrong-password login must still set an "error" flash message.');
        $this->assertContains('error', $fingerprintC['flashKeys'], 'Banned-account login must still set an "error" flash message.');

        $this->assertFingerprintsMatch($fingerprintA, $fingerprintB, 'nonexistent account', 'wrong password');
        $this->assertFingerprintsMatch($fingerprintB, $fingerprintC, 'wrong password', 'banned account');
        $this->assertFingerprintsMatch($fingerprintA, $fingerprintC, 'nonexistent account', 'banned account');
    }

    /**
     * Drives a real `backend/login` POST through the full filter chain
     * (CSRF, captcha, throttle) with a fresh CSRF hash/token pair for every
     * call -- rotation is deliberately not tracked between scenarios, each
     * call gets its own independent `generateHash()`/`getHash()` pair. No
     * scenario in this file ever posts `remember`, so the remember-me
     * cookie must never appear in any captured fingerprint below; if it
     * ever does for exactly one scenario, that is a real finding, not a
     * flaky assertion.
     */
    private function postLogin(string $email, string $password): TestResponse
    {
        $security  = \Config\Services::security();
        $tokenName = $security->getTokenName();
        $rawHash   = $security->generateHash();
        $token     = $security->getHash();

        return $this->withSession([
            $tokenName  => $rawHash,
            'cap'       => self::CAPTCHA_TOKEN,
            '__ci_vars' => ['cap' => 'old'],
        ])->post(self::ROUTE, [
            'email'    => $email,
            'password' => $password,
            'captcha'  => self::CAPTCHA_TOKEN,
            $tokenName => $token,
        ]);
    }

    /**
     * Creates a real, active Shield user via the same provider the
     * controller itself uses (`auth()->getProvider()`), matching the
     * pattern in `CustomActivationRouteTest::createInactiveUser()`.
     */
    private function createActiveUser(string $label): string
    {
        $suffix = bin2hex(random_bytes(4));
        $email  = $label . '_' . $suffix . '@example.test';

        $users = auth()->getProvider();
        $user  = new User([
            'firstname' => 'Test',
            'surname'   => 'User',
            'username'  => $label . '_' . $suffix,
            'email'     => $email,
            'password'  => self::KNOWN_PASSWORD,
            'active'    => 1,
        ]);
        $users->save($user);

        return $email;
    }

    /**
     * Creates a real, active Shield user, PROVES the credential pair logs
     * in successfully, then bans the account via Shield's own
     * `Bannable::ban()` (vendor/codeigniter4/shield/src/Traits/Bannable.php:29-39,
     * sets `status='banned'` + `status_message` and persists through
     * `auth()->getProvider()->save()`).
     *
     * The pre-ban login attempt is not incidental: without it, a typo'd
     * password or an inactive account would make the post-ban login fail
     * for the wrong reason, and `testNonexistentAccountWrongPasswordAndBannedAccountAreIndistinguishable()`
     * would pass vacuously without ever exercising
     * `Session::attempt()`'s ban branch
     * (vendor/codeigniter4/shield/src/Authentication/Authenticators/Session.php:157-163).
     *
     * @return string The now-banned account's email.
     */
    private function createBannedUser(string $label): string
    {
        $email = $this->createActiveUser($label);

        $preBanResponse = $this->postLogin($email, self::KNOWN_PASSWORD);
        $preBanResponse->assertRedirect();
        $preBanResponse->assertSessionMissing('error');
        $this->assertNotSame(
            strtolower(site_url(self::LOGIN_ROUTE_NAME)),
            strtolower((string) $preBanResponse->getRedirectUrl()),
            'Precondition failed: this account/password must log in successfully before banning it -- otherwise the post-ban failure would prove nothing about the ban branch.',
        );

        $users = auth()->getProvider();
        $user  = $users->findByCredentials(['email' => $email]);
        $this->assertNotNull($user, 'Precondition failed: the just-created user must be findable by credentials.');
        $user->ban('some distinctive test ban message');
        $this->assertTrue($user->isBanned(), 'Precondition failed: Bannable::ban() must flip status to banned.');

        return $email;
    }

    /**
     * Captures every attacker-observable surface of a `backend/login`
     * response as a comparable fingerprint. Raw values that legitimately
     * vary per request (CSRF hashes, session ids, ...) are deliberately
     * excluded -- only key/flag SETS are captured, per this task's
     * context.md.
     *
     * @return array{
     *     status: int,
     *     redirect: string|null,
     *     bodyHash: string,
     *     flashKeys: list<string>,
     *     sessionKeys: list<string>,
     *     cookies: list<string>,
     *     errorMessage: string|null,
     * }
     */
    private function fingerprint(TestResponse $response): array
    {
        $flashKeys = session()->getFlashKeys();
        sort($flashKeys);

        $sessionKeys = array_keys($_SESSION);
        sort($sessionKeys);

        $cookies = [];
        foreach ($response->response()->getCookieStore()->display() as $cookie) {
            $cookies[] = $cookie->getName()
                . '|httponly=' . ($cookie->isHTTPOnly() ? '1' : '0')
                . '|secure=' . ($cookie->isSecure() ? '1' : '0')
                . '|samesite=' . $cookie->getSameSite();
        }
        sort($cookies);

        return [
            'status'       => $response->response()->getStatusCode(),
            'redirect'     => $response->getRedirectUrl(),
            'bodyHash'     => hash('sha256', (string) $response->response()->getBody()),
            'flashKeys'    => $flashKeys,
            'sessionKeys'  => $sessionKeys,
            'cookies'      => $cookies,
            'errorMessage' => $_SESSION['error'] ?? null,
        ];
    }

    /**
     * Asserts two `fingerprint()` captures are identical on every axis,
     * with a per-axis failure message naming both scenarios being compared.
     *
     * @param array{status: int, redirect: string|null, bodyHash: string, flashKeys: list<string>, sessionKeys: list<string>, cookies: list<string>, errorMessage: string|null} $expected
     * @param array{status: int, redirect: string|null, bodyHash: string, flashKeys: list<string>, sessionKeys: list<string>, cookies: list<string>, errorMessage: string|null} $actual
     */
    private function assertFingerprintsMatch(array $expected, array $actual, string $labelA, string $labelB): void
    {
        $this->assertSame(
            $expected['status'],
            $actual['status'],
            "GHSA-ppvr-272j-ggvv regression: status codes for {$labelA} and {$labelB} must be identical.",
        );
        $this->assertSame(
            $expected['redirect'],
            $actual['redirect'],
            "GHSA-ppvr-272j-ggvv regression: redirect targets for {$labelA} and {$labelB} must be identical.",
        );
        $this->assertSame(
            $expected['bodyHash'],
            $actual['bodyHash'],
            "GHSA-ppvr-272j-ggvv regression: response bodies for {$labelA} and {$labelB} must be identical.",
        );
        $this->assertSame(
            $expected['flashKeys'],
            $actual['flashKeys'],
            "GHSA-ppvr-272j-ggvv regression: flash-data key sets for {$labelA} and {$labelB} must be identical.",
        );
        $this->assertSame(
            $expected['sessionKeys'],
            $actual['sessionKeys'],
            "GHSA-ppvr-272j-ggvv regression: session key sets for {$labelA} and {$labelB} must be identical.",
        );
        $this->assertSame(
            $expected['cookies'],
            $actual['cookies'],
            "GHSA-ppvr-272j-ggvv regression: cookie name/flag sets for {$labelA} and {$labelB} must be identical -- if this fails, one scenario is issuing a cookie (e.g. remember-me) the others are not, which is a real finding, not a flaky test.",
        );
        $this->assertSame(
            $expected['errorMessage'],
            $actual['errorMessage'],
            "GHSA-ppvr-272j-ggvv regression: the login error message for {$labelA} and {$labelB} must be identical -- an attacker must not be able to tell whether the account exists, the password was wrong, or the account is banned from this text.",
        );
    }
}
