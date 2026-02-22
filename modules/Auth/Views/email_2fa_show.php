<?php

use CodeIgniter\Shield\Entities\User;

?>

<?php echo $this->extend(config('Auth')->views['layout']) ?>

<?php echo $this->section('title') ?><?php echo lang('Auth.email2FATitle') ?> | Ci4MS - <?php echo getenv('app.version') ?> <?php echo $this->endSection() ?>

<?php echo $this->section('content') ?>

<div class="login-box">
    <div class="card card-outline card-success shadow-sm">
        <div class="card-body">
            <p class="login-box-msg"><?php echo lang('Auth.email2FATitle') ?></p>

            <p><?php echo lang('Auth.confirmEmailAddress') ?></p>

            <?php if (session('error')) : ?>
                <div class="alert alert-danger"><?php echo esc(session('error')) ?></div>
            <?php endif ?>

            <form action="<?php echo url_to('auth-action-handle') ?>" method="post">
                <?php echo csrf_field() ?>

                <!-- Email -->
                <div class="mb-2">
                    <input type="email" class="form-control" name="email"
                        inputmode="email" autocomplete="email" placeholder="<?php echo lang('Auth.email') ?>"
                        <?php /** @var User $user */ ?>
                        value="<?php echo old('email', $user->email) ?>" required>
                </div>

                <div class="d-grid col-8 mx-auto m-3">
                    <button type="submit" class="btn btn-success btn-block"><?php echo lang('Auth.send') ?></button>
                </div>

            </form>
        </div>
    </div>
</div>

<?php echo $this->endSection() ?>
