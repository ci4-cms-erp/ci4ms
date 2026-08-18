<?php

declare(strict_types=1);

namespace Tests\Modules\Backup;

use ci4commonmodel\CommonModel;
use Closure;
use CodeIgniter\Events\Events;
use CodeIgniter\HTTP\Exceptions\HTTPException;
use CodeIgniter\HTTP\Files\FileCollection;
use CodeIgniter\HTTP\Files\UploadedFile;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\ResponseInterface;
use CodeIgniter\HTTP\SiteURI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Shield\Entities\User;
use CodeIgniter\Shield\Test\AuthenticationTesting;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\App;
use Config\Services;
use Modules\Backup\Controllers\Backup;
use ReflectionProperty;
use Tests\Support\Notifications\CapturingChannel;
use Tests\Support\Notifications\FakeDispatchNotifier;
use ZipArchive;

/**
 * Locks the `ci4ms.audit` event on the SUCCESS branch of Backup::restore()
 * (Backup.php:145-150).
 *
 * BackupRestoreAuthzTest, the only other test on this action, asserts the 403
 * from the superadmin guard at Backup.php:89 — which means the whole rest of
 * restore() had never been executed by any test, and the audit event announcing
 * "somebody restored a database dump" could have been deleted without a single
 * assertion turning red. This file runs the action end to end instead: a real
 * ZIP upload, a real extraction, a real DbBackup::restore(), the flash message
 * proving the success branch was taken, and the event that goes with it.
 *
 * WHY A LOCAL Events::on LISTENER: the contract under test belongs to the
 * PRODUCER (severity 'warning', action 'rbac.backupRestored'), and the
 * app/Config/Events.php consumer rewrites the action into
 * 'audit.' . $action on the way to the notification. Asserting at the channel
 * would be asserting about the listener, which
 * tests/Modules/Notifications/AuditListenerSeverityTest.php already owns. The
 * listener is removed by identity in tearDown();
 * Events::removeAllListeners('ci4ms.audit') is NOT used — it would also drop
 * the application's own listener for the rest of the PHPUnit process.
 *
 * A FakeDispatchNotifier is injected all the same, because the application's
 * listener does fire: the real Notifier builds `new CommonModel()` on the
 * autocommitting 'default' group, so its notification rows would survive this
 * test's rollback and pile up in ci4ms_test.
 *
 * ISOLATION: tearDown() clears the session/auth state actingAs() installs.
 * BackupRestoreAuthzTest rolls back only its transaction, so its actor's id
 * stays in the session while the user row itself is gone — the next
 * FeatureTest request then hits `auth()->user()->force_reset` on null
 * (reproducible with `phpunit tests/Modules/Backup tests/Modules/Settings`).
 * Fixing that file is out of this task's scope; not repeating its mistake is
 * not.
 *
 * @internal
 */
final class BackupRestoreAuditTest extends CIUnitTestCase
{
    use DatabaseTestTrait;
    use AuthenticationTesting;

    protected $refresh = false;

    protected $migrateOnce = true;

    /** null migrates every registered namespace: Shield's users/identities plus auth_groups_users. */
    protected $namespace = null;

    private CommonModel $commonModel;

    /**
     * Every `ci4ms.audit` payload seen during the test, in trigger order.
     *
     * @var list<array<string, mixed>>
     */
    private array $auditEvents = [];

    /** The exact closure registered on `ci4ms.audit`, kept for removeListener(). */
    private ?Closure $auditListener = null;

    /**
     * Absolute paths this test (or the action it drove) may have left on disk.
     *
     * @var list<string>
     */
    private array $scratchPaths = [];

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

        $this->commonModel = new CommonModel('tests');

        Services::injectMock('notifier', new FakeDispatchNotifier(new CapturingChannel()));

        $this->auditEvents   = [];
        $this->auditListener = function (array $event): void {
            $this->auditEvents[] = $event;
        };
        Events::on('ci4ms.audit', $this->auditListener);

