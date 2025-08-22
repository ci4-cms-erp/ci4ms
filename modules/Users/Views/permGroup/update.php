<?= $this->extend('Modules\Backend\Views\base') ?>

<?= $this->section('title') ?>
<?= lang($title->pagename) ?>
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
                    <a href="<?= route_to('groupList', 1) ?>" class="btn btn-outline-info"><?= lang('Backend.backToList') ?></a>
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
            <form action="<?= route_to('group_update', $group_name->id) ?>" method="post" class="form-row">
                <?= csrf_field() ?>
                <div class="col-md-6">
                    <label for=""><?= lang('Users.permGroupName') ?></label>
                    <input type="text" class="form-control" value="<?= $group_name->name ?>" name="groupName" required>
                </div>
                <div class="col-md-6">
                    <label for="">Seflink</label>
                    <input type="text" class="form-control" value="<?= $group_name->seflink ?>" name="seflink"
                        required>
                </div>
                <div class="col-md-12">
                    <label for=""><?= lang('Backend.content') ?></label>
                    <textarea name="description" cols="30" rows="10"
                        class="form-control" required><?= $group_name->description ?></textarea>
                </div>
                <div class="col-md-12 mt-3">
                    <?php foreach ($modules as $module): ?>
                        <div class="card module-card col-12" data-module-id="<?= $module->id ?>" data-status="<?= $module->active ? 'active' : 'inactive' ?>">
                            <div class="card-header">
                                <div class="d-flex align-items-center">
                                    <div class="module-title">
                                        <div class="icon bg-secondary">
                                            <i class="<?= $module->icon ?>"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-0"><?= htmlspecialchars($module->name) ?></h5>
                                            <small class="text-muted"><?= date('d.m.Y', strtotime($module->created)) ?></small>
                                        </div>
                                    </div>
                                </div>

                                <div class="w-100 text-right">
                                    <div class="module-toggle float-right">
                                        <span class="badge bg-primary"><?= count($module->pages) ?> adet metot</span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body row">
                                <?php if (empty($module->pages)): ?>
                                    <div class="alert alert-warning">Bu modül için tanımlanmış sayfa bulunamadı</div>
                                    <?php else:
                                    foreach ($module->pages as $page): ?>
                                        <div class="page-item col-md-4 border" data-page-id="<?= $page->id ?>" data-status="inactive" data-content="<?= htmlspecialchars($page->description) ?>">
                                            <div class="d-flex">
                                                <div class="page-name w-100 d-flex"><?= htmlspecialchars($page->pagename) ?></div>
                                            </div>
                                            <div class="page-description"><?= htmlspecialchars($page->description) ?></div>
                                            <div class="page-meta gap-5">
                                                <div class="meta-item text-break">
                                                    <i class="fas fa-code me-1"></i>
                                                    <?= htmlspecialchars(str_replace('-', '\\', $page->className)) ?>::<?= htmlspecialchars($page->methodName) ?>
                                                </div>
                                                <div class="meta-item">
                                                    <i class="fas fa-link me-1"></i>
                                                    <?= htmlspecialchars($page->sefLink) ?>
                                                </div>
                                                <?php if ($page->inNavigation): ?>
                                                    <span class="ml-2 badge bg-info d-flex align-items-center">Navigation</span>
                                                <?php endif; ?>
                                                <?php if ($page->hasChild): ?>
                                                    <span class="ml-2 badge bg-warning d-flex align-items-center">Alt Sayfa Var</span>
                                                <?php endif; ?>
                                            </div>
                                            <label class="toggle-switch page-toggle">
                                                <input type="checkbox" name="perms[<?= $page->id ?>][roles]" <?php foreach($perms as $p) if($p->page_id==$page->id && ($p->create_r||$p->read_r||$p->update_r||$p->delete_r)){ echo 'checked'; break; } ?> value="<?= $page->typeOfPermissions ?>">
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                <?php endforeach;
                                endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="col-md-12">
                    <button class="btn btn-success float-right"><?= lang('Backend.update') ?></button>
                </div>
            </form>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->
</section>
<!-- /.content -->
<?= $this->endSection() ?>
