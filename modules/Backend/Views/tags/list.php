<?= $this->extend('Modules\Backend\Views\base') ?>

<?= $this->section('title') ?>
<?= lang('Backend.' . $title->pagename) ?>
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
                <h1><?= lang('Backend.' . $title->pagename) ?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <button type="button" class="btn btn-success" data-toggle="modal" data-target="#exampleModalCenter"
                            href="<?= route_to('tagCreate') ?>" class="btn btn-outline-success"><?=lang('Backend.add')?>
                    </button>
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
            <div class="table-responsive">
                <table class="table table-striped table-bordered">
                    <thead>
                    <tr>
                        <th><?=lang('Backend.tags')?></th>
                        <th><?=lang('Backend.transactions')?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($tags as $tag): ?>
                        <tr>
                            <td><?= $tag->tag ?></td>
                            <td>
                                <a href="<?= route_to('tagUpdate', $tag->_id) ?>"
                                   class="btn btn-outline-info btn-sm"><?=lang('Backend.update')?></a>
                                <a href="<?= route_to('tagDelete', $tag->_id) ?>"
                                   class="btn btn-outline-danger btn-sm"><?=lang('Backend.delete')?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($paginator->getNumPages() > 1): ?>
                <div class="card-footer clearfix">
                    <ul class="pagination pagination-sm m-0 float-right">
                        <?php if ($paginator->getPrevUrl()): ?>
                            <li class="page-item"><a class="page-link" href="<?php echo $paginator->getPrevUrl(); ?>">&laquo;</a>
                            </li>
                        <?php endif; ?>

                        <?php foreach ($paginator->getPages() as $page): ?>
                            <?php if ($page['url']): ?>
                                <li class="page-item <?php echo $page['isCurrent'] ? 'active' : ''; ?>">
                                    <a class="page-link"
                                       href="<?php echo $page['url']; ?>"><?php echo $page['num']; ?></a>
                                </li>
                            <?php else: ?>
                                <li class="disabled page-item"><span><?php echo $page['num']; ?></span></li>
                            <?php endif; ?>
                        <?php endforeach; ?>

                        <?php if ($paginator->getNextUrl()): ?>
                            <li class="page-item"><a class="page-link" href="<?php echo $paginator->getNextUrl(); ?>">&raquo;</a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->

    <div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog"
         aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLongTitle"><?=lang('Backend.add')?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="<?= route_to('tagCreate') ?>" method="post" class="form-row">
                        <div class="form-group col-md-12">
                            <label for=""><?=lang('Backend.title')?></label>
                            <input type="text" name="title" class="form-control ptitle" placeholder="Etiket Başlığı"
                                   required>
                        </div>
                        <div class="form-group col-md-12">
                            <label for=""><?=lang('Backend.url')?></label>
                            <input type="text" class="form-control seflink" name="seflink" required>
                        </div>
                        <div class="form-group col-md-12">
                            <button type="submit" class="btn btn-success float-right"><?=lang('Backend.add')?></button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?=lang('Backend.close')?></button>
                </div>
            </div>
        </div>
    </div>
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
