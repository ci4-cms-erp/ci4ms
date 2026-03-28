<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('title');
echo lang($title->pagename);
echo $this->endSection();
echo $this->section('head');
echo link_tag("be-assets/plugins/jquery-ui/jquery-ui.css");
echo link_tag("be-assets/plugins/jquery-ui/themes/smoothness/jquery-ui.min.css");
echo link_tag("be-assets/plugins/elFinder/css/elfinder.full.css"); ?>
<style {csp-style-nonce}>
/* elFinder Adjustments */
#elfinder { border: none !important; border-radius: 0 0 15px 15px; overflow: hidden; }
.elfinder-navbar { background-color: #f8fafc !important; border-right: 1px solid #edf2f7 !important; }
.elfinder-toolbar { background-image: none !important; background-color: #fff !important; border-bottom: 1px solid #edf2f7 !important; padding: 10px !important; }
.elfinder-button { background-image: none !important; border-radius: 6px !important; border-color: #e2e8f0 !important; }
.elfinder-button:hover { background-color: #edf2f7 !important; }
</style>
<?php echo $this->endSection();
echo $this->section('content'); ?>

<section class="content pt-3">
    <div class="card premium-card">
        <div class="card-header d-flex align-items-center">
            <h3 class="card-title font-weight-bold mb-0">
                <i class="fas fa-photo-video mr-2 text-primary"></i> <?php echo lang('Media.media') ?>
            </h3>
        </div>
        <div class="card-body p-0">
            <div id="elfinder"></div>
        </div>
    </div>
</section>

<?php echo $this->endSection();
echo $this->section('javascript');
echo script_tag("be-assets/plugins/jquery-ui/jquery-ui.js");
echo script_tag("be-assets/plugins/elFinder/js/elfinder.full.js");
echo script_tag("be-assets/plugins/elFinder/js/i18n/elfinder." . env('app.defaultLocale', 'tr') . ".js");
echo script_tag("be-assets/plugins/elFinder/js/extras/editors.default.js"); ?>
<script type="text/javascript" {csp-script-nonce}>
    $(document).ready(function() {
        var elf = $('#elfinder').elfinder({
            cssAutoLoad: [window.location.origin + '/be-assets/css/ci4ms-elfinder.css?v=<?php echo time(); ?>'],
            baseUrl: 'media/',
            url: '/backend/media/elfinderConnection?<?php echo csrf_token() ?>=<?php echo csrf_hash() ?>',
            height: 700,
            lang: window.CI4MS_LOCALE || 'tr',
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
        }).elfinder('instance');
    });
</script>
<?php echo $this->endSection() ?>
