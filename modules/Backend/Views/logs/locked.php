<?php

use CodeIgniter\I18n\Time;

?>
<?= $this->extend('Modules\Backend\Views\base') ?>

<?= $this->section('title') ?>
<?= lang('Backend.' . $title->pagename) ?>
<?= $this->endSection() ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="/be-assets/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css">
<link rel="stylesheet" href="/be-assets/plugins/daterangepicker/daterangepicker.css">
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><?= lang('Backend.' . $title->pagename) ?></h1>
            </div>
        </div>
    </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">

    <!-- Filter -->
    <div class="card card-outline card-filter border border-warning">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><?= lang('Filtre') ?></h3>
            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-plus"></i>
                </button>
            </div>
        </div>

        <div class="card-body">
            <form action="<?= route_to('locked/(:any)') ?>" class="form-row" method="get">
                <div class="form-group col-md-6">
                    <label for="email">Email</label>
                    <div class="input-group mb-3">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        </div>
                        <input type="text" name="email" class="form-control" placeholder="Email"
                               value="<?= $filteredData['email'] ?? null ?>">
                    </div>
                </div>

                <div class="form-group col-md-6">
                    <label>IP </label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-laptop"></i></span>
                        </div>
                        <input type="text" name="ip" class="form-control"value="<?= $filteredData['ip'] ?? null ?>">
                        <!--data-inputmask="'alias': 'ip'" data-mask -->
                    </div>
                </div>

                <div class="form-group col-md-6">
                    <label>Zaman Aralığı</label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="far fa-clock"></i></span>
                        </div>
                        <input type="text" name="date_range" class="form-control" id="reservationtime" autocomplete="off"
                               value="<?= $filteredData['date_range'] ?? null ?>">
                    </div>
                </div>

                <div class="form-group col-md-6">
                    <label>Durum</label>
                    <select class="form-control" name="status">
                        <option value="">Seçin</option>
                        <option <?= isset($filteredData['status']) && $filteredData['status'] === '1' ? 'selected' : '' ?>
                                value="1">Aktive
                        </option>
                        <option <?= isset($filteredData['status']) && $filteredData['status'] === '0' ? 'selected' : '' ?>
                                value="0">Pasif
                        </option>
                    </select>
                </div>

                <div class="col-md-9 ">
                    <a href="<?= route_to('locked',1) ?>" >Filtreyi Temizle</a>
                </div>
                <div class="col-md-3 float-right">
                    <button type="submit" class=" form-control btn btn-success">Ara</button>
                </div>
            </form>

        </div>
    </div>
    <!-- /.filter -->

    <!-- table box -->
    <div class="card card-outline card-shl">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><?= lang('Backend.' . $title->pagename) ?> <small>
                    (Toplam: <?= $totalCount ?? null ?> satır. )</small></h3>

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
                        <th>#</th>
                        <th><?=lang('Backend.email')?></th>
                        <th>IP</th>
                        <th><?=lang('Backend.start')?></th>
                        <th><?=lang('Backend.expire')?></th>
                        <th><?=lang('Backend.transactions')?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php if (!empty($locks)) :
                        foreach ($locks as $keys => $lock) : ?>
                            <tr>
                                <td><?= $keys + 1 ?></td>
                                <td><?= $lock->username ?></td>
                                <td><?= $lock->ip_address ?></td>
                                <td><?= Time::createFromFormat('Y-m-d H:i:s', new Time($lock->locked_at), 'Europe/Istanbul')->toLocalizedString('D-MMM-yy  | HH:MM') ?></td>
                                <td><?= Time::createFromFormat('Y-m-d H:i:s', new Time($lock->expiry_date), 'Europe/Istanbul')->toLocalizedString('D-MMM-yy | HH:MM ') ?></td>
                                <td>
                                    <input type="checkbox" name="my-checkbox"
                                           class="bswitch" <?= ($lock->isLocked === true) ? 'checked' : '' ?>
                                           data-id="<?= $lock->_id ?>" data-off-color="danger" data-on-color="success">
                                </td>
                            </tr>
                        <?php endforeach;
                    endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (isset($paginator)) :
                if ($paginator->getNumPages() > 1): ?>
                    <div class="card-footer clearfix">
                        <ul class="pagination pagination-sm m-0 float-right">
                            <?php if ($paginator->getPrevUrl()): ?>
                                <li class="page-item"><a class="page-link"
                                                         href="<?php echo $paginator->getPrevUrl(); ?>">&laquo;</a>
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
                                <li class="page-item"><a class="page-link"
                                                         href="<?php echo $paginator->getNextUrl(); ?>">&raquo;</a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </div>
                <?php endif;
            endif ?>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.list -->

</section>
<!-- /.content -->
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>

<script src="/be-assets/plugins/sweetalert2/sweetalert2.min.js"></script>
<script src="/be-assets/plugins/bootstrap-switch/js/bootstrap-switch.min.js"></script>
<script src="/be-assets/plugins/moment/moment.min.js"></script>
<script src="/be-assets/plugins/daterangepicker/daterangepicker.js"></script>
<script src="/be-assets/plugins/inputmask/jquery.inputmask.min.js"></script>


<script>
    // Status on-off toggle
    $('.bswitch').bootstrapSwitch();
    $('.bswitch').on('switchChange.bootstrapSwitch', function () {
        var id = $(this).data('id'), isLocked;

        if ($(this).prop('checked'))
            isLocked = 1;
        else
            isLocked = 0;

        $.post('<?=route_to('isActive')?>',
            {
                "<?=csrf_token()?>": "<?=csrf_hash()?>",
                "id": id,
                'isLocked': isLocked,
                'where': 'locked'
            }, 'json').done();
    });

    //Date range picker
    $('#reservation').daterangepicker()
    //Date range picker with time picker
    $('#reservationtime').daterangepicker({
        timePicker: true,
        timePickerIncrement: 10,
        todayHighlight: true,
        buttons: {showClear: true, showToday:true, showClose: true},
        locale: {
            format: 'D-MMM-yy HH:MM'
        }
    })


    // IP mask
    $('[data-mask]').inputmask()

</script>

<?= $this->endSection() ?>
