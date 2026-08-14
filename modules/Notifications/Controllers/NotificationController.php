<?php

namespace Modules\Notifications\Controllers;

use Modules\Notifications\Config\NotificationsConfig;
use Modules\Notifications\Libraries\Notifier;

/**
 * Notification Center — backend controller.
 *
 * Endpoints (see Config/Routes.php):
 *   GET  backend/notifications            index()        — full list page (role: read)
 *   GET  backend/notifications/feed       feed()         — AJAX: dropdown + badge (role: read)
 *   POST backend/notifications/read/(:num) markRead()    — AJAX: mark a single record as read (role: update)
 *   POST backend/notifications/readAll    markAllRead()  — AJAX: mark all as read (role: update)
 *
 * SECURITY (Model B): the read/mark relevance+read contract is enforced from a single
 * point in Notifier (applyRelevance); a user can only see and mark notifications
 * relevant to them (broadcast / their own 'user' / a 'group' they belong to) (IDOR
 * protection). Write endpoints accept AJAX only; global CSRF is already active.
 */
class NotificationController extends \Modules\Backend\Controllers\BaseController
{
    /**
     * Shared Notifier service instance.
     *
     * @return Notifier
     */
    private function notifier(): Notifier
    {
        return service('notifier');
    }

    /**
     * Whether the Model B tables (notifications + notification_reads) are ready.
     *
     * @return bool True if both exist.
     */
    private function tablesReady(): bool
    {
        return $this->commonModel->db->tableExists('notifications')
            && $this->commonModel->db->tableExists('notification_reads');
    }

    /**
     * The session user's notification list (relevant + last LIST_LIMIT records).
     *
     * @return string The rendered list view.
     */
    public function index(): string
    {
        $notifications = [];
        $unread        = 0;

        // If the tables haven't been migrated yet (module was just dropped in,
        // migration hasn't run), a click from the menu shouldn't fatal; render with an
        // empty list.
        if ($this->tablesReady()) {
            $userId        = (int) auth()->id();
            $notifications = $this->notifier()->listFor($userId, NotificationsConfig::LIST_LIMIT);
            $unread        = $this->notifier()->unreadCount($userId);
        }

        $this->defData = array_merge($this->defData, [
            'notifications' => $notifications,
            'unread'        => $unread,
        ]);

        return view('Modules\Notifications\Views\index', $this->defData);
    }

    /**
     * AJAX feed: the last FEED_LIMIT notifications + unread count for the top bar dropdown.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function feed()
    {
        if (! $this->request->isAJAX()) {
            return $this->failForbidden();
        }

        if (! $this->tablesReady()) {
            return $this->respond(['status' => true, 'unread' => 0, 'items' => []]);
        }

        $userId = (int) auth()->id();

        return $this->respond([
            'status' => true,
            'unread' => $this->notifier()->unreadCount($userId),
            'items'  => $this->notifier()->listFor($userId, NotificationsConfig::FEED_LIMIT),
        ]);
    }

    /**
     * Marks a single notification as read (only the relevant user, idempotent).
     *
     * @param int $id Notification id.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function markRead(int $id)
    {
        if (! $this->request->isAJAX()) {
            return $this->failForbidden();
        }

        if (! $this->tablesReady()) {
            return $this->failNotFound();
        }

        $userId = (int) auth()->id();

        // No such record, or it's not relevant to the user (IDOR protection) → 404.
        if (! $this->notifier()->markRead($id, $userId)) {
            return $this->failNotFound();
        }

        return $this->respond(['status' => true]);
    }

    /**
     * Marks all of the user's relevant unread notifications as read.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function markAllRead()
    {
        if (! $this->request->isAJAX()) {
            return $this->failForbidden();
        }

        if ($this->tablesReady()) {
            $this->notifier()->markAllRead((int) auth()->id());
        }

        return $this->respond(['status' => true]);
    }
}
