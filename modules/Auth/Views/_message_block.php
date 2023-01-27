<?php if (session()->has('message')) : ?>
	<div class="alert alert-success">
		<?= session('message') ?>
	</div>
<?php endif ?>

<?php if (session()->has('warning')) : ?>
    <div class="alert alert-warning">
        <?= session('warning') ?>
    </div>
<?php endif ?>

<?php if (session()->has('error')) : ?>
	<div class="alert alert-danger">
		<?= session('error') ?>
	</div>
<?php endif ?>

<?php if (session()->has('errors')) : ?>
	<ul class="alert alert-danger list-unstyled">
	<?php foreach (session('errors') as $error) : ?>
		<li><?= $error ?></li>
	<?php endforeach ?>
	</ul>
<?php endif ?>
