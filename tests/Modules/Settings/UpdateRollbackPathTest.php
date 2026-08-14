<?php

declare(strict_types=1);

namespace Tests\Modules\Settings;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\TestLogger;
use Modules\Settings\Libraries\UpdateService;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionProperty;
use Tests\Support\Settings\StubCurlRequest;

/**
 * Restore-source selection tests for UpdateService::resolveBackupDir().
 *
 * rollbackUpdate() used to build its restore source by concatenating the
 * backup_name POST field onto WRITEPATH.'backups/'. The write side was already
 * confined to the project root, but the read side was not, so the submitted name
 * decided which directory got copied over the installation — ../tmp reached the
 * directory theme and module ZIPs are extracted into.
 *
 * The selection now lives in UpdateService so it can be tested without HTTP, a
 * database or an authenticated session. Every case here runs against a real
 * scratch filesystem: the traversal targets genuinely exist, so a passing test
 * proves a reachable escape was refused rather than a missing path.
 *
 * @internal
 */
final class UpdateRollbackPathTest extends CIUnitTestCase
{
    /** Name of the backup the tests are allowed to resolve. */
    private const GOOD_BACKUP = 'v0.34.0.0_to_v0.35.0.0_20260727_120000';

    /** A second real backup, used to prove matching is exact rather than prefix based. */
    private const OTHER_BACKUP = 'v0.33.0.0_to_v0.34.0.0_20260701_090000';

    /** Root of the scratch tree; everything below it is removed in tearDown(). */
    private string $scratch;

    /** Stands in for WRITEPATH.'backups/'. */
    private string $backupBase;

    /** A real directory outside the backup base that traversal payloads aim at. */
    private string $outside;

    protected function setUp(): void
    {
        parent::setUp();

        $this->scratch    = sys_get_temp_dir() . '/ci4ms_rollback_' . bin2hex(random_bytes(6));
        $this->backupBase = $this->scratch . '/backups/';
        $this->outside    = $this->scratch . '/outside';

        mkdir($this->backupBase . self::GOOD_BACKUP, 0777, true);
        mkdir($this->backupBase . self::OTHER_BACKUP, 0777, true);
        mkdir($this->outside, 0777, true);

        file_put_contents($this->backupBase . self::GOOD_BACKUP . '/marker.txt', 'restored');
        file_put_contents($this->outside . '/shell.php', '<?php // staged payload');

        // listBackups() globs with GLOB_ONLYDIR, so a stray archive must not be selectable.
        file_put_contents($this->backupBase . 'backup_2026-05-16.zip', 'not a directory');

        $this->clearLoggedMessages();
    }

    protected function tearDown(): void
    {
        $this->removeTree($this->scratch);

        parent::tearDown();
    }

    public function testResolvesAKnownBackupToItsRealDirectory(): void
    {
        $resolved = $this->service()->resolveBackupDir(self::GOOD_BACKUP);

        $this->assertNotNull($resolved);
        $this->assertSame(realpath($this->backupBase . self::GOOD_BACKUP) . '/', $resolved);
        $this->assertFileExists($resolved . 'marker.txt');
    }

    public function testAcceptsEveryNameTheListingAdvertises(): void
    {
        $service = $this->service();

        foreach ($service->listBackups() as $item) {
            $this->assertNotNull(
                $service->resolveBackupDir($item['name']),
                "listBackups() advertised {$item['name']} but resolveBackupDir() refused it",
            );
        }
    }

