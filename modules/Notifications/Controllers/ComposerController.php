<?php

namespace Modules\Notifications\Controllers;

use CodeIgniter\Shield\Config\AuthGroups;
use Modules\Notifications\Config\NotificationsConfig;
use Modules\Notifications\Libraries\Channels\ChannelResult;
use Modules\Notifications\Libraries\DispatchOutcome;
use Modules\Notifications\Libraries\NotificationMessage;
use Modules\Notifications\Libraries\Notifier;

/**
 * Bildirim oluşturucu (composer) — yöneticinin elle bildirim gönderdiği backend controller.
 *
 * Uçlar (bkz. Config/Routes.php):
 *   GET  backend/notifications/compose         index()   — gönderim formu (role: read)
 *   GET  backend/notifications/compose/users   users()   — AJAX: select2 kullanıcı kaynağı (role: read)
 *   POST backend/notifications/compose/preview preview() — AJAX: alıcı sayısı önizlemesi (role: create)
 *   POST backend/notifications/compose         send()    — yayını gönderir (role: create)
 *
 * ÖNİZLEME NEDEN 'create': preview() bir sayı döndürür ama o sayı bir ÜYELİK/VARLIK
 * sızıntısıdır — `groups[]=superadmin` seçimine bir kimlik ekleyip sayının değişip
 * değişmediğine bakan biri, o kimliğin superadmin olup olmadığını (ve genel olarak var
 * olup olmadığını) öğrenir. 'read' izni üst çubuk çanı için HERKESE verildiğinden, bu
 * uç gönderim yetkisiyle aynı kovaya alınmıştır: önizleyemeyen zaten gönderemez.
 *
 * GÜVENLİK — bu yetki-hassas bir özelliktir, aşağıdakiler sözleşmedir:
 *   1. GÖNDEREN KİMLİĞİ: daima `auth()->id()`. İstemcinin POST'ladığı bir `created_by`
 *      ya da `user_id` alanı HİÇBİR yerde okunmaz, dolayısıyla etkisizdir.
 *   2. MASS-ASSIGNMENT YOK: POST dizisi hiçbir yere spread edilmez; her alan adıyla
 *      TEK TEK okunur ({@see payload()}) ve doğrulama da o sunucu-kurulu dizi üzerinde
 *      ({@see \CodeIgniter\Controller::validateData()}) yapılır. Böylece formda hiç
 *      olmayan bir anahtar ne doğrulamaya ne de yayına girebilir.
 *   3. GRUP WHITELIST'İ: kabul edilen grup adları Shield'in `AuthGroups` yapılandırmasının
 *      anahtarlarından gelir ({@see allowedGroups()}); listede olmayan bir ad SESSİZCE
 *      YUTULMAZ, doğrulama hatası olarak geri döner.
 *   4. KULLANICI KİMLİKLERİ: `users` tablosuna karşı TEK sorguda doğrulanır
 *      ({@see existingUserIds()}); ADRESLENEBİLİR olmayan (var olmayan, soft-delete
 *      edilmiş ya da banlı) bir kimlik isteği reddeder. Döngü içinde sorgu YOKTUR.
 *      Hedef sayısı ayrıca {@see NotificationsConfig::TARGETS_MAX} ile sınırlıdır.
 *   5. GÖNDERİM YOLU: yalnız `service('notifier')` builder'ı. `notifications` tablosuna
 *      doğrudan INSERT YOKTUR; temizleme (strip_tags, URL doğrulama, severity ve hariç
 *      tutma normalizasyonu) TEK yerde, {@see NotificationMessage} yapıcısında olur ve
 *      burada TEKRARLANMAZ.
 *   6. CSRF: global koruma açıktır; bu uçlar `NotificationsConfig::$csrfExcept`'e
 *      EKLENMEZ — AJAX POST'lar token'ı gövdede taşır (bkz. Views/compose.php).
 *   7. YETKİ: rotalar `backendGuard` + `role` bayrağı arkasındadır (fail-closed);
 *      izin kaydı Methods taramasıyla düşer.
 *
 * Bildirim TİPİ istemciden ALINMAZ: composer'dan çıkan her yayın {@see TYPE} ile
 * damgalanır, böylece tercih/susturma whitelist'i sabit bir slug üzerinden çalışır ve
 * istemci kendi tipini uydurup mevcut susturmaları atlatamaz.
 */
