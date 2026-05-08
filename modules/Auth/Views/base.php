<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noimageindex, nofollow, nosnippet">

    <?php echo link_tag("be-assets/img/favicon.ico", "shortcut icon", "image/x-icon") ?>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <?php echo link_tag("be-assets/plugins/fontawesome-free/css/all.min.css");
    echo link_tag("be-assets/css/adminlte.min.css");
    echo link_tag("be-assets/custom.css");
    echo $this->renderSection('head') ?>
</head>

<body class="hold-transition login-page">
    <?php echo $this->renderSection('content') ?>
    </div>
    <?php echo script_tag("be-assets/plugins/jquery/jquery.min.js");
    echo script_tag("be-assets/plugins/bootstrap/js/bootstrap.bundle.min.js");
    echo script_tag("be-assets/js/adminlte.min.js");
    echo script_tag("be-assets/js/demo.js");
    echo $this->renderSection('javascript') ?>
</body>

</html>
