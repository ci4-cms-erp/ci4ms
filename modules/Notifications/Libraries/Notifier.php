<?php

declare(strict_types=1);

namespace Modules\Notifications\Libraries;

use ci4commonmodel\CommonModel;
use CodeIgniter\Database\BaseBuilder;
use Modules\Notifications\Config\NotificationsConfig;
use Modules\Notifications\Libraries\Channels\ChannelInterface;
use Modules\Notifications\Libraries\Channels\ChannelResult;

/**
 * Bildirim Merkezi'nin merkezî servisi (Model B: küresel kayıt + per-user okundu-durumu).
 *
 * Uygulamanın hiçbir yeri `notifications` tablosuna doğrudan yazmaz; her üretim
 * `notify()` builder'ından ya da geriye-uyum wrapper'larından (toUser/toRole) geçer.
 * Okuma tarafında `applyRelevance()` TEK relevans chokepoint'idir (IDOR kapısı):
 * bir kullanıcı yalnız broadcast, kendi 'user' hedefi ve üye olduğu 'group' hedefi
 * bildirimlerini görebilir; ayrıca satırın hariç tuttuğu (`exclude_users`) ve kullanıcının
 * susturduğu (`notification_preferences`) kayıtlar burada elenir. Okundu-durumu ayrı
 * `notification_reads` tablosunda tutulur; `notifications` satırları kullanıcıya özel
 * değildir (user_id = null).
 *
 * REALTIME: SSE ucu yalnızca içeriksiz bir "nudge" (`{"t":...}`) yayar, istemci feed
 * ucundan reconcile eder → feed listFor() → applyRelevance(). Bu yüzden hariç tutma ve
 * tercih filtreleri anlık teslime de kendiliğinden yansır; ayrı bir realtime filtresi
 * YOKTUR (hariç tutulan kullanıcı yalnız boşa bir reconcile yapar, içerik sızmaz).
 *
 * Kullanım:
 *   service('notifier')->notify('comment.new')->title('Yeni yorum')->toUser(5)->dispatch();
 *   service('notifier')->notify('update.available')->severity('warning')->broadcast()->dispatch();
 */
class Notifier
{
    private CommonModel $model;

    public function __construct()
    {
        $this->model = new CommonModel();
    }

    /**
     * Yeni bir yayın kurucusu (fluent builder) başlatır.
     *
     * @param string $type Makine-okunur olay tipi ('comment.new' ...).
     *
     * @return NotificationBuilder
     */
    public function notify(string $type): NotificationBuilder
    {
        return new NotificationBuilder($this, $type);
    }

    /**
     * Taban kanal haritasını modül taramasıyla birleştirip döndürür.
     *
     * Taban NotificationsConfig::$channels'tan gelir; her modülün
     * `Config\{Name}Config::$notificationChannels` property'si (Filters $csrfExcept
     * tarama kalıbıyla) scandir → class_exists → property_exists ile taranıp
     * merge edilir. Aynı slug'ı sonradan tanımlayan modül tabanı geçersiz kılar.
     *
     * @return array<string, ChannelInterface> slug => kanal örneği.
     */
    public function resolveChannels(): array
    {
        /** @var NotificationsConfig $config */
        $config = config(NotificationsConfig::class);

        $map = [];
        foreach ($config->channels as $slug => $class) {
            if (class_exists($class)) {
                $map[$slug] = new $class();
            }
        }

        $modulesPath = ROOTPATH . 'modules/';
        $modules     = array_filter(
            scandir($modulesPath) ?: [],
            static fn ($module) => ! in_array($module, ['.', '..', '.DS_Store'], true)
                && is_dir($modulesPath . $module)
        );

        foreach ($modules as $module) {
            $configClass = "Modules\\{$module}\\Config\\{$module}Config";
            if (! class_exists($configClass)) {
                continue;
            }

            $instance = new $configClass();
            if (! property_exists($instance, 'notificationChannels') || ! is_array($instance->notificationChannels)) {
                continue;
            }

            foreach ($instance->notificationChannels as $slug => $class) {
                if (is_string($class) && class_exists($class)) {
                    $map[$slug] = new $class();
                }
            }
        }

        return $map;
    }

