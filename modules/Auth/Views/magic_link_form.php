<?php echo $this->extend(config('Auth')->views['layout']) ?>

<?php echo $this->section('title') ?><?php echo lang('Auth.useMagicLink') ?> | Ci4MS - <?php echo getenv('app.version') ?> <?php echo $this->endSection() ?>

<?php echo $this->section('content') ?>

<div class="login-box">
    <div class="card card-outline card-success shadow-sm">
        <div class="card-body">
            <p class="login-box-msg"><?php echo lang('Auth.useMagicLink') ?></p>

            <?php echo view('Modules\Auth\Views\_message_block') ?>

            <form action="<?php echo url_to('magic-link') ?>" method="post">
                <?php echo csrf_field() ?>
                <div class="row">
                    <!-- Email -->
                    <div class="col-12">
                        <input type="email" class="form-control" id="floatingEmailInput" name="email" autocomplete="email" placeholder="<?php echo lang('Auth.email') ?>"
                            value="<?php echo old('email', auth()->user()->email ?? null) ?>" required>
                    </div>

                    <div class="col-12">
                        <button type="submit" class="btn btn-success btn-block"><?php echo lang('Auth.send') ?></button>
                    </div>
                </div>
            </form>

            <p class="text-center"><a href="<?php echo url_to('login') ?>" class="btn btn-light btn-block"><i class="fas fa-arrow-circle-left"></i> <?php echo lang('Auth.backToLogin') ?></a></p>
        </div>
    </div>
</div>

<?php echo $this->endSection() ?>
