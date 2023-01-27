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
                <a href="<?= route_to('pageCreate') ?>" class="btn btn-outline-success float-right"><?=lang('Backend.add')?></a>
            </div>
        </div>
    </div><!-- /.container-fluid -->
</section>
<!-- Main content -->
<section class="content">
    <!-- Default box -->
    <div class="card card-outline card-shl">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><?=lang('Backend.'.$title->pagename)?></h3>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <?= view('Modules\Auth\Views\_message_block') ?>
            <div class="container-fluid">
                <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                    <tr class="row">
                        <th class="col-md-9"><?=lang('Backend.title')?></th>
                        <th class="col-md-1"><?=lang('Backend.status')?></th>
                        <th class="col-md-2"><?=lang('Backend.transactions')?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pages as $page): ?>
                    <tr class="row">
                        <td class="col-md-9"><?=$page->title?></td>
                        <td class="col-md-1">
                            <input type="checkbox" name="my-checkbox" class="bswitch" <?=($page->isActive===true)?'checked':''?> data-id="<?=$page->_id?>" data-off-color="danger" data-on-color="success">
                        </td>
                        <td class="col-md-2">
                            <a href="<?= route_to('pageUpdate', $page->_id) ?>"
                               class="btn btn-outline-info btn-sm"><?=lang('Backend.update')?></a>
                            <a href="<?= route_to('pageDelete', $page->_id) ?>"
                               class="btn btn-outline-danger btn-sm"><?=lang('Backend.delete')?></a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            </div>
        </div>
        <?php if ($paginator->getNumPages() > 1): ?>
            <div class="card-footer clearfix">
                <ul class="pagination pagination-sm m-0 float-right">
                    <?php if ($paginator->getPrevUrl()): ?>
                        <li class="page-item"><a class="page-link" href="<?php echo $paginator->getPrevUrl(); ?>">&laquo;</a></li>
                    <?php endif; ?>

                    <?php foreach ($paginator->getPages() as $page): ?>
                        <?php if ($page['url']): ?>
                            <li class="page-item <?php echo $page['isCurrent'] ? 'active' : ''; ?>">
                                <a class="page-link" href="<?php echo $page['url']; ?>"><?php echo $page['num']; ?></a>
                            </li>
                        <?php else: ?>
                            <li class="disabled page-item"><span><?php echo $page['num']; ?></span></li>
                        <?php endif; ?>
                    <?php endforeach; ?>

                    <?php if ($paginator->getNextUrl()): ?>
                        <li class="page-item"><a class="page-link" href="<?php echo $paginator->getNextUrl(); ?>">&raquo;</a></li>
                    <?php endif; ?>
                </ul>
            </div>
        <?php endif; ?>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->
</section>
<!-- /.content -->
<?= $this->endSection() ?>
<?= $this->section('javascript') ?>
<?=script_tag("be-assets/plugins/sweetalert2/sweetalert2.min.js")?>
<!-- Bootstrap Switch -->
<?=script_tag("be-assets/plugins/bootstrap-switch/js/bootstrap-switch.min.js")?>
<script>
    $('.bswitch').bootstrapSwitch();
    $('.bswitch').on('switchChange.bootstrapSwitch',function(){
        var id=$(this).data('id'), isActive;

        if($(this).prop('checked'))
            isActive=1;
        else
            isActive=0;

        $.post('<?=route_to('isActive')?>',
            {"<?=csrf_token()?>": "<?=csrf_hash()?>",
                "id":id,
                'isActive':isActive,
                'where':'pages'},'json').done();
    });
</script>
<?= $this->endSection() ?>
