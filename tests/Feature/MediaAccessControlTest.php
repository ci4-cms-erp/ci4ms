<?php

namespace Tests\Feature;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Media\Controllers\Media;
use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Regression lock for the Media / elFinder access-control hardening
 * (security audit 2026-06-21, github-security.md follow-up).
 *
 * The Broken Access Control issue is already closed; these assertions pin the
 * three-layer protection in place so a future refactor cannot silently
 * reopen it.
 *
 * Why this is a controller-logic test instead of a full HTTP feature test:
 *  1. The elFinder connector's output routine ends in exit() (see
 *     vendor/studio-42/elfinder/php/elFinderConnector.class.php), so a writer
 *     request that reaches the connector would terminate the PHPUnit process.
 *  2. The project's migrations contain MySQL-only SQL (ALTER ... ON UPDATE,
 *     ADD FOREIGN KEY) that do not run on the default in-memory SQLite `tests`
 *     database group, so a DatabaseTestTrait/FeatureTestTrait test cannot be
 *     green without a dedicated MySQL test database.
 *
 * Exercising the controller's access-control primitives directly keeps the
 * regression locked deterministically in any environment.
 *
 * @internal
 */
final class MediaAccessControlTest extends CIUnitTestCase
{
    /**
     * The four github-security.md proof-of-concept write commands.
     *
     * @var list<string>
     */
    private const POC_COMMANDS = ['mkfile', 'put', 'mkdir', 'rm'];

    /**
     * Read-only user: every write command must be blocked at the Layer 2 gate.
     */
    public function testReadOnlyUserIsBlockedForEveryWriteCommand(): void
    {
        $controller = $this->makeController(false);

        foreach ($this->writeCommands() as $cmd) {
            $this->assertTrue(
                $this->callIsWriteBlocked($controller, $cmd),
                "Write command '{$cmd}' must be blocked for a read-only user."
            );
        }
    }

    /**
     * The four documented PoC commands are all part of the blocked write set.
     */
    public function testPocCommandsAreInWriteCommandList(): void
    {
        foreach (self::POC_COMMANDS as $cmd) {
            $this->assertContains(
                $cmd,
                $this->writeCommands(),
                "PoC command '{$cmd}' is missing from Media::WRITE_COMMANDS."
            );
        }
    }

    /**
     * Write-capable user: write commands pass the Layer 2 gate (behaviour
     * preserved — a create-permitted user can still mkdir/upload).
     */
    public function testWriterUserPassesControllerGate(): void
    {
        $controller = $this->makeController(true);

        foreach ($this->writeCommands() as $cmd) {
            $this->assertFalse(
                $this->callIsWriteBlocked($controller, $cmd),
                "Write command '{$cmd}' must NOT be blocked for a write-capable user."
            );
        }
    }

    /**
     * Non-write commands are never blocked, regardless of write capability.
     */
    public function testReadCommandsAreNeverBlocked(): void
    {
        $readOnly = $this->makeController(false);

        foreach (['open', 'file', 'tree', 'ls', 'tmb', 'size', 'search', ''] as $cmd) {
            $this->assertFalse(
                $this->callIsWriteBlocked($readOnly, $cmd),
                "Read command '{$cmd}' must not be blocked."
            );
        }
    }

    /**
     * Authoritative guarantee: elfinderAccess() denies the `write` attribute
     * for users without write permission, independent of the command.
     */
    public function testElfinderAccessDeniesWriteForReadOnlyUser(): void
    {
        $controller = $this->makeController(false);

        $result = $controller->elfinderAccess(
            'write',
            ROOTPATH . 'public/media/poc.txt',
            null,
            null,
            false,
            '/poc.txt'
        );

        $this->assertFalse(
            $result,
            'elfinderAccess() must return false for the write attribute when the user cannot write.'
        );
    }

    /**
     * Write-capable user: elfinderAccess() defers to elFinder (null) for the
     * write attribute on a normal (non-dot) path.
     */
    public function testElfinderAccessDefersWriteForWriterUser(): void
    {
        $controller = $this->makeController(true);

        $result = $controller->elfinderAccess(
            'write',
            ROOTPATH . 'public/media/qa',
            null,
            null,
            true,
            '/qa'
        );

        $this->assertNull(
            $result,
            'elfinderAccess() must defer to elFinder (null) for a write-capable user on a normal path.'
        );
    }

    /**
     * Dot-prefixed entries (e.g. .git, .trash internals) stay denied for reads.
     */
    public function testElfinderAccessHidesDotPrefixedEntries(): void
    {
        $controller = $this->makeController(true);

        $denied = $controller->elfinderAccess(
            'read',
            ROOTPATH . 'public/media/.git',
            null,
            null,
            true,
            '/.git'
        );

        $this->assertFalse($denied, 'Dot-prefixed entries must be denied for the read attribute.');
    }

    // ── helpers ─────────────────────────────────────────────────────────────

    /**
     * Builds a Media controller instance with the protected $mediaCanWrite flag
     * set, bypassing the framework's initController() bootstrap.
     *
     * @param bool $canWrite Whether the simulated user has write capability.
     */
    private function makeController(bool $canWrite): Media
    {
        $controller = new Media();

        $property = new ReflectionProperty(Media::class, 'mediaCanWrite');
        $property->setAccessible(true);
        $property->setValue($controller, $canWrite);

        return $controller;
    }

    /**
     * Invokes the protected Media::isWriteBlocked() gate via reflection.
     */
    private function callIsWriteBlocked(Media $controller, string $cmd): bool
    {
        $method = new ReflectionMethod(Media::class, 'isWriteBlocked');
        $method->setAccessible(true);

        return (bool) $method->invoke($controller, $cmd);
    }

    /**
     * Reads the private Media::WRITE_COMMANDS source-of-truth via reflection.
     *
     * @return list<string>
     */
    private function writeCommands(): array
    {
        /** @var list<string> $commands */
        $commands = (new ReflectionClassConstant(Media::class, 'WRITE_COMMANDS'))->getValue();

        return $commands;
    }
}
