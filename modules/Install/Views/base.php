<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noimageindex, nofollow, nosnippet">

    <title>Install Ci4MS <?= getGitVersion() ?></title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <?= link_tag("be-assets/plugins/fontawesome-free/css/all.min.css") ?>
    <!-- Theme style -->
    <?= link_tag("be-assets/css/adminlte.min.css") ?>
    <?= link_tag("be-assets/custom.css") ?>
    <?= csrf_meta() ?>
    <?= $this->renderSection('head') ?>
</head>

<body class="hold-transition sidebar-mini">
    <!-- Site wrapper -->
    <div class="wrapper">

        <!-- Content Wrapper. Contains page content -->
        <div class="container mt-5 pt-5 mb-5">
            <?= $this->renderSection('content') ?>
            <div class="row p-3 bg-success">
                <div class="col-12">
                    <div class="float-right d-none d-sm-block">
                        <b>Version</b> <?= getGitVersion() ?>
                    </div>
                    <strong>Copyright &copy; <?= date('Y') ?>.</strong> All rights reserved.
                </div>
            </div>
        </div>
        <!-- /.content-wrapper -->
    </div>
    <!-- ./wrapper -->

    <!-- jQuery -->
    <?= script_tag("be-assets/plugins/jquery/jquery.min.js") ?>
    <!-- Bootstrap 4 -->
    <?= script_tag("be-assets/plugins/bootstrap/js/bootstrap.bundle.min.js") ?>
    <!-- AdminLTE App -->
    <?= script_tag("be-assets/js/adminlte.min.js") ?>
    <!-- AdminLTE for demo purposes -->
    <?= script_tag("be-assets/js/demo.js") ?>
    <?= $this->renderSection('javascript') ?>
</body>

</html>
