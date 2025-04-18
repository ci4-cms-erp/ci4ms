<?= $this->extend($config->viewLayout) ?>
<?= $this->section('head') ?>
<title><?= $config->vers ?> | Giriş Yap</title>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="login-box">
    <!-- /.login-logo -->
    <div class="card card-outline card-success">
        <div class="card-header text-center">
            <img src="<?=base_url('be-assets/img/kunduz-yazilim-logo.png')?>" alt="" class="img-fluid">
        </div>
        <div class="card-body">
            <p class="login-box-msg">Oturumunuzu başlatmak için giriş yapın</p>
            <?= view('Modules\Auth\Views\_message_block') ?>
            <form action="<?= route_to('login') ?>" method="post">
                <?= csrf_field() ?>
                <div class="input-group mb-3">
                    <input type="email" name="email" class="form-control" placeholder="Email" required
                           autofocus>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-envelope"></span>
                        </div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="password" name="password" class="form-control" required placeholder="Password">
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-lock"></span>
                        </div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <img src="<?php echo $cap->inline(); ?>"/>
                    <input type="text" name="captcha" class="form-control" required placeholder="Doğrulama kodu">
                </div>
                <div class="row">
                    <div class="col-md-8">
                        <div class="icheck-success">
                            <input type="checkbox" name="remember" id="remember">
                            <label for="remember">
                                <?=lang('Auth.rememberMe')?>
                            </label>
                        </div>
                    </div>
                    <!-- /.col -->
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-success btn-block"><?=lang('Auth.loginAction')?></button>
                    </div>
                    <!-- /.col -->
                </div>
            </form>
            <hr>
            <p class="mb-1">
                <a href="<?= route_to('backend/forgot') ?>"><?=lang('Auth.forgotPassword')?></a>
            </p>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->
</div>
<?= $this->endSection() ?>
