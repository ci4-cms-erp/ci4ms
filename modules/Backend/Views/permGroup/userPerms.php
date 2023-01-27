<?= $this->extend('Modules\Backend\Views\base') ?>

<?= $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><?=$group_perms->firstname.' '.$group_perms->sirname?> <?=lang('Backend.permUpdate')?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <a href="<?= route_to('officeWorker',1) ?>" class="btn btn-outline-info"><?=lang('Backend.backToList')?></a>
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
            <h3 class="card-title font-weight-bold"><?=$group_perms->firstname.' '.$group_perms->sirname?> <?=lang('Backend.permUpdate')?></h3>

            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>

        <div class="card-body">
            <?= view('Modules\Auth\Views\_message_block') ?>
            <form action="<?= route_to('user_perms',$group_perms->id) ?>" method="post" class="form-row">
                <?= csrf_field() ?>
                <div class="col-md-12 mt-3">
                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                            <tr>
                                <td><?=lang('Backend.pages')?></td>
                                <td><?=lang('Backend.content')?></td>
                                <td style="width: 200px"><?=lang('Backend.perms')?></td>
                            </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($pages as $page):
                                $c=null; $r=null; $u=null; $d=null;
                                if(!empty($group_perms->auth_users_permissions)):
                                foreach ($group_perms->auth_users_permissions as $perms):
                                    if ($perms->page_id == $page->id):
                                        if($perms->create_r == true)
                                            $c='checked';
                                        if($perms->read_r == true)
                                            $r='checked';
                                        if($perms->update_r == true)
                                            $u='checked';
                                        if($perms->delete_r == true)
                                            $d='checked';
                                    endif;
                                endforeach;
                                endif; ?>
                                        <tr>
                                            <td><?= $page->pagename ?></td>
                                            <td><?= $page->description ?? '' ?></td>
                                            <td>
                                                <div class="btn-group btn-group-toggle" data-toggle="buttons">
                                                    <label class="btn btn-outline-secondary">
                                                        <input type="checkbox"
                                                               name="perms[<?= (string)$page->id ?>][c]" <?= $c ?>>
                                                        Create
                                                    </label>
                                                    <label class="btn btn-outline-secondary">
                                                        <input type="checkbox"
                                                               name="perms[<?= (string)$page->id ?>][r]" <?= $r ?>>
                                                        Read
                                                    </label>
                                                    <label class="btn btn-outline-secondary">
                                                        <input type="checkbox"
                                                               name="perms[<?= (string)$page->id ?>][u]" <?= $u ?>>
                                                        Update
                                                    </label>
                                                    <label class="btn btn-outline-secondary">
                                                        <input type="checkbox"
                                                               name="perms[<?= (string)$page->id ?>][d]" <?= $d ?>>
                                                        Delete
                                                    </label>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="col-md-12">
                    <button class="btn btn-success float-right"><?=lang('Backend.add')?></button>
                </div>
            </form>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->

</section>
<!-- /.content -->
<?= $this->endSection() ?>
