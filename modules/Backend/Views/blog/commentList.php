<?= $this->extend('Modules\Backend\Views\base') ?>

<?= $this->section('title') ?>
<?=lang('Backend.'.$title->pagename)?>
<?= $this->endSection() ?>

<?= $this->section('head') ?>
<?= link_tag('be-assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') ?>
<?= link_tag('be-assets/plugins/datatables-responsive/css/responsive.bootstrap4.min.css') ?>
<?= link_tag('be-assets/plugins/datatables-buttons/css/buttons.bootstrap4.min.css') ?>
<?= link_tag("be-assets/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css") ?>
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
            <div class="col-12">
                <button class="btn btn-info unapproved" type="button">Show Unapproved</button>
            </div>
            <div class="col-12">
                <table id="example1" class="table table-bordered table-striped">
                    <thead>
                    <tr>
                        <th></th>
                        <th>Name Surname</th>
                        <th>E-mail</th>
                        <th>Created At</th>
                        <th>Status</th>
                        <th>Process</th>
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
<?= script_tag("be-assets/plugins/sweetalert2/sweetalert2.min.js") ?>
<script>
    let isApprove=true;

    $('.unapproved').on('click',function (){
        isApprove = !isApprove;
        var buttonText = isApprove ? 'Show Unapproved' : 'Show Approved';
        $(this).text(buttonText);
        table.context[0].ajax.data.isApproved=isApprove;
        table.ajax.reload();
    });

    var table = $("#example1").DataTable({
        responsive: true, lengthChange: false, autoWidth: false,
        buttons: ["pageLength",{
            text:"Refresh",
            className: "btn btn-teal",
            action: function (e,dt,node,config){
                dt.ajax.reload();
            }
            }],
        processing: true, pageLength: 10, serverSide: true,
        ordering: false, "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
        ajax: {
            url: '<?=route_to('commentResponse')?>',
            type: 'POST',
            data:{isApproved: isApprove}
        },
        columns: [
            {data: 'id'},
            {data: 'com_name_surname'},
            {data: 'email'},
            {data:'created_at'},
            {data:'status'},
            {data:'process'}
        ],
        initComplete: function () {
            table.buttons().container()
                .appendTo($('.col-md-6:eq(0)', table.table().container()));
        }
    });
</script>
<?= $this->endSection() ?>
