<?php echo $this->extend('Modules\Backend\Views\base') ?>

<?php echo $this->section('title') ?>
<?php echo lang($title->pagename) ?>
<?php echo $this->endSection() ?>

<?php echo $this->section('head') ?>
<?php echo $this->endSection() ?>

<?php echo $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-12">
                <h1><?php echo lang($title->pagename) ?></h1>
            </div>
        </div>
    </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">

    <!-- Default box -->
    <div class="card card-outline card-shl">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><?php echo lang($title->pagename) ?></h3>

            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <form action="<?php echo route_to("badwordsAdd") ?>" class="form-row" method="post">
                <?php echo csrf_field() ?>
                <div class="col-12 form-group">
                    <div class="form-check form-check-inline">
                        <input type="checkbox" name="status" id="status" class="form-check-input" <?php echo set_checkbox('status', 'on', (bool)$badwords->status === true) ?>>
                        <label for="status" class="form-check-label"><?php echo lang('Blog.enableFiltering') ?>
                        </label>
                    </div>

                    <div class="form-check form-check-inline">
                        <input type="checkbox" name="autoReject" id="autoReject" class="form-check-input" <?php echo set_checkbox('autoReject', 'on', (bool)$badwords->autoReject === true) ?>>
                        <label for="autoReject" class="form-check-label"><?php echo lang('Blog.autoReject') ?>
                        </label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input type="checkbox" name="autoAccept" id="autoAccept" class="form-check-input" <?php echo set_checkbox('autoAccept', 'on', (bool)$badwords->autoAccept === true) ?>>
                        <label for="autoAccept" class="form-check-label"><?php echo lang('Blog.autoAccept') ?>
                        </label>
                    </div>
                </div>
                <div class="col-12 form-group">
                    <label for=""><?php echo lang('') ?></label>
                    <textarea name="badwords" id="" cols="30" rows="10" class="form-control"><?php echo old('badwords', $badwords->list) ?></textarea>
                </div>
                <div class="col-12 form-group">
                    <button class="btn btn-success float-right" type="submit"><?php echo lang('Backend.save') ?></button>
                </div>
            </form>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->

</section>
<!-- /.content -->
<?php echo $this->endSection() ?>

<?php echo $this->section('javascript') ?>
<?php echo $this->endSection() ?>
