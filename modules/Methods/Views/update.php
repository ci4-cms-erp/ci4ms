<?php echo $this->extend('Modules\Backend\Views\base') ?>
<?php echo $this->section('title') ?>
<?php echo lang($title->pagename) ?>
<?php echo $this->endSection() ?>
<?php echo $this->section('head') ?>
<?php echo link_tag("be-assets/plugins/select2/css/select2.min.css") ?>
<?php echo link_tag("be-assets/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css") ?>
<?php echo $this->endSection() ?>

<?php echo $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><?php echo lang($title->pagename) ?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <a href="<?php echo route_to('list') ?>" class="btn btn-outline-info"><?php echo lang('Backend.backToList') ?></a>
                </ol>
            </div>
        </div>
    </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">
    <div class="card card-outline card-shl">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><?php echo lang($title->pagename) ?></h3>

            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <form action="<?php echo route_to('methodUpdate', $method->id) ?>" method="post" class="form-row">
                <?php echo csrf_field() ?>
                <div class="form-group col-md-4">
                    <label for=""><?php echo lang('Methods.pageName') ?></label>
                    <input type="text" name="pagename" class="form-control" value="<?php echo $method->pagename ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label for=""><?php echo lang('Methods.description') ?></label>
                    <input type="text" name="description" class="form-control" value="<?php echo $method->description ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for=""><?php echo lang('Methods.controller') ?></label>
                    <input type="text" name="className" class="form-control" value="<?php echo $method->className ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for=""><?php echo lang('Methods.methodName') ?></label>
                    <input type="text" name="methodName" class="form-control" value="<?php echo $method->methodName ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for="">Seflink</label>
                    <input type="text" name="sefLink" class="form-control" value="<?php echo $method->sefLink ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label for=""><?php echo lang('Methods.pageOrder') ?></label>
                    <input type="number" name="pageSort" class="form-control" value="<?php echo $method->pageSort ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for=""><?php echo lang('Methods.symbol') ?> <small><a href="https://fontawesome.com/v5/icons#packs" target="_blank">(FontAwesome 5)</a></small></label>
                    <input type="text" name="symbol" class="form-control" value="<?php echo $method->symbol ?>">
                </div>
                <div class="form-group col-md-4">
                    <label for=""><?php echo lang('Users.perms') ?></label>
                    <div class="w-100 btn-group btn-group-toggle" data-toggle="buttons">
                        <?php $method->typeOfPermissions = (array)json_decode($method->typeOfPermissions); ?>
                        <label class="btn btn-outline-success">
                            <input type="checkbox" name="typeOfPermissions[]" value="create" autocomplete="off" <?php echo !empty($method->typeOfPermissions['create_r']) && $method->typeOfPermissions['create_r'] === true ? 'checked' : '' ?>> Create
                        </label>
                        <label class="btn btn-outline-secondary">
                            <input type="checkbox" name="typeOfPermissions[]" value="read" autocomplete="off" <?php echo !empty($method->typeOfPermissions['read_r']) && $method->typeOfPermissions['read_r'] === true ? 'checked' : '' ?>> Read
                        </label>
                        <label class="btn btn-outline-info">
                            <input type="checkbox" name="typeOfPermissions[]" value="update" autocomplete="off" <?php echo !empty($method->typeOfPermissions['update_r']) && $method->typeOfPermissions['update_r'] === true ? 'checked' : '' ?>> Update
                        </label>
                        <label class="btn btn-outline-danger">
                            <input type="checkbox" name="typeOfPermissions[]" value="delete" autocomplete="off" <?php echo !empty($method->typeOfPermissions['delete_r']) && $method->typeOfPermissions['delete_r'] === true ? 'checked' : '' ?>> Delete
                        </label>
                    </div>
                </div>
                <div class="form-group col-md-4">
                    <label for=""><?php echo lang('Methods.parentPage') ?></label>
                    <select name="parent_pk" id="parentPk" class="form-control select2">
                        <option value="" disabled selected><?php echo lang('Backend.selectOption', [lang('Methods.parentPage')]) ?></option>
                        <?php foreach ($methods as $methd): ?>
                            <option value="<?php echo $methd->id ?>" <?php echo $method->parent_pk == $methd->id ? 'selected' : '' ?>><?php echo lang($methd->pagename) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-3">
                    <label for=""><?php echo lang('Methods.module') ?></label>
                    <select name="moduleName" id="moduleName" class="form-control select2">
                        <option value="" disabled selected><?php echo lang('Backend.select') ?></option>
                        <?php foreach ($modules as $module) : ?>
                            <option value="<?php echo $module->id ?>" <?php echo $method->module_id == $module->id ? 'selected' : '' ?>><?php echo $module->name ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group col-md-9 d-flex align-items-end">
                    <div class="w-100 btn-group btn-group-toggle" data-toggle="buttons">
                        <label class="btn btn-outline-primary <?php echo $method->inNavigation == true ? 'active' : '' ?>">
                            <input class="custom-control-input" value="1" name="inNavigation" type="checkbox" id="inNavigation" <?php echo $method->inNavigation == true ? 'checked' : '' ?>> Menüde mi ?
                        </label>
                        <label class="btn btn-outline-primary <?php echo $method->isBackoffice == true ? 'active' : '' ?>">
                            <input class="custom-control-input" value="1" name="isBackoffice" type="checkbox" id="isBackoffice" <?php echo $method->isBackoffice == true ? 'checked' : '' ?>> Panelde mi ?
                        </label>
                        <label class="btn btn-outline-primary <?php echo $method->hasChild == true ? 'active' : '' ?>">
                            <input class="custom-control-input" value="1" name="hasChild" type="checkbox" id="hasChild" <?php echo $method->hasChild == true ? 'checked' : '' ?>> Alt sayfası var mı ?
                        </label>
                    </div>
                </div>
                <div class="form-group col-md-12">
                    <button class="btn btn-success float-right"><?php echo lang('Backend.update') ?></button>
                </div>
            </form>
        </div>
    </div>
</section>
<!-- /.content -->
<?php echo $this->endSection() ?>

<?php echo $this->section('javascript') ?>
<?php echo script_tag('be-assets/plugins/select2/js/select2.full.min.js') ?>
<script {csp-script-nonce}>
    $('.select2').select2({
        theme: 'bootstrap4'
    });
</script>
<?php echo $this->endSection() ?>
