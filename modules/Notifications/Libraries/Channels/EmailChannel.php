<?php

namespace Modules\Notifications\Libraries\Channels;

use Modules\Notifications\Libraries\NotificationMessage;

/**
 * E-posta kanalı — henüz uygulanmadı; teslimi atlar (no-op stub).
 *
 * Kanal keşfi ve builder `via('email')` yolu bu stub sayesinde bugünden çalışır;
 * gerçek gönderim ileride burada uygulanacaktır.
 */
final class EmailChannel implements ChannelInterface
{
    /**
     * Mesajı işlemeden atlar.
     *
     * @param NotificationMessage $message Mesaj (kullanılmaz).
     *
     * @return ChannelResult Her zaman skipped('not-implemented').
     */
    public function send(NotificationMessage $message): ChannelResult
    {
        return ChannelResult::skipped('not-implemented');
    }
}
