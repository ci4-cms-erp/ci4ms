<?php

declare(strict_types=1);

namespace Modules\Notifications\Libraries\Channels;

use Modules\Notifications\Libraries\NotificationMessage;

/**
 * Bir bildirim teslim kanalının sözleşmesi (in-app, e-posta, webhook ...).
 */
interface ChannelInterface
{
    /**
     * Tek bir bildirim mesajını bu kanaldan teslim eder.
     *
     * @param NotificationMessage $message Temizlenmiş, tek-hedefli mesaj.
     *
     * @return ChannelResult Teslim sonucu (ok/skipped + meta).
     */
    public function send(NotificationMessage $message): ChannelResult;
}
