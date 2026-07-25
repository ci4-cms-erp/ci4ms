<?php

namespace Modules\Notifications\Libraries\Channels;

use Modules\Notifications\Libraries\NotificationMessage;

/**
 * Webhook kanalı — henüz uygulanmadı; teslimi atlar (no-op stub).
 *
 * Kanal keşfi ve builder `via('webhook')` yolu bu stub sayesinde bugünden çalışır;
 * gerçek HTTP teslimi ileride burada uygulanacaktır.
 */
final class WebhookChannel implements ChannelInterface
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
