<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('title');
echo lang($title->pagename);
echo $this->endSection();
echo $this->section('content'); ?>
<!-- Main content -->
<section class="content pt-3">

    <!-- Default box -->
    <div class="card premium-card">
        <div class="card-header d-flex align-items-center">
            <h3 class="card-title font-weight-bold mb-0">
                <?php echo lang($title->pagename) ?></h3>

            <div class="ml-auto">
                <a href="<?php echo route_to('downloadStarterTheme') ?>" class="btn btn-sm btn-success px-4 mr-2" style="border-radius:10px">
                    <i class="fas fa-download"></i> <?php echo lang('Theme.downloadStarter') ?>
                </a>
                <a href="<?php echo route_to('settings') ?>" class="btn btn-sm btn-info px-4" style="border-radius:10px">
                    <i class="fas fa-arrow-circle-left"></i> <?php echo lang('Settings.settings') ?>
                </a>
            </div>
        </div>
        <div class="card-body">
            <form class="form-row" action="<?php echo route_to('themesUpload') ?>" method="post" enctype="multipart/form-data">
                <?php echo csrf_field() ?>
                <div class="col-12">
                    <div class="form-group">
                        <label for="exampleInputFile"><?php echo lang('Backend.fileInput') ?></label>
                        <div class="input-group">
                            <div class="custom-file">
                                <input type="file" name="theme" class="custom-file-input" accept="application/x-zip,application/zip,application/x-zip-compressed,application/s-compressed,multipart/x-zip" id="exampleInputFile">
                                <label class="custom-file-label" for="exampleInputFile"><?php echo lang('Backend.chooseFile') ?></label>
                            </div>
                            <div class="input-group-append">
                                <button type="submit" class="input-group-text" id="uploadTheme"><?php echo lang('Backend.upload') ?></button>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
            <?php if (session()->has('log')) : ?>
                <ul class="alert alert-info list-unstyled">
                    <?php foreach (session('log') as $log) : ?>
                        <li><?php echo $log ?></li>
                    <?php endforeach ?>
                </ul>
            <?php endif ?>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->

</section>
<!-- /.content -->
<?php echo $this->endSection();
echo $this->section('javascript');
echo script_tag('be-assets/plugins/bs-custom-file-input/bs-custom-file-input.min.js'); ?>
<script {csp-script-nonce}>
    $(function() {
        bsCustomFileInput.init();
    });
    $('#uploadTheme').on('click', function() {

    });
</script>
<?php echo $this->endSection() ?>
