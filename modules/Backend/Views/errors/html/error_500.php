<?php
ob_start();
?>
<div class="error-card">
    <div class="error-code text-danger">500</div>
    <div class="error-icon text-danger">
        <i class="fas fa-server"></i>
    </div>
    <h2><?= lang('Backend.err500Heading') ?></h2>
    <p><?= lang('Backend.err500Body') ?></p>
    <div class="d-flex justify-content-center" style="gap: 0.75rem;">
        <a href="<?= base_url('backend') ?>" class="btn btn-danger">
            <i class="fas fa-home mr-1"></i> <?= lang('Backend.errHomePage') ?>
        </a>
        <a href="javascript:location.reload()" class="btn btn-outline-secondary">
            <i class="fas fa-redo mr-1"></i> <?= lang('Backend.errRetry') ?>
        </a>
    </div>
</div>
<?php
$errorContent = ob_get_clean();
echo view('Modules\Backend\Views\errors\html\error_layout', [
    'pageTitle'    => lang('Backend.err500Title'),
    'errorContent' => $errorContent,
]);
