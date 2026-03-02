<?php echo $this->extend('Modules\Backend\Views\base') ?>

<?php echo $this->section('title') ?>
<?php echo lang($title->pagename) ?>
<?php echo $this->endSection() ?>

<?php echo $this->section('head') ?>
<?php echo link_tag('be-assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') ?>
<?php echo link_tag('be-assets/plugins/datatables-responsive/css/responsive.bootstrap4.min.css') ?>
<?php echo link_tag('be-assets/plugins/datatables-buttons/css/buttons.bootstrap4.min.css') ?>
<?php echo link_tag('be-assets/plugins/dropzone/min/dropzone.min.css') ?>
<link rel="stylesheet" href="//code.jquery.com/ui/1.12.1/themes/base/jquery-ui.css">
<?php echo $this->endSection() ?>

<?php echo $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><?php echo lang($title->pagename) ?></h1>
            </div>
            <div class="col-sm-6">
                <div class="btn-group float-sm-right" role="group" aria-label="Basic example">
                    <a href="<?php echo route_to('uploadModule') ?>" class="btn btn-outline-success" data-toggle="modal" data-target="#modal-default">
                        <?php echo lang('Backend.add') ?>
                    </a>
                </div>
                <ol class="breadcrumb float-sm-right">
                    <li>

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
            <h3 class="card-title font-weight-bold"><?php echo lang($title->pagename) ?></h3>

            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">

        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->
    <div class="modal fade" id="modal-default">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h4 class="modal-title"><?php echo lang('Modules.uploadModule') ?></h4>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="card-body">
                        <div id="actions" class="row">
                            <div class="col-lg-12">
                                <div class="btn-group w-100">
                                    <span class="btn btn-success col fileinput-button">
                                        <i class="fas fa-plus"></i>
                                        <span><?php echo lang('Modules.addFiles') ?></span>
                                    </span>
                                    <button type="submit" class="btn btn-primary col start">
                                        <i class="fas fa-upload"></i>
                                        <span><?php echo lang('Modules.startUpload') ?></span>
                                    </button>
                                    <button type="reset" class="btn btn-warning col cancel">
                                        <i class="fas fa-times-circle"></i>
                                        <span><?php echo lang('Modules.cancelUpload') ?></span>
                                    </button>
                                </div>
                            </div>
                            <div class="col-lg-12 d-flex align-items-center">
                                <div class="fileupload-process w-100">
                                    <div id="total-progress" class="progress progress-striped active" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                                        <div class="progress-bar progress-bar-success" style="width:0%;" data-dz-uploadprogress></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="table table-striped files" id="previews">
                            <div id="template" class="row mt-2">
                                <div class="col-auto">
                                    <span class="preview"><img src="data:," alt="" data-dz-thumbnail /></span>
                                </div>
                                <div class="col d-flex align-items-center">
                                    <p class="mb-0">
                                        <span class="lead" data-dz-name></span>
                                        (<span data-dz-size></span>)
                                    </p>
                                    <strong class="error text-danger" data-dz-errormessage></strong>
                                </div>
                                <div class="col-4 d-flex align-items-center">
                                    <div class="progress progress-striped active w-100" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                                        <div class="progress-bar progress-bar-success" style="width:0%;" data-dz-uploadprogress></div>
                                    </div>
                                </div>
                                <div class="col-auto d-flex align-items-center">
                                    <div class="btn-group">
                                        <button class="btn btn-primary start">
                                            <i class="fas fa-upload"></i>
                                            <span><?php echo lang('Modules.start') ?></span>
                                        </button>
                                        <button data-dz-remove class="btn btn-warning cancel">
                                            <i class="fas fa-times-circle"></i>
                                            <span><?php echo lang('Backend.cancel') ?></span>
                                        </button>
                                        <button data-dz-remove class="btn btn-danger delete">
                                            <i class="fas fa-trash"></i>
                                            <span><?php echo lang('Backend.delete') ?></span>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-between">
                    <button type="button" class="btn btn-default" data-dismiss="modal"><?php echo lang('Backend.cancel') ?></button>
                </div>
            </div>
            <!-- /.modal-content -->
        </div>
        <!-- /.modal-dialog -->
    </div>
    <!-- /.modal -->
</section>
<!-- /.content -->
<?php echo $this->endSection() ?>

