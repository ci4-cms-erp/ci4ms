<?php

namespace Modules\Notifications\Libraries;

use Modules\Notifications\Libraries\Channels\ChannelResult;
use Modules\Notifications\Libraries\Channels\DurableChannelInterface;

/**
 * Bildirim yayınının akıcı (fluent) kurucusu.
 *
 * Kullanım:
 *   service('notifier')->notify('comment.new')
 *       ->severity('info')->title('Yeni yorum')->body('...')->url('/backend/blog/comments')
 *       ->toUser(5)->via('inapp')->dispatch();
 *
 * Birden çok hedef direktifi (toUser/toGroup) ayrı küresel satırlar üretir;
 * broadcast() diğer tüm direktiflere baskındır. Kanal keşfi Notifier'a delege edilir.
 * exceptUser() ile verilen kimlikler her satırın `exclude_users` alanına düşer ve
 * okuma yolunda (applyRelevance) elenir.
 */
final class NotificationBuilder
{
    private Notifier $notifier;
    private string $type;
    private string $severity = 'info';
    private string $title = '';
    private ?string $body = null;
    private ?string $url = null;

    /** @var array<int, array{0:string, 1:?string}> */
    private array $targets = [];

    /** @var list<int> exceptUser() ile biriken, hedeften çıkarılacak kullanıcı kimlikleri. */
    private array $excludeUsers = [];

    /** Yayını üreten kullanıcının kimliği; null = sistem (olay/CLI) üretimi. */
    private ?int $createdBy = null;

    private bool $broadcastFlag = false;

    /**
     * Varsayılan teslim kanalları: önce inapp (kalıcı DB kaydı), sonra realtime
     * (Redis-destekli SSE best-effort emit). Sıra önemli — realtime, inapp commit'inden
     * SONRA çalışmalı ki emit anında satır zaten yazılmış olsun (dispatch() bu
     * diziyi sırayla işler). `via(...)` bu varsayılanı ezerek açık kanal seçimi verir.
     *
     * @var string[]
     */
    private array $channels = ['inapp', 'realtime'];

    /**
     * @param Notifier $notifier Kanal haritasını çözecek servis.
     * @param string   $type     Makine-okunur olay tipi.
     */
    public function __construct(Notifier $notifier, string $type)
    {
        $this->notifier = $notifier;
        $this->type     = $type;
    }

    /**
     * Önem seviyesini ayarlar (geçersiz değer 'info'ya düşürülür).
     *
     * @param string $level info|warning|critical.
     *
     * @return self
     */
    public function severity(string $level): self
    {
        $this->severity = NotificationMessage::normalizeSeverity($level);

        return $this;
    }

