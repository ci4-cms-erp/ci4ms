<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('title');
echo lang($title->pagename);
echo $this->endSection();
echo $this->section('head');
echo link_tag('be-assets/plugins/select2/css/select2.min.css');
echo link_tag('be-assets/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css');
echo $this->endSection();
echo $this->section('content'); ?>
<section class="content-header">
    <div class="container-fluid">
        <div class="row pb-3 border-bottom">
            <div class="col-sm-6">
                <h1><?php echo lang($title->pagename) ?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <a href="<?php echo route_to('users', 1) ?>" class="btn btn-sm btn-outline-info">
                        <?php echo lang('Backend.backToList') ?></a>
                </ol>
            </div>
        </div>
    </div><!-- /.container-fluid -->
</section>
<!-- Main content -->
<section class="content">
    <div class="card card-outline shadow-sm">
        <div class="card-body">
            <form action="<?php echo route_to('update_user', $userInfo->id) ?>" method="post" class="form-row">
                <?php echo csrf_field() ?>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for=""><?php echo lang('Backend.fullName') ?> <?php echo lang('Backend.required') ?></label>
                        <div class="input-group">
                            <input type="text" aria-label="<?php echo lang('Backend.name') ?>" name="firstname" value="<?php echo old('firstname', esc($userInfo->firstname)) ?>" class="form-control" placeholder="<?php echo lang('Backend.name') ?>" required>
                            <input type="text" aria-label="<?php echo lang('Backend.surname') ?>" name="surname" value="<?php echo old('surname', esc($userInfo->surname)) ?>" class="form-control" placeholder="<?php echo lang('Backend.surname') ?>" required>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for=""><?php echo lang('Backend.email') ?> <?php echo lang('Backend.required') ?></label>
                        <input type="email" name="email" class="form-control" value="<?php echo old('email', esc($userInfo->email)) ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for=""><?php echo lang('Users.authority') ?> <?php echo lang('Backend.required') ?></label>
                        <select name="group[]" class="form-control select2bs4" multiple="multiple" data-placeholder="<?php echo lang('Backend.selectOption', [lang('Users.authority')]) ?>" required>
                            <option value=""><?php echo lang('Backend.select') ?></option>
                            <?php foreach ($groups as $group): ?>
                                <option value="<?php echo $group->id ?>" <?php echo set_select('group', $group->id, $userInfo->inGroup($group->group)) ?>><?php echo $group->group ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for=""><?php echo lang('Auth.username') ?> <?php echo lang('Backend.required') ?></label>
                        <input type="text" class="form-control" name="username" minlength="3" maxlength="30"
                            value="<?php echo old('username', esc($userInfo->username)) ?>" required>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for=""><?php echo lang('Auth.password') ?> <?php echo lang('Backend.takeNotePassword') ?></label>
                        <input type="text" class="form-control" name="password" minlength="8" value="">
                    </div>
                </div>
                <div class="col-md-12">
                    <button class="btn btn-outline-success float-right"><?php echo lang('Backend.update') ?></button>
                </div>
            </form>
        </div>
    </div>
</section>

<!-- /.content -->
<?php echo $this->endSection();
echo $this->section('javascript');
echo script_tag("be-assets/plugins/select2/js/select2.full.min.js"); ?>
<script>
    $('.select2bs4').select2({
        theme: 'bootstrap4'
    });
</script>
<?php echo $this->endSection(); ?>
