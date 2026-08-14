<?php

declare(strict_types=1);

namespace Tests\Modules\MigrationManager;

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Verification that the new runSourceCli key works with lang() helper.
 *
 * @internal
 */
final class VerifyRunSourceCliLangTest extends CIUnitTestCase
{
    private string $originalLocale;

    public function setUp(): void
    {
        parent::setUp();
        $this->originalLocale = service('language')->getLocale();
    }

    public function tearDown(): void
    {
        service('language')->setLocale($this->originalLocale);
        parent::tearDown();
    }

    public function testRunSourceCliLangKeyInEnglish(): void
    {
        service('language')->setLocale('en');
        $value = lang('MigrationManager.runSourceCli');
        $this->assertSame('Command line (CLI)', $value);
    }

    public function testRunSourceCliLangKeyInTurkish(): void
    {
        service('language')->setLocale('tr');
        $value = lang('MigrationManager.runSourceCli');
        $this->assertSame('Komut satırı (CLI)', $value);
    }
}
