<?php if (session()->has('error')) : ?>
    <script {csp-script-nonce}>
        Swal.fire({
            title: "<?php echo lang('Backend.error') ?> !",
            text: '<?php echo session('error') ?>',
            icon: 'error',
        });
    </script>
<?php endif;
if (session()->has('errors')) : ?>
    <script {csp-script-nonce}>
        Swal.fire({
            icon: 'error',
            title: "<?php echo lang('Backend.error') ?> !",
            html: '<ul class="alert alert-danger list-unstyled">' +
                <?php foreach (session('errors') as $error) : ?> '<li><?php echo $error ?></li>' +
                <?php endforeach ?> '</ul>',

        });
    </script>
<?php endif;
if (session()->has('message')) : ?>
    <script {csp-script-nonce}>
        Swal.fire({
            title: "<?php echo lang('Backend.success') ?>",
            text: '<?php echo session('message') ?>',
            icon: 'success',
        });
    </script>
<?php endif;
if (session()->has('messages')) : ?>
    <script {csp-script-nonce}>
        Swal.fire({
            title: "<?php echo lang('Backend.success') ?>",
            icon: 'success',
            html: '<ul class="alert alert-success list-unstyled">' +
                <?php foreach (session('messages') as $message) : ?> '<li><?php echo $message ?></li>' +
                <?php endforeach ?> '</ul>',
        });
    </script>
<?php endif;
if (session()->has('warning')) : ?>
    <script {csp-script-nonce}>
        Swal.fire({
            title: "<?php echo lang('Backend.warning') ?> !",
            text: '<?php echo session('warning') ?>',
            icon: 'warning',
        });
    </script>
<?php endif ?>
