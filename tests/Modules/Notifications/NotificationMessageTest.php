<?php

namespace Tests\Modules\Notifications;

use CodeIgniter\Test\CIUnitTestCase;
use Modules\Notifications\Config\NotificationsConfig;
use Modules\Notifications\Libraries\NotificationMessage;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * NotificationMessage DTO sanitising unit tests (pure logic, DB-free).
 *
 * In Model B every field is cleaned once, in the DTO constructor, so channels
 * never touch raw input. These tests pin that contract at its real home: URL
 * open-redirect/CRLF rejection (sanitizeUrl), severity normalisation, the
 * VARCHAR(64) type clamp, title/body strip_tags and the null-body invariant.
 *
 * @internal
 */
final class NotificationMessageTest extends CIUnitTestCase
{
    /**
     * sanitizeUrl accepts site-internal and http(s) targets, rejects the rest.
     *
     * @param string|null $input    Raw URL handed to the DTO.
     * @param string|null $expected Sanitised result the DTO must store.
     *
     * @return void
     */
    #[DataProvider('urlProvider')]
    public function testSanitizeUrlAcceptsSafeAndRejectsHostile(?string $input, ?string $expected): void
    {
        $this->assertSame($expected, NotificationMessage::sanitizeUrl($input));
    }

    /**
     * URL sanitisation matrix: [input, expected].
     *
     * @return array<string, array{0: string|null, 1: string|null}>
     */
    public static function urlProvider(): array
    {
        return [
            'root-relative path'     => ['/backend/x', '/backend/x'],
            'single slash'           => ['/', '/'],
            'https absolute'         => ['https://a.example/x', 'https://a.example/x'],
            'http absolute'          => ['http://a.example', 'http://a.example'],
            'javascript scheme'      => ['javascript:alert(1)', null],
            'protocol-relative'      => ['//evil.example', null],
            'backslash protocol-rel' => ['/\evil.example', null],
            'backslash then slash'   => ['/\/evil', null],
            'double backslash'       => ["/\\\\evil", null],
            'inner CRLF'             => ["/foo\r\nbar", null],
            'inner tab'              => ["/foo\tbar", null],
            'inner null byte'        => ["/foo\0bar", null],
            'empty string'           => ['', null],
            'whitespace only'        => ['   ', null],
            'bare relative word'     => ['foo', null],
            'ftp scheme'             => ['ftp://a.example', null],
            'null'                   => [null, null],
        ];
    }

    /**
     * normalizeSeverity folds case/whitespace and drops unknown levels to info.
     *
     * @param string $input    Raw severity.
     * @param string $expected Normalised severity.
     *
     * @return void
     */
    #[DataProvider('severityProvider')]
    public function testNormalizeSeverityFoldsToAllowedSet(string $input, string $expected): void
    {
        $this->assertSame($expected, NotificationMessage::normalizeSeverity($input));
    }

    /**
     * Severity normalisation matrix: [input, expected].
     *
     * @return array<string, array{0: string, 1: string}>
     */
    public static function severityProvider(): array
    {
        return [
            'info'            => ['info', 'info'],
            'warning'         => ['warning', 'warning'],
            'critical'        => ['critical', 'critical'],
            'uppercase'       => ['WARNING', 'warning'],
            'padded mixed'    => ['  Critical ', 'critical'],
            'unknown'         => ['bogus', 'info'],
            'empty'           => ['', 'info'],
        ];
    }

    /**
     * The constructor clamps type to the column width, strips markup and keeps a safe url.
     *
     * @return void
     */
    public function testConstructorClampsTypeStripsTagsAndValidatesUrl(): void
    {
        $message = new NotificationMessage(
            str_repeat('a', 80),
            'WARNING',
            '  <b>Hi</b> ',
            '  <p>Body</p> ',
            '/backend/x',
            'user',
            '5',
            ['inapp']
        );

        $this->assertSame(NotificationsConfig::TYPE_MAX, strlen($message->type), 'type is clamped to the column width');
        $this->assertSame(str_repeat('a', NotificationsConfig::TYPE_MAX), $message->type);
        $this->assertSame('warning', $message->severity);
        $this->assertSame('Hi', $message->title, 'title is stripped of markup');
        $this->assertSame('Body', $message->body, 'body is stripped of markup');
        $this->assertSame('/backend/x', $message->url);
        $this->assertSame('user', $message->targetType);
        $this->assertSame('5', $message->targetValue);
        $this->assertSame(['inapp'], $message->channels);
    }

    /**
     * A null or whitespace-only body collapses to null; a hostile url is dropped.
     *
     * @return void
     */
    public function testConstructorKeepsNullBodyAndRejectsHostileUrl(): void
    {
        $nullBody = new NotificationMessage('t', 'info', 'T', null, null, 'broadcast', null, []);
        $this->assertNull($nullBody->body, 'a null body stays null');

        $blankBody = new NotificationMessage('t', 'info', 'T', '   ', null, 'broadcast', null, []);
        $this->assertNull($blankBody->body, 'a whitespace-only body collapses to null, not an empty string');

        $hostileUrl = new NotificationMessage('t', 'info', 'T', null, 'javascript:alert(1)', 'broadcast', null, []);
        $this->assertNull($hostileUrl->url, 'a hostile url is rejected during construction');
    }

    /**
     * Clamping counts CHARACTERS, the way the validation rule that guards it does.
     *
     * `max_length` counts with mb_strlen while a byte-based clamp cuts at the 255th
     * BYTE, so a 255-character Turkish title passed validation and was then cut to about
     * a third of its length — with the last multi-byte character sliced in half. The
     * project runs with `strictOn = false`, so MySQL accepted that broken tail without a
     * word. The title is asserted below in characters AND in valid UTF-8.
     *
     * @return void
     */
    public function testConstructorClampsByCharactersNotByBytes(): void
    {
        $title = str_repeat('ğ', NotificationsConfig::TITLE_MAX + 10);

        $message = new NotificationMessage('t', 'info', $title, null, null, 'broadcast', null, []);

        $this->assertSame(NotificationsConfig::TITLE_MAX, mb_strlen($message->title, 'UTF-8'), 'the full character budget is kept');
        $this->assertSame(str_repeat('ğ', NotificationsConfig::TITLE_MAX), $message->title);
        $this->assertTrue(mb_check_encoding($message->title, 'UTF-8'), 'no character was cut in half');
    }

    /**
     * The body is clamped too, and by characters as well.
     *
     * `body` is a TEXT column, so an unclamped value is not refused but silently cut at
     * 65 535 bytes — the same half-character ending, only further away.
     *
     * @return void
     */
    public function testConstructorClampsTheBodyToItsOwnCeiling(): void
    {
        $body = str_repeat('ş', NotificationsConfig::BODY_MAX + 25);

        $message = new NotificationMessage('t', 'info', 'T', $body, null, 'broadcast', null, []);

        $this->assertSame(NotificationsConfig::BODY_MAX, mb_strlen((string) $message->body, 'UTF-8'));
        $this->assertTrue(mb_check_encoding((string) $message->body, 'UTF-8'));
    }
}
