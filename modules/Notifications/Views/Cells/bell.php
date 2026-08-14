<?php
/**
 * Top bar notification bell — AdminLTE navbar dropdown.
 *
 * @var int   $unread          Number of unread notifications
 * @var array $items           Latest ~FEED_LIMIT relevant notifications (stdClass rows)
 * @var bool  $realtimeEnabled Whether Redis-backed PHP-SSE realtime delivery is on (false -> 60s polling only)
 *
 * CSRF: POST requests are made with jQuery; the be-assets/js/ci4ms.js global AJAX
 * layer automatically adds the X-CSRF-TOKEN header and the body token — no
 * manual token handling is needed here.
 */

// severity -> [icon, color] mapping; an unknown value falls back to info.
$severityMap = [
    'info'     => ['far fa-bell', 'text-primary'],
    'warning'  => ['fas fa-exclamation-triangle', 'text-warning'],
    'critical' => ['fas fa-exclamation-circle', 'text-danger'],
];
$severityOf = static fn ($s) => $severityMap[$s] ?? $severityMap['info'];
?>
<style>
    /* Bootstrap's .dropdown-item is nowrap, and dropdown-menu-lg only sets a min-width:
       a long title wouldn't wrap and would widen the item, and the menu would overflow
       off the right edge of the screen. We fix the width and enable wrapping; max-width
       prevents overflowing the viewport on narrow screens. */
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
        // The server side already validates this; on the client, also only accept '/...' or http(s).
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

    // Global scope — for the onclick handlers inside the dropdown.
    window.ci4msNotifMarkRead = function (id) {
        $.post(readBase + '/' + id).done(function () { refresh(); });
    };
    window.ci4msNotifMarkAll = function (e) {
        if (e) e.preventDefault();
        $.post(readAllUrl).done(function () { refresh(); });
    };

    // Reconcile: re-read from the feed endpoint (DB source-of-truth) WITHOUT TRUSTING the SSE payload.
    // A short debounce ensures a single request during bursts of messages/pings.
    var reconcileTimer = null;
    function reconcile() {
        if (reconcileTimer) return;
        reconcileTimer = setTimeout(function () {
            reconcileTimer = null;
            refresh();
        }, 400);
    }

    // Multi-tab sync: each tab opens its own EventSource (simple + correct; the admin
    // panel has few open tabs, not worth leader election). When a tab receives a message,
    // it calls the other tabs to reconcile via BroadcastChannel (or a storage event
    // fallback); thanks to the debounce this doesn't turn into a storm of unnecessary requests.
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

    // realtime off → BEHAVIOR IS IDENTICAL TO BEFORE: only 60s polling, the SSE code never runs.
    if (!realtime) {
        $(function () { setInterval(refresh, pollMs); });
        return;
    }

    // realtime on → EventSource + polling fallback (exponential backoff with jitter).
    var es         = null;
    var pollTimer  = null;
    var connecting = false;
    var backoff    = 1000;
    var backoffMax = 30000;
    var coolDownMs = 300000;   // single retry interval after a permanent close (5 min)

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
        // Connect directly to the same-origin SSE endpoint; the session cookie travels via
        // withCredentials, channels are derived from the session on the server (the client never sends a topic).
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
            backoff = 1000;              // healthy connection → reset backoff
            stopPollingFallback();       // no need for polling while SSE is running
            reconcile();                 // catch anything that might have been missed on open
        };
        es.onmessage = function () {
            reconcile();                 // don't trust the payload; reconcile from the DB
            broadcastPing();             // wake up the other tabs too
        };
        es.onerror = function () {
            connecting = false;
            // Browser reality: onerror does NOT give the HTTP status code to JS. But per spec, if
            // the server returns a non-2xx response (429 connection cap full, 204 realtime off) or
            // the wrong content-type, the browser closes the stream PERMANENTLY → readyState CLOSED (2).
            // On a transient network drop, readyState stays CONNECTING (0). That's why we read the
            // state BEFORE close() (close() sets readyState to CLOSED).
            // In the CLOSED branch we can't tell a 429 apart from a 204; that's fine, the correct action
            // is the same either way: switch to polling and DO NOT RUN the backoff CHAIN. Otherwise, while
            // the cap is full, the reconnect loop would put even more strain on the worker pool we're
            // trying to protect.
            var permanent = !es || es.readyState === EventSource.CLOSED;
            if (es) { es.close(); es = null; }
            startPollingFallback();
            if (permanent) {
                // The backoff CHAIN is not run: scheduleReconnect() is not called, there is no
                // exponential ramp-up — every permanent error retries after a fixed 5 min instead
                // (~0.003 req/s per tab; 5x cheaper than the same tab's 60s polling).
                // Goal: don't condemn a tab to lifelong polling over a transient Redis outage or
                // momentary fullness.
                setTimeout(connect, coolDownMs);
                return;
            }
            scheduleReconnect();
        };
    }

    $(function () {
        refresh();   // fetch the initial state right away (even if SSE never opens)
        connect();
    });
})();
</script>
<?php $this->endSection(); ?>
