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
        <div class="col-md-4">
            <div class="m-stat-card">
                <div class="m-stat-icon st-total"><i class="fas fa-users"></i></div>
                <div><div class="m-stat-value"><?php echo $stats['total'] ?></div><div class="m-stat-label"><?php echo lang('Users.totalUsers') ?? 'Toplam Kullanıcı' ?></div></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="m-stat-card">
                <div class="m-stat-icon st-active"><i class="fas fa-user-check"></i></div>
                <div><div class="m-stat-value"><?php echo $stats['active'] ?></div><div class="m-stat-label"><?php echo lang('Backend.active') ?? 'Aktif Kullanıcı' ?></div></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="m-stat-card">
                <div class="m-stat-icon st-banned"><i class="fas fa-user-slash"></i></div>
                <div><div class="m-stat-value"><?php echo $stats['banned'] ?></div><div class="m-stat-label"><?php echo lang('Users.bannedUsers') ?? 'Kara Liste' ?></div></div>
            </div>
        </div>
    </div>

    <!-- Main Table Card -->
    <div class="card premium-card">
        <div class="card-header d-flex align-items-center">
            <h3 class="card-title font-weight-bold mb-0">
                <i class="fas fa-user-shield mr-2 text-dark"></i> <?php echo lang($title->pagename) ?>
            </h3>
            <div class="ml-auto">
                <a href="<?php echo route_to('create_user') ?>" class="btn btn-sm btn-success px-4" style="border-radius:10px">
                    <i class="fas fa-user-plus mr-1"></i> <?php echo lang('Users.addUser') ?>
                </a>
                <button class="btn btn-sm btn-outline-secondary ml-1" id="btnRefresh" style="border-radius:10px" title="Yenile">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="p-4">
                <table id="userTable" class="table table-hover w-100">
                    <thead>
                        <tr>
                            <th><?php echo lang('Backend.fullName') ?></th>
                            <th><?php echo lang('Backend.email') ?></th>
                            <th style="text-align:center"><?php echo lang('Backend.status') ?></th>
                            <th><?php echo lang('Users.authority') ?></th>
                            <th style="text-align:right"><?php echo lang('Backend.transactions') ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>

            <!-- Blacklist Modal -->
            <div class="modal fade" id="blackListModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content" style="border-radius:15px;overflow:hidden">
                        <div class="modal-header" style="background:#f7f9fc;border-bottom:1px solid #edf2f7">
                            <h5 class="modal-title font-weight-bold" id="modalTitle">Kara Liste İşlemi</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                        </div>
                        <form id="blackListForm" method="post">
                            <?php echo csrf_field() ?>
                            <input type="hidden" name="uid" id="modal_uid">
                            <div class="modal-body p-4">
                                <div id="modalNoteArea" class="mb-4">
                                    <label class="text-muted small font-weight-bold uppercase">Mevcut Not:</label>
                                    <div id="currentNote" class="alert alert-secondary border-0" style="border-radius:10px"></div>
                                </div>
                                <div class="form-group">
                                    <label id="noteLabel" class="font-weight-bold">İşlem Notu</label>
                                    <textarea name="note" id="noteInput" class="form-control" rows="4" style="border-radius:10px;border:1px solid #e2e8f0"></textarea>
                                </div>
                            </div>
                            <div class="modal-footer" style="background:#f7f9fc;border-top:1px solid #edf2f7">
                                <button type="button" class="btn btn-light px-4" data-dismiss="modal" style="border-radius:10px">Vazgeç</button>
                                <button type="submit" id="modalSubmitBtn" class="btn btn-dark px-4" style="border-radius:10px">Kaydet</button>
                            </div>
                        </form>
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
<script {csp-script-nonce}>
    $(function() {
        var table = $('#userTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: { url: "<?php echo route_to('users') ?>", type: "POST" },
            columns: [
                { data: "fullname", render: (d, t, r) => `<div class="d-flex align-items-center"><img src="${r.profileIMG || '/be-assets/img/avatar.png'}" class="user-avatar" onerror="this.src='/be-assets/img/avatar.png'"><span class="font-weight-600">${d}</span></div>` },
                { data: "email" },
                { data: "status", className: "text-center", render: (d) => d === 'banned' ? '<span class="m-status-pill pill-banned">Banned</span>' : '<span class="m-status-pill pill-active">Active</span>' },
                { data: "groupName", render: (d) => `<span class="badge badge-light border" style="border-radius:6px;font-weight:500;color:#718096">${d}</span>` },
                { data: "actions", orderable: false, className: "text-right" }
            ],
            language: ci4msDtLanguage('<?php echo lang('Users.searchPlaceholder') ?>')
        });

        $('#btnRefresh').click(() => table.ajax.reload());
    });

    $(document).on('click', '.open-blacklist-modal', function() {
        var uid = $(this).data('id'), status = $(this).data('status'), note = $(this).data('note');
        $('#modal_uid').val(uid);
        if (status === 'banned') {
            $('#modalTitle').text('<?php echo lang('Users.removeFromBlacklistTitle') ?>');
            $('#modalNoteArea').show(); $('#currentNote').text(note); $('#noteInput, #noteLabel').hide();
            $('#blackListForm').attr('action', "<?php echo route_to('removeFromBlacklist') ?>");
            $('#modalSubmitBtn').html('<i class="fas fa-user-check mr-1"></i> <?php echo lang('Users.removeFromBlacklistBtn') ?>');
        } else {
            $('#modalTitle').text('<?php echo lang('Users.addToBlacklistTitle') ?>');
            $('#modalNoteArea').hide(); $('#noteInput, #noteLabel').show();
            $('#blackListForm').attr('action', "<?php echo route_to('blackList') ?>");
            $('#modalSubmitBtn').html('<i class="fas fa-user-slash mr-1"></i> <?php echo lang('Users.addToBlacklistBtn') ?>');
        }
        $('#blackListModal').modal('show');
    });

    $('#blackListForm').on('submit', function(e) {
        e.preventDefault();
        $.post($(this).attr('action'), $(this).serialize(), function(data) {
            if (data.result) { showToast(data.error.message); table.ajax.reload(); $('#blackListModal').modal('hide'); }
            else { showToast('İşlem başarısız!', 'error'); }
        }, 'json');
    });

    function forceResetPassword(uid) {
        $.post("<?php echo route_to('forceResetPassword') ?>", { uid: uid }, function(data) {
            if (data.result == true) { showToast(data.error.message); table.ajax.reload(); }
            else showToast('İşlem başarısız!', 'error');
        }, 'json');
    }

    function deleteItem(id) {
        Swal.fire({
            title: '<?php echo lang('Backend.areYouSure') ?>',
            text: "<?php echo lang('Backend.youWillNotBeAbleToRecoverThis') ?>",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e53e3e',
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<?php echo lang('Backend.delete') ?>',
            cancelButtonText: '<?php echo lang('Backend.cancel') ?>'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('<?php echo route_to('user_del') ?>', { "id": id, "<?php echo csrf_token() ?>": "<?php echo csrf_hash() ?>" }, 'json').done(function(response) {
                    if (response.status == 'success') { showToast(response.message); table.ajax.reload(); }
                    else showToast(response.message, 'error');
                });
            }
        });
    }
</script>
<?php echo $this->endSection() ?>