    /**
     * Kullanıcının okunmamış (ilgili + okunmamış) bildirim sayısı.
     *
     * `notif_unread_{userId}` anahtarıyla NotificationsConfig::UNREAD_CACHE_TTL sn
     * cache'lenir — polling DB'yi yormasın. Tablolar hazır değilse rozet 0'dır.
     *
     * @param int $userId Oturumdaki kullanıcının kimliği.
     *
     * @return int Okunmamış bildirim sayısı.
     */
    public function unreadCount(int $userId): int
    {
        if (! $this->tablesReady()) {
            return 0;
        }

        $key   = self::cacheKey($userId);
        $count = cache($key);

        if ($count === null) {
            $groups  = $this->groupsFor($userId);
            $builder = $this->model->db->table('notifications n');
            $builder->join('notification_reads r', 'r.notification_id = n.id AND r.user_id = ' . $this->model->db->escape($userId), 'left');
            $builder->where('r.id', null);
            $this->applyRelevance($builder, $userId, $groups);

            $count = $builder->countAllResults();
            cache()->save($key, $count, NotificationsConfig::UNREAD_CACHE_TTL);
        }

        return (int) $count;
    }

    /**
     * Kullanıcı için ilgili bildirim akışı (en yeniden eskiye).
     *
     * Her satırda `read_at` NULL ise okunmamış demektir (view sözleşmesi).
     *
     * @param int $userId Oturumdaki kullanıcının kimliği.
     * @param int $limit  Döndürülecek en fazla satır sayısı.
     *
     * @return list<\stdClass> id,type,severity,title,body,url,created_at,read_at içeren satırlar.
     */
    public function listFor(int $userId, int $limit): array
    {
        if (! $this->tablesReady()) {
            return [];
        }

        $groups  = $this->groupsFor($userId);
        $builder = $this->model->db->table('notifications n');
        $builder->select('n.id, n.type, n.severity, n.title, n.body, n.url, n.created_at, r.read_at');
        $builder->join('notification_reads r', 'r.notification_id = n.id AND r.user_id = ' . $this->model->db->escape($userId), 'left');
        $this->applyRelevance($builder, $userId, $groups);
        $builder->orderBy('n.created_at', 'DESC');
        $builder->limit($limit);

        return $builder->get()->getResult();
    }

    /**
     * Tek bir bildirimi kullanıcı için okundu işaretler (IDOR-safe + idempotent).
     *
     * Kayıt yoksa ya da kullanıcıya ilgili değilse false döner (controller → 404).
     * Zaten okunmuşsa yeniden yazmaz. Başarıda okunmamış cache'i düşürülür.
     *
     * @param int $id     Bildirim kimliği.
     * @param int $userId Oturumdaki kullanıcının kimliği.
     *
     * @return bool İşaretlenebildiyse (ya da zaten işaretliyse) true; yoksa/ilgisizse false.
     */
    public function markRead(int $id, int $userId): bool
    {
        if (! $this->tablesReady()) {
            return false;
        }

        if ($this->model->selectOne('notifications', ['id' => $id]) === null) {
            return false;
        }

        if (! $this->isRelevant($id, $userId)) {
            return false;
        }

        $already = $this->model->selectOne('notification_reads', ['notification_id' => $id, 'user_id' => $userId]);
        if ($already === null) {
            $this->model->create('notification_reads', [
                'notification_id' => $id,
                'user_id'         => $userId,
                'read_at'         => date('Y-m-d H:i:s'),
            ]);
        }

        cache()->delete(self::cacheKey($userId));

        return true;
    }

