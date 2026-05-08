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
    <div class="card card-outline shadow-sm">
        <div class="card-header d-flex align-items-center">
            <h3 class="card-title font-weight-bold mb-0">
                <i class="fas fa-tags mr-2 text-primary"></i> <?php echo lang($title->pagename) ?>
            </h3>
            <div class="ml-auto d-flex">
                <a href="<?php echo route_to('categoryCreate') ?>" class="btn btn-sm btn-success px-3 d-flex align-items-center" style="border-radius:8px">
                    <?php echo lang('Backend.add') ?>
                </a>
                <button class="btn btn-sm btn-outline-secondary ml-2 d-flex align-items-center" id="btnRefresh" style="border-radius:8px" title="refresh">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="p-3">
                <table id="example1" class="table table-hover w-100">
                    <thead>
                        <tr>
                            <th><?php echo lang('Backend.title') ?></th>
                            <th style="text-align:right"><?php echo lang('Backend.transactions') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                    </tbody>
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
<script type="text/javascript" <?php echo csp_script_nonce(); ?>>
    var table;
    $(function() {
        table = $("#example1").DataTable({
            processing: true,
            serverSide: true,
            ordering: false,
            ajax: {
                url: '<?php echo route_to('categories') ?>',
                type: 'POST',
                data: (d) => {
                    d[CI4MS_CSRF.name] = CI4MS_CSRF.getHash();
                }
            },
            columns: [{
                    data: 'title'
                },
                {
                    data: 'actions',
                    className: 'text-right'
                }
            ],
            language: ci4msDtLanguage('Search categories...')
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
            cancelButtonColor: '#3085d6',
            confirmButtonText: '<?php echo lang('Backend.delete') ?>',
            cancelButtonText: '<?php echo lang('Backend.cancel') ?>'
        }).then((result) => {
            if (result.isConfirmed) {
                $.post('<?php echo route_to('categoryDelete') ?>', {
                    "id": id,
                    [CI4MS_CSRF.name]: CI4MS_CSRF.getHash()
                }, 'json').done(function(response) {
                    if (response.status == 'success') {
                        if (typeof showToast !== 'undefined') {
                            showToast(response.message);
                            table.ajax.reload();
                        } else {
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
                        }
                    } else {
                        if (typeof showToast !== 'undefined') {
                            showToast(response.message, 'error');
                        } else {
                            Swal.fire({
                                title: '<?php echo lang('Backend.error') ?>',
                                text: response.message,
                                icon: 'error',
                                confirmButtonText: '<?php echo lang('Backend.ok') ?>'
                            });
                        }
                    }
                });
            }
        });
    }
</script>
<?php echo $this->endSection() ?>
