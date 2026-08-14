<?php

declare(strict_types=1);

namespace Tests\Modules\Settings;

use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\TestLogger;
use Config\App;
use Modules\Settings\Controllers\Settings;
use ReflectionProperty;
use Tests\Support\Settings\SpyUpdateService;

/**
 * HTTP contract of Settings::rollbackUpdate().
 *
 * UpdateRollbackPathTest pins the resolution primitive; this class pins what the
 * endpoint actually answers with — status codes, message keys, and above all
 * whether rollback() is reached at all on a refused name.
 *
 * Why the controller is driven directly instead of through call('post', ...):
 * the project's migrations are MySQL-only (ALTER ... ON UPDATE, ADD FOREIGN KEY)
 * and fail on the SQLite `tests` group, so DatabaseTestTrait/FeatureTestTrait
 * cannot come up green here — the pre-existing tests/Feature classes that set
 * $refresh = true error out for exactly that reason. Pointing the tests group at
 * the live MariaDB instead is not an option. Instantiating the controller and
 * injecting request, response and a spied UpdateService keeps the real method
 * body under test in any environment and touches neither the database nor
 * ROOTPATH. What is therefore NOT covered here is the router and filter chain in
 * front of the method (Ci4MsAuthFilter and the `update` permission).
 *
 * @internal
 */
final class UpdateRollbackControllerTest extends CIUnitTestCase
{
    /** Backup the scratch tree advertises as restorable. */
    private const GOOD_BACKUP = 'v0.34.0.0_to_v0.35.0.0_20260727_120000';

    private string $scratch;

    private string $backupBase;

    private SpyUpdateService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scratch    = sys_get_temp_dir() . '/ci4ms_rollback_ctrl_' . bin2hex(random_bytes(6));
        $this->backupBase = $this->scratch . '/backups/';

        mkdir($this->backupBase . self::GOOD_BACKUP . '/app/Config', 0777, true);
        mkdir($this->scratch . '/outside', 0777, true);

        file_put_contents($this->backupBase . self::GOOD_BACKUP . '/app/Config/App.php', '<?php // backed up');
        file_put_contents($this->backupBase . self::GOOD_BACKUP . '/README.md', '# backed up');
        file_put_contents($this->scratch . '/outside/shell.php', '<?php // staged payload');

        $this->service = new SpyUpdateService($this->backupBase);