    /**
     * Kullanıcının tüm ilgili + okunmamış bildirimlerini tek batch'te okundu işaretler.
     *
     * `INSERT IGNORE` ile UNIQUE(notification_id,user_id) çakışmaları atlanır; idempotent.
     *
     * @param int $userId Oturumdaki kullanıcının kimliği.
     *
     * @return int Okundu olarak eklenen satır sayısı.
     */
    public function markAllRead(int $userId): int
    {
        if (! $this->tablesReady()) {
            return 0;
        }

        $groups  = $this->groupsFor($userId);
        $builder = $this->model->db->table('notifications n');
        $builder->select('n.id');
        $builder->join('notification_reads r', 'r.notification_id = n.id AND r.user_id = ' . $this->model->db->escape($userId), 'left');
        $builder->where('r.id', null);
        $this->applyRelevance($builder, $userId, $groups);
        $rows = $builder->get()->getResult();

        if ($rows === []) {
            cache()->delete(self::cacheKey($userId));

            return 0;
        }

        $now  = date('Y-m-d H:i:s');
        $data = array_map(static fn ($row) => [
            'notification_id' => (int) $row->id,
            'user_id'         => $userId,
            'read_at'         => $now,
        ], $rows);

        $this->model->db->table('notification_reads')->ignore(true)->insertBatch($data);

        cache()->delete(self::cacheKey($userId));

        return count($data);
    }

    /**
     * Tek bir kullanıcıya bildirim gönderir (geriye-uyum wrapper'ı; builder'a delege eder).
     *
     * Model B semantiği: user_id'li satır yerine target_type='user' küresel satır yazar.
     *
     * DÖNÜŞ DEĞERİNİN ANLAMI (teknik borç, bilerek kayıt altında): dönüş HERHANGİ bir
     * kanalın ok demesidir ({@see anyOk()}), "satır yazıldı" DEĞİLDİR. Realtime kanal
     * açıkken kalıcı yazım reddedilse bile (hariç tutma uygulanamadı, tablo yok, insert
     * başarısız) bu metot true döner. Doğru ölçü {@see DispatchOutcome}'dur; composer
     * yolu ({@see \Modules\Notifications\Controllers\ComposerController::send()}) onu
     * kullanır. Bu iki wrapper'ın davranışı geriye-uyum için OLDUĞU GİBİ bırakılmıştır;
     * çağıranları dönüşü bir teslim garantisi sayamaz.
     *
     * @param int         $userId Alıcı kullanıcı kimliği.
     * @param string      $type   Olay tipi.
     * @param string      $title  Başlık.
     * @param string|null $body   Gövde ya da null.
     * @param string|null $url    Tıklama hedefi ya da null.
     *
     * @return bool Kanallardan en az biri ok döndüyse true (satır yazıldığının GARANTİSİ DEĞİL).
     */
    public function toUser(int $userId, string $type, string $title, ?string $body = null, ?string $url = null): bool
    {
        $results = $this->notify($type)
            ->severity('info')->title($title)->body($body)->url($url)
            ->toUser($userId)
            ->dispatch();

        return $this->anyOk($results);
    }

    /**
     * Bir Shield grubuna bildirim gönderir (geriye-uyum wrapper'ı; builder'a delege eder).
     *
     * Model B semantiği: ARTIK fan-out YOK — üye başına satır açmaz, tek bir küresel
     * 'group' satırı yazar ve dönüş 1/0'dır (üye sayısı değil, teslim denemesi ok mu).
     * Grup üyeliği okuma-zamanında relevans sorgusuyla çözülür.
     *
     * DÖNÜŞ DEĞERİNİN ANLAMI: {@see toUser()} ile aynı teknik borç geçerlidir — 1,
     * "herhangi bir kanal ok döndü" demektir, "satır yazıldı" demek DEĞİLDİR.
     *
     * @param string      $groupName Grup adı.
     * @param string      $type      Olay tipi.
     * @param string      $title     Başlık.
     * @param string|null $body      Gövde ya da null.
     * @param string|null $url       Tıklama hedefi ya da null.
     *
     * @return int Kanallardan en az biri ok döndüyse 1, aksi halde 0 (satır garantisi DEĞİL).
     */
    public function toRole(string $groupName, string $type, string $title, ?string $body = null, ?string $url = null): int
    {
        $results = $this->notify($type)
            ->severity('info')->title($title)->body($body)->url($url)
            ->toGroup($groupName)
            ->dispatch();

        return $this->anyOk($results) ? 1 : 0;
    }

