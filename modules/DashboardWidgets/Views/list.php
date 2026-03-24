<?php echo $this->extend($backConfig->viewLayout) ?>
<?php echo $this->section('title') ?>
<?php echo lang($title->pagename) ?>
<?php echo $this->endSection() ?>

<?php echo $this->section('head') ?>
<?php echo link_tag('be-assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') ?>
<?php echo link_tag('be-assets/plugins/datatables-responsive/css/responsive.bootstrap4.min.css') ?>
<?php echo link_tag('be-assets/plugins/datatables-buttons/css/buttons.bootstrap4.min.css') ?>
<?php echo $this->endSection() ?>
<?php echo $this->section('content') ?>
<section class="content pt-3">
    <div class="card card-outline card-primary shadow-sm">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-th-large mr-2"></i><?php echo lang('DashboardWidgets.manageWidgets') ?></h3>
            <div class="card-tools">
                <a href="<?php echo site_url('backend/dashboard-widgets/seed') ?>" class="btn btn-sm btn-outline-info mr-1" id="btnSeed">
                    <i class="fas fa-database mr-1"></i><?php echo lang('DashboardWidgets.seedDefaults') ?>
                </a>
                <a href="<?php echo site_url('backend/dashboard-widgets/create') ?>" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus mr-1"></i><?php echo lang('DashboardWidgets.createWidget') ?>
                </a>
            </div>
        </div>
        <div class="card-body table-responsive p-0">
            <table id="widgetsTable" class="table table-hover table-striped" style="width:100%">
                <thead>
                    <tr>
                        <th width="40"><?php echo lang('Backend.id') ?></th>
                        <th><?php echo lang('Backend.title') ?></th>
                        <th><?php echo lang('DashboardWidgets.slug') ?></th>
                        <th width="80"><?php echo lang('DashboardWidgets.type') ?></th>
                        <th width="100"><?php echo lang('DashboardWidgets.defaultSize') ?></th>
                        <th width="120"><?php echo lang('Backend.status') ?></th>
                        <th width="120"><?php echo lang('Backend.actions') ?></th>
                    </tr>
                </thead>
            </table>
        </div>
    </div>
</section>
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
<script type="text/javascript" {csp-script-nonce}>
    $(function() {
        var table = $('#widgetsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '<?php echo site_url('backend/dashboard-widgets') ?>',
                type: 'POST',
                data: function(d) {
                    d['<?php echo csrf_token() ?>'] = '<?php echo csrf_hash() ?>';
                }
            },
            columns: [{
                    data: 0
                }, {
                    data: 1
                }, {
                    data: 2
                }, {
                    data: 3
                },
                {
                    data: 4
                }, {
                    data: 5,
                    orderable: false
                },
                {
                    data: 6,
                    orderable: false,
                    searchable: false
                }
            ],
            order: [
                [0, 'asc']
            ],
            language: {
                url: '<?php echo site_url('be-assets/plugins/datatables/i18n/' . service('request')->getLocale() . '.json') ?>'
            },
            drawCallback: function() {
                bindActions();
            }
        });

        function bindActions() {
            $('.btn-toggle-widget').off('click').on('click', function() {
                var id = $(this).data('id');
                $.post('<?php echo site_url('backend/dashboard-widgets/toggle/') ?>' + id, {
                    '<?php echo csrf_token() ?>': '<?php echo csrf_hash() ?>'
                }, function() {
                    table.ajax.reload(null, false);
                }, 'json');
            });

            $('.btn-delete-widget').off('click').on('click', function() {
                var id = $(this).data('id');
                Swal.fire({
                    title: '<?php echo lang('Backend.deleteConfirmTitle') ?>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#d33',
                    confirmButtonText: '<?php echo lang('Backend.deleteConfirmBtn') ?>',
                    cancelButtonText: '<?php echo lang('Backend.cancel') ?>'
                }).then(function(result) {
                    if (result.isConfirmed) {
                        $.post('<?php echo site_url('backend/dashboard-widgets/delete/') ?>' + id, {
                            '<?php echo csrf_token() ?>': '<?php echo csrf_hash() ?>'
                        }, function(r) {
                            Swal.fire({
                                title: r.message,
                                icon: r.status === 'success' ? 'success' : 'error',
                                timer: 2000
                            });
                            table.ajax.reload(null, false);
                        }, 'json');
                    }
                });
            });
        }
    });
</script>
<?php echo $this->endSection() ?>
