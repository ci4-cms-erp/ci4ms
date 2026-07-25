<?php

namespace Modules\Notifications\Libraries\Channels;

use ci4commonmodel\CommonModel;
use Modules\Notifications\Config\NotificationsConfig;
use Modules\Notifications\Libraries\NotificationMessage;
use Modules\Notifications\Libraries\SchemaGuard;

/**
 * In-app kanal — mesajı `notifications` tablosuna küresel (Model B) satır olarak yazar.
 *
 * Satır `user_id = null` ile açılır; hedef yalnız `target_type`/`target_value` ile
 * ifade edilir ve okuma anında relevans sorgusuyla çözülür (fan-out yok). Yazımdan
 * sonra okunmamış-rozet cache'i taşınabilir bir `deleteMatching` guard'ıyla düşürülür;
 * desteklemeyen cache handler'larında 60 sn'lik TTL üst-sınır olarak devreye girer.
 *
 * Hariç tutma bu kanalda KAYNAĞINA göre ele alınır: AÇIK (`exceptUser()`) bir dışlama
 * garanti edilemiyorsa satır yazılmaz (FAIL-CLOSED), yalnız TÜRETİLMİŞ (örtüşme
 * daraltması) bir dışlama uygulanamıyorsa satır yazılır (FAIL-OPEN + uyarı).
 * Gerekçe: {@see refuseUnenforceableExclusion()}.
 *
 * KALICILIK BEYANI: bu kanal {@see DurableChannelInterface} uygular, yani `ok` dönen
 * her sonucu "bildirim gerçekten yazıldı" olarak sayılabilir kılar. Yayının teslim
 * edilip edilmediği kararı YALNIZ böyle işaretlenmiş kanalların sonuçlarından
 * türetilir ({@see \Modules\Notifications\Libraries\DispatchOutcome}).
 */
final class InAppChannel implements DurableChannelInterface
{
    /** Küresel satırlarda kullanıcı-özel değeri yoktur. */
    private const GLOBAL_USER_ID = null;

    private CommonModel $model;

    public function __construct()
    {
        $this->model = new CommonModel();
    }

    /**
     * Mesajı bir `notifications` satırına yazar ve okunmamış cache'ini geçersiz kılar.
     *
     * Uygulanamayan bir AÇIK hariç tutma isteğinde satır YAZILMAZ; yalnız türetilmiş
     * daraltma uygulanamıyorsa satır yazılır ve durum uyarı olarak loglanır. Gerekçe:
     * {@see refuseUnenforceableExclusion()}.
     *
     * @param NotificationMessage $message Temizlenmiş, tek-hedefli mesaj.
     *
     * @return ChannelResult Satır yazıldıysa ok(insertId), aksi halde skipped.
     */
    public function send(NotificationMessage $message): ChannelResult
    {
        if (! $this->model->db->tableExists('notifications')) {
            return ChannelResult::skipped('notifications-table-missing');
        }

        $refusal = $this->refuseUnenforceableExclusion($message);
        if ($refusal !== null) {
            return $refusal;
        }

        $insertId = $this->model->create('notifications', $this->buildRow($message));

        if ($insertId <= 0) {
            return ChannelResult::skipped('insert-failed');
        }

        $this->invalidateUnreadCaches();

        return ChannelResult::ok($insertId);
    }

    /**
     * Uygulanamayacak bir hariç tutma isteğinde yazımı reddeder (yalnız AÇIK dışlama için).
     *
     * `exceptUser()` bir teslim TERCİHİ değil, çağıranın açıkça istediği bir dışlama
     * GARANTİSİdir ({@see \Modules\Notifications\Libraries\NotificationBuilder::exceptUser()}
     * sözleşmesi: "hariç tutma HER ZAMAN kazanır"). Garantinin verilemediği iki durumda
     * satırı yazmak, hariç tutulan kullanıcının bildirimi GÖRMESİ demektir — sessiz bir
     * sızıntı. Bu yüzden satır hiç yazılmaz ve olay `critical` seviyede loglanır:
     *   1. `exclude_users` kolonu henüz migrate edilmemiştir (SchemaGuard false döner);
     *      alan yazılamaz, okuma yolundaki filtre de hiç eklenmez.
     *   2. Liste {@see NotificationsConfig::EXCLUDE_USERS_MAX} tavanını aşar; TEXT
     *      taşmasında değer sessizce kesilir ve sentinel sarmalı bozulur. KIRPMA
     *      yapılmaz — kırpma da tam olarak sızıntı yönünde bozardı.
     *
     * KAYNAK AYRIMI: örtüşme daraltmasından TÜREYEN liste ({@see
     * \Modules\Notifications\Libraries\NotificationBuilder::coveredUserTargets()}) bir
     * garanti değil, çift-teslim önleyen bir optimizasyondur. Kolon yokken onun için de
     * fail-closed davranmak, kimsenin `exceptUser()` çağırmadığı `toUser(5) +
     * toGroup('x')` yayınında GRUP satırının tamamen kaybolması demekti: tüm grup
     * bildirimi alamıyordu. Bu, önlenmek istenen sızıntıdan daha ağır bir kayıp
     * olduğundan yalnız türetilmiş dışlamada satır YAZILIR (FAIL-OPEN) ve durum
     * `warning` ile loglanır — bedeli, doğrudan hedeflenen kullanıcının bildirimi iki
     * kez görmesidir. Tavan kontrolü ise kaynağı umursamaz: taşan CSV'nin sızıntısı
     * hangi kaynaktan geldiğine bakmaz, birleşik sayı üzerinden değerlendirilir.
     *
     * Hariç tutma İSTEMEYEN yayınlar (`excludeUsers === []`) bu yoldan hiç geçmez, yani
     * migrate edilmemiş bir modülde normal bildirimler eskisi gibi yazılmaya devam eder.
     *
     * @param NotificationMessage $message Teslim edilmek üzere olan mesaj.
     *
     * @return ChannelResult|null Reddedildiyse skipped sonucu, aksi halde null.
     */
    private function refuseUnenforceableExclusion(NotificationMessage $message): ?ChannelResult
    {
        if ($message->excludeUsers === []) {
            return null;
        }

        $where = $message->targetType . '/' . ($message->targetValue ?? '-');

        if (! SchemaGuard::hasExcludeUsers($this->model->db)) {
            if ($message->explicitExcludeUsers === []) {
                log_message('warning', sprintf(
                    'InAppChannel: `%s` bildiriminde örtüşme daraltması UYGULANAMADI — `notifications.exclude_users` kolonu yok (hedef: %s). Satır yazıldı; doğrudan hedeflenen %d kullanıcı bildirimi iki kez görebilir. `php spark migrate --all` çalıştırın.',
                    $message->type,
                    $where,
                    count($message->derivedExcludeUsers)
                ));

                return null;
            }

            log_message('critical', sprintf(
                'InAppChannel: `%s` bildirimi YAZILMADI — %d kullanıcı hariç tutulacaktı ama `notifications.exclude_users` kolonu yok (hedef: %s). `php spark migrate --all` çalıştırın.',
                $message->type,
                count($message->excludeUsers),
                $where
            ));

            return ChannelResult::skipped('exclusion-unsupported');
        }

        if (count($message->excludeUsers) > NotificationsConfig::EXCLUDE_USERS_MAX) {
            log_message('critical', sprintf(
                'InAppChannel: `%s` bildirimi YAZILMADI — hariç tutma listesi %d kullanıcı, tavan %d (hedef: %s). Liste kırpılsaydı hariç tutulan kullanıcılar bildirimi görürdü.',
                $message->type,
                count($message->excludeUsers),
                NotificationsConfig::EXCLUDE_USERS_MAX,
                $where
            ));

            return ChannelResult::skipped('exclusion-too-large');
        }

        return null;
    }

