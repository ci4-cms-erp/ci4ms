<?= $this->extend('Modules\Backend\Views\base') ?>
<?= $this->section('title') ?>
<?= lang($title->pagename) ?>
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
                <h1><?= lang($title->pagename) ?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <a href="<?= route_to('list') ?>" class="btn btn-outline-info"><?=lang('Backend.backToList')?></a>
                </ol>
            </div>
        </div>
    </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">
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
                    <label for="">Kontrolcü</label>
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
                    <label for="">Sembol <small><a href="https://fontawesome.com/v5/icons#packs" target="_blank">(FontAwesome 5)</a></small></label>
                    <input type="text" name="symbol" class="form-control">
                </div>
                <div class="form-group col-md-4">
                    <label for="">Yetki</label>
                    <div class="w-100 btn-group btn-group-toggle" data-toggle="buttons">
                        <label class="btn btn-outline-success">
                            <input type="checkbox" name="typeOfPermissions[]" value="create" autocomplete="off"> Create
                        </label>
                        <label class="btn btn-outline-secondary">
                            <input type="checkbox" name="typeOfPermissions[]" value="read" autocomplete="off"> Read
                        </label>
                        <label class="btn btn-outline-info">
                            <input type="checkbox" name="typeOfPermissions[]" value="update" autocomplete="off"> Update
                        </label>
                        <label class="btn btn-outline-danger">
                            <input type="checkbox" name="typeOfPermissions[]" value="delete" autocomplete="off"> Delete
                        </label>
                    </div>
                </div>
                <div class="form-group col-md-4">
                    <label for="">Üst Sayfası</label>
                    <select name="parent_pk" id="parentPk" class="form-control select2">
                        <option value="" disabled selected>Üst sayfa seçin</option>
                        <?php foreach ($permPages as $page): ?>
                            <option value="<?= $page->id ?>"><?= lang($page->pagename) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for="">Hangi Modüle Ait</label>
                    <select name="moduleName" id="moduleName" class="form-control select2">
                        <option value="" disabled selected>Seçiniz</option>
                        <?php foreach ($modules as $module) : ?>
                            <option value="<?= $module->id ?>"><?= $module->name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-9 d-flex align-items-end">
                    <div class="w-100 btn-group btn-group-toggle" data-toggle="buttons">
                        <label class="btn btn-outline-primary">
                            <input class="custom-control-input" value="1" name="inNavigation" type="checkbox" id="inNavigation"> Menüde mi ?
                        </label>
                        <label class="btn btn-outline-primary active">
                            <input class="custom-control-input" value="1" name="isBackoffice" type="checkbox" id="isBackoffice" checked> Panelde mi ?
                        </label>
                        <label class="btn btn-outline-primary">
                            <input class="custom-control-input" value="1" name="hasChild" type="checkbox" id="hasChild"> Alt sayfası var mı ?
                        </label>
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
    $('.select2').select2({
        theme: 'bootstrap4'
    });
</script>
<?= $this->endSection() ?>