    /**
     * Okunmamış-rozet cache anahtarı.
     *
     * @param int $userId Kullanıcı kimliği.
     *
     * @return string Cache anahtarı ('notif_unread_{userId}').
     */
    public static function cacheKey(int $userId): string
    {
        return 'notif_unread_' . $userId;
    }

    /**
     * Kullanıcının YETKİLİ kanal (Redis-destekli SSE) listesi — IDOR kapısı.
     *
     * `applyRelevance()` ile TAM tutarlı olmalıdır: bir kullanıcı yalnız 'broadcast',
     * kendi 'user/{id}' ve üye olduğu 'group/{name}' kanallarını dinleyebilir.
     * RealtimeController::stream() dinlenecek kanalları YALNIZ bu server-side
     * topicsFor() okumasından türetir; istemci hiçbir kanal göndermez (IDOR koruması burada).
     *
     * @param int $userId Oturumdaki kullanıcının kimliği.
     *
     * @return list<string> Yetkili topic adları.
     */
    public function topicsFor(int $userId): array
    {
        $topics = [
            self::topicFor('broadcast', null),
            self::topicFor('user', (string) $userId),
        ];

        foreach ($this->groupsFor($userId) as $group) {
            $topics[] = self::topicFor('group', $group);
        }

        return $topics;
    }

    /**
     * Bir hedeften (target_type/target_value) Redis-destekli SSE kanal adını türetir (tek kaynak).
     *
     * Hem yayın (bump, RealtimeChannel) hem yetkilendirme (dinleme, topicsFor)
     * aynı şemadan gelsin diye buradadır: 'user' → user/{id}, 'group' → group/{name},
     * diğer her şey (broadcast dahil) → 'broadcast'.
     *
     * @param string      $targetType  'broadcast' | 'user' | 'group'.
     * @param string|null $targetValue Hedef değeri (user id / grup adı) ya da null.
     *
     * @return string Kanal adı.
     */
    public static function topicFor(string $targetType, ?string $targetValue): string
    {
        return match ($targetType) {
            'user'  => 'user/' . (string) $targetValue,
            'group' => 'group/' . (string) $targetValue,
            default => 'broadcast',
        };
    }

    /**
     * Relevans filtresini query builder'a uygular (TEK relevans chokepoint — IDOR kapısı).
     *
     * Üç katman sırayla uygulanır ve DÖRT okuma yolu da (unreadCount, listFor,
     * markAllRead, isRelevant) bu tek metottan geçer:
     *   1. HEDEF: broadcast + kendi 'user' hedefi + üye olunan 'group' hedefi.
     *   2. HARİÇ TUTMA: satırın `exclude_users` listesinde olan kullanıcı elenir.
     *   3. TERCİH: kullanıcının susturduğu tip/kanal elenir (kritik hariç).
     * Bu yüzden markRead() de hariç tutulan/susturulmuş kayda 404 döner (isRelevant).
     *
     * @param BaseBuilder $builder Üzerine WHERE eklenecek builder.
     * @param int         $userId  Kullanıcı kimliği.
     * @param string[]    $groups  Kullanıcının üye olduğu gruplar.
     */
    private function applyRelevance(BaseBuilder $builder, int $userId, array $groups): void
    {
        $builder->groupStart()
            ->where('n.target_type', 'broadcast')
            ->orGroupStart()
                ->where('n.target_type', 'user')->where('n.target_value', (string) $userId)
            ->groupEnd();

        if (! empty($groups)) {
            $builder->orGroupStart()
                ->where('n.target_type', 'group')->whereIn('n.target_value', $groups)
            ->groupEnd();
        }

        $builder->groupEnd();

        $this->applyExclusion($builder, $userId);
        $this->applyPreferences($builder, $userId);
    }

