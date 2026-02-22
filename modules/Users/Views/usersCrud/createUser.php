<?php echo $this->extend('Modules\Backend\Views\base') ?>

<?php echo $this->section('title') ?>
<?php echo lang($title->pagename) ?>
<?php echo $this->endSection() ?>
<?php echo $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row pb-3 border-bottom">
            <div class="col-sm-6">
                <h1><?php echo lang($title->pagename) ?></h1>
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
    <div class="card card-outline card-shl">
        <div class="card-body">
            <form action="<?php echo route_to('create_user') ?>" method="post" class="form-row">
                <?php echo csrf_field() ?>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for=""><?php echo lang('Backend.fullName') ?> <?php echo lang('Backend.required') ?></label>
                        <div class="input-group">
                            <input type="text" aria-label="<?php echo lang('Backend.name') ?>" name="firstname" class="form-control" placeholder="<?php echo lang('Backend.name') ?>"
                                value="<?php echo old('firstname') ?>" required>
                            <input type="text" aria-label="<?php echo lang('Backend.surname') ?>" name="surname" class="form-control"
                                placeholder="<?php echo lang('Backend.surname') ?>" value="<?php echo old('surname') ?>" required>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for=""><?php echo lang('Backend.email') ?> <?php echo lang('Backend.required') ?></label>
                        <input type="email" name="email" class="form-control" value="<?php echo old('email') ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for=""><?php echo lang('Users.authority') ?> <?php echo lang('Backend.required') ?></label>
                        <select name="group" class="form-control" required>
                            <option value=""><?php echo lang('Backend.select') ?></option>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?php echo $group->id ?>" <?php echo set_select('group', $group->id) ?>><?php echo esc($group->group) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for=""><?php echo lang('Auth.username') ?> <?php echo lang('Backend.required') ?></label>
                        <input type="text" class="form-control" name="username" minlength="3" maxlength="30"
                            value="<?php echo old('username') ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for=""><?php echo lang('Auth.password') ?> <?php echo lang('Backend.takeNotePassword') ?></label>
                        <input type="text" class="form-control" name="password" minlength="8"
                            value="<?php echo old('password', randomPassword()) ?>" required>
                    </div>
                </div>
                <div class="col-md-12">
                    <button class="btn btn-outline-success float-right"><?php echo lang('Backend.add') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>

<!-- /.content -->
<?php echo $this->endSection() ?>