    /**
     * @param string $payload Value an attacker would submit as backup_name
     */
    #[DataProvider('traversalPayloads')]
    public function testRefusesPathTraversalPayloads(string $payload): void
    {
        $this->assertNull($this->service()->resolveBackupDir($payload));
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
            ['../outside/'],
            ['..'],
            ['.'],
            ['../'],
            ['/etc'],
            ['/'],
            [''],
            ['   '],
            ['../../../../../../etc/passwd'],
            ["../outside\0"],
            ["..\n/outside"],
            ['%2e%2e%2foutside'],
            ['....//outside'],
        ];
    }

    /**
     * The escape targets are only meaningful if they really exist; otherwise the
     * test above would pass against a codebase with no guard at all.
     */
    public function testTheTraversalTargetIsGenuinelyReachableByConcatenation(): void
    {
        $concatenated = $this->backupBase . '../outside' . '/';

        $this->assertDirectoryExists($concatenated);
        $this->assertFileExists($concatenated . 'shell.php');
        $this->assertSame(realpath($this->outside), realpath($concatenated));

        $this->assertNull($this->service()->resolveBackupDir('../outside'));
    }

    public function testRefusesAnArchiveSittingBesideTheBackups(): void
    {
        $this->assertFileExists($this->backupBase . 'backup_2026-05-16.zip');
        $this->assertNull($this->service()->resolveBackupDir('backup_2026-05-16.zip'));
    }

    public function testRefusesASymlinkPlantedInsideTheBackupDirectory(): void
    {
        if (! @symlink($this->outside, $this->backupBase . 'v9.9.9.9_to_v9.9.9.9_evil')) {
            $this->markTestSkipped('The filesystem does not allow creating symlinks here.');
        }

        $resolved = $this->service()->resolveBackupDir('v9.9.9.9_to_v9.9.9.9_evil');

        $this->assertNull($resolved, 'A symlinked backup entry must not resolve outside the backup directory');
        $this->assertNotEmpty(
            array_filter(
                $this->loggedAt('warning'),
                static fn (string $message): bool => str_contains($message, 'outside the backup directory'),
            ),
            'Refusing a symlinked backup entry must leave a warning behind',
        );
    }

    /**
     * macOS ships a case-insensitive filesystem, so is_dir() would have accepted a
     * case-flipped name. hash_equals() is case sensitive and the listing is the
     * only source of truth, which makes the guard stricter than the filesystem.
     */
    public function testRefusesACaseFlippedNameEvenWhenTheFilesystemWouldAcceptIt(): void
    {
        $flipped = strtoupper(self::GOOD_BACKUP);

        $this->assertNotSame(self::GOOD_BACKUP, $flipped);
        $this->assertNull($this->service()->resolveBackupDir($flipped));
    }

    /**
     * @param string $payload Name that shares a prefix or suffix with a real backup
     */
    #[DataProvider('partialNames')]
    public function testRefusesPartialMatches(string $payload): void
    {
        $this->assertNull($this->service()->resolveBackupDir($payload));
    }

    /**
     * @return list<array{string}>
     */
    public static function partialNames(): array
    {
        return [
            ['v0.34.0.0_to_v0.35.0.0'],
            ['v0.34.0.0_to_v0.35.0.0_20260727_120000_extra'],
            ['0.34.0.0_to_v0.35.0.0_20260727_120000'],
            ['v0.34.0.0_to_v0.35.0.0_20260727_12000'],
        ];
    }

    public function testResolutionDoesNotDependOnATrailingSlashOrSurroundingSpace(): void
    {
        $expected = realpath($this->backupBase . self::GOOD_BACKUP) . '/';

        $this->assertSame($expected, $this->service()->resolveBackupDir('  ' . self::GOOD_BACKUP . '  '));
        $this->assertSame($expected, $this->service()->resolveBackupDir(self::GOOD_BACKUP . '/'));
    }

    public function testReturnsNullWhenTheBackupDirectoryDoesNotExist(): void
    {
        $service = new UpdateService(new StubCurlRequest());
        (new ReflectionProperty(UpdateService::class, 'backupBaseDir'))
            ->setValue($service, $this->scratch . '/no-such-directory/');

        $this->assertNull($service->resolveBackupDir(self::GOOD_BACKUP));
    }

    public function testRefusingANameLeavesTheScratchTreeUntouched(): void
    {
        $before = $this->treeSnapshot($this->scratch);

        foreach (self::traversalPayloads() as [$payload]) {
            $this->service()->resolveBackupDir($payload);
        }

        $this->assertSame($before, $this->treeSnapshot($this->scratch));
    }

    /**
     * An UpdateService whose backup base points at the scratch tree.
     */
    private function service(): UpdateService
    {
        $service = new UpdateService(new StubCurlRequest());

        (new ReflectionProperty(UpdateService::class, 'backupBaseDir'))
            ->setValue($service, $this->backupBase);

        return $service;
    }

    /**
     * @return list<string> Every path under $dir, sorted
     */
    private function treeSnapshot(string $dir): array
    {
        $paths = [];

        foreach ((array) glob($dir . '/*') as $path) {
            $paths[] = (string) $path;

            if (is_dir((string) $path) && ! is_link((string) $path)) {
                $paths = array_merge($paths, $this->treeSnapshot((string) $path));
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
