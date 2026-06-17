<?php
ob_start();
?>
<div class="error-card">
    <div class="error-code text-warning">403</div>
    <div class="error-icon text-warning">
        <i class="fas fa-ban"></i>
    </div>
    <h2><?= lang('Backend.err403Heading') ?></h2>
    <p><?= lang('Backend.err403Body') ?></p>
    <div class="d-flex justify-content-center" style="gap: 0.75rem;">
        <a href="<?= base_url('backend') ?>" class="btn btn-warning">
            <i class="fas fa-home mr-1"></i> <?= lang('Backend.errHomePage') ?>
        </a>
        <a href="javascript:history.back()" class="btn btn-outline-secondary">
            <i class="fas fa-arrow-left mr-1"></i> <?= lang('Backend.errGoBack') ?>
        </a>
    </div>
</div>
<?php
$errorContent = ob_get_clean();
echo view('Modules\Backend\Views\errors\html\error_layout', [
    'pageTitle'    => lang('Backend.err403Title'),
    'errorContent' => $errorContent,
]);
