<?php
ob_start();
?>
<div class="error-card">
    <div class="error-code" style="color: #fd7e14; font-size: 6rem; font-weight: 700; line-height: 1; margin-bottom: 0.5rem;">429</div>
    <div class="error-icon" style="color: #fd7e14; font-size: 3rem; margin-bottom: 1rem;">
        <i class="fas fa-stopwatch"></i>
    </div>
    <h2><?= lang('Backend.err429Heading') ?></h2>
    <p><?= lang('Backend.err429Body') ?></p>
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
    'pageTitle'    => lang('Backend.err429Title'),
    'errorContent' => $errorContent,
]);
