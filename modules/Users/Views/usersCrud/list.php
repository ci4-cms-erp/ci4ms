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
        <div class="row pb-3 border-bottom">
            <div class="col-sm-6">
                <h1><?php echo lang($title->pagename) ?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right"><a href="<?php echo route_to('create_user') ?>"
                        class="btn btn-outline-success"><i
                            class="fas fa-user-plus"></i> <?php echo lang('Users.addUser') ?></a></ol>
            </div>
        </div>
    </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">
    <div class="card card-outline card-shl">
        <!-- /.card-header -->
        <div class="card-body">
            <div class="table-responsive">
                <table id="userTable" class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th><?php echo lang('Backend.fullName') ?></th>
                            <th><?php echo lang('Backend.email') ?></th>
                            <th><?php echo lang('Backend.status') ?></th>
                            <th><?php echo lang('Users.authority') ?></th>
                            <th><?php echo lang('Backend.transactions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
                </table>
            </div>

            <div class="modal fade" id="blackListModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="modalTitle">Kara Liste İşlemi</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <form id="blackListForm" method="post">
                            <?php echo csrf_field() ?>
                            <input type="hidden" name="uid" id="modal_uid">
                            <div class="modal-body">
                                <div id="modalNoteArea" class="mb-3">
                                    <label>Mevcut Not:</label>
                                    <div id="currentNote" class="alert alert-secondary"></div>
                                </div>
                                <div class="form-group">
                                    <label id="noteLabel">İşlem Notu</label>
                                    <textarea name="note" id="noteInput" class="form-control" rows="4"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Vazgeç</button>
                                <button type="submit" id="modalSubmitBtn" class="btn btn-dark">Kaydet</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <!-- /.card-body -->
    </div>
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
<!-- SweetAlert2 -->
<script {csp-script-nonce}>
    $('#modalNoteArea').hide();
    var table = $('#userTable').DataTable({
        "processing": true,
        "serverSide": true, // Sunucu taraflı işlem için
        "ajax": {
            "url": "<?php echo route_to('users') ?>", // Controller'da bu rotayı tanımlamalısınız
            "type": "POST"
        },
        "columns": [{
                "data": "fullname"
            },
            {
                "data": "email"
            },
            {
                "data": "status"
            },
            {
                "data": "groupName"
            },
            {
                "data": "actions",
                "orderable": false
            }
        ]
    });

    // 2. Dinamik Modal Açma (Kara Liste için)
    $(document).on('click', '.open-blacklist-modal', function() {
        var uid = $(this).data('id');
        var status = $(this).data('status');
        var note = $(this).data('note');

        $('#modal_uid').val(uid);

        if (status === 'banned') {
            $('#modalTitle').text('Kara Listeden Çıkar');
            $('#modalNoteArea').show();
            $('#currentNote').text(note);
            $('#noteInput').hide();
            $('#noteLabel').hide();
            $('#blackListForm').attr('action', "<?php echo route_to('removeFromBlacklist') ?>");
            $('#modalSubmitBtn').html('<i class="fas fa-user-check"></i> Listeden Çıkar');
        } else {
            $('#modalTitle').text('Kara Listeye Ekle');
            $('#modalNoteArea').hide();
            $('#noteInput').show();
            $('#noteLabel').show();
            $('#blackListForm').attr('action', "<?php echo route_to('blackList') ?>");
            $('#modalSubmitBtn').html('<i class="fas fa-user-slash"></i> Kara Listeye Al');
        }

        $('#blackListModal').modal('show');
    });

    // 3. Form Gönderimi (AJAX)
    $('#blackListForm').on('submit', function(e) {
        e.preventDefault();
        $.post($(this).attr('action'), $(this).serialize(), function(data) {
            if (data.result) {
                Swal.fire({
                    icon: 'success',
                    title: data.error.message
                });
                table.ajax.reload();
                $('#blackListModal').modal('hide');
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'İşlem başarısız!'
                });
            }
        }, 'json');
    });

    function forceResetPassword(uid) {
        $('.fpwd' + uid).addClass('disabled');
        $.post("<?php echo route_to('forceResetPassword') ?>", {
            uid: uid
        }, function(data) {
            if (data.result == true) {
                Swal.fire({
                    icon: data.error.type,
                    title: data.error.message
                }).then(function() {
                    table.ajax.reload();
                });
            } else
                Swal.fire({
                    icon: 'warning',
                    title: '<?php echo lang('Backend.operationFailed') ?>'
                }).then(function() {
                    $('.modal').modal('toggle');
                });
        }, 'json');
    }

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
                $.post('<?php echo route_to('user_del') ?>', {
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
