<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('title');
echo lang($title->pagename);
echo $this->endSection();
echo $this->section('head');
echo link_tag("be-assets/plugins/jquery-ui/jquery-ui.css");
echo link_tag("be-assets/plugins/jquery-ui/themes/smoothness/jquery-ui.min.css");
echo link_tag("be-assets/plugins/elFinder/css/elfinder.full.css");
echo $this->endSection();
echo $this->section('content'); ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><?php echo lang($title->pagename) ?></h1>
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
    <div class="card card-outline shadow-sm">
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
<?php echo $this->endSection();
echo $this->section('javascript');
echo script_tag("be-assets/plugins/jquery-ui/jquery-ui.js");
echo script_tag("be-assets/plugins/elFinder/js/elfinder.full.js");
echo script_tag("be-assets/plugins/elFinder/js/i18n/elfinder.tr.js");
echo script_tag("be-assets/plugins/elFinder/js/extras/editors.default.js"); ?>
<script type="text/javascript" {csp-script-nonce}>
    $(document).ready(function() {
        var i18nPath = '/be-assets/plugins/elFinder/js/i18n',
            start = function(lng) {
                $().ready(function() {
                    var elf = $('#elfinder').elfinder({
                        cssAutoLoad: [window.location.origin + '/be-assets/plugins/elfinder-material-theme/css/theme.css'],
                        baseUrl: 'media/',
                        url: '/backend/media/elfinderConnection',
                        height: 768,
                        workerBaseUrl: "/be-assets/plugins/elFinder/js/worker",
                        getFileCallback: function(file, fm) {
                            if (typeof top.elfinder_callback === 'function') {
                                top.elfinder_callback(file);
                                if (top.$ && typeof top.$.colorbox === 'function' && typeof top.$.colorbox.close === 'function') {
                                    top.$.colorbox.close();
                                }
                            } else {
                                fm.exec('quicklook');
                            }
                        },

                        soundPath: '/be-assets/plugins/elFinder/sounds',
                        sync: 1000,
                        handlers: {
                            upload: function() {
                                $('.elfinder-dialog-error').hide();
                            }
                        }
                    }).elfinder('intance');
                });
            },
            loct = window.location.search,
            full_lng, locm, lng;

        // detect language
        if (loct && (locm = loct.match(/lang=([a-zA-Z_-]+)/))) {
            full_lng = locm[1];
        } else {
            full_lng = (navigator.browserLanguage || navigator.language || navigator.userLanguage);
        }
        lng = full_lng.substr(0, 2);
        if (lng == 'ja') lng = 'jp';
        else if (lng == 'pt') lng = 'pt_BR';
        else if (lng == 'zh') lng = (full_lng.substr(0, 5) == 'zh-tw') ? 'zh_TW' : 'zh_CN';

        if (lng != 'en') {
            $.ajax({
                    url: i18nPath + '/elfinder.' + lng + '.js',
                    cache: true,
                    dataType: 'script'
                })
                .done(function() {
                    start(lng);
                })
                .fail(function() {
                    start('en');
                });
        } else {
            start(lng);
        }

    })
</script>
<?php echo $this->endSection() ?>
