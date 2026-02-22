<?php echo $this->extend('Modules\Backend\Views\base') ?>

<?php echo $this->section('title') ?>
<?php echo lang($title->pagename) ?>
<?php echo $this->endSection() ?>

<?php echo $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><?php echo lang($title->pagename) ?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <a href="<?php echo route_to('comments') ?>" class="btn btn-outline-info"><i
                            class="fas fa-arrow-circle-left"></i> Listeye Dön</a>
                </ol>
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
            <form action="<?php echo route_to('confirmComment', $commentInfo->id) ?>" class="row" method="post">
                <?php echo csrf_field() ?>
                <div class="col-md-6 form-group">
                    <label for=""><?php echo lang('Backend.createdAt') ?></label>
                    <?php echo date('d-m-Y H-i-s', strtotime($commentInfo->created_at)) ?>
                </div>
                <div class="col-md-6 form-group">
                    <div class="btn-group float-right">
                        <a href="<?php echo site_url('blog/' . $blogInfo->seflink) ?>" target="_blank" class="btn btn-outline-success float-right font-weight-bold">
                            Related Post
                        </a>
                        <div class="btn-group btn-group-toggle" data-toggle="buttons">
                            <label class="btn btn-outline-primary <?php echo ((bool)$commentInfo->isApproved === true) ? 'checked' : '' ?>">
                                <input type="radio" name="options" value="1" id="option1" <?php echo ((bool)$commentInfo->isApproved === true) ? 'checked' : '' ?> required> <?php echo lang('Blog.publish') ?>
                            </label>
                            <label class="btn btn-outline-danger">
                                <input type="radio" name="options" value="2" id="option2" required> <?php echo lang('Backend.delete') ?>
                            </label>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 form-group">
                    <label for=""><?php echo lang('Backend.fullName') ?></label>
                    <input type="text" readonly value="<?php echo $commentInfo->comFullName ?>" class="form-control">
                </div>
                <div class="col-md-6 form-group">
                    <label for=""><?php echo lang('Backend.email') ?></label>
                    <input type="text" readonly value="<?php echo $commentInfo->comEmail ?>" class="form-control">
                </div>
                <div class="col-md-12 form-group">
                    <label for=""><?php echo lang('Blog.comment') ?></label>
                    <textarea name="" id="" cols="30" rows="10" class="form-control" readonly><?php echo $commentInfo->comMessage ?></textarea>
                </div>
                <div class="col-md-12 form-group">
                    <button class="btn btn-success float-right"><?php echo lang('Backend.save') ?></button>
                </div>
            </form>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->

</section>
<!-- /.content -->
<?php echo $this->endSection() ?>
