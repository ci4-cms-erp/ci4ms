<?php

namespace Modules\Notifications\Cells;

use ci4commonmodel\CommonModel;
use Modules\Notifications\Config\NotificationsConfig;

/**
 * Üst çubuk bildirim çanı (View Cell).
 *
 * base.php'de şu şekilde çağrılır (WidgetCell kalıbıyla aynı):
 *   <?= view_cell('Modules\Notifications\Cells\BellCell::render', ['user_id' => auth()->id()]) ?>
 *
 * İlk render'da okunmamış sayısını + son FEED_LIMIT ilgili bildirimi basar; sonrasında
 * bell.php içindeki küçük JS `notifFeed` ucunu periyodik yoklayarak günceller.
 */
class BellCell
{
    /**
     * Çan dropdown'ını render eder (oturum yoksa ya da tablolar hazır değilse boş).
     *
     * @param array{user_id?: int} $params Görünüm parametreleri.
     *
     * @return string Render edilmiş çan ya da boş string.
     */
    public function render(array $params): string
    {
        // Parametre yalnız render-kapısıdır (base.php'de auth()->id()); oturumsuz
        // (login) sayfada çanı hiç basmamayı DB'ye dokunmadan sağlar. Kimlik ise
        // KATI biçimde oturumdan türetilir — parametre değeri asla kimlik olamaz.
        if ((int) ($params['user_id'] ?? 0) <= 0) {
            return '';
        }

        $userId = (int) (auth()->id() ?? 0);
        if ($userId <= 0) {
            return '';
        }

        $model = new CommonModel();

        // Model B tabloları henüz migrate edilmediyse (modül yeni bırakıldı,
        // migration çalışmadı) çanı hiç basma. base.php bu cell'i HER backend
        // sayfasında çağırır; korumasız bir okuma tüm backend'i düşürür.
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
