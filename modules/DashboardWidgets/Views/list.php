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
                <div class="m-stat-icon st-total"><i class="fas fa-th-large"></i></div>
                <div><div class="m-stat-value"><?php echo $stats['total'] ?></div><div class="m-stat-label"><?php echo lang('DashboardWidgets.totalWidgets') ?? 'Total Widgets' ?></div></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="m-stat-card">
                <div class="m-stat-icon st-active"><i class="fas fa-toggle-on"></i></div>
                <div><div class="m-stat-value"><?php echo $stats['active'] ?></div><div class="m-stat-label"><?php echo lang('Backend.active') ?? 'Active Widgets' ?></div></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="m-stat-card">
                <div class="m-stat-icon st-system"><i class="fas fa-cogs"></i></div>
                <div><div class="m-stat-value"><?php echo $stats['system'] ?></div><div class="m-stat-label"><?php echo lang('DashboardWidgets.systemWidgets') ?? 'System Widgets' ?></div></div>
            </div>
        </div>
    </div>

    <div class="card premium-card">
        <div class="card-header d-flex align-items-center">
            <h3 class="card-title font-weight-bold mb-0">
                <i class="fas fa-th-large mr-2 text-primary"></i> <?php echo lang('DashboardWidgets.manageWidgets') ?>
            </h3>
            <div class="ml-auto">
                <a href="<?php echo site_url('backend/dashboard-widgets/seed') ?>" class="btn btn-sm btn-outline-info mr-1" style="border-radius:10px">
                    <i class="fas fa-database mr-1"></i><?php echo lang('DashboardWidgets.seedDefaults') ?>
                </a>
                <a href="<?php echo site_url('backend/dashboard-widgets/create') ?>" class="btn btn-sm btn-primary px-4" style="border-radius:10px">
                    <i class="fas fa-plus mr-1"></i><?php echo lang('DashboardWidgets.createWidget') ?>
                </a>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="p-4">
                <table id="widgetsTable" class="table table-hover w-100">
                    <thead>
                        <tr>
                            <th width="40">ID</th>
                            <th><?php echo lang('Backend.title') ?></th>
                            <th><?php echo lang('DashboardWidgets.slug') ?></th>
                            <th width="80"><?php echo lang('DashboardWidgets.type') ?></th>
                            <th width="100"><?php echo lang('DashboardWidgets.defaultSize') ?></th>
                            <th width="120"><?php echo lang('Backend.status') ?></th>
                            <th width="120" style="text-align:right"><?php echo lang('Backend.actions') ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
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
        var table = $('#widgetsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '<?php echo site_url('backend/dashboard-widgets') ?>',
                type: 'POST',
                data: (d) => { d['<?php echo csrf_token() ?>'] = '<?php echo csrf_hash() ?>'; }
            },
            columns: [
                { data: 0 }, { data: 1 }, { data: 2 }, { data: 3 }, { data: 4 }, { data: 5 },
                { data: 6, className: 'text-right' }
            ],
            language: ci4msDtLanguage('<?php echo lang('DashboardWidgets.searchPlaceholder') ?>'),
            drawCallback: function() { bindActions(); }
        });

        function bindActions() {
            $('.btn-toggle-widget').off('click').on('click', function() {
                var id = $(this).data('id');
                $.post('<?php echo site_url('backend/dashboard-widgets/toggle/') ?>' + id, {
                    '<?php echo csrf_token() ?>': '<?php echo csrf_hash() ?>'
                }, function(r) {
                    showToast(r.message);
                    table.ajax.reload(null, false);
                }, 'json');
            });

            $('.btn-delete-widget').off('click').on('click', function() {
                var id = $(this).data('id');
                Swal.fire({
                    title: '<?php echo lang('Backend.areYouSure') ?>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#e53e3e',
                    confirmButtonText: '<?php echo lang('Backend.delete') ?>',
                    cancelButtonText: '<?php echo lang('Backend.cancel') ?>'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        $.post('<?php echo site_url('backend/dashboard-widgets/delete/') ?>' + id, {
                            '<?php echo csrf_token() ?>': '<?php echo csrf_hash() ?>'
                        }, function(r) {
                            showToast(r.message, r.status === 'success' ? 'success' : 'error');
                            table.ajax.reload(null, false);
                        }, 'json');
                    }
                });
            });
        }
    });
</script>
<?php echo $this->endSection() ?>
