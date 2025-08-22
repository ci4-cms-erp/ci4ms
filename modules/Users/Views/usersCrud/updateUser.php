<?= $this->extend('Modules\Backend\Views\base') ?>

<?= $this->section('title') ?>
<?=lang($title->pagename)?>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row pb-3 border-bottom">
            <div class="col-sm-6">
                <h1><?=lang($title->pagename)?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <a href="<?= route_to('users',1) ?>" class="btn btn-outline-info">
                        <?=lang('Backend.backToList')?></a>
                </ol>
            </div>
        </div>
    </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">
    <div class="card card-outline card-shl">
        <div class="card-body">
            <?= view('Modules\Auth\Views\_message_block') ?>
        <form action="<?=route_to('update_user',$userInfo->id)?>" method="post" class="form-row">
            <?= csrf_field() ?>
            <div class="col-md-6">
                <div class="form-group">
                    <label for=""><?=lang('Backend.fullName')?> <?=lang('Backend.required')?></label>
                    <div class="input-group">
                        <input type="text" aria-label="<?=lang('Backend.name')?>" name="firstname" value="<?=$userInfo->firstname?>" class="form-control" placeholder="<?=lang('Backend.name')?>" required>
                        <input type="text" aria-label="<?=lang('Backend.sirname')?>" name="sirname" value="<?=$userInfo->sirname?>" class="form-control" placeholder="<?=lang('Backend.sirname')?>" required>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for=""><?=lang('Backend.email')?> <?=lang('Backend.required')?></label>
                    <input type="email" name="email" class="form-control" value="<?=$userInfo->email?>" required>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for=""><?=lang('Backend.authority')?> <?=lang('Backend.required')?></label>
                    <select name="group" class="form-control" required>
                        <option value=""><?=lang('Backend.select')?></option>
                        <?php foreach($groups as $group):
                            if ($group->name != 'super user'): ?>
                            <option value="<?=$group->id?>" <?=((int)$group->id==(int)$userInfo->group_id)?'selected':''?>><?=$group->name?></option>
                        <?php endif;
                        endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="col-md-6">
                <div class="form-group">
                    <label for=""><?=lang('Backend.password')?> <?=lang('Backend.takeNotePassword')?></label>
                    <input type="text" class="form-control" name="password" minlength="8" value="">
                </div>
            </div>
            <div class="col-md-12">
                <button class="btn btn-outline-success float-right"><?=lang('Backend.update')?></button>
            </div>
        </form>
        </div>
    </div>
</section>

<!-- /.content -->
<?= $this->endSection() ?>
