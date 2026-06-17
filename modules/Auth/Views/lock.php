<?php echo $this->extend(config('Auth')->views['layout']);
echo $this->section('head');
echo '<title>' . lang('Auth.screenLocked') . ' | Ci4MS - ' . getenv('app.version') . '</title>';
echo $this->endSection();
echo $this->section('content'); ?>
<div class="login-box">
    <div class="card card-outline card-primary shadow-sm">
        <div class="card-header text-center">
            <span class="h4 mb-0"><i class="fas fa-lock mr-2"></i><?php echo lang('Auth.screenLocked') ?></span>
        </div>
        <div class="card-body">
            <div class="text-center mb-3">
                <?php if (! empty($user->profileIMG) && $user->profileIMG !== '/be-assets/img/no-avatar.png'): ?>
                    <img src="<?php echo esc($user->profileIMG) ?>" alt="<?php echo lang('Auth.email') ?>"
                        width="80" height="80" class="img-circle elevation-2">
                <?php else: ?>
                    <i class="fas fa-user-circle fa-4x text-muted"></i>
                <?php endif ?>
                <h5 class="mt-2 mb-0"><?php echo esc(trim(($user->firstname ?? '') . ' ' . ($user->surname ?? ''))) ?></h5>
                <small class="text-muted"><?php echo esc($user->email) ?></small>
            </div>

            <p class="login-box-msg pt-0"><?php echo lang('Auth.enterPasswordToUnlock') ?></p>
            <?php echo view('Modules\Auth\Views\_message_block') ?>

            <form action="<?php echo base_url('backend/lock') ?>" method="post">
                <?php echo csrf_field() ?>
                <input type="hidden" name="redirect" value="<?php echo esc($redirect) ?>">
                <div class="input-group mb-3">
                    <input type="password" name="password" id="lockPassword" class="form-control"
                        placeholder="<?php echo lang('Auth.password') ?>" required autofocus>
                    <div class="input-group-append">
                        <button type="button" id="togglePassword" class="btn btn-outline-secondary">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-unlock-alt mr-1"></i><?php echo lang('Auth.unlockScreen') ?>
                </button>
            </form>
            <hr>
            <p class="text-center mb-0">
                <a href="<?php echo route_to('lock-switch') ?>">
                    <i class="fas fa-user-circle mr-1"></i><?php echo lang('Auth.switchAccount') ?>
                </a>
            </p>
        </div>
    </div>
    <p class="text-center text-muted small">
        <i class="fas fa-info-circle mr-1"></i><?php echo lang('Auth.lockScreenInfo') ?>
    </p>
</div>
<?php echo $this->endSection();
echo $this->section('javascript'); ?>
<script>
    document.getElementById('togglePassword').addEventListener('click', function () {
        const input = document.getElementById('lockPassword');
        const icon = this.querySelector('i');
        const isHidden = input.type === 'password';
        input.type = isHidden ? 'text' : 'password';
        icon.classList.toggle('fa-eye', !isHidden);
        icon.classList.toggle('fa-eye-slash', isHidden);
    });
</script>
<?php echo $this->endSection() ?>
