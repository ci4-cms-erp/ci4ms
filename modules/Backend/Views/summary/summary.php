<?= $this->extend('Modules\Backend\Views\base') ?>

<?= $this->section('title') ?>
<?= lang('Backend.' . $title->pagename) ?>
<?= $this->endSection() ?>

<?= $this->section('head') ?>
<?= link_tag('be-assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') ?>
<?= link_tag('be-assets/plugins/datatables-responsive/css/responsive.bootstrap4.min.css') ?>
<?= link_tag('be-assets/plugins/datatables-buttons/css/buttons.bootstrap4.min.css') ?>
<?= link_tag("be-assets/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css") ?>
<?= link_tag("be-assets/plugins/daterangepicker/daterangepicker.css") ?>
<?= link_tag("be-assets/plugins/tempusdominus-bootstrap-4/css/tempusdominus-bootstrap-4.min.css") ?>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><?= lang('Backend.' . $title->pagename) ?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <button type="button" class="btn btn-outline-success"><i
                                class="fas fa-file-upload"></i> Dosya Yükle
                    </button>
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
        <div class="row">
            <div class="card-body col-lg-6">
                <?= view('Modules\Auth\Views\_message_block') ?>
                <h3 class="font-weight-bold">Yatırım Listesi</h3>
                <form action="<?= route_to('summary') ?>" class="form-row">
                    <div class="form-group col-md-4">
                        <label for="">Tarih Aralığı</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" id="basic-addon1"><i
                                            class="far fa-calendar-alt"></i></span>
                            </div>
                            <input type="text" class="form-control" name="inv_daterange" id="reservation"
                                   value="<?= (!empty($req->daterange)) ? $req->inv_daterange : '' ?>">
                        </div>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="">Site</label>
                        <select name="inv_website" class="form-control">
                            <option value=""></option>
                            <?php foreach ($websites as $website) { ?>
                                <option value="<?= $website->id ?>" <?= (!empty($req->inv_website) && (int)$req->inv_website == (int)$website->id) ? 'selected' : '' ?>><?= $website->site_name ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="">Yöntem</label>
                        <select name="inv_methods" class="form-control">
                            <option value=""></option>
                            <?php foreach ($methods as $method) { ?>
                                <option value="<?= $method->id ?>" <?= (!empty($req->inv_methods) && (int)$req->inv_methods == (int)$method->id) ? 'selected' : '' ?>><?= $method->method_name ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group col-12">
                        <button class="btn btn-primary float-right">Arama Yap</button>
                    </div>
                </form>
                <hr class="w-100">
                <div class="row">
                    <div class="col-12">
                        <table id="example1" class="table table-bordered table-striped">
                            <thead>
                            <tr>
                                <th></th>
                                <th>Kullanıcı Adı</th>
                                <th>Site</th>
                                <th>Yatırım Tarihi</th>
                                <th>Miktar</th>
                                <th>Yöntem</th>
                            </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="card-body col-lg-6">
                <h3 class="font-weight-bold">Çekim Listesi</h3>
                <form action="<?= route_to('summary') ?>" class="form-row">
                    <div class="form-group col-md-4">
                        <label for="">Tarih Aralığı</label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text" id="basic-addon1"><i
                                            class="far fa-calendar-alt"></i></span>
                            </div>
                            <input type="text" class="form-control" name="wd_daterange" id="reservation1"
                                   value="<?= (!empty($req->wd_daterange)) ? $req->wd_daterange : '' ?>">
                        </div>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="">Site</label>
                        <select name="wd_website" class="form-control">
                            <option value=""></option>
                            <?php foreach ($websites as $website) { ?>
                                <option value="<?= $website->id ?>" <?= (!empty($req->wd_daterange) && (int)$req->wd_daterange == (int)$website->id) ? 'selected' : '' ?>><?= $website->site_name ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group col-md-4">
                        <label for="">Yöntem</label>
                        <select name="wd_methods" class="form-control">
                            <option value=""></option>
                            <?php foreach ($methods as $method) { ?>
                                <option value="<?= $method->id ?>" <?= (!empty($req->wd_daterange) && (int)$req->wd_daterange == (int)$method->id) ? 'selected' : '' ?>><?= $method->method_name ?></option>
                            <?php } ?>
                        </select>
                    </div>
                    <div class="form-group col-12">
                        <button class="btn btn-primary float-right">Arama Yap</button>
                    </div>
                </form>
                <hr class="w-100">
                <div class="row">
                    <div class="col-12">
                        <table id="example" class="table table-bordered table-striped">
                            <thead>
                            <tr>
                                <th></th>
                                <th>Kullanıcı Adı</th>
                                <th>Site</th>
                                <th>Yatırım Tarihi</th>
                                <th>Miktar</th>
                                <th>Yöntem</th>
                            </tr>
                            </thead>
                            <tbody>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <div class="card-body col-lg-12">
                <form action="" class="form-row repeater">
                    <div data-repeater-list="expense" class="col-md-12 form-group">
                        <div class="row form-group" data-repeater-item>
                            <div class="col-6">
                                <input type="text" name="expense_name" class="form-control" placeholder="Masraf Adı">
                            </div>
                            <div class="col-5">
                                <input type="text" name="expense_amount" class="form-control">
                            </div>
                            <div class="col-1">
                                <input data-repeater-delete type="button" class="btn btn-danger w-100"
                                       value="<?=lang('Backend.deleteText')?>"/>
                            </div>
                        </div>
                    </div>
                    <div class="row col-12 form-group">
                        <div class="col-md-6 form-group">
                            <input data-repeater-create type="button" class="btn btn-secondary"
                                   value="<?=lang('Backend.addText')?>"/>
                        </div>
                        <div class="col-md-6 form-group">
                            <button class="btn btn-success float-right">Seçilen tarih rapor al</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->

</section>
<!-- /.content -->
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>
<?= script_tag('be-assets/plugins/sweetalert2/sweetalert2.min.js') ?>
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
<?= script_tag('be-assets/plugins/moment/moment.min.js') ?>
<?= script_tag('be-assets/plugins/inputmask/jquery.inputmask.min.js') ?>
<?= script_tag('be-assets/plugins/daterangepicker/daterangepicker.js') ?>
<?= script_tag("be-assets/node_modules/jquery.repeater/jquery.repeater.js") ?>
<script>
    var table = $("#example1").DataTable({
        responsive: true, lengthChange: false, autoWidth: false,
        buttons: ["pageLength", "excel", "pdf"],
        processing: true, pageLength: 10, serverSide: true,
        ordering: false, "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
        ajax: {
            url: '<?=route_to('summary_render')?>',
            type: 'POST',
            data: {
                daterange: '<?=(!empty($req->daterange)) ? $req->daterange : ''?>',
                website: '<?=(!empty($req->website)) ? $req->website : ''?>',
                methods: '<?=(!empty($req->methods)) ? $req->methods : ''?>',
                inv: 1
            }
        },
        columns: [
            {data: 'id'},
            {data: 'user_name'},
            {data: 'website'},
            {data: 'investment_history'},
            {data: 'amount'},
            {data: 'method'}
        ],
        initComplete: function () {
            table.buttons().container().appendTo($('.col-md-6:eq(0)', table.table().container()));
        }
    });

    var table1 = $("#example").DataTable({
        responsive: true, lengthChange: false, autoWidth: false,
        buttons: ["pageLength", "excel", "pdf"],
        processing: true, pageLength: 10, serverSide: true,
        ordering: false, "lengthMenu": [[10, 25, 50, -1], [10, 25, 50, "All"]],
        ajax: {
            url: '<?=route_to('summary_render')?>',
            type: 'POST',
            data: {
                daterange: '<?=(!empty($req->daterange)) ? $req->daterange : ''?>',
                website: '<?=(!empty($req->website)) ? $req->website : ''?>',
                methods: '<?=(!empty($req->methods)) ? $req->methods : ''?>',
                withdraw: 1
            }
        },
        columns: [
            {data: 'id'},
            {data: 'user_name'},
            {data: 'website'},
            {data: 'withdraw_history'},
            {data: 'amount'},
            {data: 'method'}
        ],
        initComplete: function () {
            table1.buttons().container().appendTo($('.col-md-6:eq(0)', table1.table().container()));
        }
    });

    $('#reservation').daterangepicker({
        <?php if(!empty($req->daterange)) {
        $date = explode(' - ', $req->daterange);
        ?>
        startDate: '<?=$date[0]?>',
        endDate: '<?=$date[1]?>',
        <?php }?>
        autoUpdateInput: false,
        locale: {
            format: "DD/MM/YYYY",
            cancelLabel: 'Clear'
        }
    }).on('apply.daterangepicker', function (ev, picker) {$(this).val(picker.startDate.format('DD/MM/YYYY') + ' - ' + picker.endDate.format('DD/MM/YYYY'));})
        .on('cancel.daterangepicker', function (ev, picker) {$(this).val('');});

    $('#reservation1').daterangepicker({
        <?php if(!empty($req->daterange)) {
        $date = explode(' - ', $req->daterange);
        ?>
        startDate: '<?=$date[0]?>',
        endDate: '<?=$date[1]?>',
        <?php }?>
        autoUpdateInput: false,
        locale: {
            format: "DD/MM/YYYY",
            cancelLabel: 'Clear'
        }
    }).on('apply.daterangepicker', function (ev, picker) {$(this).val(picker.startDate.format('DD/MM/YYYY') + ' - ' + picker.endDate.format('DD/MM/YYYY'));})
        .on('cancel.daterangepicker', function (ev, picker) {$(this).val('');});

    $('.repeater').repeater({
        show: function () {
            $(this).slideDown();
        },
        hide: function (deleteElement) {
            Swal.fire({
                title: 'Silmek istediğinizden emin misiniz?',
                showCancelButton: true,
                confirmButtonText: `Sil`,
                cancelButtonText: `Vazgeç`,
            }).then((result) => {
                /* Read more about isConfirmed, isDenied below */
                if (result.isConfirmed) {
                    $(this).slideUp(deleteElement);
                    Swal.fire('Silindi!', '', 'success');
                }
            })
        }
    });
</script>
<?= $this->endSection() ?>
