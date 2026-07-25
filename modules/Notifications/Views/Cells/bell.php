<?php
/**
 * Üst çubuk bildirim çanı — AdminLTE navbar dropdown.
 *
 * @var int   $unread          Okunmamış bildirim sayısı
 * @var array $items           Son ~FEED_LIMIT ilgili bildirim (stdClass satırları)
 * @var bool  $realtimeEnabled Redis destekli PHP-SSE anlık teslim açık mı (false → yalnız 60sn polling)
 *
 * CSRF: POST istekleri jQuery ile yapılır; be-assets/js/ci4ms.js global AJAX
 * katmanı X-CSRF-TOKEN başlığını ve gövde token'ını otomatik ekler — burada
 * elle token yönetimi gerekmez.
 */

// severity → [ikon, renk] eşlemesi; bilinmeyen değer info'ya düşer.
$severityMap = [
    'info'     => ['far fa-bell', 'text-primary'],
    'warning'  => ['fas fa-exclamation-triangle', 'text-warning'],
    'critical' => ['fas fa-exclamation-circle', 'text-danger'],
];
$severityOf = static fn ($s) => $severityMap[$s] ?? $severityMap['info'];
?>
<style>
    /* Bootstrap'ın .dropdown-item'ı nowrap'tir ve dropdown-menu-lg yalnız min-width verir:
       uzun başlık sarmayıp öğeyi genişletir, menü de ekranın sağına taşardı. Genişliği
       sabitleyip sarmayı açıyoruz; max-width dar ekranda viewport'u aşmayı engeller. */
    #ci4msNotifBell .dropdown-menu {
        width: 22rem;
        max-width: calc(100vw - 1rem);
    }

    #ci4msNotifBell .dropdown-item {
        white-space: normal;
        overflow-wrap: break-word;
    }
</style>
<li class="nav-item dropdown" id="ci4msNotifBell">
    <a class="nav-link" data-toggle="dropdown" href="#" aria-expanded="false">
        <i class="far fa-bell"></i>
        <span class="badge badge-danger navbar-badge" id="ci4msNotifBadge"
              style="<?php echo ((int) $unread > 0) ? '' : 'display:none;' ?>"><?php echo (int) $unread ?></span>
    </a>
    <div class="dropdown-menu dropdown-menu-lg dropdown-menu-right">
        <span class="dropdown-item dropdown-header">
            <span id="ci4msNotifCount"><?php echo (int) $unread ?></span> <?php echo lang('Notifications.unread') ?>
        </span>
        <div class="dropdown-divider"></div>

        <div id="ci4msNotifList">
            <?php if (empty($items)): ?>
                <span class="dropdown-item text-muted small"><?php echo lang('Notifications.noNotifications') ?></span>
            <?php else: ?>
                <?php foreach ($items as $n): ?>
                    <?php [$sevIcon, $sevColor] = $severityOf($n->severity ?? 'info'); ?>
                    <a href="<?php echo !empty($n->url) ? esc($n->url, 'attr') : '#' ?>"
                       class="dropdown-item <?php echo empty($n->read_at) ? 'font-weight-bold' : '' ?>"
                       data-id="<?php echo (int) $n->id ?>"
                       onclick="ci4msNotifMarkRead(<?php echo (int) $n->id ?>)">
                        <i class="<?php echo $sevIcon ?> mr-2 <?php echo empty($n->read_at) ? $sevColor : 'text-muted' ?>"></i>
                        <?php echo esc($n->title) ?>
                        <span class="float-right text-muted text-sm"><?php echo esc($n->created_at) ?></span>
                    </a>
                    <div class="dropdown-divider"></div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <a href="#" class="dropdown-item dropdown-footer" onclick="ci4msNotifMarkAll(event)">
            <i class="fas fa-check-double mr-1"></i><?php echo lang('Notifications.markAllRead') ?>
        </a>
        <a href="<?php echo route_to('notifications') ?>" class="dropdown-item dropdown-footer">
            <?php echo lang('Notifications.viewAll') ?>
        </a>
    </div>
