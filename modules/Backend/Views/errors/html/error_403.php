<?= $this->extend('Modules\Backend\Views\base') ?>

<?= $this->section('title') ?>
403 - Forbidden
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1>403 - Forbidden</h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li class="breadcrumb-item"><a href="<?= base_url('/backend') ?>">Anasayfa</a></li>
                    <li class="breadcrumb-item active">403 - Forbidden</li>
                </ol>
            </div>
        </div>
    </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">
    <div class="error-page">
        <h2 class="headline text-warning font-weight-bold"> 403</h2>

        <div class="error-content">
            <h3 class="font-weight-bold"><i class="fas fa-exclamation-triangle text-warning"></i> Sayfaya erişim
                yetkiniz bulunmamakta.</h3>

            <p>
                Lütfen yönetici ile iletişime geçin veya butona tıklayarak geri dönün.
            </p>

            <div><a href="<?= previous_url() ?>" class="btn btn-warning w-100"><i class="fas fa-arrow-left"></i> Geri
                    Dön</a></div>
        </div>
        <!-- /.error-content -->
    </div>
    <!-- /.error-page -->
</section>
<!-- /.content -->
<?= $this->endSection() ?>
