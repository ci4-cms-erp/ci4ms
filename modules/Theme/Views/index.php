<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('title');
echo lang('Theme.' . $title->pagename);
echo $this->endSection();
echo $this->section('content'); ?>
<!-- Main content -->
<section class="content pt-3">

    <!-- Default box -->
    <div class="card card-outline shadow-sm">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><?php echo lang('Theme.' . $title->pagename) ?></h3>

            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-minus"></i>
                </button>
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
