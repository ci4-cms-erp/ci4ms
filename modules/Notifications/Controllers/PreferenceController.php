<?php

namespace Modules\Notifications\Controllers;

use Modules\Notifications\Config\NotificationsConfig;
use Modules\Notifications\Libraries\Notifier;
use Modules\Notifications\Libraries\SchemaGuard;

/**
 * Bildirim tercihleri (opt-out) — backend controller.
 *
 * Uçlar (bkz. Config/Routes.php):
 *   GET  backend/notifications/preferences  index() — susturma matrisi (role: read)
 *   POST backend/notifications/preferences  save()  — matrisi kaydet (role: update)
 *
 * GÜVENLİK: `user_id` DAİMA `auth()->id()`'den okunur, istemciden ASLA alınmaz.
 * Kabul edilen `type`/`channel` değerleri NotificationsConfig::$preferenceTypes ve
 * $preferenceChannels whitelist'lerinden gelir; POST dizisi üzerinde değil, whitelist
 * üzerinde dönülür — böylece bilinmeyen anahtar satır kurulumuna hiç giremez
 * (mass-assignment kapalı). Whitelist matrisi TEK yerde ({@see whitelistKeys()})
 * kurulur ve okuma, yazma, arayüz aynı matrisi kullanır: matriste olmayan bir satır
 * ne gösterilir ne de DOKUNULUR. Global CSRF aktiftir; bu uçlar $csrfExcept'e EKLENMEZ.
 *
 * Model B gereği tercih GÖNDERİM anında değil, OKUMA anında uygulanır
 * ({@see Notifier::applyRelevance()}); burada yalnız susturma satırları tutulur.
 */
class PreferenceController extends \Modules\Backend\Controllers\BaseController
{
    /** Tercihlerin tutulduğu tablo. */
    private const TABLE = 'notification_preferences';

    /**
     * Whitelist değerlerinde YASAK karakterler: LIKE joker'leri ve kaçış karakteri.
     *
     * Okuma yolu tercih tipini `n.type LIKE CONCAT(p.type, '.%')` ile karşılaştırır;
     * `%` ya da `_` taşıyan bir `type` orada joker gibi davranıp satır SAHİBİNİN tüm
     * bildirimlerini susturabilir. Bugün whitelist yüzünden böyle bir değer yazılamaz,
     * bu guard onu whitelist'in KENDİSİ için de garanti eder (seed/import/restore ile
     * gelecek bir yapılandırma hatası sessizce silahlanmasın).
     */
    private const LIKE_WILDCARDS = '%_\\';

    /**
     * Susturma matrisini render eder (satır: tip, sütun: kanal).
     *
     * @return string Render edilmiş tercih görünümü.
     */
    public function index(): string
    {
        $ready              = $this->tableReady();
        [$types, $channels] = $this->safeWhitelist();
        $whitelist          = $this->whitelistKeys();

        $muted = [];
        if ($ready) {
            foreach ($this->existingRows((int) auth()->id()) as $key => $row) {
                // Matris dışı satır arayüzde YOKTUR; gösterilseydi kapatılamayan
                // (ve save() tarafından da dokunulmayan) ölü bir kutu olurdu.
                if (isset($whitelist[$key]) && (int) $row->enabled === 0) {
                    $muted[] = $key;
                }
            }
        }

        $this->defData = array_merge($this->defData, [
            'prefReady'    => $ready,
            'prefTypes'    => $types,
            'prefChannels' => $channels,
            'prefMuted'    => $muted,
        ]);

        return view('Modules\Notifications\Views\preferences', $this->defData);
    }