class ComposerController extends \Modules\Backend\Controllers\BaseController
{
    /**
     * Composer'dan çıkan yayınların sabit tipi.
     *
     * İstemciden ALINMAZ: serbest tip kabul edilseydi gönderen, kullanıcıların MEVCUT
     * susturmalarını her gönderimde yeni bir slug uydurarak atlatabilir ve tip uzayını
     * sınırsızca kirletebilirdi. Susturma whitelist'i
     * ({@see NotificationsConfig::$preferenceTypes}) sabit slug'lar üzerinde çalışır.
     *
     * DÜRÜSTLÜK NOTU: bu slug şu anda o whitelist'te DEĞİLDİR, yani composer çıktısı
     * tercih ekranından susturulamaz. Bunun bir ürün kararı olarak gözden geçirilmesi
     * gerekir (yönetici duyurusu susturulabilmeli mi?); teknik olarak tek gereken
     * slug'ı `$preferenceTypes`'a ve etiketini Language/{en,tr}'ye eklemektir.
     */
    private const TYPE = 'announcement';

    /** Select2 uzak kaynağının tek istekte döndürdüğü en fazla kullanıcı sayısı. */
    private const USER_PICKER_LIMIT = 20;

    /**
     * Kullanıcı arama teriminin en fazla karakter uzunluğu.
     *
     * Aranan en uzun kolon 255 karakterdir (`firstname`/`surname`); daha uzun bir terim
     * hiçbir satıra uyamaz, yalnız her tuş vuruşunda gereksiz büyük bir sorgu bağlar.
     */
    private const USER_PICKER_TERM_MAX = 255;

    /** Hedef kipi: tüm kullanıcılar (tek 'broadcast' satırı). */
    private const MODE_BROADCAST = 'broadcast';

    /** Hedef kipi: seçili kullanıcılar ve/veya gruplar. */
    private const MODE_TARGETED = 'targeted';

    /**
     * Önem seviyesi slug'ı => görünen etiketin lang anahtarı.
     *
     * Hangi seviyelerin VAR OLDUĞUNUN tek kaynağı {@see NotificationMessage::SEVERITIES};
     * bu dizi yalnız onlara etiket takar ({@see severityChoices()}).
     *
     * @var array<string, string>
     */
    private const SEVERITY_LABELS = [
        'info'     => 'Notifications.severityInfo',
        'warning'  => 'Notifications.severityWarning',
        'critical' => 'Notifications.severityCritical',
    ];

    /**
     * Gönderim formunu render eder (kullanıcı ve grup kaynakları sunucudan gelir).
     *
     * Grup listesi doğrudan whitelist'in kendisidir; kullanıcı listesi büyüyebileceği
     * için forma gömülmez, {@see users()} uzak kaynağından sayfa sayfa çekilir.
     *
     * @return string Render edilmiş composer görünümü.
     */
    public function index(): string
    {
        $this->defData = array_merge($this->defData, [
            'composeGroups'     => $this->allowedGroups(),
            'composeSeverities' => self::severityChoices(),
            'composeType'       => self::TYPE,
            'composeOldUsers'   => $this->oldUserLabels(),
        ]);

        return view('Modules\Notifications\Views\compose', $this->defData);
    }