    /**
     * Satır bazlı hariç tutma filtresini ekler (`exclude_users` sentinel-CSV'si).
     *
     * Depolanan biçim daima virgülle sarmalıdır (`,5,12,`), bu yüzden `,1,` kalıbı
     * `,12,` değerine YANLIŞ eşleşmez ({@see NotificationMessage::encodeExcludeUsers()}).
     * Kimlik metotta int'e cast edilmiş olarak gelir ve kalıp ayrıca `escape()` ile
     * kaçırılır (iki katman). Kolon henüz migrate edilmemişse filtre hiç eklenmez.
     *
     * @param BaseBuilder $builder Üzerine WHERE eklenecek builder.
     * @param int         $userId  Kullanıcı kimliği.
     */
    private function applyExclusion(BaseBuilder $builder, int $userId): void
    {
        if (! SchemaGuard::hasExcludeUsers($this->model->db)) {
            return;
        }

        $pattern = $this->model->db->escape(NotificationMessage::excludeMatchPattern($userId));

        $builder->where("(n.exclude_users IS NULL OR n.exclude_users NOT LIKE {$pattern})", null, false);
    }

    /**
     * Kullanıcı tercihlerini (opt-out) okuma zamanında uygular (anti-join).
     *
     * Model B satırları küresel olduğu için susturma GÖNDERİM anında uygulanamaz;
     * filtre burada, `notification_preferences` tablosuna LEFT JOIN + `p.id IS NULL`
     * ile kurulur. `p.type = n.type OR n.type LIKE CONCAT(p.type, '.%')` sayesinde
     * bir tercih satırı tam tip ya da tip ÖNEKİ olarak yazılabilir ('audit' → 'audit.*').
     *
     * KRİTİK: `n.severity <> 'critical'` koşulu bilerek JOIN'in ON tarafındadır,
     * WHERE'de DEĞİL. Böylece kritik satırlar hiç JOIN'lenmez → (a) asla susturulamaz,
     * (b) birden çok eşleşen tercih satırı olsa bile listFor() satırı ÇOĞALTMAZ ve
     * unreadCount()'un countAllResults() sayısını ŞİŞİRMEZ (ayakta kalan her satırın
     * eşleşme sayısı sıfırdır, yani çıktı satırı tektir). Aynı nedenle bu JOIN
     * countAllResults()'ı bozmaz: sorguya GROUP BY/DISTINCT eklenmez.
     *
     * Performans: JOIN indeksli `user_id` ile daralır — `notif_pref_unique` ve
     * `notif_pref_lookup` indekslerinin İKİSİ de `user_id` ile başlar, hangisinin
     * kullanılacağı optimizer'ın kararıdır — ve kullanıcı başına tercih kümesi
     * küçüktür; ek sorgu yok, N+1 yok.
     *
     * @param BaseBuilder $builder Üzerine JOIN/WHERE eklenecek builder.
     * @param int         $userId  Kullanıcı kimliği.
     */
    private function applyPreferences(BaseBuilder $builder, int $userId): void
    {
        if (! SchemaGuard::hasPreferences($this->model->db)) {
            return;
        }

        $table = $this->model->db->prefixTable('notification_preferences');
        $owner = $this->model->db->escape($userId);

        $builder->join(
            "`{$table}` p",
            "p.user_id = {$owner}"
                . ' AND p.enabled = 0'
                . " AND (p.channel = '*' OR p.channel = n.channel)"
                . " AND (p.type = n.type OR n.type LIKE CONCAT(p.type, '.%'))"
                . " AND n.severity <> 'critical'",
            'left',
            false
        );

        $builder->where('p.id', null);
    }

    /**
     * Tek bir bildirimin kullanıcıya ilgili olup olmadığını doğrular (id-scope'lu relevans).
     *
     * @param int $id     Bildirim kimliği.
     * @param int $userId Kullanıcı kimliği.
     *
     * @return bool İlgiliyse true.
     */
    private function isRelevant(int $id, int $userId): bool
    {
        $groups  = $this->groupsFor($userId);
        $builder = $this->model->db->table('notifications n');
        $builder->select('n.id');
        $builder->where('n.id', $id);
        $this->applyRelevance($builder, $userId, $groups);

        return $builder->get()->getRow() !== null;
    }

