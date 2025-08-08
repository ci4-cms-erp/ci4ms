<?php if (session()->has('error')) : ?>
    Swal.fire({
        title: "HATA !",
        text: '<?= session('error') ?>',
        icon: 'error'
    });
<?php endif ?>

<?php if (session()->has('errors')) : ?>
    Swal.fire({
        title: "HATA !",
        html: '<ul class="alert alert-danger list-unstyled">' +
            <?php foreach (session('errors') as $error) : ?> '<li><?= $error ?></li>' +
            <?php endforeach ?> '</ul>',
        icon: 'error'
    });
<?php endif ?>
<?php if (session()->has('message')) : ?>
    Swal.fire({
        title: "BAŞARILI",
        text: '<?= session('message') ?>',
        icon: 'success'
    });
<?php endif ?>

<?php if (session()->has('warning')) : ?>
    Swal.fire({
        title: "UYARI !",
        text: '<?= session('warning') ?>',
        icon: 'warning'
    });
<?php endif ?>