    /**
     * Bir küresel bildirim satırının alan setini hazırlar.
     *
     * `exclude_users` yalnız kolon migrate edilmişse yazılır (FAZ 2 additive kolonu);
     * biçim sentinel-sarmalı CSV'dir ({@see NotificationMessage::encodeExcludeUsers()}),
     * boş listede NULL kalır.
     *
     * `created_by` de aynı şekilde kolona bağlıdır, AMA sözleşmesi TERSİDİR ve bu
     * FAIL-OPEN davranış kasıtlıdır: kolon yoksa satır YİNE yazılır, yalnız iz düşer.
     * `exclude_users`'ın fail-closed refüzü ({@see refuseUnenforceableExclusion()})
     * BURAYA TAŞINMAZ, çünkü ikisi farklı şeyleri korur: hariç tutma bir TESLİM
     * garantisidir — uygulanmazsa dışlanan kullanıcı bildirimi görür, yani sızıntı
     * olur. `created_by` ise yalnız bir HESAP VEREBİLİRLİK izidir; uygulanamaması
     * kimseye yanlış içerik göstermez. Migrate edilmemiş bir kurulumda bildirimlerin
     * tamamen kaybolması, "kim gönderdi" bilgisinin kaybolmasından çok daha ağır bir
     * sonuçtur, bu yüzden durum yalnız loglanır.
     *
     * @param NotificationMessage $message Zaten temizlenmiş mesaj.
     *
     * @return array<string, mixed>
     */
    private function buildRow(NotificationMessage $message): array
    {
        $row = [
            'user_id'      => self::GLOBAL_USER_ID,
            'type'         => $message->type,
            'severity'     => $message->severity,
            'target_type'  => $message->targetType,
            'target_value' => $message->targetValue,
            'title'        => $message->title,
            'body'         => $message->body,
            'url'          => $message->url,
            'channel'      => 'inapp',
            'read_at'      => null,
            'created_at'   => date('Y-m-d H:i:s'),
        ];

        if (SchemaGuard::hasExcludeUsers($this->model->db)) {
            $row['exclude_users'] = NotificationMessage::encodeExcludeUsers($message->excludeUsers);
        }

        if ($message->createdBy !== null) {
            if (SchemaGuard::hasCreatedBy($this->model->db)) {
                $row['created_by'] = $message->createdBy;
            } else {
                log_message('warning', sprintf(
                    'InAppChannel: `%s` bildiriminin `created_by` izi YAZILAMADI — `notifications.created_by` kolonu yok (üreten: %d). Satır yine de yazıldı. `php spark migrate --all` çalıştırın.',
                    $message->type,
                    $message->createdBy
                ));
            }
        }

        return $row;
    }

    /**
     * Küresel yazımda tüm kullanıcıların okunmamış-rozet cache'lerini süpürür.
     *
     * `deleteMatching` CacheInterface'te tanımlıdır, ancak bazı handler'lar
     * (Memcached/Wincache) onu `: never` olarak uygulayıp istisna fırlatır; bu
     * yüzden portatiflik guard'ı method_exists değil try/catch'tir. Desteklemeyen
     * handler'da 60 sn'lik TTL üst-sınır olarak devreye girer (fatal atılmaz).
     */
    private function invalidateUnreadCaches(): void
    {
        try {
            cache()->deleteMatching('notif_unread_*');
        } catch (\Throwable $e) {
            log_message('debug', 'InAppChannel deleteMatching cache handler tarafından desteklenmiyor: ' . $e->getMessage());
        }
    }
}
