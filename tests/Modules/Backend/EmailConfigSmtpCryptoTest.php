<?php

declare(strict_types=1);

namespace Tests\Modules\Backend;

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\Mock\MockCache;
use Config\Email;
use Config\Services;

/**
 * SMTPCrypto / SMTPHost mapping in Config\Email::__construct() (app/Config/Email.php:151-161).
 *
 * The stored `tls` flag is a bool (Settings.php:180-183) but CI4 only starts
 * STARTTLS on SMTPCrypto === 'tls'; on port 465 CI4 already opens tls:// itself.
 *
 * The 'settings' cache is replaced by an in-memory MockCache so the real
 * writable/cache entry used by the live app is never touched. No database.
 * Cache shape mirrors app/Config/Filters.php:138-146: array<string, object>
 * with 'mail' json_decode()d into a stdClass.
 *
 * @internal
 */
final class EmailConfigSmtpCryptoTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        Services::resetSingle('cache');
        parent::tearDown();
    }

    /**
     * @param array<string, mixed> $mail
     * @return Email
     */
    private function buildConfig(array $mail): Email
    {
        $cache = new MockCache();
        $cache->save('settings', ['mail' => json_decode((string) json_encode($mail))], 60);
        Services::injectMock('cache', $cache);

        return new Email();
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function cryptoProvider(): array
    {
        return [
            'B1 tls true 587'     => [['tls' => true, 'port' => 587], 'tls'],
            'B1 tls true 25'      => [['tls' => true, 'port' => 25], 'tls'],
            'B2 tls true 465'     => [['tls' => true, 'port' => 465], ''],
            'B3 tls false 587'    => [['tls' => false, 'port' => 587], ''],
            'B3 tls false 465'    => [['tls' => false, 'port' => 465], ''],
            'B4 legacy 587'       => [['port' => 587], 'tls'],
            'B4 legacy 465'       => [['port' => 465], ''],
            'B4 legacy no port'   => [[], 'tls'],
            'B5 ssl 587'          => [['tls' => 'ssl', 'port' => 587], 'ssl'],
            'B5 ssl 465'          => [['tls' => 'ssl', 'port' => 465], 'ssl'],
            'B5 tls string 587'   => [['tls' => 'tls', 'port' => 587], 'tls'],
            'B5 tls string 465'   => [['tls' => 'tls', 'port' => 465], ''],
        ];
    }

    /**
     * @dataProvider cryptoProvider
     * @param array<string, mixed> $mail
     * @return void
     */
    public function testSmtpCryptoMapping(array $mail, string $expected): void
    {
        $this->assertSame($expected, $this->buildConfig($mail)->SMTPCrypto);
    }

    /**
     * @return void
     */
    public function testSmtpHostComesFromServerField(): void
    {
        $config = $this->buildConfig(['server' => 'smtp.gmail.com', 'port' => 587, 'tls' => true]);

        $this->assertSame('smtp.gmail.com', $config->SMTPHost);
        $this->assertSame(587, $config->SMTPPort);
    }
}
