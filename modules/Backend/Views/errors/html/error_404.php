<?= $this->extend('Modules\Backend\Views\base') ?>

<?= $this->section('head') ?>
<title>Soar <?= $config->vers ?> | 404 File Not Found</title>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>404 Error Page</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="<?= base_url('/backend') ?>">Anasayfa</a></li>
                    <li class="breadcrumb-item active">404 - File Not Found</li>
                </ol>
            </div>
        </div>
    </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">
    <div class="error-page">
        <h2 class="headline text-warning"> 404 File Not Found</h2>

        <div class="error-content">
            <h3><i class="fas fa-exclamation-triangle text-warning"></i> Oops! Page not found.</h3>

            <p>
                <?php if (!empty($message) && $message !== '(null)') : ?>
                    <?= esc($message) ?>
                <?php else : ?>
                    Sorry! Cannot seem to find the page you were looking for.
                <?php endif ?>
            </p>
            <div><a href="<?= previous_url() ?>" class="btn btn-warning"> Geri Dön</a></div>
        </div>
        <!-- /.error-content -->
    </div>
    <!-- /.error-page -->
</section>
<!-- /.content -->
<?= $this->endSection() ?>
