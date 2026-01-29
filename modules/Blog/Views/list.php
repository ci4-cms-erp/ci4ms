<?= $this->extend('Modules\Backend\Views\base') ?>

<?= $this->section('title') ?>
<?= lang($title->pagename) ?>
<?= $this->endSection() ?>

<?= $this->section('head') ?>
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
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <a href="<?= route_to('blogCreate') ?>" class="btn btn-outline-success">
                        <?= lang('Backend.add') ?>
                    </a>
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
            <div class="table-responsive">
                <table id="example1" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th><?= lang('Backend.title') ?></th>
                            <th><?= lang('Backend.status') ?></th>
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
    <!-- /.card -->

</section>
<!-- /.content -->
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>
<!-- Bootstrap Switch -->
<?= script_tag("be-assets/plugins/bootstrap-switch/js/bootstrap-switch.min.js") ?>
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
    $('.bswitch').bootstrapSwitch();
    $('.bswitch').on('switchChange.bootstrapSwitch', function() {
        var id = $(this).data('id'),
            isActive;

        if ($(this).prop('checked'))
            isActive = 1;
        else
            isActive = 0;

        $.post('<?= route_to('isActive') ?>', {
            "id": id,
            'isActive': isActive,
            'where': 'blog'
        }, 'json').done();
    });
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
            url: '<?= route_to('blogs') ?>',
            type: 'POST',
            data: {
                isApproved: isApprove
            }
        },
        columns: [{
                data: 'title'
            },
            {
                data: 'isActive'
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
