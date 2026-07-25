<?php

namespace Modules\Notifications\Controllers;

use Modules\Notifications\Config\NotificationsConfig;
use Modules\Notifications\Libraries\Notifier;

/**
 * Bildirim Merkezi — backend controller.
 *
 * Uçlar (bkz. Config/Routes.php):
 *   GET  backend/notifications            index()        — tam liste sayfası (role: read)
 *   GET  backend/notifications/feed       feed()         — AJAX: dropdown + rozet (role: read)
 *   POST backend/notifications/read/(:num) markRead()    — AJAX: tek kaydı okundu işaretle (role: update)
 *   POST backend/notifications/readAll    markAllRead()  — AJAX: tümünü okundu işaretle (role: update)
 *
 * GÜVENLİK (Model B): Okuma/işaretleme relevans+okundu sözleşmesi Notifier'da
 * tek noktadan (applyRelevance) uygulanır; bir kullanıcı yalnız kendisine ilgili
 * (broadcast / kendi 'user' / üye olduğu 'group') bildirimi görür ve işaretleyebilir
 * (IDOR koruması). Yazma uçları yalnız AJAX kabul eder; global CSRF zaten aktiftir.
 */
class NotificationController extends \Modules\Backend\Controllers\BaseController
{
    /**
     * Paylaşımlı Notifier servis örneği.
     *
     * @return Notifier
     */
    private function notifier(): Notifier
    {
        return service('notifier');
    }

    /**
     * Model B tablolarının (notifications + notification_reads) hazır olup olmadığı.
     *
     * @return bool İkisi de mevcutsa true.
     */
    private function tablesReady(): bool
    {
        return $this->commonModel->db->tableExists('notifications')
            && $this->commonModel->db->tableExists('notification_reads');
    }

    /**
     * Oturumdaki kullanıcının bildirim listesi (ilgili + son LIST_LIMIT kayıt).
     *
     * @return string Render edilmiş liste görünümü.
     */
    public function index(): string
    {
        $notifications = [];
        $unread        = 0;

        // Tablolar henüz migrate edilmemişse (modül bırakıldı, migration çalışmadı)
        // menüden gelen tıklama fatal atmamalı; boş listeyle render et.
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
     * AJAX besleme: üst çubuk dropdown'ı için son FEED_LIMIT bildirim + okunmamış sayısı.
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
     * Tek bir bildirimi okundu olarak işaretler (yalnız ilgili kullanıcı, idempotent).
     *
     * @param int $id Bildirim kimliği.
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

        // Kayıt yok ya da kullanıcıya ilgili değil (IDOR koruması) → 404.
        if (! $this->notifier()->markRead($id, $userId)) {
            return $this->failNotFound();
        }

        return $this->respond(['status' => true]);
    }

    /**
     * Kullanıcının tüm ilgili okunmamış bildirimlerini okundu işaretler.
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
