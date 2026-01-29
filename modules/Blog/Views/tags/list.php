<?= $this->extend('Modules\Backend\Views\base') ?>

<?= $this->section('title') ?>
<?= lang($title->pagename) ?>
<?= $this->endSection() ?>

<?= $this->section('head') ?>
<?= link_tag("be-assets/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css") ?>
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
                    <button type="button" class="btn btn-success" data-toggle="modal" data-target="#exampleModalCenter"
                        href="<?= route_to('tagCreate') ?>" class="btn btn-outline-success"><?= lang('Backend.add') ?>
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
                            <th><?= lang('Blog.tags') ?></th>
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

    <div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog"
        aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLongTitle"><?= lang('Backend.add') ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="<?= route_to('tagCreate') ?>" method="post" class="form-row">
                        <?= csrf_field() ?>
                        <div class="form-group col-md-12">
                            <label for=""><?= lang('Backend.title') ?></label>
                            <input type="text" name="title" class="form-control ptitle" placeholder="Etiket Başlığı"
                                required>
                        </div>
                        <div class="form-group col-md-12">
                            <label for=""><?= lang('Backend.url') ?></label>
                            <input type="text" class="form-control seflink" name="seflink" required>
                        </div>
                        <div class="form-group col-md-12">
                            <button type="submit" class="btn btn-success float-right"><?= lang('Backend.add') ?></button>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal"><?= lang('Backend.close') ?></button>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- /.content -->
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>
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
    $('.ptitle').on('change', function() {
        $.post('<?= route_to('checkSeflink') ?>', {
            "<?= csrf_token() ?>": "<?= csrf_hash() ?>",
            'makeSeflink': $(this).val(),
            'where': 'tags'
        }, 'json').done(function(data) {
            $('.seflink').val(data.seflink);
        });
    });

    $('.seflink').on('change', function() {
        $.post('<?= route_to('checkSeflink') ?>', {
            "<?= csrf_token() ?>": "<?= csrf_hash() ?>",
            'makeSeflink': $(this).val(),
            'where': 'tags'
        }, 'json').done(function(data) {
            $('.seflink').val(data.seflink);
        });
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
            url: '<?= route_to('tags') ?>',
            type: 'POST',
            data: {
                isApproved: isApprove
            }
        },
        columns: [{
                data: 'tag'
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