        $this->clearLoggedMessages();
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->scratch);

        parent::tearDown();
    }

    public function testRejectsANonAjaxRequest(): void
    {
        $response = $this->dispatch(self::GOOD_BACKUP, ajax: false);

        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(0, $this->service->rollbackCalls);
    }

    public function testRejectsAMissingBackupName(): void
    {
        $response = $this->dispatch(null);

        $this->assertSame(400, $response->getStatusCode());
        $this->assertStringContainsString(lang('Settings.backupNameRequired'), (string) $response->getBody());
        $this->assertSame(0, $this->service->rollbackCalls);
    }

    public function testRejectsAWhitespaceOnlyBackupName(): void
    {
        $response = $this->dispatch('   ');

        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(0, $this->service->rollbackCalls);
    }

    public function testRejectsAnUnknownBackupName(): void
    {
        $response = $this->dispatch('v9.9.9.9_to_v9.9.9.9_nope');

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringContainsString(lang('Settings.invalidBackupName'), (string) $response->getBody());
        $this->assertSame(0, $this->service->rollbackCalls);
    }

    /**
     * The regression that matters: a traversal payload must never reach rollback(),
     * because rollback() is what copies the chosen directory over the installation.
     *
     * @param string $payload Value submitted as backup_name
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('traversalPayloads')]
    public function testATraversalPayloadNeverReachesTheRestoreCall(string $payload): void
    {
        $response = $this->dispatch($payload);

        $this->assertContains($response->getStatusCode(), [400, 404]);
        $this->assertSame(0, $this->service->rollbackCalls, "rollback() was reached with: {$payload}");
        $this->assertNull($this->service->rollbackDir);
    }

    /**
     * @return list<array{string}>
     */
    public static function traversalPayloads(): array
    {
        return [
            ['../outside'],
            ['../../outside'],
            ['./../outside'],
            ['..'],
            ['.'],
            ['/etc'],
            ["../outside\0"],
            ['....//outside'],
        ];
    }

    public function testRestoresAKnownBackupAndReportsSuccess(): void
    {
        $response = $this->dispatch(self::GOOD_BACKUP);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString(lang('Settings.rollbackSuccess'), (string) $response->getBody());
        $this->assertSame(1, $this->service->rollbackCalls);
        $this->assertSame(realpath($this->backupBase . self::GOOD_BACKUP) . '/', $this->service->rollbackDir);
    }

    /**
     * The controller enumerates the restore set itself, so the file list handed to
     * rollback() is asserted rather than assumed.
     */
    public function testHandsTheFullRelativeFileListToTheRestoreCall(): void
    {
        $this->dispatch(self::GOOD_BACKUP);

        $files = $this->service->rollbackFiles;
        sort($files);

        $this->assertSame(['README.md', 'app/Config/App.php'], $files);
    }

    public function testReportsFailureWhenTheRestoreCallFails(): void
    {
        $this->service->rollbackResult = false;

        $response = $this->dispatch(self::GOOD_BACKUP);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(1, $this->service->rollbackCalls);
    }

    public function testRefusedRequestsLeaveAWarningBehind(): void
    {
        $this->dispatch('../outside');

        $this->assertNotEmpty(
            array_filter(
                $this->loggedAt('warning'),
                static fn (string $message): bool => str_contains($message, 'Rollback rejected an unknown backup name'),
            ),
        );
    }

    /**
     * Nothing the endpoint does may alter the scratch tree: the restore itself is
     * stubbed, and every refusal must be inert.
     */
    public function testNoRequestMutatesTheBackupTree(): void
    {
        $before = $this->treeSnapshot($this->scratch);

        foreach (self::traversalPayloads() as [$payload]) {
            $this->dispatch($payload);
        }
        $this->dispatch(self::GOOD_BACKUP);
        $this->dispatch(null);

        $this->assertSame($before, $this->treeSnapshot($this->scratch));
    }

    /**
     * Runs the real controller method against an injected request and response.
     *
     * @param string|null $backupName Value of the backup_name POST field, null to omit it
     * @param bool        $ajax       Whether the request announces itself as XHR
     */
    private function dispatch(?string $backupName, bool $ajax = true): ResponseInterface
    {
        $config  = config(App::class);
        $request = new IncomingRequest($config, new SiteURI($config), null, new UserAgent());

        $request->setGlobal('post', $backupName === null ? [] : ['backup_name' => $backupName]);

        if ($ajax) {
            $request->setHeader('X-Requested-With', 'XMLHttpRequest');
        }

        $controller = new Settings($this->service);

        (new ReflectionProperty(\CodeIgniter\Controller::class, 'request'))->setValue($controller, $request);
        (new ReflectionProperty(\CodeIgniter\Controller::class, 'response'))->setValue($controller, service('response', $config, false));

        $result = $controller->rollbackUpdate();

        $this->assertInstanceOf(ResponseInterface::class, $result);

        return $result;
    }

    /**
     * @return list<string>
     */
    private function treeSnapshot(string $dir): array
    {
        $paths = [];

        foreach ((array) glob($dir . '/*') as $path) {
            $path    = (string) $path;
            $paths[] = $path . ':' . (is_file($path) ? (string) filesize($path) : 'dir');

            if (is_dir($path) && ! is_link($path)) {
                $paths = array_merge($paths, $this->treeSnapshot($path));
            }
        }

        sort($paths);

        return $paths;
    }

    private function removeTree(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        foreach ((array) glob($dir . '/*') as $path) {
            $path = (string) $path;

            if (is_link($path) || is_file($path)) {
                unlink($path);
            } elseif (is_dir($path)) {
                $this->removeTree($path);
            }
        }

        rmdir($dir);
    }

    private function clearLoggedMessages(): void
    {
        (new ReflectionProperty(TestLogger::class, 'op_logs'))->setValue(null, []);
    }

    /**
     * @return list<string>
     */
    private function loggedAt(string $level): array
    {
        /** @var list<array{level: mixed, message: string, file: string|null}> $entries */
        $entries  = (new ReflectionProperty(TestLogger::class, 'op_logs'))->getValue();
        $messages = [];

        foreach ($entries as $entry) {
            $entryLevel = $entry['level'];

            if (is_string($entryLevel) && strtolower($entryLevel) === $level) {
                $messages[] = $entry['message'];
            }
        }

        return $messages;
    }
}
