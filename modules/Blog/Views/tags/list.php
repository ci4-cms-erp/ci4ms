<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('title');
echo lang($title->pagename);
echo $this->endSection();
echo $this->section('head');
echo link_tag("be-assets/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css");
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
                <i class="fas fa-tags mr-2 text-primary"></i> <?php echo lang($title->pagename) ?>
            </h3>

            <div class="ml-auto d-flex">
                <button type="button" class="btn btn-sm btn-success px-3 d-flex align-items-center" data-toggle="modal" data-target="#exampleModalCenter" style="border-radius:8px">
                    <?php echo lang('Backend.add') ?>
                </button>
                <button class="btn btn-sm btn-outline-secondary ml-2 d-flex align-items-center" id="btnRefresh" style="border-radius:8px" title="Yenile">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="p-3">
                <table id="example1" class="table table-hover w-100">
                    <thead>
                        <tr>
                            <th><?php echo lang('Blog.tags') ?></th>
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

    <div class="modal fade" id="exampleModalCenter" tabindex="-1" role="dialog"
        aria-labelledby="exampleModalCenterTitle" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content" style="border-radius: 12px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.1);">
                <div class="modal-header" style="border-bottom: 1px solid rgba(0,0,0,0.05); background: #f8f9fa; border-radius: 12px 12px 0 0;">
                    <h5 class="modal-title font-weight-bold" id="exampleModalLongTitle"><?php echo lang('Backend.add') ?> <?php echo lang('Blog.tags') ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body p-4">
                    <form action="<?php echo route_to('tagCreate') ?>" method="post" class="form-row">
                        <?php echo csrf_field() ?>
                        <div class="form-group col-md-12">
                            <label for="" class="font-weight-bold"><?php echo lang('Backend.title') ?></label>
                            <input type="text" name="title" class="form-control ptitle" placeholder="<?php echo lang('Backend.title') ?>"
                                value="<?php echo old('title') ?>" required style="border-radius: 8px;">
                        </div>
                        <div class="form-group col-md-12">
                            <label for="" class="font-weight-bold"><?php echo lang('Backend.url') ?></label>
                            <input type="text" class="form-control seflink" name="seflink" value="<?php echo old('seflink') ?>" required style="border-radius: 8px;">
                        </div>
                        <div class="form-group col-md-12 mt-3 mb-0">
                            <button type="submit" class="btn btn-success float-right" style="border-radius: 8px; padding: 10px 20px;"><i class="fas fa-check mr-1"></i> <?php echo lang('Backend.add') ?></button>
                            <button type="button" class="btn btn-secondary float-right mr-2" data-dismiss="modal" style="border-radius: 8px; padding: 10px 20px;"><?php echo lang('Backend.close') ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- /.content -->
<?php echo $this->endSection();
echo $this->section('javascript');
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
    $('.ptitle').on('change', function() {
        $.post('<?php echo route_to('checkSeflink') ?>', {
            "<?php echo csrf_token() ?>": "<?php echo csrf_hash() ?>",
            'makeSeflink': $(this).val(),
            'where': 'tags'
        }, 'json').done(function(data) {
            $('.seflink').val(data.seflink);
        });
    });

    $('.seflink').on('change', function() {
        $.post('<?php echo route_to('checkSeflink') ?>', {
            "<?php echo csrf_token() ?>": "<?php echo csrf_hash() ?>",
            'makeSeflink': $(this).val(),
            'where': 'tags'
        }, 'json').done(function(data) {
            $('.seflink').val(data.seflink);
        });
    });

    var table;
    $(function() {
        table = $("#example1").DataTable({
            responsive: true,
            lengthChange: false,
            autoWidth: false,
            buttons: ["pageLength", {
                text: '<i class="fas fa-sync-alt"></i>',
                className: "btn btn-outline-secondary btn-sm ml-2",
                action: function(e, dt, node, config) {
                    dt.ajax.reload();
                },
                attr: {
                    style: 'border-radius:8px;'
                }
            }],
            processing: true,
            pageLength: 10,
            serverSide: true,
            ordering: false,
            ajax: {
                url: '<?php echo route_to('tags') ?>',
                type: 'POST',
                data: {
                    isApproved: true,
                    "<?php echo csrf_token() ?>": "<?php echo csrf_hash() ?>"
                }
            },
            columns: [{
                    data: 'tag'
                },
                {
                    data: 'actions',
                    className: 'text-right'
                }
            ],
            initComplete: function() {
                var btns = table.buttons().container().appendTo($('.card-header .ml-auto', table.table().container()));
                btns.find('.btn-group').removeClass('btn-group');
            },
            language: ci4msDtLanguage('Search tags...')
        });

        $('#btnRefresh').click(() => table.ajax.reload());
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
                $.post('<?php echo route_to('tagDelete') ?>', {
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
