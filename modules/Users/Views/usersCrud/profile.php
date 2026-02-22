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

                </ol>
            </div>
        </div>
    </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">
    <div class="card card-outline card-shl">
        <div class="card-body">
            <form action="<?php echo route_to('profile') ?>" method="post" class="form-row" enctype="multipart/form-data">
                <?php echo csrf_field() ?>
                <div class="col-md-6">
                    <div class="text-center mb-3">
                        <div class="position-relative d-inline-block">
                            <img id="profileImagePreview"
                                src="<?php echo esc($user->profileIMG) ?>"
                                alt="Profil Resmi"
                                class="img-circle elevation-2"
                                style="width: 150px; height: 150px; object-fit: cover; border: 3px solid #dee2e6;">
                            <label for="avatarInput"
                                class="btn btn-sm btn-outline-secondary position-absolute"
                                style="bottom: 5px; right: 5px; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; cursor: pointer; background: #fff;">
                                <i class="fas fa-camera"></i>
                            </label>
                        </div>
                        <input type="file" id="avatarInput" name="profileIMG" accept="image/jpeg,image/png,image/webp"
                            class="d-none">
                        <div class="mt-2">
                            <small class="text-muted">JPG, PNG veya WEBP — Maks. 2MB — 150x150px</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for=""><?php echo lang('Backend.fullName') ?> <?php echo lang('Backend.required') ?></label>
                        <div class="input-group">
                            <input type="text" aria-label="<?php echo lang('Backend.name') ?>" name="firstname" value="<?php echo old('firstname', esc($user->firstname)) ?>" class="form-control" placeholder="<?php echo lang('Backend.name') ?>"
                                required>
                            <input type="text" aria-label="<?php echo lang('Backend.surname') ?>" name="surname" value="<?php echo old('surname', esc($user->surname)) ?>" class="form-control"
                                placeholder="<?php echo lang('Backend.surname') ?>" required>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for=""><?php echo lang('Backend.email') ?> <?php echo lang('Backend.required') ?></label>
                        <input type="email" name="email" class="form-control" value="<?php echo old('email', esc($user->email)) ?>" required>
                        <small class="text-info"><?php echo lang('Backend.profileUpdateEmail') ?></small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for=""><?php echo lang('Auth.password') ?> <?php echo lang('Backend.takeNotePassword') ?></label>
                        <input type="text" class="form-control" name="password" minlength="8">
                    </div>
                </div>
                <div class="col-md-12">
                    <button class="btn btn-outline-success float-right"><?php echo lang('Backend.update') ?></button>
                </div>
            </form>
        </div>
    </div>
</section>
<?php echo $this->endSection() ?>
<?php echo $this->section('javascript') ?>
<script>
    document.getElementById('avatarInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) {
            alert('Dosya boyutu 2MB\'dan büyük olamaz.');
            this.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = function(ev) {
            document.getElementById('profileImagePreview').src = ev.target.result;
        };
        reader.readAsDataURL(file);
    });
</script>
<?php echo $this->endSection() ?>
