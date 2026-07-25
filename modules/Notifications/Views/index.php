<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('title');
echo lang('Notifications.title');
echo $this->endSection();
echo $this->section('content');

// severity → [ikon, renk] eşlemesi; bilinmeyen değer info'ya düşer.
$severityMap = [
    'info'     => ['far fa-bell', 'text-primary'],
    'warning'  => ['fas fa-exclamation-triangle', 'text-warning'],
    'critical' => ['fas fa-exclamation-circle', 'text-danger'],
];
$severityOf = static fn ($s) => $severityMap[$s] ?? $severityMap['info'];
?>
<section class="content pt-3">
    <div class="card border-0 shadow-sm" style="border-radius:12px">
        <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between">
            <h6 class="mb-0 font-weight-bold">
                <i class="fas fa-bell mr-2 text-primary"></i><?php echo lang('Notifications.title') ?>
                <?php if (!empty($unread)): ?>
                    <span class="badge badge-danger ml-1"><?php echo (int) $unread ?> <?php echo lang('Notifications.unread') ?></span>
                <?php endif; ?>
            </h6>
            <div>
                <?php if (!empty($notifications)): ?>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="notifMarkAll()">
                        <i class="fas fa-check-double mr-1"></i><?php echo lang('Notifications.markAllRead') ?>
                    </button>
                <?php endif; ?>
                <a href="<?php echo route_to('notifPrefs') ?>" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-sliders-h mr-1"></i><?php echo lang('Notifications.preferences') ?>
                </a>
                <?php // İzin kontrolü YOK: projede view-içi izin helper'ı bulunmuyor, gate'leme
                      // fail-closed olarak rotanın kendisinde (backendGuard + role) yapılır — yetkisiz
                      // kullanıcı bağlantıyı görür ama uç 403 döner. ?>
                <a href="<?php echo route_to('notifCompose') ?>" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-paper-plane mr-1"></i><?php echo lang('Notifications.compose') ?>
                </a>
            </div>
        </div>
        <div class="card-body pt-0">
            <?php if (empty($notifications)): ?>
                <p class="small text-muted mb-0"><?php echo lang('Notifications.noNotifications') ?></p>
            <?php else: ?>
                <div class="list-group list-group-flush">
                    <?php foreach ($notifications as $n): ?>
                        <?php [$sevIcon, $sevColor] = $severityOf($n->severity ?? 'info'); ?>
                        <div class="list-group-item bg-transparent px-0 d-flex align-items-start <?php echo empty($n->read_at) ? 'font-weight-bold' : '' ?>" id="notif-<?php echo (int) $n->id ?>">
                            <div class="mr-3 mt-1">
                                <i class="<?php echo $sevIcon ?> small <?php echo empty($n->read_at) ? $sevColor : 'text-muted' ?>"></i>
                            </div>
                            <div class="flex-grow-1">
                                <?php if (!empty($n->url)): ?>
                                    <a href="<?php echo esc($n->url, 'attr') ?>" class="text-dark"><?php echo esc($n->title) ?></a>
                                <?php else: ?>
                                    <?php echo esc($n->title) ?>
                                <?php endif; ?>
                                <?php if (!empty($n->body)): ?>
                                    <div class="small text-muted"><?php echo esc($n->body) ?></div>
                                <?php endif; ?>
                                <div class="small text-muted"><?php echo esc($n->created_at) ?></div>
                            </div>
                            <?php if (empty($n->read_at)): ?>
                                <button type="button" class="btn btn-xs btn-outline-primary border-0" title="<?php echo esc(lang('Notifications.markRead'), 'attr') ?>" onclick="notifMarkRead(<?php echo (int) $n->id ?>, '<?php echo route_to('notifRead', $n->id) ?>')">
                                    <i class="fas fa-check"></i>
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php echo $this->endSection();
echo $this->section('javascript'); ?>
<script>
    // CSRF: token be-assets/js/ci4ms.js global AJAX katmanınca eklenir (kanonik).
    function notifMarkRead(id, url) {
        $.post(url).done(function () { $('#notif-' + id).removeClass('font-weight-bold'); });
    }

    function notifMarkAll() {
        $.post('<?php echo route_to('notifReadAll') ?>').done(function () { location.reload(); });
    }
</script>
<?php echo $this->endSection(); ?>