    /**
     * Kullanıcının üye olduğu Shield gruplarının adlarını döndürür (TEK grup-sorgusu kaynağı).
     *
     * Hem modül içi relevans/topic türetimi (applyRelevance, topicsFor) hem de modül dışı
     * rol bazlı politikalar (RealtimeController'ın SSE bağlantı cap'i) bu tek okumayı
     * kullanır; ikinci bir grup sorgusu implementasyonu YOKTUR.
     *
     * UYARI (yetki): Bu metot YETKİ KONTROLÜ YAPMAZ — verilen herhangi bir userId'nin
     * Shield grup üyeliğini olduğu gibi döndürür. ÇAĞIRAN, `$userId`'nin oturum sahibine
     * ait olduğunu (`auth()->id()`) garanti ETMEK ZORUNDADIR; istemciden gelen bir id ile
     * çağrılırsa rol keşfine açık bir IDOR olur.
     *
     * @internal Modül içi kullanım + RealtimeController cap politikası içindir.
     *
     * @param int $userId Kullanıcı kimliği (çağıran tarafından oturum sahibi olduğu doğrulanmış).
     *
     * @return string[] Grup adları.
     */
    public function groupsFor(int $userId): array
    {
        $rows = $this->model->lists('auth_groups_users', 'group', ['user_id' => $userId]) ?: [];

        return array_column(array_map(static fn ($row) => (array) $row, $rows), 'group');
    }

    /**
     * Verilen kullanıcıların, verilen grupların hangisine üye olduğunu TEK sorguda çözer.
     *
     * Yalnız GÖNDERİM anındaki örtüşme temizliği içindir (bkz.
     * {@see NotificationBuilder::coveredUserTargets()}): aynı yayında hem `toUser(5)`
     * hem de 5'in üyesi olduğu bir gruba `toGroup(...)` verildiğinde kullanıcının iki
     * satır görmesini engellemek üzere, kullanıcı grup satırının `exclude_users`
     * listesine yazılır. Fan-out DEĞİLDİR: yalnızca AÇIKÇA hedeflenen kimliklerle
     * sınırlı bir kesişim okumasıdır, grup üyelerinin tamamı materyalize edilmez.
     *
     * `auth_groups_users` sorgusu — {@see groupsFor()} ile birlikte — bu sınıfta
     * kalır; modül dışında ikinci bir grup-sorgusu implementasyonu YOKTUR.
     * Shield tabloları henüz migrate edilmemişse boş harita döner (fatal atmaz).
     *
     * @internal Yalnız gönderim anındaki örtüşme temizliği içindir (NotificationBuilder).
     *
     * @param list<int>    $userIds Açıkça hedeflenmiş kullanıcı kimlikleri.
     * @param list<string> $groups  Aynı yayında hedeflenmiş grup adları.
     *
     * @return array<string, list<int>> Grup adı => o gruba üye olan hedeflenmiş kimlikler.
     */
    public function groupMembersAmong(array $userIds, array $groups): array
    {
        if ($userIds === [] || $groups === [] || ! $this->model->db->tableExists('auth_groups_users')) {
            return [];
        }

        $rows = $this->model->db->table('auth_groups_users')
            ->select('user_id, group')
            ->whereIn('user_id', $userIds)
            ->whereIn('group', $groups)
            ->get()->getResultArray();

        $map = [];
        foreach ($rows as $row) {
            $map[(string) $row['group']][] = (int) $row['user_id'];
        }

        return $map;
    }

