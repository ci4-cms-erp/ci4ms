<?php

// retryAfter: BackendMaintenanceFilter'dan gelir (gerçek kalan saniye).
// Yoksa / 0 ise geri sayım gösterilmez ve sayfa bugünkü gibi kalır.
$retryAfter = (isset($retryAfter) && (int) $retryAfter > 0) ? (int) $retryAfter : null;

ob_start();
?>
<div class="error-card">
    <div class="error-code text-secondary">503</div>
    <div class="error-icon text-secondary">
        <i class="fas fa-tools"></i>
    </div>
    <h2><?= lang('Backend.err503Heading') ?></h2>
    <p><?= lang('Backend.err503Body') ?></p>
    <?php if ($retryAfter !== null): ?>
        <div class="mb-4">
            <div class="font-weight-bold text-secondary" style="font-size: 0.9rem;">
                <i class="fas fa-hourglass-half mr-1"></i> <?= lang('Backend.err503CdTitle') ?>
            </div>
            <div class="text-muted mb-2" style="font-size: 0.8rem;"><?= lang('Backend.err503CdSub') ?></div>
            <div class="d-flex justify-content-center align-items-center err-countdown" data-countdown="<?= $retryAfter ?>" style="gap: 0.75rem;">
                <div>
                    <div class="font-weight-bold" style="font-size: 1.75rem; line-height: 1;" data-cd="h">00</div>
                    <div class="text-muted" style="font-size: 0.7rem; text-transform: uppercase;"><?= lang('Backend.errCdHours') ?></div>
                </div>
                <div class="font-weight-bold text-muted" style="font-size: 1.5rem;">:</div>
                <div>
                    <div class="font-weight-bold" style="font-size: 1.75rem; line-height: 1;" data-cd="m">00</div>
                    <div class="text-muted" style="font-size: 0.7rem; text-transform: uppercase;"><?= lang('Backend.errCdMinutes') ?></div>
                </div>
                <div class="font-weight-bold text-muted" style="font-size: 1.5rem;">:</div>
                <div>
                    <div class="font-weight-bold" style="font-size: 1.75rem; line-height: 1;" data-cd="s">00</div>
                    <div class="text-muted" style="font-size: 0.7rem; text-transform: uppercase;"><?= lang('Backend.errCdSeconds') ?></div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    <div class="d-flex justify-content-center" style="gap: 0.75rem;">
        <a href="javascript:location.reload()" class="btn btn-secondary">
            <i class="fas fa-redo mr-1"></i> <?= lang('Backend.errRetry') ?>
        </a>
    </div>
</div>
<?php if ($retryAfter !== null): ?>
    <script>
        (function() {
            var cd = document.querySelector('.err-countdown[data-countdown]');
            if (!cd) return;
            var t = parseInt(cd.getAttribute('data-countdown'), 10) || 0;
            var h = cd.querySelector('[data-cd="h"]'),
                m = cd.querySelector('[data-cd="m"]'),
                s = cd.querySelector('[data-cd="s"]');
            var pad = function(n) { return (n < 10 ? '0' : '') + n; };
            var timer;
            var tick = function() {
                if (h) h.textContent = pad(Math.floor(t / 3600));
                if (m) m.textContent = pad(Math.floor((t % 3600) / 60));
                if (s) s.textContent = pad(t % 60);
                if (t <= 0) {
                    clearInterval(timer);
                    location.reload();
                    return;
                }
                t--;
            };
            tick();
            timer = setInterval(tick, 1000);
        })();
    </script>
<?php endif; ?>
<?php
$errorContent = ob_get_clean();
echo view('Modules\Backend\Views\errors\html\error_layout', [
    'pageTitle'    => lang('Backend.err503Title'),
    'errorContent' => $errorContent,
]);
