<?php echo $this->extend('Modules\Backend\Views\base') ?>

<?php echo $this->section('title') ?>
<?php echo lang($title->pagename) ?>
<?php echo $this->endSection() ?>

<?php echo $this->section('head') ?>
<?php echo link_tag('be-assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') ?>
<?php echo link_tag('be-assets/plugins/datatables-responsive/css/responsive.bootstrap4.min.css') ?>
<?php echo link_tag('be-assets/plugins/datatables-buttons/css/buttons.bootstrap4.min.css') ?>
<?php echo $this->endSection() ?>

<?php echo $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-12">
                <h1><?php echo lang($title->pagename) ?></h1>
            </div>
        </div>
    </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">

    <!-- Default box -->
    <div class="card card-outline card-shl">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><?php echo lang($title->pagename) ?></h3>

            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="col-12">
                <table id="example1" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th></th>
                            <th><?php echo lang('Backend.fullName') ?></th>
                            <th><?php echo lang('Backend.email') ?></th>
                            <th><?php echo lang('Backend.createdAt') ?></th>
                            <th><?php echo lang('Backend.status') ?></th>
                            <th><?php echo lang('Backend.transactions') ?></th>
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
<?php echo $this->endSection() ?>

<?php echo $this->section('javascript') ?>
<?php echo script_tag('be-assets/plugins/datatables/jquery.dataTables.min.js') ?>
<?php echo script_tag('be-assets/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') ?>
<?php echo script_tag('be-assets/plugins/datatables-responsive/js/dataTables.responsive.min.js') ?>
<?php echo script_tag('be-assets/plugins/datatables-responsive/js/responsive.bootstrap4.min.js') ?>
<?php echo script_tag('be-assets/plugins/datatables-buttons/js/dataTables.buttons.min.js') ?>
<?php echo script_tag('be-assets/plugins/datatables-buttons/js/buttons.bootstrap4.min.js') ?>
<?php echo script_tag('be-assets/plugins/jszip/jszip.min.js') ?>
<?php echo script_tag('be-assets/plugins/pdfmake/pdfmake.min.js') ?>
<?php echo script_tag('be-assets/plugins/pdfmake/vfs_fonts.js') ?>
<?php echo script_tag('be-assets/plugins/datatables-buttons/js/buttons.html5.min.js') ?>
<?php echo script_tag('be-assets/plugins/datatables-buttons/js/buttons.print.min.js') ?>
<?php echo script_tag('be-assets/plugins/datatables-buttons/js/buttons.colVis.min.js') ?>
<script {csp-script-nonce}>
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
            },
            {
                text: "Show Unapproved",
                className: "unapproved",
                action: function(e, dt, node, config) {
                    isApprove = !isApprove;
                    var buttonText = isApprove ? 'Show Unapproved' : 'Show Approved';
                    node.text(buttonText);
                    dt.context[0].ajax.data.isApproved = isApprove;
                    dt.ajax.reload();
                }
            }
        ],
        processing: true,
        pageLength: 10,
        serverSide: true,
        ordering: false,
        lengthMenu: [10, 25, 50, {
            label: 'All',
            value: -1
        }],
        ajax: {
            url: '<?php echo route_to('comments') ?>',
            type: 'POST',
            data: {
                isApproved: isApprove
            }
        },
        columns: [{
                data: 'id'
            },
            {
                data: 'com_name_surname'
            },
            {
                data: 'email'
            },
            {
                data: 'created_at'
            },
            {
                data: 'status'
            },
            {
                data: 'process'
            }
        ],
        initComplete: function() {
            table.buttons().container()
                .appendTo($('.col-md-6:eq(0)', table.table().container()));
        }
    });

    function deleteItem(id) {
        Swal.fire({
            title: '<?php echo lang('Backend.areYouSure') ?>',
            text: "<?php echo lang('Backend.youWillNotBeAbleToRecoverThis') ?>",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#d33',
            cancelButtonColor: '#3085d6',
            confirmButtonText: '<?php echo lang('Backend.delete') ?>',
            cancelButtonText: '<?php echo lang('Backend.cancel') ?>'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('<?php echo route_to('blogDelete') ?>', {
                    "id": id,
                    "<?php echo csrf_token() ?>": "<?php echo csrf_hash() ?>"
                }, 'json').done(function(response) {
                    if (response.status == 'success') {
                        Swal.fire({
                            title: '<?php echo lang('Backend.success') ?>',
                            text: response.message,
                            icon: 'success',
                            confirmButtonText: '<?php echo lang('Backend.ok') ?>'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                table.ajax.reload();
                            }
                        });
                    }else{
                        Swal.fire({
                            title: '<?php echo lang('Backend.error') ?>',
                            text: response.message,
                            icon: 'error',
                            confirmButtonText: '<?php echo lang('Backend.ok') ?>'
                        });
                    }
                });
            }
        });
    }
</script>
<?php echo $this->endSection() ?>
