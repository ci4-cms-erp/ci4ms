<?php

declare(strict_types=1);

namespace Tests\Modules\Backend;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Backend\Libraries\ZipSecurityValidator;
use ZipArchive;

/**
 * Locks the shared pre-extraction ZIP hardening extracted from the theme and
 * module upload paths. Every malicious-entry class must be rejected with the
 * right reason, and the theme path in particular must now also reject the two
 * classes it previously missed (Windows drive-letter prefixes, NUL bytes).
 *
 * Size checks use tiny caps passed straight into validate() so the "zip-bomb"
 * cases stay in the byte range and never allocate the real 20MB/50MB limits.
 */
final class ZipSecurityValidatorTest extends CIUnitTestCase
{
    private ZipSecurityValidator $validator;
    /** @var list<string> */
    private array $tmpFiles = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ZipSecurityValidator();
    }

    protected function tearDown(): void
    {
        foreach ($this->tmpFiles as $f) {
            if (is_file($f)) {
                @unlink($f);
            }
        }
        parent::tearDown();
    }

    /**
     * @param array<int, array{name: string, data?: string, extAttr?: int}> $entries
     */
    private function makeZip(array $entries): string
    {
        $path = tempnam(sys_get_temp_dir(), 'zsv') . '.zip';
        $this->tmpFiles[] = $path;

        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'temp zip could not be created');
        foreach ($entries as $e) {
            $zip->addFromString($e['name'], $e['data'] ?? 'x');
            if (isset($e['extAttr'])) {
                $zip->setExternalAttributesName($e['name'], ZipArchive::OPSYS_UNIX, $e['extAttr']);
            }
        }
        $zip->close();

        return $path;
    }

    private function open(string $path): ZipArchive
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($path) === true, 'temp zip could not be reopened');
        return $zip;
    }

    public function testCleanArchivePasses(): void
    {
        $zip = $this->open($this->makeZip([
            ['name' => 'MyModule/Config/Routes.php', 'data' => '<?php'],
            ['name' => 'MyModule/info.xml', 'data' => '<info/>'],
        ]));
        $result = $this->validator->validate($zip, 1024, 4096);
        $zip->close();

        $this->assertTrue($result['ok']);
        $this->assertSame(ZipSecurityValidator::OK, $result['reason']);
    }

    public function testAbsolutePathRejected(): void
    {
        $zip = $this->open($this->makeZip([['name' => '/etc/passwd']]));
        $result = $this->validator->validate($zip, 1024, 4096);
        $zip->close();

        $this->assertFalse($result['ok']);
        $this->assertSame(ZipSecurityValidator::PATH_TRAVERSAL, $result['reason']);
    }

    public function testParentTraversalRejected(): void
    {
        $zip = $this->open($this->makeZip([['name' => 'a/../../b']]));
        $result = $this->validator->validate($zip, 1024, 4096);
        $zip->close();

        $this->assertFalse($result['ok']);
        $this->assertSame(ZipSecurityValidator::PATH_TRAVERSAL, $result['reason']);
    }

    /** Union gain: the theme path did not reject this before. */
    public function testWindowsDriveLetterRejected(): void
    {
        $zip = $this->open($this->makeZip([['name' => 'C:\\Windows\\evil']]));
        $result = $this->validator->validate($zip, 1024, 4096);
        $zip->close();

        $this->assertFalse($result['ok']);
        $this->assertSame(ZipSecurityValidator::PATH_TRAVERSAL, $result['reason']);
    }

    public function testOversizedEntryRejected(): void
    {
        $zip = $this->open($this->makeZip([['name' => 'big.bin', 'data' => str_repeat('A', 200)]]));
        // per-entry cap of 100 bytes; the 200-byte entry must trip it.
        $result = $this->validator->validate($zip, 100, 100000);
        $zip->close();

        $this->assertFalse($result['ok']);
        $this->assertSame(ZipSecurityValidator::ZIP_BOMB, $result['reason']);
    }

    public function testOversizedTotalRejected(): void
    {
        $zip = $this->open($this->makeZip([
            ['name' => 'a.bin', 'data' => str_repeat('A', 80)],
            ['name' => 'b.bin', 'data' => str_repeat('B', 80)],
        ]));
        // Each entry (80) is under the per-entry cap (100) but the running
        // total (160) exceeds the total cap (150).
        $result = $this->validator->validate($zip, 100, 150);
        $zip->close();

        $this->assertFalse($result['ok']);
        $this->assertSame(ZipSecurityValidator::ZIP_BOMB, $result['reason']);
    }

    public function testSymlinkRejected(): void
    {
        // S_IFLNK (0xA000) | 0777, shifted into the upper 16 bits of external_attr.
        $zip = $this->open($this->makeZip([['name' => 'link', 'data' => 'target', 'extAttr' => (0xA000 | 0777) << 16]]));
        $result = $this->validator->validate($zip, 1024, 4096);
        $zip->close();

        $this->assertFalse($result['ok']);
        $this->assertSame(ZipSecurityValidator::SYMLINK, $result['reason']);
    }

    /** A regular file with mode bits set but no S_IFLNK must NOT be flagged. */
    public function testRegularFileWithModeBitsPasses(): void
    {
        $zip = $this->open($this->makeZip([['name' => 'ok.txt', 'data' => 'hi', 'extAttr' => (0x8000 | 0644) << 16]]));
        $result = $this->validator->validate($zip, 1024, 4096);
        $zip->close();

        $this->assertTrue($result['ok']);
    }

    /** Path checks must win over size checks for the same entry. */
    public function testPathCheckPrecedesSize(): void
    {
        $zip = $this->open($this->makeZip([['name' => '../huge', 'data' => str_repeat('A', 200)]]));
        $result = $this->validator->validate($zip, 100, 100);
        $zip->close();

        $this->assertFalse($result['ok']);
        $this->assertSame(ZipSecurityValidator::PATH_TRAVERSAL, $result['reason']);
    }
}