    /**
     * Susturma matrisini kaydeder (oturum sahibinin kendi tercihleri).
     *
     * İşaretli kutu = SUSTUR (`enabled = 0`); işaretsiz = varsayılan (`enabled = 1`).
     * Yazımdan sonra kullanıcının okunmamış-rozet cache'i düşürülür, çünkü susturma
     * okuma-zamanı filtresidir ve sayıyı anında değiştirir.
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function save()
    {
        if (! $this->tableReady()) {
            return redirect()->route('notifPrefs')->with('error', lang('Notifications.prefTableMissing'));
        }

        $userId = (int) auth()->id();

        $this->persist($userId, $this->existingRows($userId), $this->postedMutes());

        cache()->delete(Notifier::cacheKey($userId));

        return redirect()->route('notifPrefs')->with('message', lang('Notifications.prefSaved'));
    }

    /**
     * Tercih tablosu migrate edilmiş mi (istek ömrü boyunca hafızalanır).
     *
     * @return bool Tablo varsa true.
     */
    private function tableReady(): bool
    {
        return SchemaGuard::hasPreferences($this->commonModel->db);
    }

    /**
     * Kullanıcının mevcut tercih satırlarını TEK sorguda okur.
     *
     * @param int $userId Oturum sahibinin kimliği.
     *
     * @return array<string, \stdClass> '{type}|{channel}' => satır (id, type, channel, enabled).
     */
    private function existingRows(int $userId): array
    {
        $rows = $this->commonModel->lists(self::TABLE, 'id, type, channel, enabled', ['user_id' => $userId]) ?: [];

        $map = [];
        foreach ($rows as $row) {
            $map[$row->type . '|' . $row->channel] = $row;
        }

        return $map;
    }

    /**
     * Yapılandırmanın, LIKE joker'i taşımayan tip ve kanal whitelist'leri.
     *
     * Elenen değer için sessiz kalmak yerine `warning` loglanır: arayüzde görünmeyen
     * bir tip, kaybolduğu fark edilmeyen bir yapılandırma hatasıdır.
     *
     * @return array{0: array<string, string>, 1: list<string>} [tip => lang anahtarı, kanal listesi].
     */
    private function safeWhitelist(): array
    {
        /** @var NotificationsConfig $config */
        $config = config(NotificationsConfig::class);

        $types    = array_filter($config->preferenceTypes, static fn ($type): bool => self::isSafeKeyPart((string) $type), ARRAY_FILTER_USE_KEY);
        $channels = array_values(array_filter($config->preferenceChannels, static fn (string $channel): bool => self::isSafeKeyPart($channel)));

        return [$types, $channels];
    }

    /**
     * Bir whitelist değerinin LIKE joker'i / kaçış karakteri taşıyıp taşımadığı.
     *
     * @param string $value Yapılandırmadan gelen tip ya da kanal.
     *
     * @return bool Güvenliyse true; joker taşıyorsa (loglanarak) false.
     */
    private static function isSafeKeyPart(string $value): bool
    {
        if (strpbrk($value, self::LIKE_WILDCARDS) === false) {
            return true;
        }

        log_message('warning', sprintf(
            'PreferenceController: `%s` tercih anahtarı YOK SAYILDI — LIKE joker\'i (%s) taşıyan bir değer okuma yolunda tüm bildirimleri susturabilir.',
            $value,
            self::LIKE_WILDCARDS
        ));

        return false;
    }

    /**
     * Kabul edilen '{tip}|{kanal}' anahtarlarının matrisi (TEK whitelist kaynağı).
     *
     * POST okuma ({@see postedMutes()}), yazma ({@see persist()}) ve arayüz
     * ({@see index()}) aynı matristen beslenir; bir anahtarın burada olmaması, o
     * satırın hiçbir yolda okunmadığı VE yazılmadığı anlamına gelir.
     *
     * @return array<string, array{0:string, 1:string}> '{type}|{channel}' => [tip, kanal].
     */
    private function whitelistKeys(): array
    {
        [$types, $channels] = $this->safeWhitelist();

        $keys = [];
        foreach (array_keys($types) as $type) {
            foreach ($channels as $channel) {
                $keys[$type . '|' . $channel] = [(string) $type, $channel];
            }
        }

        return $keys;
    }