    /**
     * Select2 uzak kaynağı: arama terimiyle filtrelenmiş kullanıcı listesi.
     *
     * Yalnız AJAX kabul eder ve TEK sorgu açar; sonuç {@see USER_PICKER_LIMIT} ile
     * sınırlıdır, yani kullanıcı tablosunun tamamı hiçbir zaman istemciye dökülmez.
     * Yalnız ADRESLENEBİLİR hesaplar görünür ({@see Notifier::scopeAddressableUsers()}):
     * soft-delete edilmiş ve banlı kimlikler operatöre sızmaz.
     *
     * ARAMA TERİMİ: CI4'ün `like()` kuralı değeri BİND eder (SQL enjeksiyonu yoktur) ama
     * LIKE joker'lerine DOKUNMAZ — `%`/`_` kalıp olarak çalışmaya devam eder. Bu, tek
     * başına bir enjeksiyon değil bir SAYIM (enumeration) yükselticidir: `a%`, `_a%` gibi
     * kalıplarla {@see USER_PICKER_LIMIT}'lik pencere kaydırılıp kullanıcı listesi
     * haritalanabilir. Bu yüzden terim {@see searchTerm()} içinde nötrlenir ve
     * uzunluğu sınırlanır.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface `{status, results: [{id, text}]}`.
     */
    public function users()
    {
        if (! $this->request->isAJAX()) {
            return $this->failForbidden();
        }

        if (! $this->commonModel->db->tableExists('users')) {
            return $this->respond(['status' => true, 'results' => []]);
        }

        $term    = self::searchTerm($this->request->getGet('q'));
        $builder = Notifier::scopeAddressableUsers(
            $this->commonModel->db->table('users')->select('id, username, firstname, surname')
        );

        if ($term !== '') {
            $builder->groupStart()
                ->like('username', $term)
                ->orLike('firstname', $term)
                ->orLike('surname', $term)
                ->groupEnd();
        }

        /** @var list<\stdClass> $rows */
        $rows = $builder->orderBy('username', 'ASC')->limit(self::USER_PICKER_LIMIT)->get()->getResult();

        return $this->respond([
            'status'  => true,
            'results' => array_map(static fn (\stdClass $row): array => [
                'id'   => (int) $row->id,
                'text' => self::userLabel($row),
            ], $rows),
        ]);
    }

    /**
     * Gönderim öncesi alıcı sayısı önizlemesi (hiçbir şey yazmaz).
     *
     * Sayım {@see Notifier::recipientCount()}'a delege edilir — grup sorgusunun tek
     * sahibi odur. Geçersiz seçimler burada HATA vermez, yalnız sayıma girmez:
     * önizleme bir doğrulama ucu değildir, asıl karar {@see send()}'de verilir.
     *
     * @return \CodeIgniter\HTTP\ResponseInterface `{status, count}`.
     */
    public function preview()
    {
        if (! $this->request->isAJAX()) {
            return $this->failForbidden();
        }

        $userIds    = $this->postedIds('users');
        $excludeIds = $this->postedIds('exclude_users');
        $existing   = $this->existingUserIds(array_merge($userIds, $excludeIds));

        $count = $this->notifier()->recipientCount(
            $this->postedString('mode') === self::MODE_BROADCAST,
            array_intersect($userIds, $existing),
            array_intersect($this->postedNames('groups'), array_keys($this->allowedGroups())),
            array_intersect($excludeIds, $existing)
        );

        return $this->respond(['status' => true, 'count' => $count]);
    }

