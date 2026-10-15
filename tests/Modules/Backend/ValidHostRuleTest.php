<?php

declare(strict_types=1);

namespace Tests\Modules\Backend;

use CodeIgniter\Test\CIUnitTestCase;
use Config\Services;
use Modules\Backend\Validation\CustomRules;

/**
 * Contract for CustomRules::valid_host() (modules/Backend/Validation/CustomRules.php:79-90),
 * the rule behind the SMTP server field (Settings.php:164 'required|valid_host').
 * No database is touched.
 *
 * @internal
 */
final class ValidHostRuleTest extends CIUnitTestCase
{
    protected function tearDown(): void
    {
        Services::resetSingle('validation');
        parent::tearDown();
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function acceptedProvider(): array
    {
        return [
            'gmail'     => ['smtp.gmail.com'],
            'localhost' => ['localhost'],
            'hyphen+tld' => ['mail-1.example.co.uk'],
            'ipv4'      => ['192.168.1.10'],
            'loopback'  => ['127.0.0.1'],
            'punycode'  => ['xn--mller-kva.de'],
        ];
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function rejectedProvider(): array
    {
        return [
            'underscore'    => ['smtp_relay'],
            'unicode idn'   => ['müller.de'],
            'host:port'     => ['smtp.gmail.com:587'],
            'scheme'        => ['ssl://smtp.gmail.com'],
            'space'         => ['a b'],
            'crlf'          => ["evil.com\r\nX"],
            'leading dash'  => ['-bad.com'],
            'empty'         => [''],
            'null'          => [null],
            'array'         => [[]],
            'int'           => [123],
            'ipv6 loopback' => ['::1'],
            'ipv6 full'     => ['2001:db8::1'],
            'ipv6 bracket'  => ['[::1]'],
        ];
    }

    /**
     * @dataProvider acceptedProvider
     * @param mixed $value
     * @return void
     */
    public function testAcceptsValidHostsAndIps($value): void
    {
        $error = null;

        $this->assertTrue((new CustomRules())->valid_host($value, $error));
        $this->assertNull($error);
    }

    /**
     * @dataProvider rejectedProvider
     * @param mixed $value
     * @return void
     */
    public function testRejectsInvalidValuesWithLocalizedError($value): void
    {
        $error = null;

        $this->assertFalse((new CustomRules())->valid_host($value, $error));
        $this->assertNotEmpty($error);
        $this->assertSame(lang('Backend.invalidHost'), $error);
        $this->assertNotSame('Backend.invalidHost', $error, 'language key must resolve');
    }

    /**
     * The rule is reachable through the CI4 validation service, proving
     * app/Config/Validation.php auto-registers Modules\Backend\Validation\CustomRules.
     *
     * @return void
     */
    public function testRuleWorksThroughValidationService(): void
    {
        $rules = ['mServer' => 'required|valid_host'];

        $ok = Services::validation();
        $this->assertTrue($ok->setRules($rules)->run(['mServer' => 'smtp.gmail.com']));

        $bad = Services::validation();
        $this->assertFalse($bad->setRules($rules)->run(['mServer' => 'smtp.gmail.com:587']));
        $this->assertSame(lang('Backend.invalidHost'), $bad->getError('mServer'));
    }
}
