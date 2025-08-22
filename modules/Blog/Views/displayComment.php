<?= $this->extend('Modules\Backend\Views\base') ?>

<?= $this->section('title') ?>
<?=lang($title->pagename)?>
<?= $this->endSection() ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="/be-assets/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><?=lang($title->pagename)?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <a href="<?= route_to('comments') ?>" class="btn btn-outline-info"><i
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
            <h3 class="card-title font-weight-bold"><?= lang($title->pagename) ?></h3>

            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <?= view('Modules\Auth\Views\_message_block') ?>
            <form action="<?=route_to('confirmComment',$commentInfo->id)?>" class="row" method="post">
                <?=csrf_field()?>
                <div class="col-md-6 form-group">
                    <label for="">Created At</label>
                    <?=date('d-m-Y H-i-s',strtotime($commentInfo->created_at))?>
                </div>
                <div class="col-md-6 form-group">
                    <div class="btn-group float-right">
                        <a href="<?=site_url('blog/'.$blogInfo->seflink)?>" target="_blank" class="btn btn-outline-success float-right font-weight-bold">
                            Related Post
                        </a>
                        <div class="btn-group btn-group-toggle" data-toggle="buttons">
                            <label class="btn btn-outline-primary <?=((bool)$commentInfo->isApproved===true)?'checked':''?>">
                                <input type="radio" name="options" value="1" id="option1" <?=((bool)$commentInfo->isApproved===true)?'checked':''?> required> Yayınla
                            </label>
                            <label class="btn btn-outline-danger">
                                <input type="radio" name="options" value="2" id="option2" required> Sil
                            </label>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 form-group">
                    <label for="">Full Name</label>
                    <input type="text" readonly value="<?=$commentInfo->comFullName?>" class="form-control">
                </div>
                <div class="col-md-6 form-group">
                    <label for="">E-Mail</label>
                    <input type="text" readonly value="<?=$commentInfo->comEmail?>" class="form-control">
                </div>
                <div class="col-md-12 form-group">
                    <label for="">Comment</label>
                    <textarea name="" id="" cols="30" rows="10" class="form-control" readonly><?=$commentInfo->comMessage?></textarea>
                </div>
                <div class="col-md-12 form-group">
                    <button class="btn btn-success float-right">Kaydet</button>
                </div>
            </form>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->

</section>
<!-- /.content -->
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>
<script src="/be-assets/plugins/sweetalert2/sweetalert2.min.js"></script>
<?= $this->endSection() ?>
