<?= $this->extend('Modules\Backend\Views\base') ?>
<?= $this->section('title') ?>
<?= lang('Backend.' . $title->pagename) ?>
<?= $this->endSection() ?>
<?= $this->section('head') ?>
<?= link_tag("be-assets/plugins/select2/css/select2.min.css") ?>
<?= link_tag("be-assets/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css") ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><?= lang('Backend.' . $title->pagename) ?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right"></ol>
            </div>
        </div>
    </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">
    <div class="card card-outline card-shl">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><?= lang('Backend.' . $title->pagename) ?></h3>

            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <?= view('Modules\Auth\Views\_message_block') ?>
            <form action="<?= route_to('methodCreate') ?>" method="post" class="form-row">
                <?= csrf_field() ?>
                <div class="form-group col-md-4">
                    <label for="">Sayfa Adı</label>
                    <input type="text" name="pagename" class="form-control" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="">Açıklama</label>
                    <input type="text" name="description" class="form-control">
                </div>
                <div class="form-group col-md-4">
                    <label for="">Sınıf Adı</label>
                    <input type="text" name="className" class="form-control">
                </div>
                <div class="form-group col-md-4">
                    <label for="">Metot adı</label>
                    <input type="text" name="methodName" class="form-control">
                </div>
                <div class="form-group col-md-4">
                    <label for="">Seflink</label>
                    <input type="text" name="sefLink" class="form-control" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="">Sayfa Sırası</label>
                    <input type="number" name="pageSort" class="form-control">
                </div>
                <div class="form-group col-md-4">
                    <label for="">Sembol <small>(FontAwesome 5)</small></label>
                    <input type="text" name="symbol" class="form-control">
                </div>
                <div class="form-group col-md-4">
                    <label for="">Yetki</label>
                    <input type="text" name="typeOfPermissions" class="form-control" required>
                </div>
                <div class="form-group col-md-4">
                    <label for="">Üst Sayfası</label>
                    <select name="parent_pk" id="parentPk" class="form-control select2">
                        <option value="" disabled selected>Üst sayfa seçin</option>
                    </select>
                </div>
                <div class="form-group col-md-4">
                    <div class="custom-control custom-checkbox">
                        <input class="custom-control-input" value="1" name="inNavigation" type="checkbox" id="inNavigation">
                        <label for="inNavigation" class="custom-control-label">Menüde mi ?</label>
                    </div>
                </div>
                <div class="form-group col-md-4">
                    <div class="custom-control custom-checkbox">
                        <input class="custom-control-input" value="1" name="isBackoffice" type="checkbox" id="isBackoffice" checked>
                        <label for="isBackoffice" class="custom-control-label">Panelde mi ?</label>
                    </div>
                </div>
                <div class="form-group col-md-4">
                    <div class="custom-control custom-checkbox">
                        <input class="custom-control-input" value="1" name="hasChild" type="checkbox" id="hasChild">
                        <label for="hasChild" class="custom-control-label">Alt sayfası var mı ?</label>
                    </div>
                </div>
                <div class="form-group col-md-12">
                    <button class="btn btn-success float-right"><?= lang('Backend.add') ?></button>
                </div>
            </form>
        </div>
    </div>
</section>
<!-- /.content -->
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>
<?= script_tag('be-assets/plugins/select2/js/select2.full.min.js') ?>
<script>
    $('#parentPk').select2({
        theme: 'bootstrap4'
    });
</script>
<?= $this->endSection() ?>
