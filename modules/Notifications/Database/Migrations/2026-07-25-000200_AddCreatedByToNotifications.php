<?php

namespace Modules\Notifications\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Hesap verebilirlik izi için `notifications` tablosuna additive (DROP'suz)
 * `created_by` kolonunu ekler.
 *
 * ANLAM: satırı ÜRETEN kullanıcının kimliği — alıcısı DEĞİL. Model B satırları
 * küreseldir (`user_id` daima NULL, hedef `target_type`/`target_value` ile taşınır),
 * bu yüzden "kim gönderdi" sorusunun cevabı için ayrı bir kolon gerekir. Yalnız
 * insan eliyle üretilen yayınlarda (FAZ 3 composer) dolar; olay/CLI kaynaklı
 * bildirimlerde NULL kalır ve bu ayrım kasıtlıdır: NULL = "sistem üretti".
 * Yazan TEK yer: InAppChannel::buildRow(); değer DAİMA sunucuda `auth()->id()`'den
 * gelir, istemciden ASLA okunmaz.
 *
 * FOREIGN KEY YOK — bilerek: bu bir denetim izidir. `users`'a CASCADE bir FK
 * takılsaydı bir hesabın silinmesi onun gönderdiği bildirimleri de silerdi, SET NULL
 * ise izin kendisini yok ederdi; ikisi de izin var olma amacını bozar. Kullanıcı
 * silindikten sonra kalan kimlik, çözülemeyen bir referans olarak korunur
 * (okuma yolu bu kolona hiç bakmaz, dolayısıyla JOIN maliyeti de yoktur).
 *
 * up() fieldExists guard'lıdır (tekrar çalıştırılabilir) ve `after` yalnız dayanak
 * kolonun VARLIĞI doğrulandığında verilir: modül klasörü, FAZ 2 migration'ı henüz
 * koşmamış bir veritabanına da bırakılabilir. down() yalnız dosya bütünlüğü içindir:
 * kolon veri taşır, otomatik DROP veri kaybı olurdu.
 */
class AddCreatedByToNotifications extends Migration
{
    public function up()
    {
        // fieldExists() sonucu bağlantıda önbelleklenir ve şema bu süreç içinde
        // değişince bayat kalır (migrate:refresh / rollback+migrate). Guard yalan
        // söylemesin diye önce sıfırlanır.
        $this->db->resetDataCache();

        $table = 'notifications';

        if ($this->db->fieldExists('created_by', $table)) {
            return;
        }

        $definition = [
            'type'       => 'INT',
            'constraint' => 11,
            'unsigned'   => true,
            'null'       => true,
            'default'    => null,
        ];

        // `after` yalnız dayanak kolon gerçekten varsa verilir; yoksa MySQL
        // "Unknown column in 'notifications'" ile ALTER'ı tamamen reddeder.
        if ($this->db->fieldExists('exclude_users', $table)) {
            $definition['after'] = 'exclude_users';
        }

        $this->forge->addColumn($table, ['created_by' => $definition]);
    }

    public function down()
    {
        $this->db->resetDataCache();

        // Geri alma ELLE yapılır — bu kolon denetim verisi taşır, otomatik DROP veri kaybıdır.
        // Manuel adım (gerekirse):
        //   ALTER TABLE `{prefix}notifications` DROP COLUMN `created_by`;
    }
}
