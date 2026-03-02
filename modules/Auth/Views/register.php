<?php echo $this->extend(config('Auth')->views['layout']) ?>

<?php echo $this->section('title') ?><?php echo lang('Auth.register') ?> | Ci4MS - <?php echo getenv('app.version') ?> <?php echo $this->endSection() ?>

<?php echo $this->section('content') ?>

<div class="register-box">
    <div class="card card-outline card-success shadow-sm">
        <div class="card-body register-card-body">
            <p class="login-box-msg"><?php echo lang('Auth.register') ?></p>

            <?php echo view('Modules\Auth\Views\_message_block') ?>

            <form action="<?php echo url_to('register') ?>" method="post">
                <?php echo csrf_field() ?>

                <!-- Email -->
                <div class="form-floating mb-2">
                    <input type="email" class="form-control" id="floatingEmailInput" name="email" inputmode="email" autocomplete="email" placeholder="<?php echo lang('Auth.email') ?>" value="<?php echo old('email') ?>" required>
                </div>

                <!-- Username -->
                <div class="form-floating mb-4">
                    <input type="text" class="form-control" id="floatingUsernameInput" name="username" inputmode="text" autocomplete="username" placeholder="<?php echo lang('Auth.username') ?>" value="<?php echo old('username') ?>" required>
                </div>

                <!-- Password -->
                <div class="form-floating mb-2">
                    <input type="password" class="form-control" id="floatingPasswordInput" name="password" inputmode="text" autocomplete="new-password" placeholder="<?php echo lang('Auth.password') ?>" required>
                </div>

                <!-- Password (Again) -->
                <div class="form-floating mb-5">
                    <input type="password" class="form-control" id="floatingPasswordConfirmInput" name="password_confirm" inputmode="text" autocomplete="new-password" placeholder="<?php echo lang('Auth.passwordConfirm') ?>" required>
                </div>

                <div class="d-grid col-12 col-md-8 mx-auto m-3">
                    <button type="submit" class="btn btn-success btn-block"><?php echo lang('Auth.register') ?></button>
                </div>

                <p class="text-center"><?php echo lang('Auth.haveAccount') ?> <a href="<?php echo url_to('login') ?>"><i class="fas fa-arrow-circle-left"></i> <?php echo lang('Auth.backToLogin') ?></a></p>

            </form>
        </div>
    </div>
</div>

<?php echo $this->endSection() ?>
