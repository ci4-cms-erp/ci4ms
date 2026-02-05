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
            <div class="col-sm-12">
                <h1><?= lang($title->pagename) ?></h1>
            </div>
        </div>
    </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">
    <!-- Default box -->
    <div class="card card-outline card-shl">
        <div class="card-body">
            <div class="row">
                <div class="col-md-4">

                    <div class="card card-primary card-outline">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-save mr-1"></i> <?= lang('Backup.createNewBackup') ?>
                            </h3>
                        </div>
                        <div class="card-body text-center">
                            <p class="text-muted"><?= lang('Backup.createBackupDescription') ?></p>
                            <button type="button" id="createBackupBtn" class="btn btn-app bg-success">
                                <i class="fas fa-file-download"></i> <?= lang('Backup.backupAndDownload') ?>
                            </button>
                        </div>
                    </div>

                    <div class="card card-danger card-outline">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-upload mr-1"></i> <?= lang('Backup.restoreFromBackup') ?>
                            </h3>
                        </div>
                        <form action="<?= route_to('backupRestore') ?>" method="post" enctype="multipart/form-data">
                            <div class="card-body">
                                <div class="form-group">
                                    <label for="backupFile">SQL Dosyası Seçin</label>
                                    <div class="input-group">
                                        <div class="custom-file">
                                            <input type="file" class="custom-file-input" id="backupFile" name="backup_file" accept=".sql,.zip" required>
                                            <label class="custom-file-label" for="backupFile">Dosya seç...</label>
                                        </div>
                                    </div>
                                    <small class="text-danger mt-2 d-block">
                                        <i class="fas fa-exclamation-triangle"></i> <?= lang('Backup.restoreWarning') ?>
                                    </small>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="submit" class="btn btn-danger btn-block" onclick="return confirm('Emin misiniz? Mevcut veriler kaybolacak!')">
                                    <i class="fas fa-history"></i> <?= lang('Backup.startRestore') ?>
                                </button>
                            </div>
                        </form>
                    </div>

                </div>

                <div class="col-md-8">
                    <div class="card card-info card-outline">
                        <div class="card-header">
                            <h3 class="card-title">
                                <i class="fas fa-list-ul mr-1"></i> <?= lang('Backup.backupsFromServer') ?>
                            </h3>
                            <div class="card-tools">
                                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                                    <i class="fas fa-minus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="card-body p-0">
                            <table id="example1" class="table table-bordered table-striped">
                                <thead>
                                    <tr>
                                        <th style="width: 1%">#</th>
                                        <th style="width: 40%"><?= lang('Backup.filename') ?></th>
                                        <th style="width: 20%"><?= lang('Backup.date') ?></th>
                                        <th style="width: 15%"><?= lang('Backup.size') ?></th>
                                        <th style="width: 20%" class="text-right"><?= lang('Backend.transactions') ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->
</section>
<!-- /.content -->
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>
<?= script_tag('be-assets/plugins/bs-custom-file-input/bs-custom-file-input.min.js') ?>
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
            url: '<?= route_to('backup') ?>',
            type: 'POST',
            data: {
                isApproved: isApprove
            }
        },
        columns: [{
                data: 'id'
            },
            {
                data: 'filename'
            },
            {
                data: 'created_at'
            },
            {
                data: 'file_size'
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

    $('#createBackupBtn').on('click', function(e) {
        e.preventDefault();

        Swal.fire({
            title: 'Lütfen bekleyin...',
            text: 'Yedekleme dosyası oluşturuluyor.',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        $.ajax({
            url: '<?= route_to('backupCreate') ?>',
            type: 'POST',
            dataType: 'json',
            success: function(response) {
                Swal.close();
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: '<?= lang('Backend.success') ?>',
                        text: '<?= lang('Backend.created',['Backup']) ?>',
                        confirmButtonText: '<?= lang('Backup.download') ?>',
                        showCancelButton: true,
                        cancelButtonText: '<?= lang('Backend.cancel') ?>'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            var link = document.createElement('a');
                            link.href = response.download_url;
                            link.download = '';
                            link.style.display = 'none';
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                        }
                        table.ajax.reload();
                    });
                } else {
                    Swal.fire('<?= lang('Backend.error') ?>', response.error || '<?= lang('Backend.notCreated',['Backup']) ?>', 'error');
                }
            },
            error: function(jqXHR, textStatus, errorThrown) {
                Swal.fire('<?= lang('Backend.error') ?>', jqXHR.responseJSON.error || '<?= lang('Backend.operationFailed') ?>', 'error');
            }
        });
    });
    $(function() {
        bsCustomFileInput.init();
    });

    function remove (id) {
        Swal.fire({
            icon: 'warning',
            title: '<?= lang('Backend.confirmDelete',['Backup']) ?>',
            confirmButtonText: '<?= lang('Backend.delete') ?>',
            showCancelButton: true,
            cancelButtonText: '<?= lang('Backend.cancel') ?>',
            allowOutsideClick: false
        }).then((result) => {
            if (result.isConfirmed) {
                $.ajax({
                    url: '/backend/backup/delete/'+ id,
                    type: 'POST',
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            Swal.fire({
                                icon: 'success',
                                title: '<?= lang('Backend.success') ?>',
                                text: response.message || '<?= lang('Backend.deleted',['Backup']) ?>',
                            });
                            table.ajax.reload();
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: '<?= lang('Backend.error') ?>',
                                text: response.error || '<?= lang('Backend.notDeleted',['Backup']) ?>',
                            });
                        }
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        Swal.fire({
                            icon: 'error',
                            title: '<?= lang('Backend.error') ?>',
                            text: jqXHR.responseJSON.error || '<?= lang('Backend.operationFailed') ?>',
                        });
                    }
                });
            }
        });
    }
</script>
<?= $this->endSection() ?>