    /**
     * Başlığı ayarlar (temizleme mesaj yapımında yapılır).
     *
     * @param string $title Görünen başlık.
     *
     * @return self
     */
    public function title(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Gövdeyi ayarlar.
     *
     * @param string|null $body Görünen gövde ya da null.
     *
     * @return self
     */
    public function body(?string $body): self
    {
        $this->body = $body;

        return $this;
    }

    /**
     * Tıklama hedefini ayarlar (doğrulama mesaj yapımında yapılır).
     *
     * @param string|null $url Site-içi '/...' ya da http(s) URL.
     *
     * @return self
     */
    public function url(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Tek bir kullanıcıyı hedefe ekler (küresel 'user' satırı).
     *
     * @param int $userId Alıcı kullanıcı kimliği.
     *
     * @return self
     */
    public function toUser(int $userId): self
    {
        $this->targets[] = ['user', (string) $userId];

        return $this;
    }

    /**
     * Bir Shield grubunu hedefe ekler (küresel 'group' satırı).
     *
     * @param string $group Grup adı.
     *
     * @return self
     */
    public function toGroup(string $group): self
    {
        $this->targets[] = ['group', $group];

        return $this;
    }

    /**
     * Bir ya da daha çok kullanıcıyı hedefin DIŞINDA bırakır (birikimli).
     *
     * Hedef ne olursa olsun (broadcast dahil) geçerlidir ve toUser() ile verilen
     * doğrudan hedefi de ezer: hariç tutma HER ZAMAN kazanır. Kimlikler int'e cast
     * edilir, 0/negatif olanlar atılır, liste tekilleştirilip sıralanır. Filtre
     * okuma zamanında `Notifier::applyRelevance()` içinde uygulanır; bu sayede
     * markRead() de hariç tutulan kullanıcıya 404 döner.
     *
     * @param int|array<int, mixed> $userIds Tek kimlik ya da kimlik listesi.
     *
     * @return self
     */
    public function exceptUser(int|array $userIds): self
    {
        $this->excludeUsers = NotificationMessage::normalizeExcludeUsers(
            array_merge($this->excludeUsers, is_array($userIds) ? $userIds : [$userIds])
        );

        return $this;
    }

    /**
     * Yayını ÜRETEN kullanıcıyı işaretler (hesap verebilirlik izi, hedefleme DEĞİL).
     *
     * Yalnız `notifications.created_by` alanına düşer; hiçbir teslim kararını
     * (relevans, hariç tutma, tercih) etkilemez — gönderen kendi yayınını da görür,
     * görmesin isteniyorsa ayrıca {@see exceptUser()} çağrılmalıdır. Çağıran, kimliği
     * SUNUCUDAN (`auth()->id()`) vermek zorundadır; istemciden gelen bir değer burada
     * kabul edilirse iz sahteleşir. 0/negatif değer {@see NotificationMessage} içinde
     * null'a indirgenir.
     *
     * Kolon henüz migrate edilmemişse satır YİNE yazılır, yalnız iz kaybolur
     * (fail-open — gerekçe: {@see \Modules\Notifications\Libraries\Channels\InAppChannel::buildRow()}).
     *
     * @param int|null $userId Yayını üreten kullanıcının kimliği ya da null (sistem).
     *
     * @return self
     */
    public function createdBy(?int $userId): self
    {
        $this->createdBy = $userId;

        return $this;
    }

    /**
     * Yayını tüm kullanıcılara işaretler (tek 'broadcast' satırı; diğer hedeflere baskın).
     *
     * @return self
     */
    public function broadcast(): self
    {
        $this->broadcastFlag = true;

        return $this;
    }

    /**
     * Teslim kanallarını belirler (boş bırakılırsa varsayılan ['inapp','realtime']).
     *
     * @param string ...$channels Kanal slug'ları.
     *
     * @return self
     */
    public function via(string ...$channels): self
    {
        if ($channels !== []) {
            $this->channels = $channels;
        }

        return $this;
    }

    /**
     * Yayını çözer, her hedef için mesaj üretir ve her kanaldan teslim eder.
     *
     * Hiç hedef yoksa (ve broadcast çağrılmadıysa) hiçbir şey gönderilmez.
     *
     * ÇİFT GÖRÜNME (dedup): TargetResolver aynı [tip|değer] direktifini tekilleştirir,
     * ama `toUser(5)` + `toGroup('admin')` (5 numaralı kullanıcı admin üyesi) iki AYRI
     * satır üretir ve okuma yolunda İKİSİ de kullanıcı 5'e ilgilidir. Bu örtüşme
     * {@see coveredUserTargets()} ile TEK sorguda çözülür ve kullanıcı, GRUP satırının
     * `exclude_users` alanına yazılarak elenir: kendi 'user' satırını görür, grup
     * satırını görmez. Doğrudan hedef korunduğu için kullanıcı sonradan gruptan
     * çıksa bile bildirimi kaybetmez.
     *
     * Bu daraltma mesaja AÇIK dışlamalardan AYRI alanda taşınır
     * ({@see NotificationMessage::$derivedExcludeUsers}), çünkü teslim garantisi
     * farklıdır: `exceptUser()` bir GARANTİdir — uygulanamıyorsa kanal satırı hiç
     * yazmaz. Örtüşme daraltması ise yalnız bir OPTİMİZASYONdur; uygulanamadığında
     * satır yine yazılır ve kullanıcı bildirimi iki kez görür. Bildirimin tamamen
     * kaybolması, iki kez görünmesinden daha kötü bir sonuçtur.
     *
     * SINIR: iki GRUP hedefinin kesişimi (`toGroup('a')` + `toGroup('b')`, kullanıcı
     * ikisinde birden) çözülmez — bu, gönderim anında grup üyeliğinin tümünü
     * materyalize etmeyi (Model B'nin kaçındığı fan-out) ve `exclude_users` metnini
     * grup boyunda büyütmeyi gerektirirdi. O senaryoda kullanıcı iki satır görür.
     *
     * @return ChannelResult[] Her (hedef × kanal) için bir sonuç; her sonuç onu üreten
     *                         kanalın slug'ı ve kalıcılık bayrağıyla etiketlidir, yani
     *                         çağıran "kaç satır GERÇEKTEN yazıldı" sorusunu
     *                         {@see DispatchOutcome} ile cevaplayabilir.
     */
    public function dispatch(): array
    {
        $rows = (new TargetResolver())->resolve($this->targets, $this->broadcastFlag);
        if ($rows === []) {
            return [];
        }

        $covered    = $this->coveredUserTargets($rows);
        $channelMap = $this->notifier->resolveChannels();
        $results    = [];

        foreach ($rows as [$targetType, $targetValue]) {
            $message = new NotificationMessage(
                $this->type,
                $this->severity,
                $this->title,
                $this->body,
                $this->url,
                $targetType,
                $targetValue,
                $this->channels,
                $this->excludeUsers,
                $this->derivedExcludesFor($targetType, $targetValue, $covered),
                $this->createdBy
            );

            foreach ($this->channels as $slug) {
                $channel = $channelMap[$slug] ?? null;
                if ($channel === null) {
                    continue;
                }

                // Etiketi BURADA takıyoruz: slug ile sınıf eşlemesinin tek sahibi kanal
                // haritasıdır, kanalın kendisi hangi slug'a bağlandığını bilmez. Etiketsiz
                // bir sonuçtan "gerçekten yazıldı mı" sorusu ancak insertId gibi tesadüfi
                // ipuçlarıyla cevaplanabilirdi ({@see ChannelResult}).
                $results[] = $channel->send($message)
                    ->forChannel($slug, $channel instanceof DurableChannelInterface);
            }
        }

        return $results;
    }

    /**
     * Aynı yayında hem doğrudan hem grup üzerinden hedeflenen kullanıcıları grup grup toplar.
     *
     * Tek bir toplu sorgudur (N+1 yok) ve yalnız her iki hedef türü de varken çalışır;
     * grup üyeliği bilgisi Notifier'da (tek grup-sorgusu sahibi) kalır.
     *
     * @param array<int, array{0:string, 1:?string}> $rows Çözülmüş satır tanımları.
     *
     * @return array<string, list<int>> Grup adı => o grupta ayrıca doğrudan hedeflenen kimlikler.
     */
    private function coveredUserTargets(array $rows): array
    {
        $userIds = [];
        $groups  = [];

        foreach ($rows as [$targetType, $targetValue]) {
            if ($targetType === 'user') {
                $userIds[] = (int) $targetValue;
            } elseif ($targetType === 'group' && $targetValue !== null) {
                $groups[] = $targetValue;
            }
        }

        if ($userIds === [] || $groups === []) {
            return [];
        }

        return $this->notifier->groupMembersAmong($userIds, $groups);
    }

    /**
     * Tek bir satırın TÜRETİLMİŞ hariç tutma listesi: yalnız grup satırlarında örtüşen hedefler.
     *
     * Açık exceptUser() listesi burada birleştirilmez; mesaja ayrı alan olarak geçer
     * ki kanal, uygulanamayan bir dışlamanın garanti mi yoksa optimizasyon mu
     * olduğunu ayırt edebilsin ({@see dispatch()}).
     *
     * @param string                   $targetType  'broadcast' | 'user' | 'group'.
     * @param string|null              $targetValue Hedef değeri.
     * @param array<string, list<int>> $covered     {@see coveredUserTargets()} çıktısı.
     *
     * @return list<int> Bu satırda ayrıca doğrudan hedeflenmiş kimlikler (grup değilse boş).
     */
    private function derivedExcludesFor(string $targetType, ?string $targetValue, array $covered): array
    {
        if ($targetType !== 'group' || $targetValue === null) {
            return [];
        }

        return $covered[$targetValue] ?? [];
    }
}