    /**
     * Bir yayının kaç KİŞİYE gideceğini gönderim ÖNCESİ tahmin eder (composer önizlemesi).
     *
     * Model B satırları küresel olduğu için alıcı sayısı diske hiç yazılmaz; bu metot
     * onu yayın tanımından (broadcast / kullanıcı / grup / hariç tutma) TÜRETİR ve
     * hiçbir şey yazmaz. `auth_groups_users` sorgusu — {@see groupsFor()} ve
     * {@see groupMembersAmong()} ile birlikte — bu sınıfta kalır; modülde ikinci bir
     * grup-sorgusu sahibi YOKTUR.
     *
     * SORGU BÜTÇESİ (N+1 yok, girdi boyundan bağımsız):
     *   - broadcast: 1 sorgu (toplam) + hariç tutma varsa 1 sorgu (var olanları say).
     *   - hedefli:   grup verilmişse TEK `SELECT DISTINCT user_id ... WHERE group IN (...)`;
     *     açık kimlikler ve hariç tutma PHP tarafında küme işlemiyle birleştirilir.
     *
     * KAPSAM: yalnız ADRESLENEBİLİR hesaplar sayılır ({@see scopeAddressableUsers()}) —
     * soft-delete edilmiş ve banlı hesaplar bildirimi hiçbir zaman göremez. Hariç
     * tutulanlar broadcast'te GERÇEKTEN var olan kimliklerle düşülür; var olmayan bir
     * kimliği düşmek sayıyı olduğundan küçük gösterirdi.
     *
     * YAKLAŞIKLIK (dürüstlük notu): hedefli modda grup üyeleri `auth_groups_users`'tan
     * sayılır, `users`'a JOIN yapılmaz. Silinmiş ya da banlı bir kullanıcıya ait artık
     * bir üyelik satırı kalmışsa sayı bir kişi fazla çıkabilir. Bu, ikinci bir JOIN'in
     * maliyetine değmeyecek bir sapmadır: değer bir ÖNİZLEMEdir, teslim garantisi değildir.
     *
     * Shield tabloları henüz migrate edilmemişse 0 döner (fatal atmaz).
     *
     * @param bool                 $broadcast    Yayın tüm kullanıcılara mı gidiyor (diğer hedeflere baskın).
     * @param array<int, mixed>    $userIds      Açıkça hedeflenen kullanıcı kimlikleri.
     * @param array<int, mixed>    $groups       Hedeflenen Shield grup adları.
     * @param array<int, mixed>    $excludeUsers Hedefin dışında bırakılacak kullanıcı kimlikleri.
     *
     * @return int Tekilleştirilmiş tahmini alıcı sayısı (asla negatif değil).
     */
    public function recipientCount(bool $broadcast, array $userIds, array $groups, array $excludeUsers): int
    {
        if (! $this->model->db->tableExists('users')) {
            return 0;
        }

        $excluded = self::normalizeIds($excludeUsers);

        if ($broadcast) {
            return $this->broadcastRecipientCount($excluded);
        }

        $recipients = array_merge(
            self::normalizeIds($userIds),
            $this->groupMemberIds(self::normalizeNames($groups))
        );

        return count(array_diff(array_unique($recipients), $excluded));
    }

    /**
     * Bir `users` sorgusunu ADRESLENEBİLİR hesaplarla sınırlar (TEK filtre kaynağı).
     *
     * Adreslenebilir = bildirimi GÖREBİLECEK hesap. İki eleme uygulanır:
     *   1. `deleted_at IS NULL` — Shield soft-delete edilmiş satırı hiçbir okuma
     *      yolunda döndürmez.
     *   2. `status <> 'banned'` — Shield oturum açmayı ban'da reddeder
     *      ({@see \CodeIgniter\Shield\Authentication\Authenticators\Session::login()}),
     *      yani banlı hesap bildirimi hiçbir zaman okuyamaz. Kolon NULL'lanabilir
     *      olduğu için `IS NULL OR <> 'banned'` yazılır; düz `<> 'banned'` NULL satırları
     *      da elerdi, yani sıradan kullanıcıların TAMAMINI.
     *
     * `active` kolonu BİLEREK filtrelenmez: bu kurulumda etkinleştirme akışı kapalıdır
     * (`Modules\Auth\Config\Auth::$actions['register'] === null`) ve Shield oturum
     * açarken `active` bakmaz, yani `active = 0` olan hesap pekâlâ giriş yapıp bildirim
     * okuyabilir. Onu elemek gerçek alıcıları sessizce düşürürdü.
     *
     * Hem composer'ın seçim/doğrulama sorguları hem de bu sınıfın sayımları buradan
     * geçer; kural üç yerde kopyalanmaz.
     *
     * @param BaseBuilder $builder Üzerine WHERE eklenecek `users` builder'ı.
     *
     * @return BaseBuilder Zincirlemeye uygun aynı builder.
     */
    public static function scopeAddressableUsers(BaseBuilder $builder): BaseBuilder
    {
        return $builder->where('deleted_at', null)
            ->groupStart()
                ->where('status', null)
                ->orWhere('status !=', 'banned')
            ->groupEnd();
    }

