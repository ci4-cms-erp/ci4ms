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
        <div class="col-md-3">
            <div class="m-stat-card">
                <div class="m-stat-icon st-total"><i class="fas fa-newspaper"></i></div>
                <div>
                    <div class="m-stat-value"><?php echo $stats['total'] ?></div>
                    <div class="m-stat-label"><?php echo lang('Blog.totalBlogs') ?? 'Toplam Blog' ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="m-stat-card">
                <div class="m-stat-icon st-active"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="m-stat-value"><?php echo $stats['active'] ?></div>
                    <div class="m-stat-label"><?php echo lang('Blog.activeBlogs') ?? 'Published' ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="m-stat-card">
                <div class="m-stat-icon st-cat"><i class="fas fa-folder-open"></i></div>
                <div>
                    <div class="m-stat-value"><?php echo $stats['categories'] ?></div>
                    <div class="m-stat-label"><?php echo lang('Blog.categories') ?? 'Category' ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="m-stat-card">
                <div class="m-stat-icon st-com"><i class="fas fa-comments"></i></div>
                <div>
                    <div class="m-stat-value"><?php echo $stats['comments'] ?></div>
                    <div class="m-stat-label"><?php echo lang('Blog.comments') ?? 'Comment' ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Table Card -->
    <div class="card premium-card">
        <div class="card-header d-flex align-items-center">
            <h3 class="card-title font-weight-bold mb-0">
                <i class="fas fa-list-ul mr-2 text-primary"></i> <?php echo lang($title->pagename) ?>
            </h3>
            <div class="ml-auto">
                <a href="<?php echo route_to('blogCreate') ?>" class="btn btn-sm btn-success px-3" style="border-radius:8px">
                    <?php echo lang('Backend.add') ?>
                </a>
                <button class="btn btn-sm btn-outline-secondary ml-1" id="btnRefresh" style="border-radius:8px" title="Yenile">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="p-3">
                <table id="example1" class="table table-hover w-100">
                    <thead>
                        <tr>
                            <th style="width: 60%"><?php echo lang('Backend.title') ?></th>
                            <th style="text-align:center"><?php echo lang('Backend.status') ?></th>
                            <th style="text-align:right"><?php echo lang('Backend.transactions') ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php echo $this->endSection() ?>

<?php echo $this->section('javascript');
echo script_tag("be-assets/plugins/bootstrap-switch/js/bootstrap-switch.min.js");
echo script_tag('be-assets/plugins/datatables/jquery.dataTables.min.js');
echo script_tag('be-assets/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js');
echo script_tag('be-assets/plugins/datatables-responsive/js/dataTables.responsive.min.js');
echo script_tag('be-assets/plugins/datatables-responsive/js/responsive.bootstrap4.min.js'); ?>
<script {csp-script-nonce}>
    function btstpSwitch() {
        $('.bswitch').bootstrapSwitch({
            size: 'small'
        });
        $('.bswitch').off('switchChange.bootstrapSwitch').on('switchChange.bootstrapSwitch', function(event, state) {
            $.post('<?php echo route_to('isActive') ?>', {
                "id": $(this).data('id'),
                'isActive': state ? 1 : 0,
                'where': 'blog',
                "<?php echo csrf_token() ?>": "<?php echo csrf_hash() ?>"
            }, function(res) {
                showToast(state ? '<?php echo lang('Backend.published') ?? 'İçerik yayına alındı' ?>' : '<?php echo lang('Backend.drafted') ?? 'İçerik taslağa çekildi' ?>');
            }, 'json').fail(() => showToast('Hata oluştu', 'error'));
        });
    }

    $(function() {
        var table = $("#example1").DataTable({
            responsive: true,
            lengthChange: false,
            autoWidth: false,
            processing: true,
            serverSide: true,
            ordering: false,
            pageLength: 10,
            ajax: {
                url: '<?php echo route_to('blogs') ?>',
                type: 'POST',
                data: {
                    isApproved: true
                }
            },
            columns: [{
                    data: 'title',
                    render: (d) => `<span class="font-weight-600">${d}</span>`
                },
                {
                    data: 'isActive',
                    className: 'text-center'
                },
                {
                    data: 'actions',
                    className: 'text-right'
                }
            ],
            drawCallback: function() {
                btstpSwitch();
            },
            language: ci4msDtLanguage('<?php echo lang('Blog.searchPlaceholder') ?>')
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
            cancelButtonColor: '#6c757d',
            confirmButtonText: '<?php echo lang('Backend.delete') ?>',
            cancelButtonText: '<?php echo lang('Backend.cancel') ?>'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('<?php echo route_to('blogDelete') ?>', {
                    "id": id,
                    "<?php echo csrf_token() ?>": "<?php echo csrf_hash() ?>"
                }, 'json').done(function(response) {
                    if (response.status == 'success') {
                        showToast(response.message);
                        table.ajax.reload();
                    } else showToast(response.message, 'error');
                });
            }
        });
    }
</script>
<?php echo $this->endSection() ?>
