<?php

namespace Modules\Notifications\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Kullanıcı bildirim tercihleri (opt-out) tablosu: (user_id, type, channel) başına bir satır.
 *
 * Model B küresel satır tuttuğu için tercih GÖNDERİM anında uygulanamaz (aynı satır
 * herkese aittir); filtre OKUMA zamanında `Notifier::applyRelevance()` içindeki
 * LEFT JOIN + `p.id IS NULL` anti-join'i ile uygulanır. Bu yüzden burada yalnız
 * SUSTURMA (enabled = 0) satırları anlamlıdır; satırın yokluğu "bildirim açık"tır.
 *
 * `type` bir tip ya da tip ÖNEKİdir: 'audit' satırı hem 'audit' hem 'audit.login'
 * kayıtlarını susturur (JOIN'de `n.type LIKE CONCAT(p.type, '.%')`). `channel = '*'`
 * tüm kanalları kapsar. `severity = 'critical'` satırlar JOIN'e hiç girmez, yani
 * kritik bildirimler susturulamaz.
 *
 * INDEX'ler okuma yolu içindir: JOIN önce `user_id` ile daralır (notif_pref_lookup),
 * UNIQUE ise aynı üçlünün çift yazılmasını engeller. FK CASCADE'dir: kullanıcı
 * silinince tercihleri de silinir.
 *
 * up() tableExists guard'lıdır (tekrar çalıştırılabilir). down() yalnız dosya
 * bütünlüğü içindir: tablo kullanıcı verisi taşır, otomatik DROP veri kaybı olurdu.
 */
class CreateNotificationPreferencesTable extends Migration
{
    public function up()
    {
        if ($this->db->tableExists('notification_preferences')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
                'null'           => false,
            ],
            'user_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => false,
            ],
            // Tam tip ('audit.login') ya da tip öneki ('audit').
            'type' => [
                'type'       => 'VARCHAR',
                'constraint' => 64,
                'null'       => false,
            ],
            // '*' = tüm kanallar; aksi halde notifications.channel ile birebir eşleşir.
            'channel' => [
                'type'       => 'VARCHAR',
                'constraint' => 32,
                'null'       => false,
                'default'    => '*',
            ],
            // 0 = susturuldu (okuma yolunda elenir). 1 = varsayılan, satır bilgi amaçlıdır.
            'enabled' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'null'       => false,
                'default'    => 1,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
            'updated_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['user_id', 'type', 'channel'], false, true, 'notif_pref_unique');
        $this->forge->addKey(['user_id', 'enabled'], false, false, 'notif_pref_lookup');

        // Shield `users` tablosu yoksa (modül tek başına, Shield migrate edilmemiş)
        // FK'siz oluştur: tablo yine çalışır, temizlik uygulama tarafında kalır.
        if ($this->db->tableExists('users')) {
            $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');
        } else {
            log_message('warning', 'notification_preferences: `users` tablosu yok, FK olmadan oluşturuldu.');
        }

        $this->forge->createTable('notification_preferences', true, ['ENGINE' => 'InnoDB']);
    }

    public function down()
    {
        // Geri alma ELLE yapılır — tablo kullanıcı tercihlerini taşır, otomatik DROP veri kaybıdır.
        // Manuel adım (gerekirse):
        //   DROP TABLE `{prefix}notification_preferences`;
    }
}