        $this->db->transStart();
    }

    protected function tearDown(): void
    {
        $this->forgetActor();

        $this->db->transRollback();

        if ($this->auditListener !== null) {
            Events::removeListener('ci4ms.audit', $this->auditListener);
            $this->auditListener = null;
        }

        Services::resetSingle('notifier');
        Services::resetSingle('request');
        Services::resetSingle('validation');

        foreach ($this->scratchPaths as $path) {
            if (is_file($path)) {
                @unlink($path);
            }
        }
        $this->scratchPaths = [];

        parent::tearDown();
    }

    /**
     * A superadmin restoring a dump must be announced to the other superadmins:
     * one 'warning' `rbac.backupRestored` event naming the actor.
     *
     * The flash assertions are not decoration — the failure branch of restore()
     * redirects to the very same route, so without them a test that never got
     * past the upload handling would look identical to one that restored.
     */
    public function testSuccessfulRestoreRaisesTheBackupRestoredAuditEvent(): void
    {
        $actor    = $this->createSuperadmin('backup_audit_t1');
        $response = $this->dispatchRestore($actor['id']);

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame(
            lang('Backup.dbRestore'),
            session()->getFlashdata('message'),
            'Setup invariant: restore() must have taken the success branch, otherwise the event assertions below are '
                . 'measuring an aborted upload.',
        );
        $this->assertNull(session()->getFlashdata('error'), 'The success branch must not also flash an error.');

        $events = $this->restoredEvents();
        $this->assertCount(1, $events, 'A completed restore must raise exactly one rbac.backupRestored audit event.');
        $this->assertSame(
            'warning',
            $events[0]['severity'] ?? null,
            "severity must be 'warning': app/Config/Events.php drops anything that is neither 'warning' nor "
                . "'critical', so a wrong value here silences the notification entirely.",
        );
        $this->assertSame(
            lang('Backup.auditBackupRestored', [$actor['username']]),
            $events[0]['message'] ?? null,
            'The message must name the actor who restored — that is the whole point of the audit trail.',
        );
        $this->assertSame(base_url('backend/backup'), $events[0]['url'] ?? null);
    }

    /**
     * Every captured payload whose action is the controller-level restore one.
     *
     * Filtered, not taken wholesale: DbBackup::restore() raises its own
     * `rbac.backupRestoreStatementsSkipped` event on the same channel, and a
     * dump that happened to trip it must not be counted here.
     *
     * @return list<array<string, mixed>>
     */
    private function restoredEvents(): array
    {
        return array_values(array_filter(
            $this->auditEvents,
            static fn (array $event): bool => ($event['action'] ?? null) === 'rbac.backupRestored',
        ));
    }

    /**
     * Creates an active user and puts it in the superadmin group.
     *
     * inGroup() reads auth_groups_users directly, so the row is all the guard
     * at Backup.php:89 needs — no Config\AuthGroups entry is involved.
     *
     * @return array{id: int, username: string}
     */
    private function createSuperadmin(string $label): array
    {
        $username = $label . '_' . bin2hex(random_bytes(4));
        $this->assertLessThanOrEqual(
            30,
            strlen($username),
            'users.username is varchar(30): a longer label makes the INSERT drop silently and every later lookup null.',
        );

        $users = auth()->getProvider();
        $users->save(new User([
            'firstname' => 'Test',
            'surname'   => 'User',
            'username'  => $username,
            'email'     => $username . '@example.test',
            'password'  => 'SuperSecret123!',
            'active'    => 1,
        ]));
        $id = (int) $users->getInsertID();

        $this->commonModel->create('auth_groups_users', [
            'user_id'    => $id,
            'group'      => 'superadmin',
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return ['id' => $id, 'username' => $username];
    }

    /**
     * Drives Backup::restore() as $actorId with a real ZIP holding one harmless
     * SQL statement.
     */
    private function dispatchRestore(int $actorId): ResponseInterface
    {
        $controller = new Backup();
        $upload     = $this->primeRequest($actorId, $controller);

        try {
            return $controller->restore();
        } finally {
            // The action moves the upload under writable/uploads/ with a random
            // name; only the double knows what it ended up being called.
            $this->scratchPaths[] = WRITEPATH . 'uploads/' . $upload->getName();
        }
    }

    /**
     * Builds the request the controller and the file validation rules both read
     * from, and logs $actorId in.
     *
     * The uploaded file has to be reachable twice over: `$this->request` for
     * the controller, and `service('request')` for
     * Validation\StrictRules\FileRules, which resolves the request itself. One
     * injected instance covers both.
     */
    private function primeRequest(int $actorId, Backup $controller): UploadedFile
    {
        $this->actingAs(auth()->getProvider()->findById($actorId));

        $config  = config(App::class);
        $request = new IncomingRequest($config, new SiteURI($config), null, new UserAgent());
        $request->setMethod('POST');
        $request->setGlobal('post', []);
        $request->setGlobal('request', []);

        $upload     = $this->makeZipUpload();
        $collection = new FileCollection();
        (new ReflectionProperty(FileCollection::class, 'files'))->setValue($collection, ['backup_file' => $upload]);
        (new ReflectionProperty(IncomingRequest::class, 'files'))->setValue($request, $collection);

        Services::injectMock('request', $request);
        // Rebuilt after the request swap: the rule-set objects capture
        // service('request') when Validation::run() first instantiates them.
        Services::resetSingle('validation');

        $controller->commonModel = $this->commonModel;

        (new ReflectionProperty(\CodeIgniter\Controller::class, 'request'))->setValue($controller, $request);
        (new ReflectionProperty(\CodeIgniter\Controller::class, 'response'))->setValue($controller, service('response', $config, false));

        return $upload;
    }

    /**
     * An UploadedFile over a real ZIP archive containing one harmless statement.
     *
     * `SET @qa_probe = 1;` is deliberate: 'SET' is on DbBackup's allowed-prefix
     * list (DbBackup.php:166-170) and a user variable assignment changes no
     * schema, no row and no session setting the rest of the suite can observe,
     * so restore() returns true without this test ever writing to ci4ms_test.
     *
     * The double overrides exactly two methods, both for the same reason: PHP
     * did not receive this file over HTTP, so `is_uploaded_file()` and
     * `move_uploaded_file()` refuse to acknowledge it. Nothing else about
     * UploadedFile's contract is changed — in particular getClientExtension()
     * (what `ext_in[backup_file,zip]` checks) and getExtension() (what the
     * controller branches on) run untouched against the real archive.
     */
    private function makeZipUpload(): UploadedFile
    {
        $marker  = bin2hex(random_bytes(4));
        $sqlName = 'qa_backup_restore_audit_' . $marker . '.sql';
        $zipPath = sys_get_temp_dir() . '/qa_backup_restore_audit_' . $marker . '.zip';

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'test setup: could not create the fixture ZIP');
        $zip->addFromString($sqlName, "SET @qa_probe = 1;\n");
        $zip->close();

        // Both are cleaned up in tearDown even if the action never gets to them.
        $this->scratchPaths[] = $zipPath;
        $this->scratchPaths[] = WRITEPATH . 'uploads/' . $sqlName;

        return new class ($zipPath, 'qa_backup_' . $marker . '.zip', 'application/zip', (int) filesize($zipPath), UPLOAD_ERR_OK) extends UploadedFile {
            /**
             * @return bool
             */
            public function isValid(): bool
            {
                return $this->error === UPLOAD_ERR_OK && is_file($this->path);
            }

            /**
             * @param string      $targetPath Directory to move the file into
             * @param string|null $name       Destination file name
             * @param bool        $overwrite  Overwrite an existing destination
             *
             * @return bool
             *
             * @throws HTTPException On a second move, an invalid file, or a failed rename
             */
            public function move(string $targetPath, ?string $name = null, bool $overwrite = false): bool
            {
                $targetPath = rtrim($targetPath, '/') . '/';

                if ($this->hasMoved) {
                    throw HTTPException::forAlreadyMoved();
                }

                if (! $this->isValid()) {
                    throw HTTPException::forInvalidFile();
                }

                if (! is_dir($targetPath)) {
                    mkdir($targetPath, 0777, true);
                }

                if ($name === null) {
                    helper('security');
                    $name = sanitize_filename($this->getName());
                }

                $destination = $overwrite ? $targetPath . $name : $this->getDestination($targetPath . $name);

                $this->hasMoved = rename($this->path, $destination);

                if ($this->hasMoved === false) {
                    throw HTTPException::forMoveFailed(basename($this->path), $targetPath, 'rename() returned false');
                }

                $this->path = $targetPath;
                $this->name = basename($destination);

                return true;
            }
        };
    }

    /**
     * Undoes actingAs(): drops the session user id together with the rest of
     * the session state, so the rolled-back fixture user cannot resurface as a
     * logged-in-but-missing actor in a later test.
     */
    private function forgetActor(): void
    {
        $authenticator = auth('session')->getAuthenticator();

        if ($authenticator->getUser() !== null) {
            $authenticator->logout();
        }

        $session = session();
        foreach (array_keys((array) $session->get()) as $key) {
            $session->remove((string) $key);
        }
    }
}
