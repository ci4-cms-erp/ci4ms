<?= $this->extend($config->viewLayout) ?>
<?= $this->section('head') ?>
    <title><?=$config->vers?> | Şifremi Unuttum</title>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<div class="login-box">
    <div class="card card-outline card-success">
        <div class="card-header text-center">
            <img src="<?=base_url('be-assets/img/kunduz-yazilim-logo.png')?>" alt="" class="img-fluid">
        </div>
        <div class="card-body">
            <p class="login-box-msg">Şifrenizi mi unuttunuz? Burada e-mail adresiniz ile kolayca yeni bir şifre alabilirsiniz.</p>
            <?= view('Modules\Auth\Views\_message_block') ?>
            <form action="<?=route_to('forgot')?>" method="post">
                <?=csrf_field()?>
                <div class="input-group mb-3">
                    <input type="email" class="form-control" name="email" placeholder="Email">
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-envelope"></span>
                        </div>
                    </div>
                    <div class="invalid-feedback">
                        <?= session('errors.email') ?>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <button type="submit" class="btn btn-success btn-block">Yeni şifre iste</button>
                    </div>
                    <!-- /.col -->
                </div>
            </form>
            <p class="mt-3 mb-1">
                <a href="<?=route_to('backend/login')?>"><i class="fas fa-arrow-left"></i> Giriş Yap</a>
            </p>
        </div>
        <!-- /.login-card-body -->
    </div>
</div>
<?= $this->endSection() ?>