</li>
<?php echo $this->section('javascript'); ?>
<script>
(function () {
    var feedUrl    = '<?php echo route_to('notifFeed') ?>';
    var readBase   = '<?php echo base_url('backend/notifications/read') ?>';
    var readAllUrl = '<?php echo route_to('notifReadAll') ?>';
    var streamUrl  = '<?php echo route_to('notifStream') ?>';
    var noItemsTxt = <?php echo json_encode(lang('Notifications.noNotifications')) ?>;
    var pollMs     = <?php echo \Modules\Notifications\Config\NotificationsConfig::POLL_MS ?>;
    var realtime   = <?php echo $realtimeEnabled ? 'true' : 'false' ?>;

    var severityMap = {
        info:     ['far fa-bell', 'text-primary'],
        warning:  ['fas fa-exclamation-triangle', 'text-warning'],
        critical: ['fas fa-exclamation-circle', 'text-danger']
    };
    function severityOf(s) {
        return severityMap[s] || severityMap.info;
    }

    function safeUrl(u) {
        // Sunucu tarafı zaten doğruluyor; istemcide de yalnız '/...' veya http(s) kabul et.
        if (!u) return '#';
        if (u.charAt(0) === '/' && u.charAt(1) !== '/' && u.charAt(1) !== '\\') return u;
        if (/^https?:\/\//i.test(u)) return u;
        return '#';
    }

    function renderList(items) {
        var $list = $('#ci4msNotifList').empty();
        if (!items || !items.length) {
            $list.append($('<span class="dropdown-item text-muted small"></span>').text(noItemsTxt));
            return;
        }
        items.forEach(function (n) {
            var sev = severityOf(n.severity);
            var $a = $('<a class="dropdown-item"></a>')
                .attr('href', safeUrl(n.url))
                .attr('data-id', n.id)
                .toggleClass('font-weight-bold', !n.read_at)
                .on('click', function () { ci4msNotifMarkRead(n.id); });
            $a.append('<i class="' + sev[0] + ' mr-2 ' + (n.read_at ? 'text-muted' : sev[1]) + '"></i>');
            $a.append(document.createTextNode(' ' + (n.title || '')));
            $a.append($('<span class="float-right text-muted text-sm"></span>').text(n.created_at || ''));
            $list.append($a).append('<div class="dropdown-divider"></div>');
        });
    }

    function updateBadge(count) {
        count = parseInt(count, 10) || 0;
        $('#ci4msNotifCount').text(count);
        var $b = $('#ci4msNotifBadge').text(count);
        count > 0 ? $b.show() : $b.hide();
    }

    function refresh() {
        $.get(feedUrl).done(function (res) {
            if (res && res.status) {
                updateBadge(res.unread);
                renderList(res.items);
            }
        });
    }

    // Global scope — dropdown içindeki onclick'ler için.
    window.ci4msNotifMarkRead = function (id) {
        $.post(readBase + '/' + id).done(function () { refresh(); });
    };
    window.ci4msNotifMarkAll = function (e) {
        if (e) e.preventDefault();
        $.post(readAllUrl).done(function () { refresh(); });
    };

    // Reconcile: SSE payload'a GÜVENMEDEN feed ucundan (DB source-of-truth) yeniden oku.
    // Kısa debounce ile mesaj/ping patlamalarında tek istek yapılır.
    var reconcileTimer = null;
    function reconcile() {
        if (reconcileTimer) return;
        reconcileTimer = setTimeout(function () {
            reconcileTimer = null;
            refresh();
        }, 400);
    }

    // Çoklu sekme senkronu: her sekme kendi EventSource'unu açar (basit + doğru; admin
    // panelinde sekme sayısı azdır, leader election'a değmez). Bir sekme mesaj alınca
    // BroadcastChannel (yoksa storage event) ile diğer sekmeleri de reconcile'a çağırır;
    // debounce sayesinde bu, gereksiz istek fırtınasına dönüşmez.
    var bc = null;
    try { bc = ('BroadcastChannel' in window) ? new BroadcastChannel('ci4ms_notif') : null; } catch (e) { bc = null; }
    function broadcastPing() {
        if (bc) { try { bc.postMessage('reconcile'); } catch (e) {} }
        else { try { localStorage.setItem('ci4ms_notif_ping', String(Date.now())); } catch (e) {} }
    }
    if (bc) {
        bc.onmessage = function () { reconcile(); };
    } else {
        window.addEventListener('storage', function (e) {
            if (e.key === 'ci4ms_notif_ping') reconcile();
        });
    }

    // realtime kapalı → DAVRANIŞ BİREBİR ESKİSİ: yalnız 60sn polling, SSE kodu hiç çalışmaz.
    if (!realtime) {
        $(function () { setInterval(refresh, pollMs); });
        return;
    }

    // realtime açık → EventSource + polling fallback (jitter'lı exponential backoff).
    var es         = null;
    var pollTimer  = null;
    var connecting = false;
    var backoff    = 1000;
    var backoffMax = 30000;
    var coolDownMs = 300000;   // kalıcı kapanış sonrası TEK yeniden deneme aralığı (5 dk)

    function startPollingFallback() {
        if (pollTimer) return;
        pollTimer = setInterval(refresh, pollMs);
    }
    function stopPollingFallback() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    function scheduleReconnect() {
        var delay = Math.min(backoff, backoffMax) + Math.floor(Math.random() * 1000);
        backoff = Math.min(backoff * 2, backoffMax);
        setTimeout(connect, delay);
    }

    function connect() {
        if (connecting) return;
        connecting = true;
        // Same-origin SSE ucuna doğrudan bağlan; oturum cookie'si withCredentials ile gider,
        // kanallar sunucuda session'dan türetilir (istemci topic göndermez).
        openStream(streamUrl);
    }

    function openStream(url) {
        try {
            es = new EventSource(url, { withCredentials: true });
        } catch (e) {
            connecting = false;
            startPollingFallback();
            scheduleReconnect();
            return;
        }
        es.onopen = function () {
            connecting = false;
            backoff = 1000;              // sağlıklı bağlantı → backoff sıfırla
            stopPollingFallback();       // SSE çalışırken polling'e gerek yok
            reconcile();                 // açılışta kaçırılmış olabilecekleri yakala
        };
        es.onmessage = function () {
            reconcile();                 // payload'a güvenme; DB'den reconcile et
            broadcastPing();             // diğer sekmeleri de uyar
        };
        es.onerror = function () {
            connecting = false;
            // Tarayıcı gerçeği: onerror HTTP durum kodunu JS'e VERMEZ. Ama spec gereği sunucu
            // 2xx-olmayan bir yanıt (429 bağlantı cap'i dolu, 204 realtime kapalı) ya da yanlış
            // content-type dönerse tarayıcı akışı KALICI kapatır → readyState CLOSED (2).
            // Geçici ağ kopmasında ise readyState CONNECTING (0) kalır. Bu yüzden durumu
            // close()'dan ÖNCE okuyoruz (close() readyState'i CLOSED yapar).
            // CLOSED dalında 429'u 204'ten ayırt edemeyiz; sorun değil, doğru eylem ikisinde de
            // aynı: polling'e geç ve backoff ZİNCİRİNİ ÇALIŞTIRMA. Aksi halde cap dolu iken
            // reconnect döngüsü korumaya çalıştığımız worker havuzunu daha da zorlardı.
            var permanent = !es || es.readyState === EventSource.CLOSED;
            if (es) { es.close(); es = null; }
            startPollingFallback();
            if (permanent) {
                // Backoff ZİNCİRİ çalıştırılmaz: scheduleReconnect() çağrılmaz, üstel
                // hızlanma yoktur — her kalıcı hatada sabit 5 dk sonrasına yeniden denenir
                // (sekme başına ~0.003 req/s; aynı sekmedeki 60 sn polling'den 5 kat ucuz).
                // Amaç: geçici bir Redis kesintisi ya da anlık doluluk sekmeyi ömür boyu
                // polling'e mahkûm etmesin.
                setTimeout(connect, coolDownMs);
                return;
            }
            scheduleReconnect();
        };
    }

    $(function () {
        refresh();   // ilk durumu hemen çek (SSE hiç açılmasa bile)
        connect();
    });
})();
</script>
<?php $this->endSection(); ?>
