<?php if (session()->has('error')) : ?>
    <script {csp-script-nonce}>
        Swal.fire({
            title: "<?php echo lang('Backend.error') ?> !",
            text: '<?php echo session('error') ?>',
            icon: 'error'
        });
    </script>
<?php endif ?>

<?php if (session()->has('errors')) : ?>
    <script {csp-script-nonce}>
        Swal.fire({
            icon: 'error',
            title: "<?php echo lang('Backend.error') ?> !",
            html: '<ul class="alert alert-danger list-unstyled">' +
                <?php foreach (session('errors') as $error) : ?> '<li><?php echo $error ?></li>' +
                <?php endforeach ?> '</ul>',

        });
    </script>
<?php endif ?>
<?php if (session()->has('message')) : ?>
    <script {csp-script-nonce}>
        Swal.fire({
            title: "<?php echo lang('Backend.success') ?>",
            text: '<?php echo session('message') ?>',
            icon: 'success'
        });
    </script>
<?php endif ?>

<?php if (session()->has('warning')) : ?>
    <script {csp-script-nonce}>
        Swal.fire({
            title: "<?php echo lang('Backend.warning') ?> !",
            text: '<?php echo session('warning') ?>',
            icon: 'warning'
        });
    </script>
<?php endif ?>
