<?= $this->extend('Modules\Backend\Views\base') ?>

<?= $this->section('title') ?>
<?= lang($title->pagename) ?>
<?= $this->endSection() ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="/be-assets/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><?= lang($title->pagename) ?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <a href="<?= route_to('settings') ?>" class="btn btn-outline-info"><i
                                class="fas fa-arrow-circle-left"></i> Ayarlara Dön</a>
                </ol>
            </div>
        </div>
    </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">

    <!-- Default box -->
    <div class="card card-outline card-shl">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><?= lang($title->pagename) ?></h3>

            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <?= view('Modules\Auth\Views\_message_block') ?>
            <form action="<?=route_to('templateSettings_post')?>" method="post">
                <?= csrf_field() ?>
                <div class="row">
                    <div class="col-md-6 card">
                        <div class="card-header bg-success">
                            <h3 class="card-title font-weight-bold">Sidebar Ayarları</h3>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-4">
                                    <label for="">Arama</label>
                                    <input type="checkbox" name="settings[widgets][sidebar][searchWidget]" value="true" <?=!empty($settings->templateInfos->widgets['sidebar']['searchWidget']) && (boolean)$settings->templateInfos->widgets['sidebar']['searchWidget']===true?'checked':''?>>
                                </div>
                                <div class="col-md-4">
                                    <label for="">Kategori listesi</label>
                                    <input type="checkbox" name="settings[widgets][sidebar][categoriesWidget]" value="true" <?=!empty($settings->templateInfos->widgets['sidebar']['categoriesWidget']) && (boolean)$settings->templateInfos->widgets['sidebar']['categoriesWidget']===true?'checked':''?>>
                                </div>
                                <div class="col-md-4">
                                    <label for="">Arşiv listesi</label>
                                    <input type="checkbox" name="settings[widgets][sidebar][archiveWidget]" value="true" <?=!empty($settings->templateInfos->widgets['sidebar']['archiveWidget']) && (boolean)$settings->templateInfos->widgets['sidebar']['archiveWidget']===true?'checked':''?>>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-success float-right">Kaydet</button>
                    </div>
                </div>
            </form>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->

</section>
<!-- /.content -->
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>
<script src="/be-assets/plugins/sweetalert2/sweetalert2.min.js"></script>
<?= $this->endSection() ?>
