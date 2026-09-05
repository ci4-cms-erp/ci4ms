<?php

declare(strict_types=1);

namespace Tests\Modules\Media;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Media\Controllers\Media;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Regression lock for the media upload gate.
 *
 * MediaAccessControlTest pins *who* may write; this class pins *what* may be
 * written. The two are independent: a user with `media.media.create` passes the
 * permission layer entirely, so the extension gate is the only thing standing
 * between that user and a script file inside the public web root.
 *
 * The gate exists because the MIME allowlist does not cover every dangerous
 * extension, which testTheMimeAllowlistAloneAcceptsScriptExtensions() documents
 * against the real, mounted elFinder driver: elFinderVolumeDriver ships a
 * built-in `staticMimeMap` default (elFinderVolumeDriver.class.php:276-294)
 * that forces `php`, `pht`, `php3`-`php5`/`php7`-`php9`, `phtml` and `phar` to
 * text/x-php once mount() actually runs — those extensions are already MIME-
 * blocked without this gate. What that built-in table does not cover — `.phps`,
 * `.inc`, `.ini`, `.php6` and double-extension names whose *last* segment is an
 * allowed image extension (`shell.php.jpg`, `a.php.png`) — resolves to
 * text/plain and sails through on MIME alone.
 *
 * Nothing here touches the filesystem, the database or the elFinder connector
 * (whose output routine calls exit()).
 *
 * @internal
 */
final class MediaUploadGateTest extends CIUnitTestCase
{
    /**
     * @return list<array{string}>
     */
    public static function dangerousNames(): array
    {
        return [
            ['shell.php'],
            ['shell.php5'],
            ['shell.php7'],
            ['shell.phtml'],
            ['shell.pht'],
            ['shell.phps'],
            ['shell.phar'],
            ['payload.inc'],
            ['script.cgi'],
            ['script.pl'],
            ['script.py'],
            ['script.sh'],
            ['run.bat'],
            ['run.exe'],
            ['page.jsp'],
            ['page.asp'],
            ['page.aspx'],
            ['.htaccess'],
            ['.htpasswd'],
            ['.user.ini'],
        ];
    }

    /**
     * Double extensions: which segment a server treats as the handler depends on
     * its configuration, so every segment is checked rather than only the last.
     *
     * @return list<array{string}>
     */
    public static function doubleExtensionNames(): array
    {
        return [
            ['shell.php.jpg'],
            ['shell.phtml.png'],
            ['shell.php.'],
            ['shell.php .'],
            ['avatar.jpg.php'],
            ['image.png.phar'],
            ['a.php.b.c.jpg'],
        ];
    }

    /**
     * @return list<array{string}>
     */
    public static function legitimateNames(): array
    {
        return [
            ['photo.jpg'],
            ['photo.jpeg'],
            ['diagram.png'],
            ['icon.webp'],
            ['scan.bmp'],
            ['notes.txt'],
            ['archive.tar.gz'],
            ['my-file_2026.gif'],
            ['klasör'],
            ['.thumbnails'],
        ];
    }

    #[DataProvider('dangerousNames')]
    public function testDangerousExtensionsAreRefused(string $name): void
    {
        $this->assertTrue($this->isDenied($name), "'{$name}' must never be writable into the media tree");
    }

    #[DataProvider('doubleExtensionNames')]
    public function testDoubleExtensionsAreRefused(string $name): void
    {
        $this->assertTrue($this->isDenied($name), "'{$name}' hides a script extension and must be refused");
    }

    #[DataProvider('legitimateNames')]
    public function testLegitimateNamesAreAccepted(string $name): void
    {
        $this->assertFalse($this->isDenied($name), "'{$name}' is ordinary media and must stay writable");
    }

    public function testTheCheckIsCaseInsensitive(): void
    {
        foreach (['SHELL.PHP', 'Shell.PhTmL', 'x.PHAR', '.HTACCESS'] as $name) {
            $this->assertTrue($this->isDenied($name), "'{$name}' must be refused regardless of case");
        }
    }

