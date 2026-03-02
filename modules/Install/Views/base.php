<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noimageindex, nofollow, nosnippet">

    <title>Install | Ci4MS - <?php echo getenv('app.version') ?></title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="/be-assets/plugins/fontawesome-free/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="/be-assets/css/adminlte.min.css">
    <link rel="stylesheet" href="/be-assets/custom.css">
    <?php echo csrf_meta() ?>
    <?php echo $this->renderSection('head') ?>
</head>

<body class="hold-transition sidebar-mini">
    <!-- Site wrapper -->
    <div class="wrapper">

        <!-- Content Wrapper. Contains page content -->
        <div class="container mt-5 pt-5 mb-5">
            <?php echo $this->renderSection('content') ?>
            <div class="row p-3 bg-success">
                <div class="col-12">
                    <div class="float-right d-none d-sm-block">
                        <b>Version</b> <?php echo getGitVersion() ?>
                    </div>
                    <strong>Copyright &copy; <?php echo date('Y') ?>.</strong> All rights reserved.
                </div>
            </div>
        </div>
        <!-- /.content-wrapper -->
    </div>
    <!-- ./wrapper -->

    <!-- jQuery -->
    <script src="/be-assets/plugins/jquery/jquery.min.js"></script>
    <script src="/be-assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
    <script src="/be-assets/js/adminlte.min.js"></script>
    <script src="/be-assets/js/demo.js"></script>
    <?php echo $this->renderSection('javascript') ?>
</body>

</html>
