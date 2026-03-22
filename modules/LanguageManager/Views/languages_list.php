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
<!-- Main content -->
<section class="content pt-3">
    <div class="card card-outline card-primary shadow-sm">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-globe mr-2"></i><?php echo lang('LanguageManager.languages') ?></h3>
            <div class="card-tools">
                <a href="<?php echo site_url('backend/language-manager/translations') ?>" class="btn btn-sm btn-outline-info mr-1"><i class="fas fa-language mr-1"></i><?php echo lang('LanguageManager.translations') ?></a>
                <a href="<?php echo site_url('backend/language-manager/languages/create') ?>" class="btn btn-sm btn-primary"><i class="fas fa-plus mr-1"></i><?php echo lang('LanguageManager.createLanguage') ?></a>
            </div>
        </div>
        <div class="card-body table-responsive p-0">
            <table id="langsTable" class="table table-hover table-striped" style="width:100%">
                <thead>
                    <tr>
                        <th width="40"><?php echo lang('LanguageManager.id') ?></th>
                        <th><?php echo lang('LanguageManager.code') ?></th>
                        <th><?php echo lang('LanguageManager.name') ?></th>
                        <th><?php echo lang('LanguageManager.nativeName') ?></th>
                        <th width="60"><?php echo lang('LanguageManager.direction') ?></th>
                        <th width="60"><?php echo lang('LanguageManager.sortOrder') ?></th>
                        <th width="140"><?php echo lang('LanguageManager.status') ?></th>
                        <th width="140"><?php echo lang('LanguageManager.actions') ?></th>
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
        var table = $('#langsTable').DataTable({
            processing: true,
            serverSide: true,
            ajax: {
                url: '<?php echo site_url('backend/language-manager/languages') ?>',
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
            }, {
                data: 4
            }, {
                data: 5
            }, {
                data: 6,
                orderable: false
            }, {
                data: 7,
                orderable: false,
                searchable: false
            }],
            order: [
                [5, 'asc']
            ],
            language: {
                url: '<?php echo site_url('be-assets/plugins/datatables/i18n/' . service('request')->getLocale() . '.json') ?>'
            },
            drawCallback: function() {
                $('.btn-toggle-lang').off('click').on('click', function() {
                    $.post('<?php echo site_url('backend/language-manager/languages/toggle/') ?>' + $(this).data('id'), {
                        '<?php echo csrf_token() ?>': '<?php echo csrf_hash() ?>'
                    }, function() {
                        table.ajax.reload(null, false);
                    }, 'json');
                });
                $('.btn-set-default').off('click').on('click', function() {
                    $.post('<?php echo site_url('backend/language-manager/languages/set-default/') ?>' + $(this).data('id'), {
                        '<?php echo csrf_token() ?>': '<?php echo csrf_hash() ?>'
                    }, function(r) {
                        Swal.fire({
                            title: r.message,
                            icon: 'success',
                            timer: 1500
                        });
                        table.ajax.reload(null, false);
                    }, 'json');
                });
                $('.btn-delete-lang').off('click').on('click', function() {
                    var id = $(this).data('id');
                    Swal.fire({
                        title: '<?php echo lang('LanguageManager.deleteConfirmTitle') ?>',
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#d33',
                        confirmButtonText: '<?php echo lang('LanguageManager.deleteConfirmBtn') ?>',
                        cancelButtonText: '<?php echo lang('LanguageManager.cancel') ?>'
                    }).then(function(r) {
                        if (r.isConfirmed) {
                            $.post('<?php echo site_url('backend/language-manager/languages/delete/') ?>' + id, {
                                '<?php echo csrf_token() ?>': '<?php echo csrf_hash() ?>'
                            }, function(r) {
                                table.ajax.reload(null, false);
                            }, 'json');
                        }
                    });
                });
            }
        });
    });
</script>
<?php echo $this->endSection() ?>
