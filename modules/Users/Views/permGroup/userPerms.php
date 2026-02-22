<?php echo $this->extend('Modules\Backend\Views\base') ?>

<?php echo $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><?php echo $userInfos->firstname . ' ' . $userInfos->surname ?> <?php echo lang('Users.permUpdate') ?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <a href="<?php echo route_to('users', 1) ?>" class="btn btn-outline-info"><?php echo lang('Backend.backToList') ?></a>
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
            <h3 class="card-title font-weight-bold"><?php echo $userInfos->firstname . ' ' . $userInfos->surname ?> <?php echo lang('Users.permUpdate') ?></h3>

            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>

        <div class="card-body">
            <form action="<?php echo route_to('user_perms', $userInfos->id) ?>" method="post" class="form-row">
                <?php echo csrf_field() ?>
                <div class="col-md-12 mt-3">
                    <?php foreach ($modules as $module): ?>
                        <div class="card module-card col-12" data-module-id="<?php echo $module->id ?>" data-status="<?php echo $module->active ? 'active' : 'inactive' ?>">
                            <div class="card-header">
                                <div class="d-flex align-items-center">
                                    <div class="module-title">
                                        <div class="icon bg-secondary">
                                            <i class="<?php echo $module->icon ?>"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-0"><?php echo htmlspecialchars($module->name) ?></h5>
                                            <small class="text-muted"><?php echo date('d.m.Y', strtotime($module->created)) ?></small>
                                        </div>
                                    </div>
                                </div>

                                <div class="w-100 text-right">
                                    <div class="module-toggle float-right">
                                        <span class="badge bg-primary"><?php echo lang('Methods.methodCount', [count($module->pages)]) ?></span>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body row">
                                <?php if (empty($module->pages)): ?>
                                    <div class="alert alert-warning"><?php echo lang('Methods.noPagesFound') ?></div>
                                    <?php else:
                                    foreach ($module->pages as $key => $page):
                                        $groupPermPageIds = array_column($groupPerms, 'page_id');
                                        $permPageIds = array_column($perms, 'page_id');
                                        if (in_array($page->id, $groupPermPageIds) || in_array($page->id, $permPageIds)) {
                                            unset($module->pages[$key]);
                                            continue;
                                        } ?>
                                        <div class="page-item col-md-4 border" data-page-id="<?php echo $page->id ?>" data-status="inactive" data-content="<?php echo htmlspecialchars($page->description) ?>">
                                            <div class="d-flex">
                                                <div class="page-name w-100 d-flex"><?php echo htmlspecialchars($page->pagename) ?></div>
                                            </div>
                                            <div class="page-description"><?php echo htmlspecialchars($page->description) ?></div>
                                            <div class="page-meta gap-5">
                                                <div class="meta-item text-break">
                                                    <i class="fas fa-code me-1"></i>
                                                    <?php echo htmlspecialchars(str_replace('-', '\\', $page->className)) ?>::<?php echo htmlspecialchars($page->methodName) ?>
                                                </div>
                                                <div class="meta-item">
                                                    <i class="fas fa-link me-1"></i>
                                                    <?php echo htmlspecialchars($page->sefLink) ?>
                                                </div>
                                                <?php if ($page->inNavigation): ?>
                                                    <span class="ml-2 badge bg-info d-flex align-items-center"><?php echo lang('Methods.navigation') ?></span>
                                                <?php endif; ?>
                                                <?php if ($page->hasChild): ?>
                                                    <span class="ml-2 badge bg-warning d-flex align-items-center"><?php echo lang('Methods.hasChildPages') ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <label class="toggle-switch page-toggle">
                                                <input type="checkbox" name="perms[<?php echo $page->id ?>][roles]"
                                                    <?php if (!empty($perms)) {
                                                        foreach ($perms as $p) {
                                                            $permission = explode('.', $p);
                                                            if ($permission[0] . '.' . $permission[1] == strtolower($page->pagename) && ($permission[2] || $permission[2] || $permission[2] || $permission[2])) {
                                                                echo ' checked';
                                                                break;
                                                            }
                                                        }
                                                    } ?> value="<?php echo $page->typeOfPermissions ?>">
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
                    <button class="btn btn-success float-right"><?php echo lang('Backend.update') ?></button>
                </div>
            </form>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->

</section>
<!-- /.content -->
<?php echo $this->endSection() ?>