    /**
     * Yayını gönderir: doğrular, hedefi sunucuda kurar ve builder'a devreder.
     *
     * Hedef seçimleri KİPTEN BAĞIMSIZ doğrulanır (broadcast'te kullanılmasalar bile):
     * geçersiz bir seçimi "nasılsa okunmuyor" diye geçirmek, kipin sonradan
     * değişebildiği bir formda sessiz bir kabul yüzeyi bırakırdı.
     *
     * BAŞARI RAPORU teslimin GERÇEĞİNE dayanır ({@see DispatchOutcome}): "gönderildi"
     * yalnız kalıcı satırların TAMAMI yazıldığında söylenir, kısmi teslim ayrı ve açık
     * bir hata olarak bildirilir. Kalıcı yazım güvenlik gerekçesiyle reddedildiğinde
     * (uygulanamayan hariç tutma) yöneticiye "gönderildi" demek, dışlanan kullanıcıların
     * korunduğu sanılırken hiç kimseye bildirim gitmemesi demekti.
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function send()
    {
        $payload = $this->payload();

        if (! $this->validateData($payload, $this->validationRules(), $this->validationMessages())) {
            return $this->rejected($this->validator->getErrors());
        }

        $groups = $this->postedNames('groups');
        if (array_diff($groups, array_keys($this->allowedGroups())) !== []) {
            return $this->rejected(['groups' => self::text('Notifications.composeUnknownGroup')]);
        }

        $userIds    = $this->postedIds('users');
        $excludeIds = $this->postedIds('exclude_users');

        // Tavan kontrolü var olma kontrolünden ÖNCE: aksi halde bin kimlikli bir gövde,
        // reddedileceği halde önce bin öğelik bir IN sorgusu açtırırdı.
        if (count($userIds) + count($groups) > NotificationsConfig::TARGETS_MAX) {
            return $this->rejected([
                'users' => self::text('Notifications.composeTooManyTargets', [NotificationsConfig::TARGETS_MAX]),
            ]);
        }

        $requested = array_unique(array_merge($userIds, $excludeIds));

        if (count($this->existingUserIds($requested)) !== count($requested)) {
            return $this->rejected(['users' => self::text('Notifications.composeUnknownUser')]);
        }

        $broadcast = $payload['mode'] === self::MODE_BROADCAST;
        if (! $broadcast && $userIds === [] && $groups === []) {
            return $this->rejected(['mode' => self::text('Notifications.composeNoTarget')]);
        }

        $outcome = DispatchOutcome::fromResults(
            $this->publish($payload, $broadcast, $userIds, $groups, $excludeIds)
        );

        if ($outcome->storedNothing()) {
            return $this->rejected(null, self::text('Notifications.composeFailed'));
        }

        if ($outcome->isPartial()) {
            return redirect()->route('notifCompose')->with(
                'error',
                self::text('Notifications.composePartial', [$outcome->stored(), $outcome->attempted()])
            );
        }

        $count = $this->notifier()->recipientCount($broadcast, $userIds, $groups, $excludeIds);

        return redirect()->route('notifCompose')->with('message', self::text('Notifications.composeSent', [$count]));
    }

    /**
     * Paylaşımlı Notifier servis örneği.
     *
     * @return Notifier
     */
    private function notifier(): Notifier
    {
        /** @var Notifier $notifier */
        $notifier = service('notifier');

        return $notifier;
    }

    /**
     * Formun tekil alanlarını sunucuda, adıyla tek tek okur (mass-assignment kapalı).
     *
     * Dönen dizi hem doğrulamanın hem yayının TEK girdisidir; burada olmayan bir POST
     * anahtarı sisteme hiç giremez. Değerler ham bırakılır: temizleme
     * {@see NotificationMessage} yapıcısının işidir ve iki yerde yapılmaz.
     *
     * @return array{title: string, body: string, url: string, severity: string, mode: string}
     */
    private function payload(): array
    {
        return [
            'title'    => $this->postedString('title'),
            'body'     => $this->postedString('body'),
            'url'      => $this->postedString('url'),
            'severity' => $this->postedString('severity'),
            'mode'     => $this->postedString('mode'),
        ];
    }

    /**
     * Doğrulanmış yayını builder üzerinden teslim eder (tek gönderim yolu).
     *
     * Gönderen kimliği DAİMA `auth()->id()`'dir. Hedef döngüleri yalnız builder'a
     * direktif ekler — içlerinde sorgu YOKTUR, örtüşme/grup çözümü tek batch halinde
     * {@see \Modules\Notifications\Libraries\NotificationBuilder::dispatch()} içinde olur.
     *
     * @param array{title: string, body: string, url: string, severity: string, mode: string} $payload   Doğrulanmış alanlar.
     * @param bool                                                                            $broadcast Tüm kullanıcılara mı.
     * @param list<int>                                                                       $userIds   Doğrulanmış hedef kimlikleri.
     * @param list<string>                                                                    $groups    Whitelist'ten geçmiş grup adları.
     * @param list<int>                                                                       $excludeIds Doğrulanmış hariç tutma kimlikleri.
     *
     * @return ChannelResult[] Her (hedef × kanal) için bir sonuç; teslim kararı bu
     *                         listeden {@see DispatchOutcome} ile türetilir.
     */
    private function publish(array $payload, bool $broadcast, array $userIds, array $groups, array $excludeIds): array
    {
        $builder = $this->notifier()->notify(self::TYPE)
            ->severity($payload['severity'])
            ->title($payload['title'])
            ->body(self::nullableText($payload['body']))
            ->url(self::nullableText($payload['url']))
            ->createdBy((int) auth()->id());

        if ($broadcast) {
            $builder->broadcast();
        } else {
            foreach ($userIds as $userId) {
                $builder->toUser($userId);
            }

            foreach ($groups as $group) {
                $builder->toGroup($group);
            }
        }

        if ($excludeIds !== []) {
            $builder->exceptUser($excludeIds);
        }

        return $builder->dispatch();
    }

