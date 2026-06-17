<?php
// production.php — yalnızca production ortamında kullanılır, detay gösterilmez.
ob_start();
?>
<div class="error-card">
    <div class="error-icon text-danger" style="font-size: 4rem; margin-bottom: 1rem;">
        <i class="fas fa-exclamation-circle"></i>
    </div>
    <h2><?= lang('Backend.errProdHeading') ?></h2>
    <p><?= lang('Backend.errProdBody') ?></p>
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
    'pageTitle'    => lang('Backend.errProdTitle'),
    'errorContent' => $errorContent,
]);
