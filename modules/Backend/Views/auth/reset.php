<?= $this->extend($config->viewLayout) ?>
<?= $this->section('head') ?>
<title><?=$config->vers?> | Şifremi Alma</title>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="login-box">
    <div class="card card-outline cbtn-success">
        <div class="card-header text-center">
            <img src="<?=base_url('be-assets/img/kunduz-yazilim-logo.png')?>" alt="" class="img-fluid">
        </div>
        <div class="card-body">
            <p class="login-box-msg">Yeni şifrenizden sadece bir adım uzaktasınız, şifrenizi şimdi oluşturun.</p>
            <?= view('Modules\Auth\Views\_message_block') ?>
            <form action="<?=route_to('reset-password',$token)?>" method="post">
                <?=csrf_field()?>

                <div class="form-group">
                    <label for="email"><?=lang('Auth.email')?></label>
                    <input type="email" class="form-control <?php if(session('errors.email')) : ?>is-invalid<?php endif ?>"
                           name="email" aria-describedby="emailHelp" placeholder="<?=lang('Auth.email')?>" value="<?= old('email') ?>">
                    <div class="invalid-feedback">
                        <?= session('errors.email') ?>
                    </div>
                </div>

                <br>

                <div class="form-group">
                    <label for="password"><?=lang('Auth.newPassword')?></label>
                    <input type="password" class="form-control <?php if(session('errors.password')) : ?>is-invalid<?php endif ?>"
                           name="password">
                    <div class="invalid-feedback">
                        <?= session('errors.password') ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="pass_confirm"><?=lang('Auth.newPasswordRepeat')?></label>
                    <input type="password" class="form-control <?php if(session('errors.pass_confirm')) : ?>is-invalid<?php endif ?>"
                           name="pass_confirm">
                    <div class="invalid-feedback">
                        <?= session('errors.pass_confirm') ?>
                    </div>
                </div>

                <br>

                <button type="submit" class="btn btn-success btn-block"><?=lang('Auth.resetPassword')?></button>
            </form>

            <p class="mt-3 mb-1">
                <a href="<?=base_url('backend/login')?>"><i class="fas fa-arrow-left"></i> Giriş Yap</a>
            </p>
        </div>
        <!-- /.login-card-body -->
    </div>
</div>
<?= $this->endSection() ?>