    /**
     * Formu, girdiyi koruyarak hata mesajıyla geri gönderir.
     *
     * @param array<string, string>|null $errors  Alan bazlı doğrulama hataları ya da null.
     * @param string|null                $message Alan bazlı olmayan tek hata mesajı ya da null.
     *
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    private function rejected(?array $errors, ?string $message = null)
    {
        $redirect = redirect()->route('notifCompose')->withInput();

        return $errors !== null
            ? $redirect->with('errors', $errors)
            : $redirect->with('error', (string) $message);
    }

    /**
     * Kabul edilen Shield grupları: grup adı => görünen başlık (TEK whitelist kaynağı).
     *
     * Kaynak, Shield'in `AuthGroups` yapılandırmasıdır; proje bu yapılandırmayı
     * `auth_groups` tablosundan doldurup cache'lediği için liste kurulumun gerçek
     * gruplarıyla aynıdır. Hem arayüz hem {@see send()} doğrulaması aynı diziden
     * beslenir: burada olmayan bir ad ne gösterilir ne de kabul edilir.
     *
     * NEDEN `setting('AuthGroups.groups')` DEĞİL (Shield'in GroupModel'i onu kullanır):
     * `setting()` okuması settings DEPOSUNA bir sorgu açar ve depo erişilemezse
     * İSTİSNA fırlatır — bu kurulumda birim test bağlamı tam olarak öyledir (settings
     * tablosu yalnız `default` bağlantısında var). Whitelist'in fatal atabilmesi,
     * çözdüğü sorundan ağırdır: bu projede `Modules\Auth\Config\AuthGroups` statik bir
     * yapılandırma sınıfıdır ve settings deposunda `AuthGroups.groups` satırı yoktur,
     * yani `setting()` bugün zaten bu aynı diziye düşüyor — sadece her çağrıda bir
     * sorgu fazlasıyla. Gruplar ileride settings üzerinden ezilebilir hale gelirse
     * burası da o kaynağa taşınmalıdır.
     *
     * @return array<string, string> Grup adı => başlık (başlık boşsa adın kendisi).
     */
    private function allowedGroups(): array
    {
        /** @var AuthGroups|null $config */
        $config = config('AuthGroups');

        if ($config === null) {
            return [];
        }

        $groups = [];

        foreach ($config->groups as $name => $info) {
            $name          = (string) $name;
            $title         = is_array($info) ? trim((string) ($info['title'] ?? '')) : '';
            $groups[$name] = $title !== '' ? $title : $name;
        }

        return $groups;
    }

    /**
     * Verilen kimliklerden ADRESLENEBİLİR olanları TEK sorguda döndürür.
     *
     * Soft-delete edilmiş ve banlı satırlar var sayılmaz ({@see
     * Notifier::scopeAddressableUsers()}): ikisi de bildirimi hiçbir zaman göremez, yani
     * anlamlı bir hedef değildirler. Bu yüzden böyle bir kimlik seçildiğinde gönderim
     * "tanınmayan kullanıcı" ile reddedilir (fail-closed) — kimliğin durumu hakkında
     * ayrıca bilgi verilmez.
     *
     * @param array<int, int> $ids Doğrulanacak kimlikler.
     *
     * @return list<int> Adreslenebilir kimlikler (tekil).
     */
    private function existingUserIds(array $ids): array
    {
        if ($ids === [] || ! $this->commonModel->db->tableExists('users')) {
            return [];
        }

        $rows = Notifier::scopeAddressableUsers($this->commonModel->db->table('users')->select('id'))
            ->whereIn('id', $ids)
            ->get()->getResultArray();

        return array_values(array_map(static fn (array $row): int => (int) $row['id'], $rows));
    }

