<?= $this->extend('Modules\Backend\Views\base') ?>

<?= $this->section('title') ?>
<?=lang('Backend.'.$title->pagename)?>
<?= $this->endSection() ?>

<?= $this->section('head') ?>
<?= link_tag("be-assets/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css")?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-12">
                <h1><?=lang('Backend.'.$title->pagename)?></h1>
            </div>
        </div>
    </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">

    <!-- Default box -->
    <div class="card card-outline card-shl">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><?= lang('Backend.' . $title->pagename) ?></h3>

            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <?= view('Modules\Auth\Views\_message_block') ?>
            <form action="<?=route_to("badwordsAdd")?>" class="form-row" method="post">
                <?=csrf_field()?>
                <div class="col-12 form-group">
                    <div class="form-check form-check-inline">
                    <input type="checkbox" name="status" id="status" class="form-check-input" <?=(!empty($badwords->status) && (bool)$badwords->status===true)?'checked':''?>>
                    <label for="status" class="form-check-label">Filtrelemeyi aktif et
                    </label>
                    </div>

                    <div class="form-check form-check-inline">
                        <input type="checkbox" name="autoReject" id="autoReject" class="form-check-input" <?=(!empty($badwords->autoReject) && (bool)$badwords->autoReject===true)?'checked':''?>>
                        <label for="autoReject" class="form-check-label">Otomatik reddetme
                        </label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input type="checkbox" name="autoAccept" id="autoAccept" class="form-check-input" <?=(!empty($badwords->autoAccept) && (bool)$badwords->autoAccept===true)?'checked':''?>>
                        <label for="autoAccept" class="form-check-label">Otomatik Kabul Etme
                        </label>
                    </div>
                </div>
                <div class="col-12 form-group">
                    <label for="">Kötü sözler</label>
                    <textarea name="badwords" id="" cols="30" rows="10" class="form-control"><?=$badwords->list?></textarea>
                </div>
                <div class="col-12 form-group">
                    <button class="btn btn-success float-right" type="submit">Kaydet</button>
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
<?= script_tag("be-assets/plugins/sweetalert2/sweetalert2.min.js")?>
<?= $this->endSection() ?>
