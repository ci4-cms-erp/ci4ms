<?php echo $this->extend(config('Auth')->views['layout']);
echo $this->section('title');
echo lang('Auth.useMagicLink') . ' | Ci4MS - ' . getenv('app.version');
echo $this->endSection();
echo $this->section('content') ?>
<div class="login-box">
    <div class="card card-outline card-success shadow-sm">
        <div class="card-body">
            <p class="login-box-msg"><?php echo lang('Auth.useMagicLink') ?></p>

            <p><b><?php echo lang('Auth.checkYourEmail') ?></b></p>

            <p><?php echo lang('Auth.magicLinkDetails', [setting('Auth.magicLinkLifetime') / 60]) ?></p>
        </div>
    </div>
</div>
<?php echo $this->endSection() ?>
