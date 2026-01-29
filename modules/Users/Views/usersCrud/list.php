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
        <div class="row pb-3 border-bottom">
            <div class="col-sm-6">
                <h1><?= lang($title->pagename) ?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right"><a href="<?= route_to('create_user') ?>"
                        class="btn btn-outline-success"><i
                            class="fas fa-user-plus"></i> <?= lang('Users.addUser') ?></a></ol>
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
                            <th><?= lang('Backend.fullName') ?></th>
                            <th><?= lang('Backend.email') ?></th>
                            <th><?= lang('Backend.status') ?></th>
                            <th><?= lang('Users.authority') ?></th>
                            <th><?= lang('Backend.transactions') ?></th>
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
                            <?= csrf_field() ?>
                            <input type="hidden" name="uid" id="modal_uid">
                            <div class="modal-body">
                                <div id="modalNoteArea" style="display:none;" class="mb-3">
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
<!-- SweetAlert2 -->
<script>
    var table = $('#userTable').DataTable({
        "processing": true,
        "serverSide": true, // Sunucu taraflı işlem için
        "ajax": {
            "url": "<?= route_to('users') ?>", // Controller'da bu rotayı tanımlamalısınız
            "type": "POST"
        },
        "columns": [
            { "data": "fullname" },
            { "data": "email" },
            { "data": "status" },
            { "data": "name" },
            { "data": "actions", "orderable": false } // İşlem butonları
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
            $('#blackListForm').attr('action', "<?= route_to('removeFromBlacklist') ?>");
            $('#modalSubmitBtn').html('<i class="fas fa-user-check"></i> Listeden Çıkar');
        } else {
            $('#modalTitle').text('Kara Listeye Ekle');
            $('#modalNoteArea').hide();
            $('#noteInput').show();
            $('#noteLabel').show();
            $('#blackListForm').attr('action', "<?= route_to('blackList') ?>");
            $('#modalSubmitBtn').html('<i class="fas fa-user-slash"></i> Kara Listeye Al');
        }

        $('#blackListModal').modal('show');
    });

    // 3. Form Gönderimi (AJAX)
    $('#blackListForm').on('submit', function(e) {
        e.preventDefault();
        $.post($(this).attr('action'), $(this).serialize(), function(data) {
            if (data.result) {
                Toast.fire({ icon: 'success', title: data.error.message });
                table.ajax.reload(); // Tabloyu yenile
                $('#blackListModal').modal('hide');
            } else {
                Toast.fire({ icon: 'error', title: 'İşlem başarısız!' });
            }
        }, 'json');
    });

    $('.fpwd').on('click', function() {
        $(this).addClass('disabled');
        $.post("<?= route_to('forceResetPassword') ?>", {
            uid: $(this).data('uid')
        }, function(data) {
            if (data.result == true) {
                Toast.fire({
                    icon: data.error.type,
                    title: data.error.message
                }).then(function() {
                    location.reload();
                });
            } else
                Toast.fire({
                    icon: 'warning',
                    title: '<?= lang('Backend.operationFailed') ?>'
                }).then(function() {
                    $('.modal').modal('toggle');
                });
        }, 'json');
    });
</script>
<?= $this->endSection() ?>
