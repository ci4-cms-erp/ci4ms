<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('title');
echo lang($title->pagename);
echo $this->endSection();
echo $this->section('head');
echo link_tag('be-assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css');
echo link_tag('be-assets/plugins/datatables-responsive/css/responsive.bootstrap4.min.css');
echo $this->endSection();
echo $this->section('content'); ?>

<section class="content pt-3">
    <!-- Stats Row -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="m-stat-card">
                <div class="m-stat-icon st-total"><i class="fas fa-database"></i></div>
                <div><div class="m-stat-value"><?php echo $stats['totalBackups'] ?></div><div class="m-stat-label">Toplam Yedek</div></div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="m-stat-card">
                <div class="m-stat-icon st-last"><i class="fas fa-history"></i></div>
                <div><div class="m-stat-value small"><?php echo $stats['lastBackup'] ?></div><div class="m-stat-label"><?php echo lang('Backup.lastBackupTime') ?? 'Last Backup Time' ?></div></div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- New Backup Section -->
        <div class="col-md-4">
            <div class="card premium-card mb-4">
                <div class="card-header"><h3 class="card-title font-weight-bold mb-0"><?php echo lang('Backup.quickActions') ?? 'Quick Actions' ?></h3></div>
                <div class="card-body">
                    <button class="btn btn-success btn-block mb-3" id="btnCreateBackup" style="border-radius:10px; padding: 12px;">
                        <i class="fas fa-plus-circle mr-2"></i> <?php echo lang('Backup.createNow') ?>
                    </button>
                    <hr>
                    <form action="<?php echo route_to('backupRestore') ?>" method="post" enctype="multipart/form-data">
                        <?php echo csrf_field() ?>
                        <div class="form-group">
                            <label class="font-weight-bold"><?php echo lang('Backup.restoreExternal') ?? 'Restore External SQL (.zip)' ?></label>
                            <div class="custom-file">
                                <input type="file" class="custom-file-input" name="backup_file" id="backup_file" required>
                                <label class="custom-file-label" for="backup_file"><?php echo lang('Backup.chooseFile') ?? 'Choose file...' ?></label>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-outline-primary btn-block" style="border-radius:10px">
                            <i class="fas fa-upload mr-2"></i> Geri Yükle
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Backup List Section -->
        <div class="col-md-8">
            <div class="card premium-card">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-list mr-2 text-info"></i> Yedek Listesi</h3>
                    <div class="ml-auto">
                        <button class="btn btn-sm btn-outline-secondary" id="btnRefresh" style="border-radius:10px" title="Yenile"><i class="fas fa-sync-alt"></i></button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="p-3">
                        <table id="backupTable" class="table table-hover w-100">
                            <thead>
                                <tr>
                                    <th>Dosya Adı</th>
                                    <th>Boyut</th>
                                    <th>Tarih</th>
                                    <th style="text-align:right"><?php echo lang('Backend.transactions') ?></th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php echo $this->endSection();
echo $this->section('javascript');
echo script_tag('be-assets/plugins/datatables/jquery.dataTables.min.js');
echo script_tag('be-assets/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js');
echo script_tag('be-assets/plugins/datatables-responsive/js/dataTables.responsive.min.js');
echo script_tag('be-assets/plugins/datatables-responsive/js/responsive.bootstrap4.min.js'); ?>
<script type="text/javascript" {csp-script-nonce}>
    $(function() {
        var table = $("#backupTable").DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '<?php echo route_to('backup') ?>',
                type: 'POST',
                data: (d) => { d['<?php echo csrf_token() ?>'] = '<?php echo csrf_hash() ?>'; }
            },
            columns: [
                { data: 'filename', render: (d) => `<code>${d}</code>` },
                { data: 'file_size' },
                { data: 'created_at' },
                { data: 'actions', className: 'text-right' }
            ],
            language: ci4msDtLanguage('<?php echo lang('Backup.searchPlaceholder') ?>')
        });

        $('#btnRefresh').click(() => table.ajax.reload());

        $('#btnCreateBackup').on('click', function() {
            var btn = $(this);
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> <?php echo lang('Backup.backingUp') ?>');
            $.post('<?php echo route_to('backupCreate') ?>', {
                "<?php echo csrf_token() ?>": "<?php echo csrf_hash() ?>"
            }, function(r) {
                if(r.success) {
                    showToast('<?php echo lang('Backup.backupCreated') ?>');
                    table.ajax.reload();
                } else showToast(r.error, 'error');
                btn.prop('disabled', false).html('<i class="fas fa-plus-circle mr-2"></i> <?php echo lang('Backup.createNow') ?>');
            }, 'json').fail(() => {
                showToast('<?php echo lang('Backup.errorOccurred') ?>', 'error');
                btn.prop('disabled', false).html('<i class="fas fa-plus-circle mr-2"></i> <?php echo lang('Backup.createNow') ?>');
            });
        });
    });

    function remove(id) {
        Swal.fire({
            title: '<?php echo lang('Backup.deleteConfirmTitle') ?>',
            icon: 'warning', showCancelButton: true,
            confirmButtonColor: '#e53e3e',
            confirmButtonText: '<?php echo lang('Backup.deleteConfirmBtn') ?>',
            cancelButtonText: '<?php echo lang('Backend.cancel') ?>'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('/backend/backup/delete/' + id, {
                    "<?php echo csrf_token() ?>": "<?php echo csrf_hash() ?>"
                }, function(r) {
                    if(r.success) { showToast(r.message); $('#backupTable').DataTable().ajax.reload(); }
                    else showToast(r.error, 'error');
                }, 'json');
            }
        });
    }

    if (typeof bsCustomFileInput !== 'undefined') bsCustomFileInput.init();
</script>
<?php echo $this->endSection() ?>