    /**
     * Ham arama terimini LIKE için güvenli hale getirir (uzunluk tavanı + joker nötrleme).
     *
     * CI4 `like()` değeri bind eder, yani enjeksiyon riski yoktur; ama `%` ve `_`
     * KALIP olarak çalışmaya devam eder. Terim, CI4'ün sorguya eklediği `ESCAPE '!'`
     * sözleşmesiyle kaçırılır ({@see \CodeIgniter\Database\BaseBuilder::_like()}), yani
     * joker KARAKTERLERİ SİLİNMEZ, literalleştirilir: `john_doe` araması hâlâ o kullanıcıyı
     * bulur, `a%` ise artık her şeyle eşleşmez. Kaçış karakterinin kendisi de ('!')
     * ikizlenir, aksi halde terime '!' yazan biri sonraki karakteri kaçırabilirdi.
     *
     * @param mixed $raw Query string'ten gelen ham değer.
     *
     * @return string Sorguya bağlanmaya hazır terim ('' = filtre yok).
     */
    private static function searchTerm($raw): string
    {
        if (! is_scalar($raw)) {
            return '';
        }

        $term = trim((string) $raw);

        if ($term === '') {
            return '';
        }

        return str_replace(
            ['!', '%', '_'],
            ['!!', '!%', '!_'],
            mb_substr($term, 0, self::USER_PICKER_TERM_MAX, 'UTF-8')
        );
    }

