<?php

declare(strict_types=1);

namespace Tests\Modules\Media;

use CodeIgniter\Test\CIUnitTestCase;
use elFinderVolumeLocalFileSystem;
use Modules\Media\Controllers\Media;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use ReflectionObject;
use ReflectionProperty;

/**
 * Regression lock for the media upload gate.
 *
 * MediaAccessControlTest pins *who* may write; this class pins *what* may be
 * written. The two are independent: a user with `media.media.create` passes the
 * permission layer entirely, so the extension gate is the only thing standing
 * between that user and a script file inside the public web root.
 *
 * The gate exists because the MIME allowlist does not cover this case, which
 * testTheMimeAllowlistAloneAcceptsScriptExtensions() documents against the real
 * elFinder driver: elFinder maps only `php` to text/x-php internally, so every
 * other script extension resolves to text/plain — a MIME `settings.allowedFiles`
 * permits — and .phtml/.php5/.pht/.phar sail through.
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
     * Documents why the extension gate is load-bearing rather than belt-and-braces.
     *
     * If this ever fails, elFinder's MIME handling changed — re-read the gate's
     * justification before assuming it can be relaxed.
     */
    public function testTheMimeAllowlistAloneAcceptsScriptExtensions(): void
    {
        $accepted = [];

        foreach (['shell.php', 'shell.phtml', 'shell.php5', 'shell.pht', 'shell.phar'] as $name) {
            if ($this->mimeGateAccepts($name)) {
                $accepted[] = $name;
            }
        }

        $this->assertNotEmpty(
            $accepted,
            'The MIME allowlist now refuses every script extension; the extension gate may be reviewable',
        );
        $this->assertContains(
            'shell.phtml',
            $accepted,
            'phtml is the canonical case: unknown to elFinder, resolves to text/plain, allowed by settings',
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
     * Runs a name through elFinder's own MIME gate with this project's settings.
     */
    private function mimeGateAccepts(string $name): bool
    {
        $volume     = new elFinderVolumeLocalFileSystem();
        $reflection = new ReflectionObject($volume);

        $config = [
            'uploadAllow' => ['image/x-ms-bmp', 'image/gif', 'image/jpeg', 'image/png', 'image/x-icon', 'text/plain', 'image/webp'],
            'uploadDeny'  => ['all'],
            'uploadOrder' => ['deny', 'allow'],
        ];

        foreach ($config as $name_ => $value) {
            $property = $reflection->getProperty($name_);
            $property->setAccessible(true);
            $property->setValue($volume, $value);
        }

        $mimetype = $reflection->getMethod('mimetype');
        $mimetype->setAccessible(true);
        $allowPut = $reflection->getMethod('allowPutMime');
        $allowPut->setAccessible(true);

        return (bool) $allowPut->invoke($volume, $mimetype->invoke($volume, $name, true));
    }
}
