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

<section class="content pt-3">
    <!-- Stats Row -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="m-stat-card">
                <div class="m-stat-icon st-files"><i class="fas fa-file-medical-alt"></i></div>
                <div>
                    <div class="m-stat-value"><?php echo $stats['fileCount'] ?></div>
                    <div class="m-stat-label">Toplam Log Dosyası</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="m-stat-card">
                <div class="m-stat-icon st-size"><i class="fas fa-hdd"></i></div>
                <div>
                    <div class="m-stat-value"><?php echo $stats['totalSize'] ?></div>
                    <div class="m-stat-label">Toplam Boyut</div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="m-stat-card">
                <div class="m-stat-icon st-current"><i class="fas fa-eye"></i></div>
                <div>
                    <div class="m-stat-value"><?php echo $stats['currentFile'] ?? '-' ?></div>
                    <div class="m-stat-label">Görüntülenen Dosya</div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Sidebar - Log Files -->
        <div class="col-md-3">
            <div class="card premium-card">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold mb-0">Log Dosyaları</h3>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($files as $file): ?>
                            <div class="list-group-item d-flex align-items-center <?php echo $currentFile == $file ? 'bg-light font-weight-bold' : '' ?>">
                                <a href="?f=<?php echo base64_encode($file) ?>" class="text-dark flex-grow-1"><?php echo $file ?></a>
                                <div class="btn-group btn-group-sm">
                                    <a href="?dl=<?php echo base64_encode($file) ?>" class="btn btn-outline-info border-0"><i class="fas fa-download"></i></a>
                                    <button class="btn btn-outline-danger border-0" onclick="deleteItem('<?php echo base64_encode($file) ?>')"><i class="fas fa-trash"></i></button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content - Log Content -->
        <div class="col-md-9">
            <div class="card premium-card">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title font-weight-bold mb-0">İçerik: <small class="text-muted ml-2"><?php echo $currentFile ?></small></h3>
                    <div class="ml-auto">
                        <button class="btn btn-sm btn-outline-secondary" onclick="location.reload()" title="Yenile"><i class="fas fa-sync-alt"></i></button>
                    </div>
                </div>
                <div class="card-body p-3" style="max-height: 600px; overflow-y: auto; background: #fff;">
                    <?php echo view('Modules\Logs\Views\logs', ['logs' => $logs, 'files' => $files, 'currentFile' => $currentFile]) ?>

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
<script {csp-script-nonce}>
    $(document).ready(function() {

        $('.table-container tr').on('click', function() {
            $('#' + $(this).data('display')).toggle();
        });

        var table = $('#table-log').DataTable({
            "order": [],
            "stateSave": true,
            "stateSaveCallback": function(settings, data) {
                window.localStorage.setItem("datatable", JSON.stringify(data));
            },
            "stateLoadCallback": function(settings) {
                var data = JSON.parse(window.localStorage.getItem("datatable"));
                if (data) data.start = 0;
                return data;
            }
        });

        $('#btnRefresh').click(() => table.ajax.reload());
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
                $.post('<?php echo route_to('logDelete') ?>', {
                    "id": id,
                    "<?php echo csrf_token() ?>": "<?php echo csrf_hash() ?>"
                }, 'json').done(function(response) {
                    if (response.status == 'success') {
                        location.reload();
                    } else {
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
