<?php if (session()->has('error')) : ?>
    <script>
        Swal.fire({
            title: "HATA !",
            text: '<?= session('error') ?>',
            icon: 'error'
        });
    </script>
<?php endif ?>

<?php if (session()->has('errors')) : ?>
    <script>
        Swal.fire({
                    title: "HATA !",
                    html: '<ul class="alert alert-danger list-unstyled">' +
                        <?php foreach (session('errors') as $error) : ?> '<li><?= $error ?></li>' +
    </script> <?php endforeach ?> '</ul>',
icon: 'error'
});
<?php endif ?>
<?php if (session()->has('message')) : ?>
    <script>
        Swal.fire({
            title: "BAŞARILI",
            text: '<?= session('message') ?>',
            icon: 'success'
        });
    </script>
<?php endif ?>

<?php if (session()->has('warning')) : ?>
    <script>
        Swal.fire({
            title: "UYARI !",
            text: '<?= session('warning') ?>',
            icon: 'warning'
        });
    </script>
<?php endif ?>
