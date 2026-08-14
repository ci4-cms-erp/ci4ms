<?php

declare(strict_types=1);

namespace Tests\Modules\Backend;

use Config\Database;
use Modules\Backend\Commands\Ci4msMigrate;
use Modules\MigrationManager\Libraries\RunLock;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use ReflectionMethod;

/**
 * `php spark ci4ms:migrate` (`modules/Backend/Commands/Ci4msMigrate.php`)
 * regression tests — FAZ 2 kabul kriteri 3 (başarı dalı + hata dalında
 * NON-ZERO exit + audit satırının yazıldığı).
 *
 * Test C, gerçek bir `MigrationRunner::latest()` başarısızlığını KASITLI
 * OLARAK tetiklemez. `latest()` başarısız olduğunda framework OTOMATİK
 * olarak `regress(-1)` çağırıyor
 * (`vendor/codeigniter4/framework/system/Database/MigrationRunner.php:210-211`);
 * tek batch'li (taze) bir şemada bu, hedef batch'i 0'a düşürüp TÜM
 * namespace'lerin migration'larını geri alıyor (`:269-271`, `:289-336`) —
 * paylaşımlı `ci4ms_test` şemasını bu testte veya `--order-by` farklı
 * sıralarla koşan SONRAKİ herhangi bir testte kalıcı olarak bozma riski
 * kabul edilemez; `CHANGELOG.md:81`deki "Duplicate column name
 * 'allowed_groups'" felaketi tam bu senaryonun canlıda yaşanmış hali.
 * Bunun yerine private `recordRun()` metodu Reflection ile DOĞRUDAN
 * çağrılır: bu, gerçek insert kod yolunu (tableExists guard, try/catch,
 * kolon eşlemesi) DB'ye karşı GERÇEKTEN çalıştırır, yalnız `latest()`'in
 * kendisini atlar.
 *
 * FAZ 3 F2, `run()`'ın hata dalını (`EXIT_ERROR`) da BAŞKA bir yolla, gerçek
 * `MigrationRunner`'a hiç dokunmadan test eder: `createMigrationRunner()`
 * `protected` olduğu için bir alt sınıfta override edilip
 * `createMock(MigrationRunner::class)` enjekte edilebiliyor
 * (`MigrationRunner` `final` değil). Mock'un `latest()`'i fırlatıyor —
 * gerçek `latest()` HİÇ ÇAĞRILMIYOR, dolayısıyla yukarıdaki `regress(-1)`
 * hazardı bu testte de yok; yalnız `recordRun()`'ın gerçek `db_connect()`
 * insert'i çalışıyor (temizlenir).
 *
 * FAZ 5 I1, `RunLock` entegrasyonunun uc dalini da ayri ayri, gercek bir
 * `latest()` cagirmadan/cagirarak-ama-guvenli-sekilde kilitler:
 * `testRunReturnsExitErrorImmediatelyWithoutCallingLatestWhenRunLockIsHeld()`
 * (a) `createRunLock()` override'iyla IZOLE bir kilit dosyasina isaret eden
 * GERCEK bir `RunLock`; test once KENDI `RunLock` instance'iyla ayni dosyayi
 * `acquire()` ederek "baska bir surec tutuyor" durumunu simule ediyor, sonra
 * komutu calistiriyor - `run()`'in kilit kontrolu `latest()`'ten ONCE
 * oldugu icin (`Ci4msMigrate.php:126-138`) gercek `latest()` HIC
 * tetiklenmiyor (`regress(-1)` hazardi yok), bu bir `createMock`'un
 * `latest()`'i `expects($this->never())` ile de ayrica kanitlaniyor.
 * `testRunLockIsReleasedAfterASuccessfulRun()` (b) AYNI izole-yol teknigiyle
 * ama kilit TUTULU DEGILKEN normal (basari) yolu calistiriyor - bu, gercek
 * `latest()`'i paylasimli `ci4ms_test` semasinda kosturuyor, TAM OLARAK Test
 * A/B'nin zaten kabul ettigi hazard kapsaminda (sema zaten `migrateOnce` ile
 * guncel, "already up to date" donuyor), yeni bir risk EKLEMIYOR. FAZ 6'da
 * (b)'ye ek olarak `testRunLockIsReleasedByTheSignalSafeInnerReleaseNotJustTheOuterFinally()`
 * eklendi: (b) yalnizca "run() bitince kilit bir sekilde serbest" diyor,
 * HANGI `release()` cagrisinin (closure-ici `:159` mi, disaridaki guvenlik
 * agi `:177` mi) bunu yaptigini ayirt etmiyor (quality-lead FAZ5 mutasyonla
 * kanitladi: `:159` yorum satirina alinsa bile bu test yesil kaliyordu).
 * Yeni test `getHistory()`'nin `run()` icindeki IKINCI cagrisini (`:168`,
 * closure DONDUKTEN SONRA ama disaridaki `finally`den ONCE) yakalayip o anda
 * kilit dosyasinin zaten silinmis olup olmadigini kaydediyor - bu an yalniz
 * `:159`'un calismis olabilecegi, `:177`'nin HENUZ calismadigi tek pencere.
 * `testRunSucceedsWithoutARunLockWhenTheRunLockClassDoesNotExist()` (c)
 * `runLockClassName()` override'iyla var-olmayan bir FQCN dondurup gercek
 * `class_exists()`'in `false` donmesini sagliyor - `createRunLock()` bu
 * durumda `new $lockClass()`'a hic ULASMIYOR (`Ci4msMigrate.php:215-219`),
 * yani `RunLock` hic instantiate edilmiyor, hicbir kilit dosyasina
 * dokunulmuyor; bu, kosullu dalin kendisinden (erken `return null;`) yapisal
 * olarak garanti, ayrica bir dosya-sistemi assertion'i gerektirmiyor.
 * Paylasimli varsayilan kilit dosyasina (`WRITEPATH.'locks/migration_manager.lock'`)
 * bu testlerin HICBIRI dokunmuyor - hepsi kendi izole yolunu uretiyor ve
 * `try/finally` ile temizliyor.
 *
 * @internal
 */
