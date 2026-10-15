<?php

declare(strict_types=1);

namespace Tests\Modules\Auth;

use CodeIgniter\Email\Email;
use CodeIgniter\Exceptions\PageNotFoundException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use CodeIgniter\Test\TestResponse;
use Config\Services;

/**
 * Regression lock for GHSA-cwhg-7xgv-343q (magic-link user enumeration).
 *
 * `MagicLinkController::loginAction()` used to answer differently depending on
 * whether the account exists: unknown e-mail -> 302 + flash error echoing the
 * address, SMTP failure (reachable only for an existing account) -> 302 + flash
 * error echoing `$user->email`, success -> 200 + view. The status line alone
 * was an oracle, so changing a language string cannot close it. Both failure
 * branches now render `displayMessage()` exactly like success.
 *
 * The indistinguishability check compares status AND the SHA-256 of the full
 * body. A key-set-only comparison (the first design of the sibling
 * LoginEnumerationRegressionTest) misses a body-only difference, which is why
 * the body hash is the load-bearing axis here.
 *
 * Mail transport is replaced via `Services::injectMock('email', ...)`
 * (`emailer()` resolves `service('email')`), so no SMTP/sendmail is ever
 * touched and each branch (no-user / send fails / send succeeds) is driven
 * deterministically; the fake records whether `send()` ran, proving which
 * branch executed.
 *
 * Tests run in single-language mode (`getLocale()` is `en`); locale coverage is
 * done with the explicit `$locale` argument of `lang()`.
 *
 * @internal
 */
final class MagicLinkEnumerationRegressionTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $refresh = false;

    protected $migrateOnce = true;

    protected $namespace = null;

    private const POST_ROUTE = 'backend/login/magic-link';

    private const VERIFY_ROUTE = 'backend/login/verify-magic-link';

    private const LOCALES = ['ar', 'de', 'en', 'es', 'fr', 'hi', 'ja', 'pt', 'ru', 'tr', 'zh'];

    private const XSS = '<script>alert("x\'y")</script>';

    private const XSS_ESCAPED = '&lt;script&gt;alert(&quot;x&#039;y&quot;)&lt;/script&gt;';

    private const DEBUG_MARKER = 'FAKE-SMTP-DEBUG-MARKER';

    private array|float|int|string|null $originalUserAgent = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Refusing to run: the active connection is not pointed at the test database.',
        );

        $this->originalUserAgent = service('superglobals')->server('HTTP_USER_AGENT');

        service('routes')->loadRoutes();

        // auth-rates is 10 req/60s per IP. The shared Throttler keeps the cache
        // handler it was built with, which in a full run can be an earlier
        // test's MockCache rather than the current cache(), so cache()->clean()
        // alone does not reset the bucket (429 on a later class run). Rebuild the
        // throttler against the current cache and clean that too.
        cache()->clean();
        Services::resetSingle('throttler');

        $this->db->transStart();
    }

    protected function tearDown(): void
    {
        $this->db->transRollback();

        service('superglobals')->setServer('HTTP_USER_AGENT', (string) ($this->originalUserAgent ?? ''));
        if ($this->originalUserAgent === null) {
            unset($_SERVER['HTTP_USER_AGENT']);
        }

        Services::resetSingle('email');

        parent::tearDown();
    }

    /**
     * Plumbing guard: the harness (CSRF + mock mailer) reaches the controller
     * and the existing-account/successful-send path really executes.
     */
    public function testExistingAccountWithWorkingMailerRunsSendBranch(): void
    {
        $email  = $this->createActiveUser('ml_ok');
        $mailer = $this->injectMailer(true);

        $response = $this->postMagicLink($email);

        $this->assertSame(200, $response->response()->getStatusCode());
        $this->assertSame(1, $mailer->mlSendCalls, 'The success branch must call send() exactly once.');
        $this->assertSame([$email], $mailer->mlSentTo);
        $this->assertNotSame('', (string) $response->response()->getBody());
    }

    /**
     * The GHSA-cwhg-7xgv-343q lock: unknown account, existing account with a
     * failing mailer, and existing account with a working mailer must be
     * indistinguishable by status and by the SHA-256 of the whole body.
     */
    public function testUnknownAccountSmtpFailureAndSuccessAreIndistinguishable(): void
    {
        $existing = $this->createActiveUser('ml_existing');
        $unknown  = 'nonexistent_' . bin2hex(random_bytes(6)) . '@example.test';

        // The fingerprint MUST be taken right after each request: the response
        // object is a shared service, so a TestResponse read after a later
        // request would show that later request's body (first draft of this
        // test compared three identical snapshots and never failed).
        $mailer  = $this->injectMailer(true);
        $ok      = $this->fingerprint($this->postMagicLink($existing));
        $okCalls = $mailer->mlSendCalls;

        $mailer    = $this->injectMailer(false);
        $fail      = $this->fingerprint($this->postMagicLink($existing));
        $failCalls = $mailer->mlSendCalls;

        $mailer       = $this->injectMailer(true);
        $unknownFp    = $this->fingerprint($this->postMagicLink($unknown));
        $unknownCalls = $mailer->mlSendCalls;

        $this->assertSame(1, $okCalls, 'Success scenario must have executed send().');
        $this->assertSame(1, $failCalls, 'SMTP-failure scenario must have executed send() (and failed).');
        $this->assertSame(0, $unknownCalls, 'Unknown-account scenario must return before any mail is sent.');

        foreach (['success' => $ok, 'smtp failure' => $fail, 'unknown account' => $unknownFp] as $label => $fp) {
            $this->assertSame(200, $fp['status'], "{$label}: expected 200 (same as success).");
            $this->assertNotSame(hash('sha256', ''), $fp['bodyHash'], "{$label}: body must not be empty.");
        }

        $this->assertSame($ok['status'], $unknownFp['status'], 'status: unknown vs success');
        $this->assertSame($ok['status'], $fail['status'], 'status: smtp failure vs success');
        $this->assertSame($ok['bodyHash'], $unknownFp['bodyHash'], 'body sha256: unknown vs success');
        $this->assertSame($ok['bodyHash'], $fail['bodyHash'], 'body sha256: smtp failure vs success');
        $this->assertSame($ok['hasErrorFlash'], $unknownFp['hasErrorFlash'], 'error flash: unknown vs success');
        $this->assertSame($ok['hasErrorFlash'], $fail['hasErrorFlash'], 'error flash: smtp failure vs success');
    }

    /**
     * SMTP failure must not leak the address to the client and must be logged.
     */
    public function testSmtpFailureDoesNotReflectEmailAndIsLogged(): void
    {
        $email = $this->createActiveUser('ml_smtpfail');
        $this->injectMailer(false);

        $response = $this->postMagicLink($email);
        $body     = (string) $response->response()->getBody();

        $this->assertSame(200, $response->response()->getStatusCode());
        $this->assertNotSame('', $body);
        $this->assertStringNotContainsString($email, $body);
        $this->assertStringNotContainsString(esc($email), $body);
        $this->assertStringNotContainsString(rawurlencode($email), $body);
        $this->assertFalse(session()->has('error'), 'No flash error may carry the failure to the client.');
        $this->assertLogged('error', self::DEBUG_MARKER);
    }

    /**
     * An unknown address must not be echoed back either.
     */
    public function testUnknownAccountDoesNotReflectEmail(): void
    {
        $unknown = 'nonexistent_' . bin2hex(random_bytes(6)) . '@example.test';
        $this->injectMailer(true);

        $response = $this->postMagicLink($unknown);
        $body     = (string) $response->response()->getBody();

        $this->assertSame(200, $response->response()->getStatusCode());
        $this->assertNotSame('', $body);
        $this->assertStringNotContainsString($unknown, $body);
        $this->assertFalse(session()->has('error'));
    }

    public function testVerifyReturns404ForRobotUserAgent(): void
    {
        service('superglobals')->setServer('HTTP_USER_AGENT', 'Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)');

        // The test harness runs the app with returnResponse, so the framework's
        // 404 handler is bypassed and the exception surfaces; in production it
        // is rendered as an HTTP 404 (PageNotFoundException::getCode() === 404).
        try {
            $this->get(self::VERIFY_ROUTE . '?token=whatever');
            $this->fail('verify() must throw PageNotFoundException for a robot user agent.');
        } catch (PageNotFoundException $e) {
            $this->assertSame(404, $e->getCode());
        }
    }

    public function testVerifyDoesNotReturn404ForNormalUserAgent(): void
    {
        service('superglobals')->setServer('HTTP_USER_AGENT', 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36');

        $response = $this->get(self::VERIFY_ROUTE . '?token=whatever');

        $this->assertNotSame(404, $response->response()->getStatusCode());
        $response->assertRedirect();
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function flashKeyProvider(): iterable
    {
        yield 'message' => ['message'];
        yield 'warning' => ['warning'];
        yield 'error' => ['error'];
    }

    /**
     * @dataProvider flashKeyProvider
     */
    public function testMessageBlockEscapesScalarFlash(string $key): void
    {
        $_SESSION[$key] = self::XSS;

        $html = view('Modules\Auth\Views\_message_block');

        $this->assertStringContainsString(self::XSS_ESCAPED, $html);
        $this->assertStringNotContainsString('<script', $html);
        $this->assertStringNotContainsString(self::XSS, $html);
    }

    public function testMessageBlockEscapesErrorsArray(): void
    {
        $_SESSION['errors'] = ['first' => self::XSS, 'second' => self::XSS];

        $html = view('Modules\Auth\Views\_message_block');

        $this->assertSame(2, substr_count($html, self::XSS_ESCAPED));
        $this->assertStringNotContainsString('<script', $html);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function localeProvider(): iterable
    {
        foreach (self::LOCALES as $locale) {
            yield $locale => [$locale];
        }
    }

    /**
     * @dataProvider localeProvider
     */
    public function testNewKeyResolvesInEveryLocale(string $locale): void
    {
        $key  = 'Auth.magicLinkSentIfRegistered';
        $text = lang($key, [], $locale);

        $this->assertNotSame($key, $text, "{$locale}: key fell through to the raw key.");
        $this->assertNotSame('', trim($text));

        if ($locale !== 'en') {
            $this->assertNotSame(lang($key, [], 'en'), $text, "{$locale}: resolved to the English text (no translation).");
        }
    }

    public function testViewUsesTheGenericKeyNotCheckYourEmail(): void
    {
        $html = view('Modules\Auth\Views\magic_link_message');

        $this->assertStringContainsString(lang('Auth.magicLinkSentIfRegistered'), $html);
        $this->assertStringNotContainsString(lang('Auth.checkYourEmail'), $html);
    }

    private function injectMailer(bool $sendSucceeds): object
    {
        $mailer = new class ($sendSucceeds) extends Email {
            public int $mlSendCalls = 0;

            /** @var list<string> */
            public array $mlSentTo = [];

            public function __construct(private readonly bool $succeeds)
            {
            }

            public function initialize($config)
            {
                return $this;
            }

            public function setFrom($from, $name = '', $returnPath = null)
            {
                return $this;
            }

            public function setTo($to)
            {
                $this->mlSentTo[] = (string) $to;

                return $this;
            }

            public function setSubject($subject)
            {
                return $this;
            }

            public function setMessage($body)
            {
                return $this;
            }

            public function send($autoClear = true)
            {
                $this->mlSendCalls++;

                return $this->succeeds;
            }

            public function clear($clearAttachments = false)
            {
                return $this;
            }

            public function printDebugger($include = ['headers', 'subject', 'body'])
            {
                return 'FAKE-SMTP-DEBUG-MARKER';
            }
        };

        Services::injectMock('email', $mailer);

        return $mailer;
    }

    private function postMagicLink(string $email): TestResponse
    {
        $security  = Services::security();
        $tokenName = $security->getTokenName();
        $rawHash   = $security->generateHash();
        $token     = $security->getHash();

        return $this->withSession([
            $tokenName => $rawHash,
        ])->post(self::POST_ROUTE, [
            'email'    => $email,
            $tokenName => $token,
        ]);
    }

    private function createActiveUser(string $label): string
    {
        $suffix = bin2hex(random_bytes(4));
        $email  = $label . '_' . $suffix . '@example.test';

        auth()->getProvider()->save(new User([
            'firstname' => 'Test',
            'surname'   => 'User',
            'username'  => $label . '_' . $suffix,
            'email'     => $email,
            'password'  => 'SuperSecret123!',
            'active'    => 1,
        ]));

        return $email;
    }

    /**
     * @return array{status: int, bodyHash: string, hasErrorFlash: bool}
     */
    private function fingerprint(TestResponse $response): array
    {
        return [
            'status'        => $response->response()->getStatusCode(),
            'bodyHash'      => hash('sha256', (string) $response->response()->getBody()),
            'hasErrorFlash' => isset($_SESSION['error']),
        ];
    }
}
