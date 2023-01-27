<?= $this->extend('Modules\Backend\Views\base') ?>

<?= $this->section('title') ?>
<?=lang('Backend.'.$title->pagename)?>
<?= $this->endSection() ?>

<?= $this->section('head') ?>
<?=link_tag("be-assets/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css")?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><?=lang('Backend.'.$title->pagename)?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <a href="<?= route_to('tags',1) ?>" class="btn btn-outline-info"><?=lang('Backend.backToList')?></a>
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
            <h3 class="card-title font-weight-bold"><?= lang('Backend.' . $title->pagename) ?></h3>

            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <?= view('Modules\Auth\Views\_message_block') ?>
            <form action="<?= route_to('tagUpdate',$infos->_id) ?>" method="post" class="form-row">
                <?= csrf_field() ?>
                <div class="form-group col-md-12">
                    <label for=""><?=lang('Backend.title')?></label>
                    <input type="text" name="title" class="form-control ptitle" placeholder="Etiket Başlığı"
                           required value="<?=$infos->tag?>">
                </div>
                <div class="form-group col-md-12">
                    <label for=""><?=lang('Backend.url')?></label>
                    <input type="text" class="form-control seflink" name="seflink" required value="<?=$infos->seflink?>">
                </div>
                <div class="form-group col-md-12">
                    <button type="submit" class="btn btn-success float-right"><?=lang('Backend.update')?></button>
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
<?=script_tag("be-assets/plugins/sweetalert2/sweetalert2.min.js")?>
<script>
    $('.ptitle').on('change', function () {
        $.post('<?=route_to('checkSeflink')?>', {
            "<?=csrf_token()?>": "<?=csrf_hash()?>",
            'makeSeflink': $(this).val(),
            'where': 'tags'
        }, 'json').done(function (data) {
            $('.seflink').val(data.seflink);
        });
    });

    $('.seflink').on('change', function () {
        $.post('<?=route_to('checkSeflink')?>', {
            "<?=csrf_token()?>": "<?=csrf_hash()?>",
            'makeSeflink': $(this).val(),
            'where': 'tags'
        }, 'json').done(function (data) {
            $('.seflink').val(data.seflink);
        });
    });
</script>
<?= $this->endSection() ?>
