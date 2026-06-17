<?php
/**
 * Methods - Birleşik Form View (Oluştur & Güncelle)
 *
 * $isEdit değişkeni controller tarafından gönderilir:
 *   - create() → $isEdit = false, $method yok
 *   - update() → $isEdit = true,  $method dolu
 */
$isEdit     = isset($method);
$formAction = $isEdit
    ? route_to('methodUpdate', $method->id)
    : route_to('methodCreate');
?>
<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('title');
echo lang($title->pagename);
echo $this->endSection();
echo $this->section('head'); ?>
<link rel="stylesheet" href="/be-assets/plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="/be-assets/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
<?php echo $this->endSection();
echo $this->section('content'); ?>
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><?php echo lang($title->pagename) ?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <a href="<?php echo route_to('methodList') ?>" class="btn btn-sm btn-outline-info"><?php echo lang('Backend.backToList') ?></a>
                </ol>
            </div>
        </div>
    </div>
</section>
<section class="content">
    <div class="card card-outline shadow-sm">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><?php echo lang($title->pagename) ?></h3>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">

            <?php if (session()->has('errors')): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <ul class="mb-0">
                        <?php foreach (session('errors') as $error): ?>
                            <li><?php echo esc($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if ($isEdit): $method->typeOfPermissions = (array) json_decode($method->typeOfPermissions); endif; ?>

            <form action="<?php echo $formAction ?>" method="post" class="form-row">
                <?php echo csrf_field() ?>

                <div class="form-group col-md-4">
                    <label><?php echo lang('Methods.pageName') ?></label>
                    <input type="text" name="pagename" class="form-control"
                           value="<?php echo old('pagename', $isEdit ? $method->pagename : '') ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label><?php echo lang('Methods.description') ?></label>
                    <input type="text" name="description" class="form-control"
                           value="<?php echo old('description', $isEdit ? $method->description : '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label><?php echo lang('Methods.controller') ?></label>
                    <input type="text" name="className" class="form-control"
                           value="<?php echo old('className', $isEdit ? $method->className : '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label><?php echo lang('Methods.methodName') ?></label>
                    <input type="text" name="methodName" class="form-control"
                           value="<?php echo old('methodName', $isEdit ? $method->methodName : '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label>Seflink</label>
                    <input type="text" name="sefLink" class="form-control"
                           value="<?php echo old('sefLink', $isEdit ? $method->sefLink : '') ?>" required>
                </div>
                <div class="form-group col-md-4">
                    <label><?php echo lang('Methods.pageOrder') ?></label>
                    <input type="number" name="pageSort" class="form-control"
                           value="<?php echo old('pageSort', $isEdit ? $method->pageSort : '') ?>">
                </div>
                <div class="form-group col-md-4">
                    <label><?php echo lang('Methods.symbol') ?> <small><a href="https://fontawesome.com/v5/icons#packs" target="_blank">(FontAwesome 5)</a></small></label>
                    <input type="text" name="symbol" class="form-control"
                           value="<?php echo old('symbol', $isEdit ? $method->symbol : '') ?>">
                </div>

                <!-- İzinler -->
                <div class="form-group col-md-4">
                    <label><?php echo lang('Users.perms') ?></label>
                    <div class="w-100 btn-group btn-group-toggle" data-toggle="buttons">
                        <?php
                        $perms = $isEdit ? $method->typeOfPermissions : [];
                        $permMap = [
                            'create' => ['label' => 'btn-outline-success', 'key' => 'create_r'],
                            'read'   => ['label' => 'btn-outline-secondary', 'key' => 'read_r'],
                            'update' => ['label' => 'btn-outline-info',    'key' => 'update_r'],
                            'delete' => ['label' => 'btn-outline-danger',  'key' => 'delete_r'],
                        ];
                        foreach ($permMap as $val => $cfg):
                            $checked = $isEdit && !empty($perms[$cfg['key']]) && $perms[$cfg['key']] === true;
                        ?>
                            <label class="btn <?php echo $cfg['label'] ?> <?php echo $checked ? 'active' : '' ?>">
                                <input type="checkbox" name="typeOfPermissions[]" value="<?php echo $val ?>"
                                       autocomplete="off" <?php echo $checked ? 'checked' : '' ?>>
                                <?php echo ucfirst($val) ?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Üst Sayfa -->
                <div class="form-group col-md-4">
                    <label><?php echo lang('Methods.parentPage') ?></label>
                    <select name="parent_pk" id="parentPk" class="form-control select2">
                        <option value="" disabled selected><?php echo lang('Backend.selectOption', [lang('Methods.parentPage')]) ?></option>
                        <?php
                        // create modunda $permPages, update modunda $methods gelir
                        $parentList = $isEdit ? ($methods ?? []) : ($permPages ?? []);
                        foreach ($parentList as $item):
                        ?>
                            <option value="<?php echo $item->id ?>"
                                <?php echo ($isEdit && $method->parent_pk == $item->id) ? 'selected' : '' ?>>
                                <?php echo lang($item->pagename) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Modül -->
                <div class="form-group col-md-3">
                    <label><?php echo lang('Methods.module') ?></label>
                    <select name="moduleName" id="moduleName" class="form-control select2">
                        <option value="" disabled selected><?php echo lang('Backend.select') ?></option>
                        <?php foreach ($modules as $module): ?>
                            <option value="<?php echo $module->id ?>"
                                <?php echo ($isEdit && $method->module_id == $module->id) ? 'selected' : '' ?>>
                                <?php echo $module->name ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Ek Seçenekler -->
                <div class="form-group col-md-9 d-flex align-items-end">
                    <div class="w-100 btn-group btn-group-toggle" data-toggle="buttons">
                        <label class="btn btn-outline-primary <?php echo ($isEdit && (bool)$method->inNavigation) ? 'active' : '' ?>">
                            <input class="custom-control-input" value="1" name="inNavigation" type="checkbox" id="inNavigation"
                                   <?php echo ($isEdit && (bool)$method->inNavigation) ? 'checked' : '' ?>>
                            <?php echo lang('Methods.inMenu') ?>
                        </label>
                        <label class="btn btn-outline-primary <?php echo (!$isEdit || (bool)$method->isBackoffice) ? 'active' : '' ?>">
                            <input class="custom-control-input" value="1" name="isBackoffice" type="checkbox" id="isBackoffice"
                                   <?php echo (!$isEdit || (bool)$method->isBackoffice) ? 'checked' : '' ?>>
                            <?php echo lang('Methods.inPanel') ?>
                        </label>
                        <label class="btn btn-outline-primary <?php echo ($isEdit && (bool)$method->hasChild) ? 'active' : '' ?>">
                            <input class="custom-control-input" value="1" name="hasChild" type="checkbox" id="hasChild"
                                   <?php echo ($isEdit && (bool)$method->hasChild) ? 'checked' : '' ?>>
                            <?php echo lang('Methods.hasChildPages') ?>
                        </label>
                    </div>
                </div>

                <div class="form-group col-md-12">
                    <button type="submit" class="btn btn-success float-right">
                        <?php echo $isEdit ? lang('Backend.update') : lang('Backend.add') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>
<?php echo $this->endSection();
echo $this->section('javascript');
echo script_tag('be-assets/plugins/select2/js/select2.full.min.js'); ?>
<script type="text/javascript" <?php echo csp_script_nonce(); ?>>
    $('.select2').select2({
        theme: 'bootstrap4'
    });
</script>
<?php echo $this->endSection() ?>
