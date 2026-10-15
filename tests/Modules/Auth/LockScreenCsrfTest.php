<?php

declare(strict_types=1);

namespace Tests\Modules\Auth;

use CodeIgniter\Config\Factories;
use CodeIgniter\Security\Exceptions\SecurityException;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Modules\Auth\Models\UserSessionModel;

/**
 * `POST /backend/lock` (unlock, 3 wrong attempts end the session) and
 * `POST /backend/lock/set` (locks the session) used to sit in
 * `AuthConfig::$csrfExcept`, leaving SameSite=Lax as the only defence.
 *
 * Removing the exemption is only safe if the multi-attempt unlock overlay
 * keeps working: `Security::$regenerate` is true, so every verified POST
 * burns the token, and the next attempt needs the fresh one that
 * `CsrfTokenRefreshFilter` (after-filter on `backend/*`) writes into the
 * `X-CSRF-TOKEN` response header of AJAX requests.
 *
 * @internal
 */
final class LockScreenCsrfTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $refresh = false;

    protected $migrateOnce = true;

    protected $namespace = null;

    private const PASSWORD = 'SuperSecret123!';

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Refusing to run: the active connection is not pointed at the test database.',
        );

        service('routes')->loadRoutes();
        cache()->clean();
        $this->db->transStart();
    }

    protected function tearDown(): void
    {
        $this->db->transRollback();
        Factories::reset('config');
        \Config\Services::resetSingle('auth');
        \Config\Services::resetSingle('security');
        \Config\Services::resetSingle('language');

        parent::tearDown();
    }

    public function testUnlockPostWithoutCsrfTokenIsRejected(): void
    {
        [$user, $sessionId] = $this->lockedUser();

        $this->expectException(SecurityException::class);

        $this->actingAs($user)
            ->withSession(['ci4ms_session_tracker_id' => $sessionId])
            ->withHeaders($this->ajaxHeaders())
            ->post('backend/lock', ['password' => self::PASSWORD]);
    }

    public function testSetLockPostWithoutCsrfTokenIsRejectedAndSessionStaysUnlocked(): void
    {
        [$user, $sessionId] = $this->lockedUser(locked: false);

        try {
            $this->actingAs($user)
                ->withSession(['ci4ms_session_tracker_id' => $sessionId])
                ->withHeaders($this->ajaxHeaders())
                ->post('backend/lock/set');
            $this->fail('Token-less POST /backend/lock/set must be rejected by the CSRF filter.');
        } catch (SecurityException) {
            $row = (new UserSessionModel())->where('session_id', $sessionId)->first();
            $this->assertEmpty($row['locked_at'], 'The rejected request must not have locked the session.');
        }
    }

    public function testSetLockWithValidTokenStillLocksTheSession(): void
    {
        [$user, $sessionId] = $this->lockedUser(locked: false);
        [$raw, $token]      = $this->freshToken();

        $result = $this->actingAs($user)
            ->withSession(['ci4ms_session_tracker_id' => $sessionId, $this->tokenName() => $raw])
            ->withHeaders($this->ajaxHeaders())
            ->post('backend/lock/set', [$this->tokenName() => $token]);

        $result->assertStatus(200);
        $result->assertJSONFragment(['status' => 'locked']);
        $row = (new UserSessionModel())->where('session_id', $sessionId)->first();
        $this->assertNotEmpty($row['locked_at']);
    }

    /**
     * The load-bearing test: two consecutive wrong-password AJAX attempts,
     * the second authenticated solely by the token the first response
     * delivered in its header.
     */
    public function testMultiAttemptUnlockWorksWithTokenDeliveredInResponseHeader(): void
    {
        [$user, $sessionId] = $this->lockedUser();
        [$raw1, $token1]    = $this->freshToken();

        $first = $this->actingAs($user)
            ->withSession(['ci4ms_session_tracker_id' => $sessionId, $this->tokenName() => $raw1])
            ->withHeaders($this->ajaxHeaders())
            ->post('backend/lock', [$this->tokenName() => $token1, 'password' => 'wrong-password']);

        $first->assertStatus(401);
        $first->assertJSONFragment(['status' => 'failed', 'remaining' => 2]);
        $this->assertSame(1, (int) $_SESSION['lock_attempts'], 'First attempt must have been processed.');

        $token2 = $first->response()->getHeaderLine('X-CSRF-TOKEN');
        $this->assertNotSame('', $token2, 'CsrfTokenRefreshFilter must deliver the new token on the lock route.');
        $this->assertNotSame($token1, $token2, 'Token must be regenerated after a verified POST.');

        // The browser keeps its session across requests; carry over the server-side
        // state the first request left behind, but authenticate ONLY with the
        // token the response header delivered.
        $sessionAfterFirst = $_SESSION;
        $this->assertNotSame($raw1, $sessionAfterFirst[$this->tokenName()], 'Server-side CSRF hash must have been regenerated.');
        \Config\Services::resetSingle('security');

        $second = $this->actingAs($user)
            ->withSession($sessionAfterFirst)
            ->withHeaders($this->ajaxHeaders())
            ->post('backend/lock', [$this->tokenName() => $token2, 'password' => 'wrong-password']);

        $second->assertStatus(401);
        $second->assertJSONFragment(['status' => 'failed', 'remaining' => 1]);
        $this->assertSame(2, (int) $_SESSION['lock_attempts'], 'Second attempt must have been processed.');
    }

    public function testCorrectPasswordWithValidTokenUnlocks(): void
    {
        [$user, $sessionId] = $this->lockedUser();
        [$raw, $token]      = $this->freshToken();

        $result = $this->actingAs($user)
            ->withSession(['ci4ms_session_tracker_id' => $sessionId, $this->tokenName() => $raw])
            ->withHeaders($this->ajaxHeaders())
            ->post('backend/lock', [$this->tokenName() => $token, 'password' => self::PASSWORD]);

        $result->assertStatus(200);
        $result->assertJSONFragment(['status' => 'unlocked']);
        $row = (new UserSessionModel())->where('session_id', $sessionId)->first();
        $this->assertEmpty($row['locked_at'], 'Session must be unlocked.');
    }

    public function testNonAjaxFormFlowStillWorks(): void
    {
        [$user, $sessionId] = $this->lockedUser();

        $page = $this->actingAs($user)
            ->withSession(['ci4ms_session_tracker_id' => $sessionId])
            ->get('backend/lock');
        $page->assertStatus(200);
        $html = (string) $page->response()->getBody();
        $this->assertSame(1, preg_match('/name="' . preg_quote($this->tokenName(), '/') . '" value="([a-f0-9]+)"/', $html, $m), 'Lock form must carry csrf_field().');
        $token = $m[1];

        $sessionAfterGet = $_SESSION;
        \Config\Services::resetSingle('security');

        $result = $this->actingAs($user)
            ->withSession($sessionAfterGet + ['ci4ms_session_tracker_id' => $sessionId])
            ->post('backend/lock', [$this->tokenName() => $token, 'password' => self::PASSWORD, 'redirect' => '/backend/']);

        $this->assertTrue($result->isRedirect());
        $this->assertStringNotContainsString('/backend/lock', (string) $result->getRedirectUrl());
        $row = (new UserSessionModel())->where('session_id', $sessionId)->first();
        $this->assertEmpty($row['locked_at']);
    }

    /**
     * The flow the UI really uses: plain form POST, no X-Requested-With, no
     * X-CSRF-TOKEN header. Each retry is authenticated only by the token the
     * re-rendered lock form carries after the failed-attempt redirect.
     */
    public function testPlainFormMultiAttemptUnlockWorksViaFormReRender(): void
    {
        [$user, $sessionId] = $this->lockedUser();

        $token1 = $this->fetchLockFormToken($user, $sessionId);

        $first = $this->postPlainUnlock($user, $sessionId, $token1, 'wrong-password');
        $this->assertTrue($first->isRedirect(), 'Wrong password must redirect back to the lock screen.');
        $this->assertStringContainsString('/backend/lock', (string) $first->getRedirectUrl());
        $this->assertSame(1, (int) $_SESSION['lock_attempts'], 'First plain POST must have been processed, not CSRF-rejected.');

        $token2 = $this->fetchLockFormToken($user, $sessionId, $first->getRedirectUrl());
        $this->assertNotSame($token1, $token2, 'Re-rendered form must carry a regenerated token; identical tokens would make this test prove nothing.');

        $second = $this->postPlainUnlock($user, $sessionId, $token2, 'wrong-password');
        $this->assertTrue($second->isRedirect());
        $this->assertStringContainsString('/backend/lock', (string) $second->getRedirectUrl());
        $this->assertSame(2, (int) $_SESSION['lock_attempts'], 'Second plain POST must have been processed using only the re-rendered form token.');
    }

    /**
     * Control experiment: replaying the first (now burned) token on the second
     * attempt must be rejected by CSRF, proving the fresh token is required.
     */
    public function testPlainFormReplayOfBurnedTokenIsRejected(): void
    {
        [$user, $sessionId] = $this->lockedUser();

        $token1 = $this->fetchLockFormToken($user, $sessionId);
        $first  = $this->postPlainUnlock($user, $sessionId, $token1, 'wrong-password');
        $this->assertTrue($first->isRedirect());
        $this->assertSame(1, (int) $_SESSION['lock_attempts']);

        $replay = $this->postPlainUnlock($user, $sessionId, $token1, 'wrong-password');

        $this->assertSame(1, (int) $_SESSION['lock_attempts'], 'Replayed token must not reach unlockAction (attempts must stay at 1).');
        $this->assertTrue($replay->isRedirect(), 'With security.redirect=true a non-AJAX CSRF rejection is a redirect.');
    }

    /**
     * GET the lock screen (optionally a redirect target) carrying the session
     * state of the previous request, and return the form's csrf token.
     */
    private function fetchLockFormToken(User $user, string $sessionId, ?string $url = null): string
    {
        $carry = $_SESSION ?? [];
        \Config\Services::resetSingle('security');

        $path = 'backend/lock';
        if ($url !== null) {
            $query = parse_url($url, PHP_URL_QUERY);
            $path  = ltrim((string) parse_url($url, PHP_URL_PATH), '/') . ($query ? '?' . $query : '');
        }

        $page = $this->actingAs($user)
            ->withSession($carry + ['ci4ms_session_tracker_id' => $sessionId])
            ->get($path);
        $page->assertStatus(200);

        $html = (string) $page->response()->getBody();
        $this->assertSame(1, preg_match('/name="' . preg_quote($this->tokenName(), '/') . '" value="([a-f0-9]+)"/', $html, $m), 'Lock form must carry csrf_field().');

        return $m[1];
    }

    private function postPlainUnlock(User $user, string $sessionId, string $token, string $password): \CodeIgniter\Test\TestResponse
    {
        $carry = $_SESSION ?? [];
        \Config\Services::resetSingle('security');

        return $this->actingAs($user)
            ->withSession($carry + ['ci4ms_session_tracker_id' => $sessionId])
            ->post('backend/lock', [$this->tokenName() => $token, 'password' => $password, 'redirect' => '/backend/']);
    }

    private function tokenName(): string
    {
        return \Config\Services::security()->getTokenName();
    }

    /**
     * @return array{0: string, 1: string} server-side raw hash (session value), token a client posts
     *                                      (randomized when security.tokenRandomize is on)
     */
    private function freshToken(): array
    {
        \Config\Services::resetSingle('security');
        $security = \Config\Services::security();
        $raw      = $security->generateHash();

        return [$raw, (string) $security->getHash()];
    }

    /**
     * @return array<string, string>
     */
    private function ajaxHeaders(): array
    {
        return ['X-Requested-With' => 'XMLHttpRequest'];
    }

    /**
     * @return array{0: User, 1: string}
     */
    private function lockedUser(bool $locked = true): array
    {
        $suffix = bin2hex(random_bytes(4));
        $users  = auth()->getProvider();
        $users->save(new User([
            'firstname'    => 'Lock',
            'surname'      => 'Tester',
            'username'     => 'lockcsrf_' . $suffix,
            'email'        => 'lockcsrf_' . $suffix . '@example.test',
            'password'     => self::PASSWORD,
            'active'       => 1,
            'own_language' => 'en',
        ]));
        $user = $users->findById($users->getInsertID());
        $this->assertNotNull($user);

        $sessionId = bin2hex(random_bytes(16));
        $model     = new UserSessionModel();
        $model->insert([
            'user_id'         => (int) $user->id,
            'session_id'      => $sessionId,
            'ip_address'      => '127.0.0.1',
            'user_agent'      => 'phpunit',
            'browser'         => 'phpunit',
            'browser_version' => '1.0',
            'os'              => 'test',
            'device_type'     => 'desktop',
            'device_name'     => 'test',
            'last_activity'   => date('Y-m-d H:i:s'),
            'is_active'       => 1,
            'created_at'      => date('Y-m-d H:i:s'),
        ]);
        if ($locked) {
            $model->lockSession($sessionId);
        }

        return [$user, $sessionId];
    }
}
