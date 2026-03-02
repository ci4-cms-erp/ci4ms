<?php echo $this->extend(config('Auth')->views['layout']) ?>

<?php echo $this->section('title') ?><?php echo lang('Auth.email2FATitle') ?> | Ci4MS - <?php echo getenv('app.version') ?> <?php echo $this->endSection() ?>

<?php echo $this->section('content') ?>

<div class="login-box">
    <div class="card card-outline card-success shadow-sm">
        <div class="card-body">
            <p class="login-box-msg5"><?php echo lang('Auth.emailEnterCode') ?></p>
            <?php echo view('Modules\Auth\Views\_message_block') ?>
            <p><?php echo lang('Auth.emailConfirmCode') ?></p>

            <form action="<?php echo url_to('auth-action-verify') ?>" method="post">
                <?php echo csrf_field() ?>

                <!-- Code -->
                <div class="mb-2">
                    <input type="number" class="form-control" name="token" placeholder="000000"
                        inputmode="numeric" pattern="[0-9]*" autocomplete="one-time-code" required>
                </div>

                <div class="d-grid col-8 mx-auto m-3">
                    <button type="submit" class="btn btn-success btn-block"><?php echo lang('Auth.confirm') ?></button>
                </div>

            </form>
        </div>
    </div>
</div>

<?php echo $this->endSection() ?>
