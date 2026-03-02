<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noimageindex, nofollow, nosnippet">

    <?php echo link_tag("be-assets/img/favicon.ico", "shortcut icon", "image/x-icon") ?>
    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <?php echo link_tag("be-assets/plugins/fontawesome-free/css/all.min.css") ?>
    <!-- Theme style -->
    <?php echo link_tag("be-assets/css/adminlte.min.css") ?>
    <?php echo link_tag("be-assets/custom.css") ?>

    <?php echo $this->renderSection('head') ?>
</head>

<body class="hold-transition login-page">
    <?php echo $this->renderSection('content') ?>
    </div>
    <!-- ./wrapper -->

    <!-- jQuery -->
    <?php echo script_tag("be-assets/plugins/jquery/jquery.min.js") ?>
    <!-- Bootstrap 4 -->
    <?php echo script_tag("be-assets/plugins/bootstrap/js/bootstrap.bundle.min.js") ?>
    <!-- AdminLTE App -->
    <?php echo script_tag("be-assets/js/adminlte.min.js") ?>
    <!-- AdminLTE for demo purposes -->
    <?php echo script_tag("be-assets/js/demo.js") ?>

    <?php echo $this->renderSection('javascript') ?>
</body>

</html>
