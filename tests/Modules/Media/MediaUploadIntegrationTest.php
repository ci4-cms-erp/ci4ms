<?php

declare(strict_types=1);

namespace Tests\Modules\Media;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Media\Controllers\Media;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionProperty;
use RuntimeException;

/**
 * Integration-level regression lock for the elFinder 2.1.68 -> 2.1.70 upgrade
 * security hardening: HIGH-1 (acceptedName / isNameAccepted wired into the
 * real upload() chain) and HIGH-2 (upload.presave path-traversal fix).
 *
 * Unlike MediaUploadGateTest / MediaAccessControlTest, which pin the pure
 * helper methods in isolation, everything here drives the REAL vendor
 * elFinder engine (\elFinder::exec()) against a REAL mounted
 * elFinderVolumeLocalFileSystem rooted in a disposable sandbox directory
 * (never public/media/), wired to Media's own real
 * volumeSecurityOptions()/isNameAccepted(). That distinction is the whole
 * point: isDeniedName() was correct in isolation but never actually reached
 * by elFinder's upload() call, and no amount of reflection-based unit testing
 * of isDeniedName() alone could have caught that.
 *
 * The upload.presave closure (HIGH-2) is defined inline inside
 * Media::elfinderConnection(), a method that ends in exit() and therefore
 * cannot be invoked directly from PHPUnit. extractUploadPresaveClosureSource()
 * pulls the literal, unmodified closure text out of the real source file via
 * token_get_all() and Closure::bind()s it to a real Media instance, so these
 * tests run the actual production closure rather than a hand-copied
 * reimplementation that could silently drift from it.
 *
 * @internal
 */
final class MediaUploadIntegrationTest extends CIUnitTestCase
{
    /**
     * Mirrors app/Database/Seeds/Ci4msReferenceDataSeeder.php:360
     * (Config\Security.allowedFiles) exactly.
     *
     * @var list<string>
     */
    private const ALLOWED_FILES = [
        'image/x-ms-bmp', 'image/gif', 'image/jpeg', 'image/png',
        'image/x-icon', 'text/plain', 'image/webp',
    ];

    private string $sandbox;

    /** @var array<string, mixed> */
    private array $originalPost;
    private string|false|null $originalRequestMethod;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sandbox = sys_get_temp_dir() . '/ci4ms-media-qa-' . bin2hex(random_bytes(6));
        mkdir($this->sandbox, 0777, true);

