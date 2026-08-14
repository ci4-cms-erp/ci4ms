<?php

declare(strict_types=1);

namespace Tests\Modules\Logs;

use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Modules\Methods\Libraries\ModuleScanner;

/**
 * Regression suite for F5 (reflected XSS in modules/Logs/Views/list.php).
 *
 * Logs::index() derives both $stats['currentFile'] (list.php:36) and
 * $currentFile (list.php:70) from the `?f=` query parameter
 * (base64_decode()'d, no further sanitisation) whenever no matching log
 * file exists on disk yet -- LogViewer::getLogs() safely returns [] for a
 * non-existent filename (modules/Logs/Libraries/LogViewer.php:51-57), so a
 * crafted `f=` value never touches the filesystem, only the view. Before
 * the fix, both echoes were unescaped; an attacker-controlled `f=` value
 * rendered raw HTML into the response.
 *
 * Real HTTP GET (FeatureTestTrait) + superadmin actingAs(), same pattern as
 * tests/Modules/MigrationManager/MigrationManagerViewRenderTest.php --
 * Ci4MsAuthFilter's fail-closed permission check requires
 * ModuleScanner::runScan() to have registered Logs::index even for a
 * superadmin actor (modules/Auth/Filters/Ci4MsAuthFilter.php:52-56).
 *
 * @internal
 */
final class LogsViewEscapingTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use FeatureTestTrait;
    use AuthenticationTesting;

    protected $refresh = false;

    protected $migrateOnce = true;

    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertStringContainsString(
            'ci4ms_test',
            (string) \Config\Database::connect()->database,
            'Refusing to run: the active connection is not pointed at the test database.',
        );

        // AuthGroups/Ci4MsAuthFilter permission cache staleness guard, same
        // rationale as MigrationManagerViewRenderTest::setUp().
        cache()->clean();

        // Fail-closed layer precondition -- see class docblock.
        (new ModuleScanner())->runScan();
    }

    protected function tearDown(): void
    {
        // actingAs() logs the user in on the shared Session authenticator
        // instance cached inside Shield's Auth facade; resetSingle('auth')
        // discards it so the next service('auth') call in a later test file
        // builds fresh state (same fix as MigrationManagerViewRenderTest).
        \Config\Services::resetSingle('auth');
        \Config\Services::resetSingle('language');

        parent::tearDown();
    }

    /**
     * `?f=` base64-decodes to a raw XSS payload. LogViewer::getLogs() finds
     * no matching file on disk (no filesystem side effect either way), but
     * Logs::index() still assigns the raw decoded string to both
     * $stats['currentFile'] and $currentFile -- both must render escaped.
     */
    public function testCurrentFileXssPayloadIsEscapedInResponse(): void
    {
        $payload = '<script>alert(1)</script>';
        $admin   = $this->createSuperadmin('logs_view_sa1');

        $result = $this->actingAs($admin)->get('backend/logs?f=' . rawurlencode(base64_encode($payload)));

        $result->assertStatus(200);
        $body = (string) $result->response()->getBody();

        $this->assertStringNotContainsString($payload, $body, 'The raw XSS payload must never appear unescaped in the response body.');
        $this->assertStringContainsString(esc($payload), $body, 'The escaped form of the payload must be present (proves the value was rendered, just escaped).');
    }

    private function createSuperadmin(string $label): User
    {
        $suffix = bin2hex(random_bytes(4));

        $users = auth()->getProvider();
        $user  = new User([
            'firstname'    => 'Test',
            'surname'      => 'User',
            'username'     => $label . '_' . $suffix,
            'email'        => $label . '_' . $suffix . '@example.test',
            'password'     => 'SuperSecret123!',
            'active'       => 1,
            'own_language' => 'en',
        ]);
        $users->save($user);

        $fresh = $users->findById($users->getInsertID());
        $this->assertNotNull($fresh, 'Newly created user could not be re-fetched.');
        $fresh->setGroupsCache(['superadmin']);

        return $fresh;
    }
}
