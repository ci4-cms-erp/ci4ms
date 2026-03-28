<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('title');
echo lang($title->pagename);
echo $this->endSection();
echo $this->section('head');
echo link_tag('be-assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css');
echo link_tag('be-assets/plugins/datatables-responsive/css/responsive.bootstrap4.min.css');
echo link_tag('be-assets/plugins/datatables-buttons/css/buttons.bootstrap4.min.css');
echo $this->endSection();
echo $this->section('content'); ?>

<!-- Main content -->
<section class="content pt-3">
    <!-- Default box -->
    <div class="card premium-card">
        <div class="card-header d-flex align-items-center">
            <h3 class="card-title font-weight-bold mb-0">
                <i class="fas fa-comments mr-2 text-primary"></i> <?php echo lang($title->pagename) ?>
            </h3>

            <div class="ml-auto d-flex" id="dtButtons">
                <!-- DataTables buttons will be appended here -->
            </div>
        </div>
        <div class="card-body p-0">
            <div class="p-3">
                <table id="example1" class="table table-hover w-100">
                    <thead>
                        <tr>
                            <th style="width:50px">ID</th>
                            <th><?php echo lang('Backend.fullName') ?></th>
                            <th><?php echo lang('Backend.email') ?></th>
                            <th><?php echo lang('Backend.createdAt') ?></th>
                            <th><?php echo lang('Backend.status') ?></th>
                            <th style="text-align:right"><?php echo lang('Backend.transactions') ?></th>
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

<?php echo $this->section('javascript');
echo script_tag('be-assets/plugins/datatables/jquery.dataTables.min.js');
echo script_tag('be-assets/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js');
echo script_tag('be-assets/plugins/datatables-responsive/js/dataTables.responsive.min.js');
echo script_tag('be-assets/plugins/datatables-responsive/js/responsive.bootstrap4.min.js');
echo script_tag('be-assets/plugins/datatables-buttons/js/dataTables.buttons.min.js');
echo script_tag('be-assets/plugins/datatables-buttons/js/buttons.bootstrap4.min.js');
echo script_tag('be-assets/plugins/jszip/jszip.min.js');
echo script_tag('be-assets/plugins/pdfmake/pdfmake.min.js');
echo script_tag('be-assets/plugins/pdfmake/vfs_fonts.js');
echo script_tag('be-assets/plugins/datatables-buttons/js/buttons.html5.min.js');
echo script_tag('be-assets/plugins/datatables-buttons/js/buttons.print.min.js');
echo script_tag('be-assets/plugins/datatables-buttons/js/buttons.colVis.min.js'); ?>
<script {csp-script-nonce}>
    let isApprove = true;
    var table = $("#example1").DataTable({
        responsive: true,
        lengthChange: false,
        autoWidth: false,
        buttons: [{
                text: '<i class="fas fa-filter mr-1"></i> <span id="toggleApprovalText">Görünürleri Göster</span>',
                className: "btn btn-success btn-sm mx-1 toggle-approval",
                action: function(e, dt, node, config) {
                    isApprove = !isApprove;
                    var buttonText = isApprove ? '<i class="fas fa-filter mr-1"></i> Görünürleri Göster' : '<i class="fas fa-eye-slash mr-1"></i> Gizlileri Göster';
                    node.html(buttonText);

                    if (isApprove) {
                        node.removeClass('btn-warning').addClass('btn-success');
                    } else {
                        node.removeClass('btn-success').addClass('btn-warning');
                    }

                    dt.context[0].ajax.data.isApproved = isApprove;
                    dt.ajax.reload();
                },
                attr: {
                    style: 'border-radius:8px;'
                }
            },
            {
                text: '<i class="fas fa-sync-alt"></i>',
                className: "btn btn-warning btn-sm ml-1",
                action: function(e, dt, node, config) {
                    dt.ajax.reload();
                },
                attr: {
                    style: 'border-radius:8px;',
                    title: 'Yenile'
                }
            }
        ],
        processing: true,
        pageLength: 10,
        serverSide: true,
        ordering: false,
        ajax: {
            url: '<?php echo route_to('comments') ?>',
            type: 'POST',
            data: function(d) {
                d.isApproved = isApprove;
                d['<?php echo csrf_token() ?>'] = '<?php echo csrf_hash() ?>';
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
                data: 'process',
                className: 'text-right'
            }
        ],
        initComplete: function() {
            var btns = table.buttons().container().appendTo('#dtButtons');
            btns.find('.btn-group').removeClass('btn-group');
        },
        language: ci4msDtLanguage('Search comments...')
    });

    function deleteItem(id) {
        Swal.fire({
            title: '<?php echo lang('Backend.areYouSure') ?>',
            text: "<?php echo lang('Backend.youWillNotBeAbleToRecoverThis') ?>",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e53e3e',
            cancelButtonColor: '#3085d6',
            confirmButtonText: '<?php echo lang('Backend.delete') ?>',
            cancelButtonText: '<?php echo lang('Backend.cancel') ?>'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('<?php echo route_to('commentRemove') ?>', {
                    "id": id,
                    "<?php echo csrf_token() ?>": "<?php echo csrf_hash() ?>"
                }, 'json').done(function(response) {
                    if (response.status == 'success') {
                        if (typeof showToast !== 'undefined') {
                            showToast(response.message);
                            table.ajax.reload();
                        } else {
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
                        }
                    } else {
                        if (typeof showToast !== 'undefined') {
                            showToast(response.message, 'error');
                        } else {
                            Swal.fire({
                                title: '<?php echo lang('Backend.error') ?>',
                                text: response.message,
                                icon: 'error',
                                confirmButtonText: '<?php echo lang('Backend.ok') ?>'
                            });
                        }
                    }
                });
            }
        });
    }
</script>
<?php echo $this->endSection() ?>