        // Real browser uploads are POSTs with cmd=upload; \elFinder's constructor
        // only registers a 'bind' handler when $_POST['cmd'] matches the bound
        // command name (elFinder.class.php:816-822) -- one of the two
        // integration traps documented in context.md's K3 section. Snapshot and
        // restore so this doesn't leak into other test files sharing the process.
        $this->originalPost           = $_POST;
        $this->originalRequestMethod  = $_SERVER['REQUEST_METHOD'] ?? null;
        $_SERVER['REQUEST_METHOD']    = 'POST';
        $_POST['cmd']                 = 'upload';
    }

    protected function tearDown(): void
    {
        $_POST = $this->originalPost;
        if ($this->originalRequestMethod === null) {
            unset($_SERVER['REQUEST_METHOD']);
        } else {
            $_SERVER['REQUEST_METHOD'] = $this->originalRequestMethod;
        }

        $this->removeDirectory($this->sandbox);
        parent::tearDown();
    }

    // ── KALEM 2 (HIGH-1): acceptedName gate through the real upload() chain ──

    /**
     * The whole HIGH-1 bug was a silent no-op: is_callable([$this, 'isNameAccepted'])
     * returns false from elFinder's external scope for a protected method, so
     * elFinderVolumeDriver::nameAccepted() falls back to accepting everything
     * without ever telling anyone. Pin the visibility directly so a future
     * "cleanup" cannot quietly reopen this by making the method protected again.
     */
    public function testIsNameAcceptedIsPublicSoElfinderCanActuallyCallIt(): void
    {
        $this->assertTrue(
            is_callable([new Media(), 'isNameAccepted']),
            "Media::isNameAccepted() must stay public: elFinderVolumeDriver::nameAccepted() " .
            'invokes it as an external array callable, and a protected method silently ' .
            'turns the acceptedName gate into a no-op instead of failing loudly.',
        );
    }

    /**
     * @return list<array{string}>
     */
    public static function maliciousUploadNames(): array
    {
        return [
            ['shell.php.png'],
            ['shell.phtml.png'],
            ['shell.inc'],
            ['shell.php.jpg'],
            ['x.php6'],
            ['x.phps'],
            ['x.ini'],
            ['a.php.png'],
        ];
    }

    /**
     * The context.md K2 PoC set: names elFinder's own MIME allowlist alone
     * accepts (see MediaUploadGateTest::testTheMimeAllowlistAloneAcceptsScriptExtensions),
     * driven through the real, mounted \elFinder::exec('upload', ...) chain with
     * Media's real volumeSecurityOptions()/isNameAccepted() wired in as
     * 'acceptedName'. Before the fix these all reached disk; the fix wires
     * isDeniedName() into nameAccepted() so upload() rejects them before any
     * MIME check ever runs.
     */
    #[DataProvider('maliciousUploadNames')]
    public function testRealMountedUploadRejectsMaliciousNames(string $name): void
    {
        $mediaRoot = $this->sandbox . '/media_root';
        mkdir($mediaRoot, 0777, true);

        $elfinder = $this->mountVolume($mediaRoot);
        $target   = $this->rootHash($elfinder);

        $src = $this->writeSourceFile("GIF89a\n<?php system(\$_GET['c']); ?>\n");

        $result = $this->execUpload($elfinder, $target, [
            'name'     => [$name],
            'type'     => ['image/gif'],
            'tmp_name' => [$src],
            'error'    => [0],
            'size'     => [filesize($src)],
        ]);

        $this->assertEmpty(
            $result['added'] ?? [],
            "'{$name}' must not be reported as added by the real upload() call.",
        );
        $this->assertFileDoesNotExist(
            $mediaRoot . '/' . $name,
            "'{$name}' must not land on disk inside the mounted media root.",
        );
    }

    /**
     * Regression guard: the acceptedName gate must not collaterally block
     * ordinary media once it's wired in.
     */
    public function testRealMountedUploadStillAcceptsALegitimateJpeg(): void
    {
        $mediaRoot = $this->sandbox . '/media_root';
        mkdir($mediaRoot, 0777, true);

        $elfinder = $this->mountVolume($mediaRoot);
        $target   = $this->rootHash($elfinder);

        $src = $this->writeSourceFile($this->realJpegBytes());

        $result = $this->execUpload($elfinder, $target, [
            'name'     => ['photo.jpg'],
            'type'     => ['image/jpeg'],
            'tmp_name' => [$src],
            'error'    => [0],
            'size'     => [filesize($src)],
        ]);

        $this->assertNotEmpty($result['added'] ?? [], 'A legitimate JPEG must still be accepted.');
        $this->assertFileExists($mediaRoot . '/photo.jpg');
    }

    // ── S11 (security re-audit MEDIUM-A): six newly denied extensions ────────

    /**
     * @return list<array{string}>
     */
    public static function sixNewlyDeniedExtensions(): array
    {
        return [
            ['poly.phtm'],
            ['poly.shtml'],
            ['poly.shtm'],
            ['poly.stm'],
            ['poly.hta'],
            ['poly.phpt'],
        ];
    }

    /**
     * MEDIUM-A from the security re-audit (2026-09-03): elFinder's MIME layer
     * name-types these six extensions as text/plain, which
     * settings.allowedFiles permits, so they reached disk before
     * DENIED_EXTENSIONS grew a phtm/phpt/shtml/shtm/stm/hta group (developer's
     * S11 fix). Same real, mounted upload() chain as
     * testRealMountedUploadRejectsMaliciousNames -- see Media.php's
     * DENIED_EXTENSIONS and public/media/.htaccess's FilesMatch list, which
     * mirrors it deliberately.
     */
    #[DataProvider('sixNewlyDeniedExtensions')]
    public function testRealMountedUploadRejectsTheSixNewlyDeniedExtensions(string $name): void
    {
        $mediaRoot = $this->sandbox . '/media_root';
        mkdir($mediaRoot, 0777, true);

        $elfinder = $this->mountVolume($mediaRoot);
        $target   = $this->rootHash($elfinder);

        $src = $this->writeSourceFile("GIF89a\n<?php system(\$_GET['c']); ?>\n");

        $result = $this->execUpload($elfinder, $target, [
            'name'     => [$name],
            'type'     => ['image/gif'],
            'tmp_name' => [$src],
            'error'    => [0],
            'size'     => [filesize($src)],
        ]);

        $this->assertEmpty(
            $result['added'] ?? [],
            "'{$name}' must not be reported as added by the real upload() call.",
        );
        $this->assertFileDoesNotExist(
            $mediaRoot . '/' . $name,
            "'{$name}' must not land on disk inside the mounted media root.",
        );
    }

    // ── KALEM 3 (HIGH-2): upload.presave path traversal ──────────────────────

    /**
     * name[0]='../../../OUTSIDE/pwned.png' with a Content-Type absent from the
     * volume's extTable (so elFinder.class.php's clipboard-substitution branch
     * leaves the attacker-controlled name untouched) must not place a file
     * outside the media root. Post-fix, basename() reduces the name to a
     * harmless 'pwned.png' before any path is computed from it, so the upload
     * is neutralized into an ordinary, successful upload inside the media root
     * rather than merely failing — this pins that exact behaviour, matching
     * context.md's K3 PoC "YAMA SONRASI" evidence.
     */
    public function testPresaveClosureNormalizesTraversalNameAndStaysInsideMediaRoot(): void
    {
        [$mediaRoot, $tmpDeep, $outside, $elfinder, $target] = $this->mountWithPresaveClosure();

        $src = tempnam($tmpDeep, 'src');
        file_put_contents($src, $this->realJpegBytes());

        $result = $this->execUpload($elfinder, $target, [
            'name'     => ['image.png'],
            'type'     => ['application/x-ci4ms-qa-unknown'],
            'tmp_name' => [$src],
            'error'    => [0],
            'size'     => [filesize($src)],
        ], [
            'name' => ['../../../OUTSIDE/pwned.png'],
        ]);

        $this->assertSame([], glob($outside . '/*') ?: [], 'Traversal must not place anything outside the media root.');
        $this->assertFileExists(
            $mediaRoot . '/pwned.webp',
            'basename() must reduce the traversal name to a harmless, ordinary upload inside the media root.',
        );
        $this->assertNotEmpty($result['added'] ?? [], 'The neutralized upload must still succeed (no regression).');
    }

    /**
     * A traversal name that, once basename()'d, is ALSO a denied double
     * extension must be rejected outright by isDeniedName() inside the
     * closure -- not normalized into an accepted upload.
     */
    public function testPresaveClosureBlocksTraversalCombinedWithADeniedExtension(): void
    {
        [$mediaRoot, $tmpDeep, $outside, $elfinder, $target] = $this->mountWithPresaveClosure();

        $src = tempnam($tmpDeep, 'src');
        file_put_contents($src, $this->realJpegBytes());

        $result = $this->execUpload($elfinder, $target, [
            'name'     => ['image.png'],
            'type'     => ['application/x-ci4ms-qa-unknown'],
            'tmp_name' => [$src],
            'error'    => [0],
            'size'     => [filesize($src)],
        ], [
            'name' => ['../../../OUTSIDE/shell.php.png'],
        ]);

        $this->assertSame([], glob($outside . '/*') ?: [], 'Traversal must not place anything outside the media root.');
        $this->assertSame(
            [],
            glob($mediaRoot . '/*') ?: [],
            'A traversal name that also carries a denied double extension must not land anywhere on disk.',
        );
        $this->assertEmpty($result['added'] ?? []);
    }

    /**
     * The presave closure feeds attacker-controlled tmp file content to
     * SimpleImage before allowPutMime() ever runs. \elFinder::exec() only
     * catches elFinderTriggerException (elFinder.class.php:1231-1240); any
     * other uncaught exception is a 500. The fix wraps the SimpleImage call in
     * try/catch and re-throws as elFinderTriggerException -- this proves the
     * conversion, not just that the item was rejected.
     */
    public function testPresaveClosureCatchesAnInvalidImageInsteadOfLettingAFatalEscape(): void
    {
        [$mediaRoot, $tmpDeep, , $elfinder, $target] = $this->mountWithPresaveClosure();

        $badSrc = tempnam($tmpDeep, 'bad');
        file_put_contents($badSrc, "not an image, just plain bytes\n");

        $result = null;
        $thrown = null;

        try {
            $result = $this->execUpload($elfinder, $target, [
                'name'     => ['image.png'],
                'type'     => ['application/x-ci4ms-qa-unknown'],
                'tmp_name' => [$badSrc],
                'error'    => [0],
                'size'     => [filesize($badSrc)],
            ], [
                'name' => ['corrupt.png'],
            ]);
        } catch (\Throwable $e) {
            $thrown = $e;
        }

        $this->assertNull(
            $thrown,
            'An unreadable image must not escape as an uncaught PHP fatal: ' . ($thrown?->getMessage() ?? ''),
        );
        $this->assertSame([], glob($mediaRoot . '/*') ?: [], 'A corrupt image must not be written anywhere.');
        $this->assertEmpty($result['added'] ?? []);
    }

    /**
     * Regression guard: convertWebp must still convert an ordinary,
     * non-traversal JPEG upload to .webp exactly as before the fix.
     */
    public function testPresaveClosureStillConvertsALegitimateJpegToWebp(): void
    {
        [$mediaRoot, $tmpDeep, , $elfinder, $target] = $this->mountWithPresaveClosure();

        $src = tempnam($tmpDeep, 'src');
        file_put_contents($src, $this->realJpegBytes());

        $result = $this->execUpload($elfinder, $target, [
            'name'     => ['photo.jpg'],
            'type'     => ['image/jpeg'],
            'tmp_name' => [$src],
            'error'    => [0],
            'size'     => [filesize($src)],
        ]);

        $this->assertFileExists($mediaRoot . '/photo.webp');
        $this->assertNotEmpty($result['added'] ?? []);
    }

    // ── shared harness ────────────────────────────────────────────────────

    /**
     * Mounts a real elFinderVolumeLocalFileSystem rooted at $mediaRoot, wired
     * to Media's real volumeSecurityOptions()/isNameAccepted() via reflection
     * on the (protected) volumeSecurityOptions() helper only -- the acceptedName
     * callable it returns is invoked for real by elFinder itself, never called
     * directly by the test.
     *
     * @param array<string, mixed> $bind  Optional top-level 'bind' entries (must
     *                                    stay a top-level $opts key, not nested
     *                                    inside roots -- the second documented
     *                                    integration trap).
     * @param Media|null            $media The controller instance elfinderAccess()/
     *                                    isNameAccepted() are bound to. mediaCanWrite
     *                                    is forced true here: these tests exercise the
     *                                    name/upload gates specifically, independent
     *                                    of the Layer 1/2 permission gate that
     *                                    MediaAccessControlTest already pins.
     */
    private function mountVolume(string $mediaRoot, array $bind = [], ?Media $media = null): \elFinder
    {
        $media ??= new Media();

        $writable = new ReflectionProperty(Media::class, 'mediaCanWrite');
        $writable->setAccessible(true);
        $writable->setValue($media, true);

        $method = new ReflectionMethod(Media::class, 'volumeSecurityOptions');
        $method->setAccessible(true);
        $security = $method->invoke($media, self::ALLOWED_FILES, []);

        $opts = [
            'debug' => false,
            'roots' => [
                ['driver' => 'LocalFileSystem', 'path' => $mediaRoot, 'URL' => 'http://localhost/media/'] + $security,
            ],
        ];
        if ($bind !== []) {
            $opts['bind'] = $bind;
        }

        $elfinder = new \elFinder($opts);
        $this->assertTrue($elfinder->loaded(), 'sandbox volume failed to mount: ' . implode('; ', $elfinder->mountErrors));

        return $elfinder;
    }

    /**
     * Builds the K3 sandbox layout: dirname($tmpname) sits three levels below
     * $tmpBase, so '../../../OUTSIDE' resolves to exactly $tmpBase/OUTSIDE --
     * reproducing context.md's K3 PoC directory layout so a regression would
     * actually land a file where this test can see it.
     *
     * @return array{0: string, 1: string, 2: string, 3: \elFinder, 4: string}
     *         [mediaRoot, tmpDeepDir, outsideDir, elfinder, targetHash]
     */
    private function mountWithPresaveClosure(): array
    {
        $mediaRoot = $this->sandbox . '/media_root';
        mkdir($mediaRoot, 0777, true);

        $tmpBase = $this->sandbox . '/tmp';
        $tmpDeep = $tmpBase . '/a/b/c';
        $outside = $tmpBase . '/OUTSIDE';
        mkdir($tmpDeep, 0777, true);
        mkdir($outside, 0777, true);

        $media          = new Media();
        $media->defData = ['settings' => (object) ['convertWebp' => true]];
        $closure        = $this->boundPresaveClosure($media);

        $elfinder = $this->mountVolume($mediaRoot, ['upload.presave' => [$closure]], $media);
        $target   = $this->rootHash($elfinder);

        return [$mediaRoot, $tmpDeep, $outside, $elfinder, $target];
    }

    /**
     * Extracts the real, unmodified upload.presave closure straight out of
     * Media.php's source via the tokenizer and binds it to a real Media
     * instance. See the class docblock for why this technique is used instead
     * of hand-copying the closure's logic into the test.
     */
    private function boundPresaveClosure(Media $media): \Closure
    {
        $source  = $this->extractUploadPresaveClosureSource();
        $closure = eval('return ' . $source . ';');

        if (! $closure instanceof \Closure) {
            throw new RuntimeException('Extracted upload.presave source did not evaluate to a Closure.');
        }

        return \Closure::bind($closure, $media, Media::class);
    }

    /**
     * Locates the anonymous function bound to 'upload.presave' inside
     * Media::elfinderConnection() by scanning tokens for a `function` whose
     * parameter list contains `$thash` (its first, distinguishing parameter),
     * then captures everything up to the matching closing brace by depth.
     */
    private function extractUploadPresaveClosureSource(): string
    {
        $path   = ROOTPATH . 'modules/Media/Controllers/Media.php';
        $tokens = token_get_all(file_get_contents($path));

        $startIndex = null;
        foreach ($tokens as $i => $token) {
            if (! is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }
            for ($j = $i + 1; $j < $i + 20 && isset($tokens[$j]); $j++) {
                $candidate = $tokens[$j];
                if (is_array($candidate) && $candidate[0] === T_VARIABLE && $candidate[1] === '$thash') {
                    $startIndex = $i;
                    break 2;
                }
            }
        }

        if ($startIndex === null) {
            throw new RuntimeException(
                'Could not locate the upload.presave closure in Media.php by scanning for a ' .
                "function with a \$thash parameter -- has its signature changed?",
            );
        }

        $source    = '';
        $depth     = 0;
        $seenBrace = false;

        for ($i = $startIndex; isset($tokens[$i]); $i++) {
            $token = $tokens[$i];
            $text  = is_array($token) ? $token[1] : $token;
            $source .= $text;

            if ($text === '{') {
                $depth++;
                $seenBrace = true;
            } elseif ($text === '}') {
                $depth--;
                if ($seenBrace && $depth === 0) {
                    break;
                }
            }
        }

        return $source;
    }

    /**
     * Resolves the mounted root's target hash via a real exec('open', ...)
     * call, exactly as a client's initial request would.
     */
    private function rootHash(\elFinder $elfinder): string
    {
        $args = [];
        foreach ($elfinder->commandArgsList('open') as $key => $required) {
            $args[$key] = '';
        }
        $args['init'] = true;

        $result = $elfinder->exec('open', $args);
        $this->assertArrayHasKey('cwd', $result, 'open failed: ' . json_encode($result));

        return $result['cwd']['hash'];
    }

    /**
     * @param array<string, mixed> $filesUpload $_FILES['upload']-shaped array
     * @param array<string, mixed> $overrides   Extra/overriding top-level exec() args
     *
     * @return array<string, mixed>
     */
    private function execUpload(\elFinder $elfinder, string $target, array $filesUpload, array $overrides = []): array
    {
        $args = [];
        foreach ($elfinder->commandArgsList('upload') as $key => $required) {
            if ($key === 'FILES') {
                continue;
            }
            $args[$key] = '';
        }
        $args['target'] = $target;
        $args['FILES']  = ['upload' => $filesUpload];

        return $elfinder->exec('upload', array_replace($args, $overrides));
    }

    private function writeSourceFile(string $content): string
    {
        $dir = $this->sandbox . '/src';
        if (! is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $path = tempnam($dir, 'src');
        file_put_contents($path, $content);

        return $path;
    }

    private function realJpegBytes(): string
    {
        $image = imagecreatetruecolor(4, 4);
        imagefill($image, 0, 0, imagecolorallocate($image, 10, 20, 30));
        ob_start();
        imagejpeg($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return (string) $bytes;
    }

    /**
     * Recursively deletes a sandbox directory created under sys_get_temp_dir().
     */
    private function removeDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }

        rmdir($dir);
    }
}
