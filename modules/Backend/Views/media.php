<?= $this->extend('Modules\Backend\Views\base') ?>

<?= $this->section('title') ?>
<?= lang('Backend.' . $title->pagename) ?>
<?= $this->endSection() ?>

<?= $this->section('head') ?>
<?= link_tag("be-assets/plugins/jquery-ui/jquery-ui.css") ?>
<link rel="stylesheet" type="text/css"
      href="//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css">
<?= link_tag("be-assets/plugins/elFinder/css/elfinder.full.css") ?>
<?= link_tag("be-assets/plugins/elFinder/css/theme.css") ?>
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
                <ol class="breadcrumb float-sm-right"></ol>
            </div>
        </div>
    </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">

    <!-- Default box -->
    <div class="card card-outline card-shl">
        <div class="card-header">
            <h3 class="card-title font-weight-bold">Media</h3>

            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <div id="elfinder"></div>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->

</section>
<!-- /.content -->
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>
<?= script_tag("be-assets/plugins/jquery-ui/jquery-ui.js") ?>
<?= script_tag("be-assets/plugins/elFinder/js/elfinder.full.js") ?>
<?= script_tag("be-assets/plugins/elFinder/js/i18n/elfinder.tr.js") ?>
<?= script_tag("be-assets/plugins/elFinder/js/extras/editors.default.js") ?>
<script type="text/javascript" charset="utf-8">
    $(document).ready(function () {
        $('#elfinder').elfinder(
            {
                url: '/be-assets/plugins/elFinder/php/connector.minimal.php',  // connector URL (REQUIRED)
                cssAutoLoad: [window.location.origin + '/be-assets/node_modules/elfinder-material-theme/Material/css/theme-gray.css'],
                height: 768
            }
        );
    });
</script>
<?= $this->endSection() ?>
