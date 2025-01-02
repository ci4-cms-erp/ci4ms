<?= $this->extend('Modules\Backend\Views\base') ?>

<?= $this->section('title') ?>
<?=lang('Backend.'.$title->pagename)?>
<?= $this->endSection() ?>

<?= $this->section('head') ?>
<?= link_tag("be-assets/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css")?>
<?= link_tag('be-assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') ?>
<?= link_tag('be-assets/plugins/datatables-responsive/css/responsive.bootstrap4.min.css') ?>
<?= link_tag('be-assets/plugins/datatables-buttons/css/buttons.bootstrap4.min.css') ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><?=lang('Backend.'.$title->pagename)?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                <li>
                <a href="<?= route_to('updateRouteFile') ?>" class="btn bg-purple">
                        <i class="far fa-folder"></i> Dosya Düzenleyici
                    </a>
                </li>
                <li>
                <a href="<?= route_to('methodCreate') ?>" class="btn btn-outline-success">
                        <?=lang('Backend.add')?>
                    </a>
                </li>
                </ol>
            </div>
        </div>
    </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">

    <!-- Default box -->
    <div class="card card-outline card-shl">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><?= lang('Backend.' . $title->pagename) ?></h3>

            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <?= view('Modules\Auth\Views\_message_block') ?>
            <table id="example1" class="table table-bordered table-hover">
                <thead>
                    <tr>
                        <td>id</td>
                        <td>Sayfa Adı</td>
                        <td>Açıklama</td>
                        <td>Controller</td>
                        <td>Method Adı</td>
                        <td>Sef Link</td>
                        <td>Yetki</td>
                        <td>İşlemler</td>
                    </tr>
                </thead>
            </table>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->

</section>
<!-- /.content -->
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>
<?= script_tag("be-assets/plugins/sweetalert2/sweetalert2.min.js")?>
<?= script_tag('be-assets/plugins/datatables/jquery.dataTables.min.js') ?>
<?= script_tag('be-assets/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') ?>
<?= script_tag('be-assets/plugins/datatables-responsive/js/dataTables.responsive.min.js') ?>
<?= script_tag('be-assets/plugins/datatables-responsive/js/responsive.bootstrap4.min.js') ?>
<?= script_tag('be-assets/plugins/datatables-buttons/js/dataTables.buttons.min.js') ?>
<?= script_tag('be-assets/plugins/datatables-buttons/js/buttons.bootstrap4.min.js') ?>
<?= script_tag('be-assets/plugins/datatables-buttons/js/buttons.html5.min.js') ?>
<?= script_tag('be-assets/plugins/datatables-buttons/js/buttons.print.min.js') ?>
<?= script_tag('be-assets/plugins/datatables-buttons/js/buttons.colVis.min.js') ?>
<script>
    let table = $("#example1").DataTable({
        buttons: ["pageLength", {
            extend: 'colvis',
            text: 'Sütunlar'
        }],
        responsive: true,
        lengthChange: false,
        autoWidth: false,
        processing: true,
        pageLength: 25,
        serverSide: true,
        language: {
            info: "",
            sEmptyTable: "Tabloda herhangi bir veri mevcut değil",
            sInfoEmpty: "Kayıt yok",
            sLoadingRecords: "Yükleniyor...",
            sProcessing: "İşleniyor...",
            sSearch: "Ara:",
            sZeroRecords: "Eşleşen kayıt bulunamadı",
            oPaginate: {
                sFirst: "İlk",
                sLast: "Son",
                sNext: "Sonraki",
                sPrevious: "Önceki"
            },
            oAria: {
                sSortAscending: ": artan sütun sıralamasını aktifleştir",
                sSortDescending: ": azalan sütun sıralamasını aktifleştir"
            },
            buttons: {
                pageLength: {
                    _: "%d Satır Göster",
                    '-1': "Tümünü Göster"
                }
            }
        },
        lengthMenu: [
            [25, 50, 100, -1],
            [25, 50, 100, "Tümü"]
        ],
        ajax: {
            url: '<?= route_to('products') ?>',
            type: 'post'
        },
        columns: [{data:'id'},{
                data: 'pagename',
                orderable: false
            }, {
                data: 'description',
                orderable: false
            }, {
                data: 'className',
                orderable: false
            },
            {
                data: 'methodName',
                orderable: false
            }, {
                data: 'sefLink',
                orderable: false
            },
            {
                data: 'typeOfPermissions',
                orderable: false
            },
            {
                data: 'actions',
                orderable: false
            }
        ],
        initComplete: function() {
            table.buttons().container().appendTo($('.col-md-6:eq(0)', table.table().container()));
        }
    });
</script>
<?= $this->endSection() ?>
