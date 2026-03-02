<!doctype html>
<html>

<head>
    <meta charset="UTF-8">
    <meta name="robots" content="noindex">

    <title><?php echo lang('Errors.whoops') ?></title>

    <style>
        <?php echo preg_replace('#[\r\n\t ]+#', ' ', file_get_contents(__DIR__ . DIRECTORY_SEPARATOR . 'debug.css')) ?>
    </style>
</head>

<body>

    <div class="container text-center">

        <h1 class="headline"><?php echo lang('Errors.whoops') ?></h1>

        <p class="lead"><?php echo lang('Errors.weHitASnag') ?></p>

    </div>

</body>

</html>