<?php echo $this->section('javascript') ?>
<?php echo script_tag("be-assets/plugins/dropzone/min/dropzone.min.js") ?>
<?php echo script_tag("be-assets/plugins/jquery-ui/jquery-ui.js") ?>
<?php echo script_tag('be-assets/plugins/datatables/jquery.dataTables.min.js') ?>
<?php echo script_tag('be-assets/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js') ?>
<?php echo script_tag('be-assets/plugins/datatables-responsive/js/dataTables.responsive.min.js') ?>
<?php echo script_tag('be-assets/plugins/datatables-responsive/js/responsive.bootstrap4.min.js') ?>
<?php echo script_tag('be-assets/plugins/datatables-buttons/js/dataTables.buttons.min.js') ?>
<?php echo script_tag('be-assets/plugins/datatables-buttons/js/buttons.bootstrap4.min.js') ?>
<?php echo script_tag('be-assets/plugins/datatables-buttons/js/buttons.html5.min.js') ?>
<?php echo script_tag('be-assets/plugins/datatables-buttons/js/buttons.print.min.js') ?>
<?php echo script_tag('be-assets/plugins/datatables-buttons/js/buttons.colVis.min.js') ?>
<script {csp-script-nonce}>
    Dropzone.autoDiscover = false;

    // Get the template HTML and remove it from the doumenthe template HTML and remove it from the doument
    var previewNode = document.querySelector("#template");
    previewNode.id = "";
    var previewTemplate = previewNode.parentNode.innerHTML;
    previewNode.parentNode.removeChild(previewNode);

    var myDropzone = new Dropzone(document.body, { // Make the whole body a dropzone
        url: "<?php echo route_to('moduleUpload') ?>", // Set the url
        thumbnailWidth: 80,
        thumbnailHeight: 80,
        parallelUploads: 20,
        uploadMultiple: false,
        paramName: "modules",
        acceptedFiles: "application/zip, application/octet-stream, application/x-zip-compressed, multipart/x-zip",
        previewTemplate: previewTemplate,
        autoQueue: false, // Make sure the files aren't queued until manually added
        previewsContainer: "#previews", // Define the container to display the previews
        clickable: ".fileinput-button" // Define the element that should be used as click trigger to select files.
    })
    myDropzone.on('successmultiple', function(files, response) {
        console.log(response);
        if (response.status == 'success')
            Swal.fire({
                icon: 'success',
                title: 'Yükleme Sonucu',
                text: response.message
            });
        if (response.status == 'error')
            Swal.fire({
                icon: 'error',
                title: 'Yükleme Sonucu',
                text: response.message
            });
        myDropzone.removeAllFiles(true);
    });
    myDropzone.on("addedfile", function(file) {
        // Hookup the start button
        file.previewElement.querySelector(".start").onclick = function() {
            myDropzone.enqueueFile(file)
        }
    })

    myDropzone.on("totaluploadprogress", function(progress) {
        document.querySelector("#total-progress .progress-bar").style.width = progress + "%"
    })

    myDropzone.on("sending", function(file) {
        // Show the total progress bar when upload starts
        document.querySelector("#total-progress").style.opacity = "1"
        // And disable the start button
        file.previewElement.querySelector(".start").setAttribute("disabled", "disabled")
    })

    myDropzone.on("queuecomplete", function(progress) {
        document.querySelector("#total-progress").style.opacity = "0"
    })

    document.querySelector("#actions .start").onclick = function() {
        myDropzone.enqueueFiles(myDropzone.getFilesWithStatus(Dropzone.ADDED))
    }
    document.querySelector("#actions .cancel").onclick = function() {
        myDropzone.removeAllFiles(true)
    }

    let table = $("#example1").DataTable({
        buttons: ["pageLength", {
            extend: 'colvis',
            text: '<?php echo lang('Backend.showColumns') ?>'
        }],
        responsive: true,
        lengthChange: false,
        autoWidth: false,
        processing: true,
        pageLength: 25,
        serverSide: true,
        language: {
            info: "",
            sEmptyTable: "<?php echo lang('Backend.emptyTable') ?>",
            sInfoEmpty: "<?php echo lang('Backend.noRecords') ?>",
            sLoadingRecords: "<?php echo lang('Backend.loadingRecords') ?>",
            sProcessing: "<?php echo lang('Backend.processing') ?>",
            sSearch: "<?php echo lang('Backend.search') ?>:",
            sZeroRecords: "<?php echo lang('Backend.noRecords') ?>",
            oPaginate: {
                sFirst: "<?php echo lang('Backend.first') ?>",
                sLast: "<?php echo lang('Backend.last') ?>",
                sNext: "<?php echo lang('Backend.next') ?>",
                sPrevious: "<?php echo lang('Backend.previous') ?>"
            },
            oAria: {
                sSortAscending: ": <?php echo lang('Backend.sortAscending') ?>",
                sSortDescending: ": <?php echo lang('Backend.sortDescending') ?>"
            },
            buttons: {
                pageLength: {
                    _: "<?php echo lang('Backend.showEntries') ?>",
                    '-1': "<?php echo lang('Backend.showAll') ?>"
                }
            }
        },
        lengthMenu: [10, 25, 50, {
            label: 'All',
            value: -1
        }],
        ajax: {
            url: '<?php echo route_to('modulesInstaller') ?>',
            type: 'post'
        },
        columns: [{
                data: 'id'
            }, {
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
<?php echo $this->endSection() ?>