    /**
     * Broadcast alıcı sayısı: adreslenebilir hesap sayısı eksi var olan hariç tutulanlar.
     *
     * @param list<int> $excluded Normalize edilmiş hariç tutma kimlikleri.
     *
     * @return int Alıcı sayısı (asla negatif değil).
     */
    private function broadcastRecipientCount(array $excluded): int
    {
        $total = (int) self::scopeAddressableUsers($this->model->db->table('users'))->countAllResults();

        if ($excluded === []) {
            return $total;
        }

        $existing = (int) self::scopeAddressableUsers($this->model->db->table('users'))
            ->whereIn('id', $excluded)
            ->countAllResults();

        return max(0, $total - $existing);
    }

    /**
     * Verilen grupların üye kimliklerini TEK sorguda, tekilleştirilmiş olarak döndürür.
     *
     * @param list<string> $groups Normalize edilmiş grup adları.
     *
     * @return list<int> Üye kullanıcı kimlikleri (tekil).
     */
    private function groupMemberIds(array $groups): array
    {
        if ($groups === [] || ! $this->model->db->tableExists('auth_groups_users')) {
            return [];
        }

        $rows = $this->model->db->table('auth_groups_users')
            ->select('user_id')
            ->distinct()
            ->whereIn('group', $groups)
            ->get()->getResultArray();

        return array_values(array_map(static fn (array $row): int => (int) $row['user_id'], $rows));
    }

    /**
     * Ham kimlik listesini sayıma uygun hale getirir: int'e cast, 0/negatifi at, tekilleştir.
     *
     * {@see NotificationMessage::normalizeExcludeUsers()} ile aynı ELEME kuralını
     * uygular ama onun DEPOLAMA sözleşmesini (sıralama) taşımaz; burada tek gereken
     * küme semantiğidir, saklanan bir değer üretilmez.
     *
     * @param array<int, mixed> $ids Ham kullanıcı kimlikleri.
     *
     * @return list<int> Tekil, pozitif kimlikler.
     */
    private static function normalizeIds(array $ids): array
    {
        $normalized = [];

        foreach ($ids as $id) {
            // Skaler olmayan öğe atılır: iç içe bir dizi `(int)` cast'inde sessizce
            // 1'e dönüşür ve hedeflenmemiş bir kullanıcıyı sayıma sokardı.
            if (! is_scalar($id)) {
                continue;
            }

            $value = (int) $id;

            if ($value > 0) {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Ham grup adı listesini temizler: metne çevir, boşları at, tekilleştir.
     *
     * @param array<int, mixed> $names Ham grup adları.
     *
     * @return list<string> Tekil, boş olmayan grup adları.
     */
    private static function normalizeNames(array $names): array
    {
        $normalized = [];

        foreach ($names as $name) {
            if (! is_scalar($name)) {
                continue;
            }

            $value = trim((string) $name);

            if ($value !== '') {
                $normalized[] = $value;
            }
        }

        return array_values(array_unique($normalized));
    }

    /**
     * Model B tablolarının (notifications + notification_reads) hazır olup olmadığı.
     *
     * @return bool İkisi de mevcutsa true.
     */
    private function tablesReady(): bool
    {
        return $this->model->db->tableExists('notifications')
            && $this->model->db->tableExists('notification_reads');
    }

    /**
     * Kanal sonuçları içinde en az bir başarılı teslim var mı.
     *
     * @param ChannelResult[] $results Teslim sonuçları.
     *
     * @return bool Herhangi biri ok ise true.
     */
    private function anyOk(array $results): bool
    {
        return array_filter($results, static fn (ChannelResult $result) => $result->ok) !== [];
    }
}
