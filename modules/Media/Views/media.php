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
echo script_tag("be-assets/plugins/elFinder/js/elfinder.full.js?v=2.1.67");
echo script_tag("be-assets/plugins/elFinder/js/i18n/elfinder." . env('app.defaultLocale', 'tr') . ".js?v=2.1.67");
echo script_tag("be-assets/plugins/elFinder/js/extras/editors.default.js?v=2.1.67"); ?>
<script type="text/javascript" <?php echo csp_script_nonce(); ?>>
    $(document).ready(function() {
        var elf = $('#elfinder').elfinder({
            cssAutoLoad: [window.location.origin + '/be-assets/css/ci4ms-elfinder.css'],
            baseUrl: 'media/',
            url: '/backend/media/elfinderConnection',
            requestType: 'post',
            height: 700,
            lang: window.CI4MS_LOCALE !== 'en' ? (window.CI4MS_LOCALE || 'tr') : 'en',
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
