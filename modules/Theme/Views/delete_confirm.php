<?php echo $this->extend('Modules\Backend\Views\base'); ?>

<?php echo $this->section('title'); ?>
<?php echo lang('Theme.deleteTheme'); ?>
<?php echo $this->endSection(); ?>

<?php echo $this->section('content'); ?>
<section class="content-header">
    <div class="container-fluid">
        <div class="row pb-3 border-bottom mb-3">
            <div class="col-sm-6">
                <h1><i class="fas fa-trash-alt mr-2 text-danger"></i> <?php echo lang('Theme.deleteTheme') ?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <a href="<?php echo route_to('backendThemes') ?>" class="btn btn-outline-info"><i class="fas fa-arrow-circle-left"></i> <?php echo lang('Settings.settings') ?> Gösterge Paneline Dön</a>
                </ol>
            </div>
        </div>
    </div>
</section>

<section class="content">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-8 offset-md-2">
                <div class="card card-outline card-danger shadow-sm">
                    <div class="card-header">
                        <h3 class="card-title text-danger font-weight-bold">
                            Tema: <span class="badge badge-light border"><?php echo esc($themeName) ?></span>
                        </h3>
                    </div>
                    <form action="<?php echo route_to('deleteThemeProcess', $themeName) ?>" method="post" id="deleteThemeForm">
                        <?php echo csrf_field(); ?>
                        <div class="card-body">

                            <?php if (empty($tables)): ?>
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle mr-2"></i> <?php echo lang('Theme.noTablesFound') ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted"><i class="fas fa-exclamation-triangle text-warning mr-1"></i> <?php echo lang('Theme.deleteThemeInfo') ?></p>

                                <div class="card bg-light border-0">
                                    <div class="card-body">
                                        <div class="custom-control custom-checkbox mb-3 border-bottom pb-2">
                                            <input type="checkbox" class="custom-control-input" id="checkAllTables">
                                            <label class="custom-control-label font-weight-bold text-primary" for="checkAllTables" style="cursor:pointer;">
                                                <?php echo lang('Theme.selectAllTables') ?>
                                            </label>
                                        </div>

                                        <div class="row">
                                            <?php foreach ($tables as $table): ?>
                                                <div class="col-md-6 mb-2">
                                                    <div class="custom-control custom-checkbox">
                                                        <input type="checkbox" class="custom-control-input table-checkbox"
                                                               id="tbl_<?php echo esc($table) ?>"
                                                               name="tables[]"
                                                               value="<?php echo esc($table) ?>">
                                                        <label class="custom-control-label" for="tbl_<?php echo esc($table) ?>" style="cursor:pointer; font-family: monospace;">
                                                            <?php echo esc($table) ?>
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                        </div>
                        <div class="card-footer border-top text-right">
                            <a href="<?php echo route_to('templateSettings') ?>" class="btn btn-secondary mr-2">İptal</a>
                            <button type="button" class="btn btn-danger" id="btnConfirmDelete">
                                <i class="fas fa-trash-alt mr-1"></i> <?php echo lang('Theme.deleteFilesAndTables') ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
<?php echo $this->endSection(); ?>

<?php echo $this->section('javascript'); ?>
<script>
    document.addEventListener("DOMContentLoaded", function () {

        // Tümünü seç / bırak
        const checkAllBtn = document.getElementById('checkAllTables');
        if(checkAllBtn) {
            checkAllBtn.addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('.table-checkbox');
                checkboxes.forEach(cb => cb.checked = this.checked);
            });
        }

        // Form Submit
        document.getElementById('btnConfirmDelete').addEventListener('click', function () {
            Swal.fire({
                title: 'Emin misiniz?',
                text: "Bu tema tamamen silinecek. İşaretlediğiniz tablolar veritabanından kalıcı olarak temizlenecektir. Bu işlem geri alınamaz!",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                cancelButtonColor: '#3085d6',
                confirmButtonText: 'Evet, Kalıcı Olarak Sil!',
                cancelButtonText: 'İptal'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteThemeForm').submit();
                }
            });
        });

    });
</script>
<?php echo $this->endSection(); ?>
