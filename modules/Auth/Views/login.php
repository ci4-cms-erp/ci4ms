<?php echo $this->extend(config('Auth')->views['layout']) ?>
<?php echo $this->section('head') ?>
<title><?php echo lang('Auth.login') ?> | Ci4MS - <?php echo getenv('app.version') ?></title>
<?php echo $this->endSection() ?>
<?php echo $this->section('content') ?>
<div class="login-box">
    <!-- /.login-logo -->
    <div class="card card-outline card-success shadow-sm">
        <div class="card-header text-center">
            <img src="<?php echo base_url('be-assets/img/bfo-logo.jpg') ?>" alt="" class="img-fluid">
        </div>
        <div class="card-body">
            <p class="login-box-msg"><?php echo lang('Auth.loginMessage') ?></p>
            <?php echo view('Modules\Auth\Views\_message_block') ?>
            <form action="<?php echo route_to('login') ?>" method="post">
                <?php echo csrf_field() ?>
                <div class="input-group mb-3">
                    <input type="email" name="email" class="form-control" placeholder="<?php echo lang('Auth.email') ?>" value="<?php echo old('email') ?>" required
                        autofocus>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-envelope"></span>
                        </div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="password" name="password" class="form-control" required placeholder="<?php echo lang('Auth.password') ?>">
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-lock"></span>
                        </div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <img src="<?php echo $cap->inline(); ?>" />
                    <input type="text" name="captcha" class="form-control" required placeholder="Doğrulama kodu">
                </div>
                <div class="row">
                    <div class="col-md-8">
                        <?php if (setting('Auth.sessionConfig')['allowRemembering']): ?>
                            <div class="icheck-success">
                                <input type="checkbox" name="remember" id="remember">
                                <label for="remember">
                                    <?php echo lang('Auth.rememberMe') ?>
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>
                    <!-- /.col -->
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-success btn-block"><?php echo lang('Auth.loginAction') ?></button>
                    </div>
                    <!-- /.col -->
                </div>
            </form>
            <hr>
            <?php if (setting('Auth.allowMagicLinkLogins')) : ?>
                <p class="text-center"><?php echo lang('Auth.forgotPassword') ?> <a href="<?php echo url_to('magic-link') ?>"><?php echo lang('Auth.useMagicLink') ?></a></p>
            <?php endif ?>

            <?php if (setting('Auth.allowRegistration')) : ?>
                <p class="text-center"><?php echo lang('Auth.needAccount') ?> <a href="<?php echo url_to('register') ?>"><?php echo lang('Auth.register') ?></a></p>
            <?php endif ?>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->
</div>
<?php echo $this->endSection() ?>
