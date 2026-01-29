<?php

use CodeIgniter\I18n\Time;

?>
<?= $this->extend('Modules\Backend\Views\base') ?>

<?= $this->section('title') ?>
<?= lang('Backend.' . $title->pagename) ?>
<?= $this->endSection() ?>

<?= $this->section('head') ?>
<?= link_tag('be-assets/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css') ?>
<?= link_tag('be-assets/plugins/daterangepicker/daterangepicker.css') ?>
<?= link_tag('be-assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') ?>
<?= link_tag('be-assets/plugins/datatables-responsive/css/responsive.bootstrap4.min.css') ?>
<?= link_tag('be-assets/plugins/datatables-buttons/css/buttons.bootstrap4.min.css') ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><?= lang($title->pagename) ?></h1>
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
                    <label for="email"><?= lang('Backend.email') ?></label>
                    <div class="input-group mb-3">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                        </div>
                        <input type="text" name="email" class="form-control" placeholder="<?= lang('Backend.email') ?>"
                            value="<?= $filteredData['email'] ?? null ?>">
                    </div>
                </div>

                <div class="form-group col-md-6">
                    <label>IP </label>
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text"><i class="fas fa-laptop"></i></span>
                        </div>
                        <input type="text" name="ip" class="form-control" value="<?= $filteredData['ip'] ?? null ?>">
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
                    <label><?= lang('Backend.status') ?></label>
                    <select class="form-control" name="status">
                        <option value=""><?= lang('Backend.select') ?></option>
                        <option <?= isset($filteredData['status']) && $filteredData['status'] === '1' ? 'selected' : '' ?>
                            value="1"><?= lang('Backend.active') ?>
                        </option>
                        <option <?= isset($filteredData['status']) && $filteredData['status'] === '0' ? 'selected' : '' ?>
                            value="0"><?= lang('Backend.passive') ?>
                        </option>
                    </select>
                </div>

                <div class="col-md-9 ">
                    <a href="<?= route_to('locked', 1) ?>"><?= lang('Backend.clearFilter') ?></a>
                </div>
                <div class="col-md-3 float-right">
                    <button type="submit" class=" form-control btn btn-success"><?= lang('Backend.search') ?></button>
                </div>
            </form>

        </div>
    </div>
    <!-- /.filter -->

    <!-- table box -->
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
            <div class="table-responsive">
                <table id="example1" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th><?= lang('Backend.email') ?></th>
                            <th>IP</th>
                            <th><?= lang('Backend.start') ?></th>
                            <th><?= lang('Backend.expire') ?></th>
                            <th><?= lang('Backend.transactions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.list -->

</section>
<!-- /.content -->
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>
<?= script_tag('be-assets/plugins/sweetalert2/sweetalert2.min.js')?>
<?= script_tag('be-assets/plugins/bootstrap-switch/js/bootstrap-switch.min.js')?>
<?= script_tag('be-assets/plugins/moment/moment.min.js')?>
<?= script_tag('be-assets/plugins/daterangepicker/daterangepicker.js')?>
<?= script_tag('be-assets/plugins/inputmask/jquery.inputmask.min.js')?>

<?= script_tag('be-assets/plugins/datatables/jquery.dataTables.min.js') ?>
<?= script_tag('be-assets/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') ?>
<?= script_tag('be-assets/plugins/datatables-responsive/js/dataTables.responsive.min.js') ?>
<?= script_tag('be-assets/plugins/datatables-responsive/js/responsive.bootstrap4.min.js') ?>
<?= script_tag('be-assets/plugins/datatables-buttons/js/dataTables.buttons.min.js') ?>
<?= script_tag('be-assets/plugins/datatables-buttons/js/buttons.bootstrap4.min.js') ?>
<?= script_tag('be-assets/plugins/jszip/jszip.min.js') ?>
<?= script_tag('be-assets/plugins/pdfmake/pdfmake.min.js') ?>
<?= script_tag('be-assets/plugins/pdfmake/vfs_fonts.js') ?>
<?= script_tag('be-assets/plugins/datatables-buttons/js/buttons.html5.min.js') ?>
<?= script_tag('be-assets/plugins/datatables-buttons/js/buttons.print.min.js') ?>
<?= script_tag('be-assets/plugins/datatables-buttons/js/buttons.colVis.min.js') ?>

<script>
    // Status on-off toggle
    $('.bswitch').bootstrapSwitch();
    $('.bswitch').on('switchChange.bootstrapSwitch', function() {
        var id = $(this).data('id'),
            isLocked;

        if ($(this).prop('checked'))
            isLocked = 1;
        else
            isLocked = 0;

        $.post('<?= route_to('isActive') ?>', {
            "<?= csrf_token() ?>": "<?= csrf_hash() ?>",
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
        buttons: {
            showClear: true,
            showToday: true,
            showClose: true
        },
        locale: {
            format: 'D-MMM-yy HH:MM'
        }
    })


    // IP mask
    $('[data-mask]').inputmask();

    let isApprove = true;
    var table = $("#example1").DataTable({
        responsive: true,
        lengthChange: false,
        autoWidth: false,
        buttons: ["pageLength", {
            text: "Refresh",
            className: "btn btn-teal",
            action: function(e, dt, node, config) {
                dt.ajax.reload();
            }
        }],
        processing: true,
        pageLength: 10,
        serverSide: true,
        ordering: false,
        lengthMenu: [10, 25, 50, {
            label: 'All',
            value: -1
        }],
        ajax: {
            url: '<?= route_to('locked') ?>',
            type: 'POST',
            data: {
                isApproved: isApprove
            }
        },
        columns: [{
                data: 'id'
            },
            {
                data: 'username'
            },
            {
                data: 'ip_address'
            },
            {
                data: 'locked_at'
            },
            {
                data: 'expiry_date'
            },
            {
                data: 'actions'
            }
        ],
        initComplete: function() {
            table.buttons().container()
                .appendTo($('.col-md-6:eq(0)', table.table().container()));
        }
    });
</script>

<?= $this->endSection() ?>
