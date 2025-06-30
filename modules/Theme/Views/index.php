<?= $this->extend('Modules\Backend\Views\base') ?>

<?= $this->section('title') ?>
<?= lang('Theme.' . $title->pagename) ?>
<?= $this->endSection() ?>

<?= $this->section('head') ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-12">
                <h1><?= lang('Theme.' . $title->pagename) ?></h1>
            </div>

        </div>
    </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">

    <!-- Default box -->
    <div class="card card-outline card-shl">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><?= lang('Theme.' . $title->pagename) ?></h3>

            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <?= view('Modules\Auth\Views\_message_block') ?>
            <form class="form-row" action="<?= route_to('themesUpload') ?>" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="col-12">
                    <div class="form-group">
                        <label for="exampleInputFile">File input</label>
                        <div class="input-group">
                            <div class="custom-file">
                                <input type="file" name="theme" class="custom-file-input" accept="application/x-zip,application/zip,application/x-zip-compressed,application/s-compressed,multipart/x-zip" id="exampleInputFile">
                                <label class="custom-file-label" for="exampleInputFile">Choose file</label>
                            </div>
                            <div class="input-group-append">
                                <button type="submit" class="input-group-text" id="uploadTheme">Upload</button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <?php if (session()->has('log')) : ?>
                <ul class="alert alert-info list-unstyled">
                    <?php foreach (session('log') as $log) : ?>
                        <li><?= $log ?></li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->

</section>
<!-- /.content -->
<?= $this->endSection() ?>
<?= $this->section('javascript') ?>
<?= script_tag('be-assets/plugins/bs-custom-file-input/bs-custom-file-input.min.js') ?>
<script>
    $(function() {
        bsCustomFileInput.init();
    });
    $('#uploadTheme').on('click', function() {

    });
</script>
<?= $this->endSection() ?>