final class Ci4msMigrateCommandTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $refresh = false;

    protected $migrateOnce = true;

    protected $namespace = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assertStringContainsString(
            'ci4ms_test',
            (string) Database::connect()->database,
            'Refusing to run: the active connection is not pointed at the test database.',
        );
    }

    /**
     * Deletes a single `migration_runs` row by id. `DatabaseTestTrait` does
     * not wrap tests in a transaction (`.ci4ms/knowledge/pitfalls.md:142-154`),
     * so every row this test class inserts is cleaned up explicitly instead
     * of relying on rollback.
     *
     * @param int|null $id Row id to delete, or `null` to no-op.
     */
    private function deleteMigrationRunRow(?int $id): void
    {
        if ($id === null) {
            return;
        }

        $this->db->table('migration_runs')->where('id', $id)->delete();
    }

    /**
     * Real success path: running the actual `ci4ms:migrate` command against
     * `ci4ms_test` returns `EXIT_SUCCESS` and writes one `migration_runs`
     * audit row with `kind='migration'`, `target='*'` (sentinel),
     * `run_source='cli'`, `status='success'`.
     */
    public function testRealSuccessPathReturnsExitSuccessAndWritesAuditRow(): void
    {
        $rowId = null;

        try {
            $exit = service('commands')->run('ci4ms:migrate', []);

            $this->assertSame(EXIT_SUCCESS, $exit);

            $row = $this->db->table('migration_runs')
                ->orderBy('id', 'DESC')
                ->limit(1)
                ->get()
                ->getRow();

            $this->assertNotNull($row, 'ci4ms:migrate did not write a migration_runs audit row.');
            $rowId = (int) $row->id;

            $this->assertSame('migration', $row->kind);
            $this->assertSame('*', $row->target);
            $this->assertSame('cli', $row->run_source);
            $this->assertSame('success', $row->status);
            $this->assertNull($row->run_by);
        } finally {
            $this->deleteMigrationRunRow($rowId);
        }
    }

    /**
     * Idempotency: running the command a second time (nothing left pending)
     * still returns `EXIT_SUCCESS`, still writes a `status='success'` audit
     * row, and reports `applied_count=0` — it does not claim anything was
     * applied when it wasn't.
     */
    public function testSecondConsecutiveRunIsIdempotentAndReportsZeroApplied(): void
    {
        $firstRowId  = null;
        $secondRowId = null;

        try {
            $first = service('commands')->run('ci4ms:migrate', []);
            $this->assertSame(EXIT_SUCCESS, $first);

            $firstRow = $this->db->table('migration_runs')->orderBy('id', 'DESC')->limit(1)->get()->getRow();
            $this->assertNotNull($firstRow);
            $firstRowId = (int) $firstRow->id;

            $second = service('commands')->run('ci4ms:migrate', []);
            $this->assertSame(EXIT_SUCCESS, $second);

            $secondRow = $this->db->table('migration_runs')->orderBy('id', 'DESC')->limit(1)->get()->getRow();
            $this->assertNotNull($secondRow);
            $secondRowId = (int) $secondRow->id;

            $this->assertNotSame($firstRowId, $secondRowId, 'Second run did not write a new audit row.');
            $this->assertSame('success', $secondRow->status);
            $this->assertSame(0, (int) $secondRow->applied_count);
            $this->assertSame('cli', $secondRow->run_source);
        } finally {
            $this->deleteMigrationRunRow($firstRowId);
            $this->deleteMigrationRunRow($secondRowId);
        }
    }

    /**
     * `recordRun()` failure branch, invoked directly via Reflection (see
     * class docblock for why a real `latest()` failure is not triggered).
     * Verifies the real insert code path writes `status='failed'`,
     * `target='*'`, `run_source='cli'` and preserves the error message and
     * duration.
     */
    public function testRecordRunViaReflectionWritesFailureAuditRow(): void
    {
        $rowId = null;

        try {
            $command = new Ci4msMigrate(service('logger'), service('commands'));

            $method = new ReflectionMethod($command, 'recordRun');
            $method->setAccessible(true);
            $method->invoke($command, [], [], true, 'synthetic test failure', 7);

            $row = $this->db->table('migration_runs')
                ->orderBy('id', 'DESC')
                ->limit(1)
                ->get()
                ->getRow();

            $this->assertNotNull($row, 'recordRun() did not write a migration_runs row.');
            $rowId = (int) $row->id;

            $this->assertSame('migration', $row->kind);
            $this->assertSame('*', $row->target);
            $this->assertSame('failed', $row->status);
            $this->assertSame('cli', $row->run_source);
            $this->assertSame('synthetic test failure', $row->message);
            $this->assertSame(0, (int) $row->applied_count);
            $this->assertSame(7, (int) $row->duration_ms);
            $this->assertNull($row->run_by);
            $this->assertNull($row->ip);
        } finally {
            $this->deleteMigrationRunRow($rowId);
        }
    }

    /**
     * F2 (kabul kriteri 14): `run()`'s error branch returns `EXIT_ERROR` and
     * still writes a `status='failed'` audit row — exercised end to end
     * through the real `run()` method, not via Reflection.
     *
     * `createMigrationRunner()` is `protected` specifically so a test
     * subclass can override it (`Ci4msMigrate.php:65-76` docblock).
     * `MigrationRunner` is not `final`
     * (`vendor/codeigniter4/framework/system/Database/MigrationRunner.php:29`),
     * so `createMock()` can produce a double without ever touching the real
     * constructor, a real DB connection, or the real schema — `latest()`
     * never actually executes, so there is no `regress(-1)` hazard (unlike
     * a real `latest()` failure, which auto-regresses the previous batch
     * and can wipe a single-batch schema, see the class docblock above).
     * `recordRun()`'s own `db_connect()` is NOT mocked, so this test does
     * write a real audit row to `ci4ms_test.migration_runs` — cleaned up in
     * `finally` like every other test in this class.
     */
    public function testRunReturnsExitErrorAndWritesFailureAuditRowWhenLatestThrows(): void
    {
        $rowId = null;

        $stubRunner = $this->createMock(\CodeIgniter\Database\MigrationRunner::class);
        $stubRunner->method('setNamespace')->willReturnSelf();
        $stubRunner->method('getHistory')->willReturn([]);
        $stubRunner->method('latest')->willThrowException(new \RuntimeException('synthetic latest() failure'));

        $command = new class (service('logger'), service('commands')) extends Ci4msMigrate {
            public \CodeIgniter\Database\MigrationRunner $stubRunner;

            protected function createMigrationRunner(): \CodeIgniter\Database\MigrationRunner
            {
                return $this->stubRunner;
            }
        };
        $command->stubRunner = $stubRunner;

        try {
            $exit = $command->run([]);

            $this->assertSame(EXIT_ERROR, $exit, 'run() must return a non-zero exit code when latest() throws.');

            $row = $this->db->table('migration_runs')
                ->orderBy('id', 'DESC')
                ->limit(1)
                ->get()
                ->getRow();

            $this->assertNotNull($row, 'run() did not write a migration_runs audit row on the error branch.');
            $rowId = (int) $row->id;

            $this->assertSame('migration', $row->kind);
            $this->assertSame('*', $row->target);
            $this->assertSame('failed', $row->status);
            $this->assertSame('cli', $row->run_source);
            $this->assertSame('synthetic latest() failure', $row->message);
            $this->assertSame(0, (int) $row->applied_count);
            $this->assertNull($row->batch);
            $this->assertNull($row->run_by);
        } finally {
            $this->deleteMigrationRunRow($rowId);
        }
    }

    /**
     * `diffAppliedMigrations()` (pure logic, no DB): returns only the rows
     * present in `$after` but not in `$before`, matched by `version`.
     */
    public function testDiffAppliedMigrationsReturnsOnlyNewRows(): void
    {
        $command = new Ci4msMigrate(service('logger'), service('commands'));

        $method = new ReflectionMethod($command, 'diffAppliedMigrations');
        $method->setAccessible(true);

        $rowA = (object) ['version' => '2026-01-01-000001', 'class' => 'A', 'batch' => 1];
        $rowB = (object) ['version' => '2026-01-01-000002', 'class' => 'B', 'batch' => 1];
        $rowC = (object) ['version' => '2026-01-02-000001', 'class' => 'C', 'batch' => 2];

        $before = [$rowA, $rowB];
        $after  = [$rowA, $rowB, $rowC];

        $diff = $method->invoke($command, $before, $after);

        $this->assertCount(1, $diff);
        $this->assertSame('2026-01-02-000001', $diff[0]->version);
    }

    /**
     * `diffAppliedMigrations()`: when `$before` and `$after` are identical,
     * nothing is reported as newly applied.
     */
    public function testDiffAppliedMigrationsReturnsEmptyWhenNothingNew(): void
    {
        $command = new Ci4msMigrate(service('logger'), service('commands'));

        $method = new ReflectionMethod($command, 'diffAppliedMigrations');
        $method->setAccessible(true);

        $rowA = (object) ['version' => '2026-01-01-000001', 'class' => 'A', 'batch' => 1];

        $diff = $method->invoke($command, [$rowA], [$rowA]);

        $this->assertSame([], $diff);
    }

    /**
     * F1 regression test (kabul kriteri 13): `diffAppliedMigrations()` must
     * match on a `version + class` composite key, not `version` alone.
     *
     * `run()` uses a non-namespaced runner (`setNamespace(null)`), so
     * `getHistory()` returns rows across every namespace un-filtered
     * (`vendor/codeigniter4/framework/system/Database/MigrationRunner.php:708-711`
     * only restricts by namespace when `$this->namespace !== null`) — and
     * this repo has a real `version` collision across namespaces (verified
     * independently: listing every module's migration filenames, stripping
     * each down to its leading timestamp, and sorting for duplicates
     * surfaces exactly one hit, `2026-03-12-001000`):
     * `modules/Blog/Database/Migrations/2026-03-12-001000_CreateBlogLangsTable.php`
     * and `modules/Pages/Database/Migrations/2026-03-12-001000_CreatePagesLangsTable.php`.
     *
     * With the OLD (buggy) version-only matching logic, this exact scenario
     * was independently reproduced via `php -r` (outside PHPUnit, no
     * framework bootstrap) and printed:
     * `OLD (version-only) logic: newly applied rows detected = 0` — the
     * Pages row is silently dropped because its `version` already appears
     * in `$before` (contributed by Blog). Under that logic this test's
     * `assertCount(1, $diff)` below would fail with an actual count of `0`
     * — i.e. this test is RED against the old logic and GREEN against the
     * current `version + class` composite key (`Ci4msMigrate.php:198-208`,
     * which replicates the framework's own
     * `MigrationRunner::getObjectUid()`, `:604-608`).
     *
     * Deliberately asserts BOTH `version` AND `class` on the surviving row
     * (not `assertNotSame` on version alone) — a version-only assertion
     * could pass "by accident" under the old buggy matching for a
     * differently-shaped scenario; asserting `class` locks in that the
     * survivor really is the Pages row, not some other artifact.
     */
    public function testDiffAppliedMigrationsMatchesOnVersionAndClassAcrossNamespaceCollision(): void
    {
        $blogRow = (object) [
            'version' => '2026-03-12-001000',
            'class'   => 'CreateBlogLangsTable',
            'batch'   => 1,
        ];
        $pagesRow = (object) [
            'version' => '2026-03-12-001000',
            'class'   => 'CreatePagesLangsTable',
            'batch'   => 2,
        ];

        // $before: only Blog's migration has been applied so far (batch 1).
        $before = [$blogRow];
        // $after: Pages' migration (same version, different class) was just
        // applied in batch 2 — this is what a real `setNamespace(null)`
        // run across App+Modules produces when the two namespaces collide
        // on `version`.
        $after = [$blogRow, $pagesRow];

        $command = new Ci4msMigrate(service('logger'), service('commands'));

        $method = new ReflectionMethod($command, 'diffAppliedMigrations');
        $method->setAccessible(true);

        $diff = $method->invoke($command, $before, $after);

        $this->assertCount(
            1,
            $diff,
            'Expected exactly 1 newly-applied row (Pages); the old version-only match would silently drop it (undercount = 0).',
        );
        $this->assertSame('2026-03-12-001000', $diff[0]->version);
        $this->assertSame('CreatePagesLangsTable', $diff[0]->class);
    }

    /**
     * `resolveBatch()` (pure logic, no DB): when rows were applied, the
     * batch of the newly applied rows is used.
     */
    public function testResolveBatchReturnsAppliedBatchWhenSomethingApplied(): void
    {
        $command = new Ci4msMigrate(service('logger'), service('commands'));

        $method = new ReflectionMethod($command, 'resolveBatch');
        $method->setAccessible(true);

        $applied = [(object) ['version' => 'v2', 'class' => 'B', 'batch' => 3]];
        $after   = [(object) ['version' => 'v1', 'class' => 'A', 'batch' => 2], $applied[0]];

        $this->assertSame(3, $method->invoke($command, $applied, $after));
    }

    /**
     * `resolveBatch()`: when nothing was applied but history exists, the
     * batch of the most recent existing row is used.
     */
    public function testResolveBatchReturnsLastExistingBatchWhenNothingApplied(): void
    {
        $command = new Ci4msMigrate(service('logger'), service('commands'));

        $method = new ReflectionMethod($command, 'resolveBatch');
        $method->setAccessible(true);

        $after = [
            (object) ['version' => 'v1', 'class' => 'A', 'batch' => 1],
            (object) ['version' => 'v2', 'class' => 'B', 'batch' => 2],
        ];

        $this->assertSame(2, $method->invoke($command, [], $after));
    }

    /**
     * `resolveBatch()`: with no applied rows and no history at all, `null`
     * is returned.
     */
    public function testResolveBatchReturnsNullWhenNoHistoryAtAll(): void
    {
        $command = new Ci4msMigrate(service('logger'), service('commands'));

        $method = new ReflectionMethod($command, 'resolveBatch');
        $method->setAccessible(true);

        $this->assertNull($method->invoke($command, [], []));
    }

    /**
     * FAZ 5 I1(a): when the run lock is already held by "another process",
     * `run()` returns `EXIT_ERROR` immediately and never calls `latest()`.
     *
     * `createRunLock()` is overridden to point a real `RunLock` at an
     * isolated, per-test lock file (never the shared
     * `WRITEPATH.'locks/migration_manager.lock'` other suites rely on). The
     * test's own `$holderLock` acquires that same file first to simulate a
     * concurrent run holding it. `createMigrationRunner()` is additionally
     * overridden to inject a mock whose `latest()`/`getHistory()` are
     * asserted `never()` called — proving `run()`'s lock check
     * (`Ci4msMigrate.php:126-138`) short-circuits before touching the
     * runner, so there is no `regress(-1)` exposure here.
     */
    public function testRunReturnsExitErrorImmediatelyWithoutCallingLatestWhenRunLockIsHeld(): void
    {
        $lockPath   = WRITEPATH . 'locks/test_ci4msmigrate_' . uniqid('', true) . '.lock';
        $holderLock = new RunLock($lockPath);

        try {
            $this->assertTrue(
                $holderLock->acquire(),
                'Test setup failed: could not acquire the isolated lock file to simulate another process holding it.',
            );

            $neverCalledRunner = $this->createMock(\CodeIgniter\Database\MigrationRunner::class);
            $neverCalledRunner->method('setNamespace')->willReturnSelf();
            $neverCalledRunner->expects($this->never())->method('latest');
            $neverCalledRunner->expects($this->never())->method('getHistory');

            $command = new class (service('logger'), service('commands')) extends Ci4msMigrate {
                public string $isolatedLockPath;
                public \CodeIgniter\Database\MigrationRunner $stubRunner;

                protected function createRunLock(): ?object
                {
                    return new RunLock($this->isolatedLockPath);
                }

                protected function createMigrationRunner(): \CodeIgniter\Database\MigrationRunner
                {
                    return $this->stubRunner;
                }
            };
            $command->isolatedLockPath = $lockPath;
            $command->stubRunner       = $neverCalledRunner;

            $exit = $command->run([]);

            $this->assertSame(EXIT_ERROR, $exit, 'run() must return EXIT_ERROR when the run lock is already held by another process.');
        } finally {
            $holderLock->release();

            if (file_exists($lockPath)) {
                @unlink($lockPath);
            }
        }
    }

    /**
     * FAZ 5 I1(b): after a successful run, the run lock is released — proven
     * by re-acquiring the same isolated path immediately afterwards.
     *
     * FAZ 8: `RunLock` moved from TTL/mtime semantics to
     * `flock(LOCK_EX|LOCK_NB)` and its `release()` intentionally never
     * `unlink()`s the lock file anymore (see
     * `modules/MigrationManager/Libraries/RunLock.php:154-188` — deleting a
     * flock()'d file is a classic TOCTOU/inode-reuse hazard). The lock file
     * is therefore expected to still be present on disk after `run()`
     * finishes; only a fresh `acquire()` on the same path proves the flock
     * itself is free.
     *
     * `createMigrationRunner()` is NOT overridden here, so this exercises
     * the real `latest()` against the shared `ci4ms_test` schema — the exact
     * same "already up to date" success path Test A/B already accept
     * (schema is kept current by `migrateOnce`), so this adds no new
     * `regress(-1)` exposure. The resulting `migration_runs` audit row is
     * cleaned up like every other test in this class.
     */
    public function testRunLockIsReleasedAfterASuccessfulRun(): void
    {
        $lockPath = WRITEPATH . 'locks/test_ci4msmigrate_' . uniqid('', true) . '.lock';
        $rowId    = null;

        try {
            $command = new class (service('logger'), service('commands')) extends Ci4msMigrate {
                public string $isolatedLockPath;

                protected function createRunLock(): ?object
                {
                    return new RunLock($this->isolatedLockPath);
                }
            };
            $command->isolatedLockPath = $lockPath;

            $exit = $command->run([]);

            $this->assertSame(EXIT_SUCCESS, $exit);
            $this->assertFileExists(
                $lockPath,
                'The lock file itself must remain on disk after run() finishes — RunLock\'s flock()-based '
                    . 'release() never unlink()s it (see RunLock.php:154-188); the file staying on disk is '
                    . 'intentional and does NOT mean the lock is still held (proven below by re-acquiring).',
            );

            $row = $this->db->table('migration_runs')
                ->orderBy('id', 'DESC')
                ->limit(1)
                ->get()
                ->getRow();
            $this->assertNotNull($row, 'run() did not write a migration_runs audit row on the success path.');
            $rowId = (int) $row->id;

            $verifyLock = new RunLock($lockPath);
            $this->assertTrue(
                $verifyLock->acquire(),
                'The isolated lock must be acquirable again immediately after run() released it (idempotent proof).',
            );
            $verifyLock->release();
        } finally {
            $this->deleteMigrationRunRow($rowId);

            if (file_exists($lockPath)) {
                @unlink($lockPath);
            }
        }
    }

    /**
     * FAZ 6 G2: `testRunLockIsReleasedAfterASuccessfulRun()` above only
     * proves the lock is gone once `run()` has fully returned — it cannot
     * tell whether the closure-internal, signal-safe `release()`
     * (`Ci4msMigrate.php:159`) did the job, or whether the outer `finally`
     * safety net (`:177`) alone "rescued" a broken `:159` (quality-lead
     * FAZ5 proved this experimentally: commenting out `:159` left that test
     * green). This test pins the assertion to the one window where the two
     * calls are distinguishable: `createMigrationRunner()` injects a mock
     * `MigrationRunner` whose `getHistory()` is called exactly twice by
     * `run()` — once before `latest()` (`:145`) and once right after the
     * `withSignalsBlocked()` closure returns (`:168`), which is AFTER the
     * closure's own `finally` (`:159`) but BEFORE the outer `finally`
     * (`:177`) has had any chance to run. On the second call, a PROBE
     * `RunLock` on the same isolated lock path attempts its own `acquire()`;
     * a successful probe means the underlying flock was genuinely free at
     * that point, which can only be `:159`'s doing (`:177` has not run yet).
     *
     * FAZ 8: `file_exists($lockPath)` was retired for this check.
     * `RunLock`'s `flock()`-based `release()` never `unlink()`s the file
     * (see `RunLock.php:154-188`), so the file exists both BEFORE and AFTER
     * release() — checking for its mere presence stopped distinguishing
     * "locked" from "free" the moment `RunLock` moved off TTL/mtime
     * semantics; it would now report "still locked" forever, on both the
     * first and the second `getHistory()` call, making the whole test a
     * false negative. A probe `acquire()` is the only thing that still
     * tells the two states apart.
     */
    public function testRunLockIsReleasedByTheSignalSafeInnerReleaseNotJustTheOuterFinally(): void
    {
        $lockPath = WRITEPATH . 'locks/test_ci4msmigrate_' . uniqid('', true) . '.lock';
        $rowId    = null;

        $getHistoryCallCount           = 0;
        $lockWasFreeOnSecondGetHistory = null;

        $stubRunner = $this->createMock(\CodeIgniter\Database\MigrationRunner::class);
        $stubRunner->method('setNamespace')->willReturnSelf();
        $stubRunner->method('latest')->willReturn(true);
        $stubRunner->method('getCliMessages')->willReturn([]);
        $stubRunner->method('getHistory')->willReturnCallback(
            function () use (&$getHistoryCallCount, &$lockWasFreeOnSecondGetHistory, $lockPath): array {
                $getHistoryCallCount++;

                if ($getHistoryCallCount === 2) {
                    // Probe: aynı yola bağımsız bir RunLock ile acquire()
                    // dene. Başarılıysa asıl kilit o an genuinely serbesttir
                    // (iç release() işini yaptı) — hemen bırak, akışı bozma.
                    $probe = new RunLock($lockPath);
                    $lockWasFreeOnSecondGetHistory = $probe->acquire();

                    if ($lockWasFreeOnSecondGetHistory) {
                        $probe->release();
                    }
                }

                return [];
            },
        );

        $command = new class (service('logger'), service('commands')) extends Ci4msMigrate {
            public string $isolatedLockPath;
            public \CodeIgniter\Database\MigrationRunner $stubRunner;

            protected function createRunLock(): ?object
            {
                return new RunLock($this->isolatedLockPath);
            }

            protected function createMigrationRunner(): \CodeIgniter\Database\MigrationRunner
            {
                return $this->stubRunner;
            }
        };
        $command->isolatedLockPath = $lockPath;
        $command->stubRunner       = $stubRunner;

        try {
            $exit = $command->run([]);

            $this->assertSame(EXIT_SUCCESS, $exit);
            $this->assertSame(
                2,
                $getHistoryCallCount,
                'Test setup invariant broken: run() must call getHistory() exactly twice (before and after latest()).',
            );
            $this->assertTrue(
                $lockWasFreeOnSecondGetHistory,
                'A probe RunLock must be able to acquire the same lock path by the second getHistory() call — '
                    . 'proving the closure-internal, signal-safe release() (Ci4msMigrate.php:159) already freed '
                    . 'the flock, not the outer finally (:177), which has not executed yet at this point in run().',
            );

            $row = $this->db->table('migration_runs')
                ->orderBy('id', 'DESC')
                ->limit(1)
                ->get()
                ->getRow();
            $this->assertNotNull($row, 'run() did not write a migration_runs audit row on the success path.');
            $rowId = (int) $row->id;
        } finally {
            $this->deleteMigrationRunRow($rowId);

            if (file_exists($lockPath)) {
                @unlink($lockPath);
            }
        }
    }

    /**
     * FAZ 5 I1(c): when `Modules\MigrationManager` is absent —
     * `runLockClassName()` overridden to return a non-existent FQCN, so the
     * real `class_exists()` genuinely evaluates to `false` — `run()` still
     * succeeds. `createRunLock()`'s early `return null;` on that guard means
     * `RunLock` is never instantiated and no lock file, isolated or shared,
     * is ever touched by this test.
     */
    public function testRunSucceedsWithoutARunLockWhenTheRunLockClassDoesNotExist(): void
    {
        $rowId = null;

        $command = new class (service('logger'), service('commands')) extends Ci4msMigrate {
            protected function runLockClassName(): string
            {
                return 'Nonexistent\\Ci4msTest\\FakeRunLock';
            }
        };

        try {
            $exit = $command->run([]);

            $this->assertSame(EXIT_SUCCESS, $exit, 'run() must still succeed when the run lock class does not exist (soft dependency).');

            $row = $this->db->table('migration_runs')
                ->orderBy('id', 'DESC')
                ->limit(1)
                ->get()
                ->getRow();
            $this->assertNotNull($row, 'run() did not write a migration_runs audit row on the success path.');
            $rowId = (int) $row->id;

            $this->assertSame('success', $row->status);
        } finally {
            $this->deleteMigrationRunRow($rowId);
        }
    }
}
