<?php if (session()->has('message')) : ?>
    <div class="alert alert-success">
        <?php echo session('message') ?>
    </div>
<?php endif ?>

<?php if (session()->has('warning')) : ?>
    <div class="alert alert-warning">
        <?php echo session('warning') ?>
    </div>
<?php endif ?>

<?php if (session()->has('error')) : ?>
    <div class="alert alert-danger">
        <?php echo session('error') ?>
    </div>
<?php endif ?>

<?php if (session()->has('errors')) : ?>
    <ul class="alert alert-danger list-unstyled">
        <?php foreach (session('errors') as $error) : ?>
            <li><?php echo esc($error) ?></li>
        <?php endforeach ?>
    </ul>
<?php endif ?>