    /**
     * The gate must hold for a user who legitimately has write permission —
     * that is the whole point, since the permission layer lets them through.
     */
    #[DataProvider('dangerousNames')]
    public function testAccessControlDeniesWriteEvenForAWriteCapableUser(string $name): void
    {
        $controller = $this->makeController(true);

        $this->assertFalse(
            $controller->elfinderAccess('write', '/var/www/public/media/' . $name, null, null, false, '/' . $name),
            "A write-capable user must still not be able to write '{$name}'",
        );
    }

    public function testAccessControlLeavesOrdinaryMediaToElFinder(): void
    {
        $controller = $this->makeController(true);

        $this->assertNull(
            $controller->elfinderAccess('write', '/var/www/public/media/photo.jpg', null, null, false, '/photo.jpg'),
            'Ordinary media must be left for elFinder to decide',
        );
    }

    /**
     * Reading is untouched: an already-present file stays listable so an
     * administrator can see and delete it.
     */
    public function testAccessControlDoesNotChangeReadBehaviour(): void
    {
        $controller = $this->makeController(true);

        $this->assertNull(
            $controller->elfinderAccess('read', '/var/www/public/media/shell.phtml', null, null, false, '/shell.phtml'),
            'The extension gate must apply to writes only',
        );
    }

    public function testDotPrefixedEntriesStayHidden(): void
    {
        $controller = $this->makeController(true);

        $this->assertFalse(
            $controller->elfinderAccess('read', '/var/www/public/media/.trash', null, null, true, '/.trash'),
            'Dot-prefixed entries must remain hidden',
        );
    }

    public function testReadOnlyUsersAreStillDeniedEveryWrite(): void
    {
        $controller = $this->makeController(false);

        $this->assertFalse(
            $controller->elfinderAccess('write', '/var/www/public/media/photo.jpg', null, null, false, '/photo.jpg'),
            'A user without write permission must be denied even for ordinary media',
        );
    }

    /**
     * uploadOrder must stay deny-first. Flipped to allow-first, elFinder's own
     * logic defaults to permitting anything the allowlist does not mention.
     */
    public function testVolumeSecurityOptionsStayDenyFirst(): void
    {
        $options = $this->securityOptions(['image/jpeg']);

        $this->assertSame(['deny', 'allow'], $options['uploadOrder']);
        $this->assertSame(['all'], $options['uploadDeny']);
        $this->assertSame(['image/jpeg'], $options['uploadAllow']);
    }

    public function testVolumeSecurityOptionsCapTheUploadSize(): void
    {
        $options = $this->securityOptions(['image/jpeg']);

        $this->assertArrayHasKey('uploadMaxSize', $options);
        $this->assertMatchesRegularExpression('/^\d+[KMG]?$/', (string) $options['uploadMaxSize']);
    }

    public function testVolumeSecurityOptionsBindTheAccessControlCallback(): void
    {
        $controller = $this->makeController(true);
        $options    = $this->securityOptions(['image/jpeg'], $controller);

        $this->assertSame([$controller, 'elfinderAccess'], $options['accessControl']);
    }

    /**
     * Documents why the extension gate is load-bearing rather than belt-and-braces
     * — against a REAL, mounted elFinderVolumeLocalFileSystem, not an isolated
     * reflection call. An earlier version of this test set uploadAllow/uploadDeny
     * directly on a never-mounted volume via reflection: mount()/configure() never
     * ran, so elFinderVolumeDriver's built-in `staticMimeMap` default (which forces
     * `.phtml`/`.phar`/`.pht`/`.php3-9` to text/x-php) never merged into `mimeMap`,
     * and the test wrongly concluded those extensions sailed through as
     * text/plain. They do not, once mounted — the residual gap is narrower than
     * that: see mimeGateAccepts() and the class docblock above.
     */
    public function testTheMimeAllowlistAloneAcceptsScriptExtensions(): void
    {
        $this->assertFalse(
            $this->mimeGateAccepts('shell.phtml'),
            "elFinder's own staticMimeMap already forces .phtml to text/x-php once " .
            'mount() runs for real; if this flips to true, elFinder changed its ' .
            'built-in table and the gate documentation above needs revisiting.',
        );

        $accepted = [];

        foreach (['x.phps', 'x.inc', 'x.ini', 'x.php6', 'shell.php.jpg', 'a.php.png'] as $name) {
            if ($this->mimeGateAccepts($name)) {
                $accepted[] = $name;
            }
        }

        $this->assertNotEmpty(
            $accepted,
            'The MIME allowlist now refuses every extension DENIED_EXTENSIONS covers; the extension gate may be reviewable',
        );
        $this->assertContains(
            'x.phps',
            $accepted,
            "phps is the canonical case: outside staticMimeMap's fixed list, resolves to text/plain, allowed by settings",
        );

        foreach ($accepted as $name) {
            $this->assertTrue(
                $this->isDenied($name),
                "'{$name}' passes the MIME allowlist, so the extension gate must catch it",
            );
        }
    }