    /**
     * POST'tan istenen susturma kümesini whitelist üzerinden çıkarır.
     *
     * POST dizisi üzerinde DEĞİL, whitelist matrisi üzerinde dönülür: whitelist dışı
     * bir tip/kanal sessizce yok sayılır ve hiçbir yazma yoluna ulaşamaz.
     *
     * @return array<string, array{0:string, 1:string}> '{type}|{channel}' => [tip, kanal].
     */
    private function postedMutes(): array
    {
        $posted = $this->request->getPost('mute');
        if (! is_array($posted)) {
            return [];
        }

        $desired = [];

        foreach ($this->whitelistKeys() as $key => [$type, $channel]) {
            if (! empty($posted[$type][$channel])) {
                $desired[$key] = [$type, $channel];
            }
        }

        return $desired;
    }

    /**
     * İstenen durumu diske yazar: yeni susturmalar tek batch, değişenler tek tek.
     *
     * Döngüler whitelist matrisi (tip × kanal) ve kullanıcının kendi satırlarıyla
     * SINIRLIDIR — büyüyen bir veri kümesi üzerinde N+1 değildir; ayrıca yalnız
     * DEĞİŞEN satır için sorgu açılır (tipik kayıtta 0-2 sorgu).
     *
     * Susturmayı KALDIRMA döngüsü yalnız matristeki anahtarlara dokunur: kullanıcının
     * whitelist DIŞI bir satırı (eski bir sürümden kalan ya da elle/seed ile yazılmış)
     * arayüzde hiç görünmediği için "kullanıcı kutuyu boşalttı" diye yorumlanamaz —
     * yorumlansaydı sahibinin hiç istemediği bir susturma sessizce AÇILIRDI.
     *
     * @param int                                      $userId   Oturum sahibinin kimliği.
     * @param array<string, \stdClass>                 $existing {@see existingRows()} çıktısı.
     * @param array<string, array{0:string, 1:string}> $desired  {@see postedMutes()} çıktısı.
     *
     * @return void
     */
    private function persist(int $userId, array $existing, array $desired): void
    {
        $now       = date('Y-m-d H:i:s');
        $whitelist = $this->whitelistKeys();
        $inserts   = [];

        foreach ($desired as $key => [$type, $channel]) {
            if (! isset($existing[$key])) {
                $inserts[] = [
                    'user_id'    => $userId,
                    'type'       => $type,
                    'channel'    => $channel,
                    'enabled'    => 0,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            } elseif ((int) $existing[$key]->enabled === 1) {
                $this->setEnabled((int) $existing[$key]->id, $userId, 0, $now);
            }
        }

        foreach ($existing as $key => $row) {
            if (! isset($whitelist[$key])) {
                continue;
            }

            if (! isset($desired[$key]) && (int) $row->enabled === 0) {
                $this->setEnabled((int) $row->id, $userId, 1, $now);
            }
        }

        if ($inserts !== []) {
            // INSERT IGNORE: UNIQUE(user_id,type,channel) iki eşzamanlı kayıtta
            // çakışabilir; ignore olmadan DatabaseException batch'in TAMAMINI
            // (çakışmayan satırlar dahil) kaybettirirdi. Notifier::markAllRead()
            // ile aynı kalıp.
            $this->commonModel->db->table(self::TABLE)->ignore(true)->insertBatch($inserts);
        }
    }

    /**
     * Tek bir tercih satırının açık/kapalı durumunu günceller (sahiplik WHERE'de).
     *
     * `user_id` koşulu savunma derinliğidir: `$id` yalnız
     * {@see existingRows()}'un oturum sahibi için okuduğu satırlardan gelir, ama
     * sahiplik sorgunun KENDİSİNDE de durursa, o okumaya eklenecek tek satırlık bir
     * regresyon burayı tam bir IDOR'a çeviremez.
     *
     * @param int    $id      Tercih satırı kimliği.
     * @param int    $userId  Satırın sahibi olması gereken kullanıcı (oturum sahibi).
     * @param int    $enabled 0 = susturuldu, 1 = varsayılan.
     * @param string $now     Yazım zamanı (Y-m-d H:i:s).
     *
     * @return void
     */
    private function setEnabled(int $id, int $userId, int $enabled, string $now): void
    {
        $this->commonModel->edit(
            self::TABLE,
            ['enabled' => $enabled, 'updated_at' => $now],
            ['id' => $id, 'user_id' => $userId]
        );
    }
}