    /**
     * Bir POST alanını metin olarak okur; skaler olmayan her değer '' olur.
     *
     * `title[]=x` gibi dizi enjeksiyonları burada durur: doğrulamaya ve yayına giden
     * değer daima metindir.
     *
     * @param string $field POST alan adı.
     *
     * @return string Alanın metin değeri ya da ''.
     */
    private function postedString(string $field): string
    {
        $value = $this->request->getPost($field);

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Bir çoklu seçim POST alanını kullanıcı kimliği listesine çevirir.
     *
     * @param string $field POST alan adı.
     *
     * @return list<int> Tekil, pozitif kimlikler.
     */
    private function postedIds(string $field): array
    {
        return self::normalizeIds($this->request->getPost($field));
    }

    /**
     * Bir çoklu seçim POST alanını grup adı listesine çevirir (whitelist kontrolü ÇAĞIRANIN işidir).
     *
     * @param string $field POST alan adı.
     *
     * @return list<string> Tekil, boş olmayan adlar.
     */
    private function postedNames(string $field): array
    {
        return self::normalizeNames($this->request->getPost($field));
    }

    /**
     * Ham bir çoklu seçim değerini kullanıcı kimliği listesine indirger.
     *
     * Skaler olmayan öğeler ATILIR: `users[][]` gibi iç içe bir dizi `(int)` cast'inde
     * sessizce 1'e dönüşür ve HEDEFLENMEMİŞ bir kullanıcıya işaret ederdi. 0/negatif
     * kimlikler elenir, liste tekilleştirilir.
     *
     * NEDEN {@see NotificationMessage::normalizeExcludeUsers()}'a DELEGE EDİLMİYOR
     * (üç benzer normalize'in bilinçli ayrımı): o metot DEPOLAMA sözleşmesini uygular ve
     * bu iki noktada farklıdır —
     *   1. Skaler olmayan öğeyi ELEMEZ, doğrudan `(int)` cast eder; `[[5]]` girdisi 1'e
     *      döner, yani kimliği 1 olan hesabı (kurulumun ilk hesabı, çoğu zaman
     *      superadmin) hedeflerdi. Buradaki eleme tam olarak o hijack'i durdurur.
     *   2. Listeyi SIRALAR (aynı hariç tutma kümesi hep aynı CSV'yi üretsin diye).
     *      Hedef listesinde sıra, yazılacak satırların sırasıdır; sıralamak yöneticinin
     *      verdiği direktif sırasını sessizce değiştirirdi.
     *
     * @param mixed $raw POST ya da eski girdiden gelen ham değer.
     *
     * @return list<int> Tekil, pozitif kimlikler (girdi sırasında).
     */
    private static function normalizeIds($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $ids = array_map(
            static fn ($value): int => (int) $value,
            array_filter($raw, static fn ($value): bool => is_scalar($value))
        );

        return array_values(array_unique(array_filter($ids, static fn (int $id): bool => $id > 0)));
    }

    /**
     * Ham bir çoklu seçim değerini ad listesine indirger (skaler olmayanlar atılır).
     *
     * @param mixed $raw POST ya da eski girdiden gelen ham değer.
     *
     * @return list<string> Tekil, boş olmayan adlar.
     */
    private static function normalizeNames($raw): array
    {
        if (! is_array($raw)) {
            return [];
        }

        $names = array_map(
            static fn ($value): string => trim((string) $value),
            array_filter($raw, static fn ($value): bool => is_scalar($value))
        );

        return array_values(array_unique(array_filter($names, static fn (string $name): bool => $name !== '')));
    }

    /**
     * Hatalı gönderimden sonra select2 kutularını yeniden dolduracak kullanıcı etiketleri.
     *
     * Kullanıcı kutuları uzak kaynaktan (AJAX) beslendiği için tarayıcı, `withInput()`
     * ile geri gelen kimliklerin ETİKETİNİ bilmez; etiketsiz seçim ekranda kaybolur ve
     * yönetici tek bir doğrulama hatasında tüm hedef listesini elle yeniden kurardı.
     * Bu yüzden etiketler sunucuda, TEK sorguda çözülür — ve yalnız eski girdi VARSA,
     * yani mutlu yolda hiç sorgu açılmaz.
     *
     * @return array<int, string> Kullanıcı kimliği => görünen etiket.
     */
    private function oldUserLabels(): array
    {
        $ids = array_values(array_unique(array_merge(
            self::normalizeIds(old('users')),
            self::normalizeIds(old('exclude_users'))
        )));

        if ($ids === [] || ! $this->commonModel->db->tableExists('users')) {
            return [];
        }

        /** @var list<\stdClass> $rows */
        $rows = Notifier::scopeAddressableUsers(
            $this->commonModel->db->table('users')->select('id, username, firstname, surname')
        )->whereIn('id', $ids)->get()->getResult();

        $labels = [];

        foreach ($rows as $row) {
            $labels[(int) $row->id] = self::userLabel($row);
        }

        return $labels;
    }

    /**
     * Bir dil satırını KESİN metin olarak okur.
     *
     * `lang()` çoğul biçimli satırlarda liste döndürebilir; buradaki kullanım yerleri
     * (alan etiketi, doğrulama mesajı, hata metni) her zaman TEK satır bekler. Tip
     * daraltma her çağrı yerinde tekrarlanmasın diye tek noktada yapılır.
     *
     * @param string            $key  '{Modül}.{anahtar}' dil anahtarı.
     * @param array<int, mixed> $args Satırdaki {0}, {1} ... yer tutucularının değerleri.
     *
     * @return string Dil satırı (liste gelirse boşlukla birleştirilmiş hali).
     */
    private static function text(string $key, array $args = []): string
    {
        $line = lang($key, $args);

        return is_string($line) ? $line : implode(' ', $line);
    }

    /**
     * Boş metni null'a indirger (nullable alan sözleşmesi).
     *
     * `strip_tags` burada TEKRARLANMAZ: temizlemenin tek sahibi
     * {@see NotificationMessage} yapıcısıdır, iki yerde yapılırsa kuralın hangi
     * katmanda değiştiği izlenemez hale gelir.
     *
     * @param string $value Ham alan değeri.
     *
     * @return string|null Doldurulmuş metin ya da null.
     */
    private static function nullableText(string $value): ?string
    {
        return trim($value) ?: null;
    }

    /**
     * Bir kullanıcı satırının seçim kutusunda görünecek etiketi.
     *
     * @param \stdClass $row id, username, firstname, surname taşıyan satır.
     *
     * @return string 'Ad Soyad (kullanıcıadı)' ya da hangisi doluysa o.
     */
    private static function userLabel(\stdClass $row): string
    {
        $name     = trim(((string) ($row->firstname ?? '')) . ' ' . ((string) ($row->surname ?? '')));
        $username = trim((string) ($row->username ?? ''));

        if ($name === '') {
            return $username;
        }

        return $username === '' ? $name : $name . ' (' . $username . ')';
    }

    /**
     * Seçilebilen önem seviyeleri: slug => görünen etiketin lang anahtarı.
     *
     * @return array<string, string> Seviye slug'ı => lang anahtarı.
     */
    private static function severityChoices(): array
    {
        // Kesişim: etiketi olmayan bir seviye forma HİÇ çıkmaz (fail-closed) ve
        // etiketi olup artık var olmayan bir slug da düşer — iki sabit ayrışırsa
        // arayüz sessizce yanlış bir seçenek göstermez.
        return array_intersect_key(self::SEVERITY_LABELS, array_flip(NotificationMessage::SEVERITIES));
    }

    /**
     * Gönderim formunun doğrulama kuralları (proje regex sözleşmesiyle).
     *
     * URL BURADA, composer'a ÖZEL olarak site-içi bir yola daraltılır: '/' ile başlamayan
     * her değer (mutlak `https://...`, şemasız `example.com/x`, `javascript:` ...)
     * REDDEDİLİR. Gerekçe yetkidir: `compose.create` izinli alt-seviye bir operatör,
     * superadmin grubuna `severity=critical` bir bildirim gönderebiliyor; kritik
     * bildirimler susturulamadığı ve arayüzde gönderen görünmediği için mesaj "sistem"
     * gibi algılanır. Dış bir bağlantı bu güveni doğrudan bir kimlik avı sayfasına
     * taşırdı. {@see NotificationMessage::sanitizeUrl()}'in GENEL sözleşmesi (programatik
     * üreticiler http(s) kullanabilir) BOZULMAZ; o katman burada elenmeyen protokol-göreli
     * biçimler ('//host', '/\host') için son savunma olarak yerinde kalır.
     *
     * Geçersiz URL artık SESSİZCE düşmez: eskiden `example.com/duyuru` yazan yönetici
     * bağlantısız bir bildirim gönderip hiçbir uyarı almıyordu.
     *
     * `body` için de {@see NotificationsConfig::BODY_MAX} sınırı vardır: kolon TEXT ve
     * `strictOn = false` olduğundan sınırsız bir gövde sessizce kesilirdi.
     *
     * @return array<string, array<string, string>> CI4 doğrulama kural tanımı.
     */
    private function validationRules(): array
    {
        return [
            'title' => [
                'label' => self::text('Notifications.composeFieldTitle'),
                'rules' => 'required|max_length[' . NotificationsConfig::TITLE_MAX . ']|regex_match[/^[^<>{}=]+$/u]',
            ],
            'body' => [
                'label' => self::text('Notifications.composeFieldBody'),
                'rules' => 'permit_empty|max_length[' . NotificationsConfig::BODY_MAX . ']|regex_match[/^[^<>{}=]*$/u]',
            ],
            'url' => [
                'label' => self::text('Notifications.composeFieldUrl'),
                // '/' ile başlar ve kontrol karakteri (CR/LF/TAB/NUL) taşımaz.
                'rules' => 'permit_empty|max_length[' . NotificationsConfig::URL_MAX . ']|regex_match[/^\/[^\x00-\x1F\x7F]*$/]',
            ],
            'severity' => [
                'label' => self::text('Notifications.composeFieldSeverity'),
                'rules' => 'required|in_list[' . implode(',', NotificationMessage::SEVERITIES) . ']',
            ],
            'mode' => [
                'label' => self::text('Notifications.composeFieldMode'),
                'rules' => 'required|in_list[' . self::MODE_BROADCAST . ',' . self::MODE_TARGETED . ']',
            ],
        ];
    }

    /**
     * Doğrulama hatalarının kullanıcıya görünen metinleri.
     *
     * @return array<string, array<string, string>> Alan => kural => mesaj.
     */
    private function validationMessages(): array
    {
        $invalidText = self::text('Notifications.composeInvalidText');

        return [
            'title' => [
                'required'    => self::text('Notifications.composeTitleRequired'),
                'regex_match' => $invalidText,
            ],
            'body' => [
                'regex_match' => $invalidText,
            ],
            'url' => [
                'regex_match' => self::text('Notifications.composeInvalidUrl'),
            ],
            'severity' => [
                'required' => self::text('Notifications.composeInvalidSeverity'),
                'in_list'  => self::text('Notifications.composeInvalidSeverity'),
            ],
            'mode' => [
                'required' => self::text('Notifications.composeInvalidMode'),
                'in_list'  => self::text('Notifications.composeInvalidMode'),
            ],
        ];
    }
}