    private function makeController(bool $canWrite): Media
    {
        $controller = new Media();

        $property = new ReflectionProperty(Media::class, 'mediaCanWrite');
        $property->setAccessible(true);
        $property->setValue($controller, $canWrite);

        return $controller;
    }

    private function isDenied(string $name): bool
    {
        $method = new ReflectionMethod(Media::class, 'isDeniedName');
        $method->setAccessible(true);

        return (bool) $method->invoke($this->makeController(true), $name);
    }

    /**
     * @param list<string> $allowedFiles
     *
     * @return array<string, mixed>
     */
    private function securityOptions(array $allowedFiles, ?Media $controller = null): array
    {
        $controller ??= $this->makeController(true);

        $method = new ReflectionMethod(Media::class, 'volumeSecurityOptions');
        $method->setAccessible(true);

        return $method->invoke($controller, $allowedFiles, []);
    }

    /**
     * Runs a name through a REAL, mounted elFinder upload chain with only the
     * MIME allowlist active (uploadAllow/uploadDeny/uploadOrder) — no
     * acceptedName callable — to isolate what the MIME layer alone accepts.
     *
     * Deliberately NOT the production configuration: Media::volumeSecurityOptions()
     * always sets 'acceptedName' (Media.php:260). mount() must run for real so
     * elFinderVolumeDriver's built-in staticMimeMap default actually merges into
     * mimeMap — an isolated call to mimetype()/allowPutMime() on a never-mounted
     * volume skips that merge and reports the wrong result, which is exactly the
     * bug this test used to have (see the class docblock above).
     */
    private function mimeGateAccepts(string $name): bool
    {
        $root = sys_get_temp_dir() . '/ci4ms-media-qa-mime-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);

        try {
            $opts = [
                'debug' => false,
                'roots' => [
                    [
                        'driver'      => 'LocalFileSystem',
                        'path'        => $root,
                        'URL'         => 'http://localhost/media/',
                        'uploadDeny'  => ['all'],
                        'uploadAllow' => ['image/x-ms-bmp', 'image/gif', 'image/jpeg', 'image/png', 'image/x-icon', 'text/plain', 'image/webp'],
                        'uploadOrder' => ['deny', 'allow'],
                    ],
                ],
            ];

            $_SERVER['REQUEST_METHOD'] = 'POST';
            $_POST['cmd']              = 'upload';

            $elfinder = new \elFinder($opts);
            $this->assertTrue($elfinder->loaded(), 'sandbox volume failed to mount: ' . implode('; ', $elfinder->mountErrors));

            $openArgs = [];
            foreach ($elfinder->commandArgsList('open') as $key => $required) {
                $openArgs[$key] = '';
            }
            $openArgs['init'] = true;
            $open             = $elfinder->exec('open', $openArgs);

            $src = tempnam($root, 'src');
            file_put_contents($src, "not a real image or script, just plain bytes\n");

            $uploadArgs = [];
            foreach ($elfinder->commandArgsList('upload') as $key => $required) {
                if ($key === 'FILES') {
                    continue;
                }
                $uploadArgs[$key] = '';
            }
            $uploadArgs['target'] = $open['cwd']['hash'];
            $uploadArgs['FILES']  = ['upload' => [
                'name'     => [$name],
                'type'     => ['application/octet-stream'],
                'tmp_name' => [$src],
                'error'    => [0],
                'size'     => [filesize($src)],
            ]];

            $result = $elfinder->exec('upload', $uploadArgs);

            return ! empty($result['added']);
        } finally {
            $this->removeDirectory($root);
        }
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
