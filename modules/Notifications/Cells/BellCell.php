<?php

namespace Modules\Notifications\Cells;

use ci4commonmodel\CommonModel;
use Modules\Notifications\Config\NotificationsConfig;

/**
 * Top bar notification bell (View Cell).
 *
 * Called in base.php like this (same pattern as WidgetCell):
 *   <?= view_cell('Modules\Notifications\Cells\BellCell::render', ['user_id' => auth()->id()]) ?>
 *
 * On first render, prints the unread count + the last FEED_LIMIT relevant
 * notifications; afterwards it's updated by the small `notifFeed` JS endpoint in
 * bell.php polling periodically.
 */
class BellCell
{
    /**
     * Renders the bell dropdown (empty when there's no session or the tables aren't ready).
     *
     * @param array{user_id?: int} $params View parameters.
     *
     * @return string The rendered bell, or an empty string.
     */
    public function render(array $params): string
    {
        // The parameter is only a render gate (auth()->id() in base.php); it lets us
        // skip rendering the bell on a session-less (login) page without touching the
        // DB. The identity is STRICTLY derived from the session — the parameter value
        // can never be an identity.
        if ((int) ($params['user_id'] ?? 0) <= 0) {
            return '';
        }

        $userId = (int) (auth()->id() ?? 0);
        if ($userId <= 0) {
            return '';
        }

        $model = new CommonModel();

        // If the Model B tables haven't been migrated yet (module was just dropped
        // in, migration hasn't run), don't render the bell at all. base.php calls
        // this cell on EVERY backend page; an unguarded read here would take down
        // the whole backend.
        if (! $model->db->tableExists('notifications') || ! $model->db->tableExists('notification_reads')) {
            return '';
        }

        $notifier = service('notifier');

        return view('Modules\Notifications\Views\Cells\bell', [
            'unread'          => $notifier->unreadCount($userId),
            'items'           => $notifier->listFor($userId, NotificationsConfig::FEED_LIMIT),
            'realtimeEnabled' => (bool) config(NotificationsConfig::class)->realtimeEnabled,
        ]);
    }
}
