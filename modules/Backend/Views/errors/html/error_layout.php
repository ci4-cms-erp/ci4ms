<!DOCTYPE html>
<html lang="tr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">

    <title>Kun-CMS/ERP | <?= htmlspecialchars($pageTitle ?? 'Hata', ENT_SUBSTITUTE, 'UTF-8') ?></title>

    <link rel="shortcut icon" href="/be-assets/img/favicon.ico" type="image/x-icon">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link href="/be-assets/plugins/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="/be-assets/css/adminlte.min.css" rel="stylesheet" type="text/css">
    <link href="/be-assets/css/ci4ms-premium.css" rel="stylesheet" type="text/css">
    <link href="/be-assets/custom.css" rel="stylesheet" type="text/css">

    <style>
        body.hold-transition {
            background-color: #f4f6f9;
        }
        .error-standalone-wrapper {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        .error-standalone-header {
            background: linear-gradient(135deg, #343a40 0%, #495057 100%);
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
        }
        .error-standalone-header img {
            height: 28px;
        }
        .error-standalone-body {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 3rem 1rem;
        }
        .error-card {
            background: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 24px rgba(0,0,0,0.10);
            padding: 3rem 2.5rem;
            max-width: 560px;
            width: 100%;
            text-align: center;
        }
        .error-card .error-code {
            font-size: 6rem;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 0.5rem;
        }
        .error-card .error-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
        .error-card h2 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: #343a40;
        }
        .error-card p {
            color: #6c757d;
            margin-bottom: 2rem;
            font-size: 0.95rem;
            line-height: 1.6;
        }
        .error-standalone-footer {
            background: #343a40;
            color: #adb5bd;
            padding: 0.75rem 1.5rem;
            text-align: center;
            font-size: 0.8rem;
        }
    </style>
</head>

<body class="hold-transition">
    <div class="error-standalone-wrapper">

        <!-- Minimal Header -->
        <div class="error-standalone-header">
            <a href="<?= base_url('backend') ?>">
                <img src="/be-assets/img/logo-w.png" alt="Kun-CMS Logo">
            </a>
        </div>

        <!-- Error Body -->
        <div class="error-standalone-body">
            <?= $errorContent ?? '' ?>
        </div>

        <!-- Minimal Footer -->
        <div class="error-standalone-footer">
            Copyright &copy; <?= date('Y') ?> &mdash; Ci4MS
            <?php if (defined('CI_VERSION')) : ?>
                &mdash; CI v<?= \CodeIgniter\CodeIgniter::CI_VERSION ?>
            <?php endif; ?>
        </div>

    </div>

    <script src="/be-assets/plugins/jquery/jquery.min.js"></script>
    <script src="/be-assets/plugins/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>

</html>
