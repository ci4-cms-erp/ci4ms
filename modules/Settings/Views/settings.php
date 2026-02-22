<?php echo $this->extend('Modules\Backend\Views\base') ?>

<?php echo $this->section('title') ?>
<?php echo lang($title->pagename) ?>
<?php echo $this->endSection() ?>

<?php echo $this->section('head') ?>
<?php echo link_tag("be-assets/plugins/jquery-ui/jquery-ui.css") ?>
<link rel="stylesheet" type="text/css"
    href="//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css">
<?php echo link_tag("be-assets/plugins/elFinder/css/elfinder.full.css") ?>
<?php echo link_tag("be-assets/plugins/elFinder/css/theme.css") ?>
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
            <h3 class="card-title font-weight-bold"><?php echo lang('Settings.siteSettings') ?></h3>

            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-3">
                    <div class="nav flex-column nav-tabs h-100" id="vert-tabs-tab" role="tablist"
                        aria-orientation="vertical">
                        <a class="nav-link active" id="vert-tabs-home-tab" data-toggle="pill" href="#vert-tabs-home"
                            role="tab" aria-controls="vert-tabs-home"
                            aria-selected="true"><?php echo lang('Settings.companyInfos') ?></a>
                        <a class="nav-link" id="vert-tabs-templates-tab" data-toggle="pill"
                            href="#vert-tabs-templates"
                            role="tab" aria-controls="vert-tabs-templates"
                            aria-selected="false"><?php echo lang('Settings.templateSelect') ?></a>
                        <a class="nav-link" id="vert-tabs-social-tab" data-toggle="pill" href="#vert-tabs-social"
                            role="tab" aria-controls="vert-tabs-social"
                            aria-selected="false"><?php echo lang('Settings.socialMedia') ?></a>
                        <a class="nav-link" id="vert-tabs-mailSettings-tab" data-toggle="pill"
                            href="#vert-tabs-mailSettings"
                            role="tab" aria-controls="vert-tabs-mailSettings"
                            aria-selected="false"><?php echo lang('Settings.mailSettings') ?></a>
                        <a class="nav-link" id="vert-tabs-media-tab" data-toggle="pill"
                            href="#vert-tabs-media"
                            role="tab" aria-controls="vert-tabs-media"
                            aria-selected="false"><?php echo lang('Media.media') ?></a>
                    </div>
                </div>
                <div class="col-md-9">
                    <div class="tab-content" id="vert-tabs-tabContent">
                        <div class="tab-pane text-left fade active show" id="vert-tabs-home" role="tabpanel"
                            aria-labelledby="vert-tabs-home-tab">
                            <form action="<?php echo route_to('compInfosPost') ?>" method="post" class="form-row">
                                <?php echo csrf_field() ?>
                                <div class="col-md-6 form-group">
                                    <label for=""><?php echo lang('Settings.companyName') ?></label>
                                    <input type="text" name="cName" class="form-control"
                                        value="<?php echo old('cName', (!empty($settings->siteName)) ? esc($settings->siteName) : '') ?>">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for=""><?php echo lang('Settings.companySlogan') ?></label>
                                    <input type="text" name="cSlogan" class="form-control"
                                        value="<?php echo old('cSlogan', (!empty($settings->slogan)) ? esc($settings->slogan) : '') ?>">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for=""><?php echo lang('Settings.companyAddress') ?></label>
                                    <input type="text" name="cAddress" class="form-control"
                                        value="<?php echo old('cAddress', (!empty($settings->contact->address)) ? esc($settings->contact->address) : '') ?>">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for=""><?php echo lang('Settings.companyPhone') ?></label>
                                    <input type="text" name="cPhone" class="form-control"
                                        value="<?php echo old('cPhone', (!empty($settings->contact->phone)) ? esc($settings->contact->phone) : '') ?>">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for=""><?php echo lang('Settings.companyGsm') ?></label>
                                    <input type="text" name="cGSM" class="form-control"
                                        value="<?php echo old('cGSM', (!empty($settings->contact->gsm)) ? esc($settings->contact->gsm) : '') ?>">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for=""><?php echo lang('Settings.companyEmail') ?></label>
                                    <input type="text" name="cMail" class="form-control"
                                        value="<?php echo old('cMail', (!empty($settings->contact->email)) ? esc($settings->contact->email) : '') ?>">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for=""><?php echo lang('Settings.gmapIframe') ?></label>
                                    <input type="text" name="cMap" class="form-control"
                                        value='<?php echo (!empty($settings->map_iframe)) ? $settings->map_iframe : '' ?>'>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for=""><?php echo lang('Settings.companyLogo') ?></label>
                                    <button type="button"
                                        class="pageIMG btn btn-info w-100"><?php echo lang('Backend.selectCoverImg') ?></button>
                                    <input hidden class="pageimg-input" name="cLogo">
                                </div>
                                <div class="col-md-6 form-group rounded bg-dark p-3">
                                    <img src="<?php echo (!empty($settings->logo)) ? esc($settings->logo) : '' ?>"
                                        class="img-fluid pageimg">
                                </div>
                                <div class="col-md-12 form-group">
                                    <button class="btn btn-success float-right mt-5"><?php echo lang('Backend.update') ?></button>
                                </div>
                            </form>
                            <div class="w-100">
                                <label><?php echo lang('Settings.maintenanceMode') ?></label>
                                <input type="checkbox" name="my-checkbox" id="my-checkbox"
                                    class="bswitch" <?php echo ((bool)$settings->maintenanceMode->scalar === true) ? 'checked' : '' ?>
                                    data-id="maintenanceMode" data-off-color="danger" data-on-color="success">
                            </div>
                        </div>
                        <div class="tab-pane fade" id="vert-tabs-templates" role="tabpanel"
                            aria-labelledby="vert-tabs-templates-tab">
                            <div class="row">
                                <div class="col-12 mb-2"><a href="<?php echo route_to('backendThemes') ?>" class="btn btn-outline-success float-right">Tema Ekle</a></div>
                                <hr class="w-100">
                                <?php foreach ($templates as $key => $template):
                                    $arrContextOptions = [];
                                    if ($request->getServer('HTTPS') == 'on') $arrContextOptions = ["ssl" => ["verify_peer" => false, "verify_peer_name" => false,]];
                                    if (is_file(ROOTPATH . 'public/templates/' . $key . 'info.xml') === true):
                                        $data = simplexml_load_string(file_get_contents(ROOTPATH . 'public/templates/' . $key . 'info.xml', false, stream_context_create($arrContextOptions)), 'SimpleXMLElement', LIBXML_NOCDATA); ?>
                                        <div class="col-md-4">
                                            <div class="card bg-light rounded <?php echo ($settings->templateInfos->path == $data->defPath) ? 'border border-success shadow' : '' ?>">
                                                <div class="car-img">
                                                    <img class="img-fluid"
                                                        src="<?php echo site_url('templates/' . $data->screenshotPNG) ?>">
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <h4><?php echo $data->templateName ?></h4>
                                                            <p>Coded By <?php echo $data->codedBy ?></p>
                                                        </div>
                                                        <div class="col-md-12">
                                                            <?php if ($settings->templateInfos->path != $data->defPath): ?>
                                                                <button class="btn btn-outline-success"
                                                                    id="<?php echo $data->defPath ?>" type="button"
                                                                    onclick="chooseTemplate('<?php echo $data->defPath ?>','<?php echo $data->templateName ?>')">
                                                                    Seç
                                                                </button>
                                                            <?php else: ?>
                                                                <a href="<?php echo route_to('templateSettings') ?>"
                                                                    class="btn btn-outline-primary">Ayarlar</a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                <?php endif;
                                endforeach; ?>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="vert-tabs-social" role="tabpanel"
                            aria-labelledby="vert-tabs-social-tab">
                            <form action="<?php echo route_to('socialMediaPost') ?>" class="repeater" method="post">
                                <?php echo csrf_field() ?>
                                <div data-repeater-list="socialNetwork" class="col-md-12">
                                    <?php if (!empty($settings->socialNetwork)):
                                        foreach ($settings->socialNetwork as $socialNetwork) : ?>
                                            <div class="row border-bottom" data-repeater-item>
                                                <div class="col-md-6 form-group">
                                                    <label for=""><?php echo lang('Settings.socialMedia') ?></label>
                                                    <input type="text" class="form-control" name="smName"
                                                        value="<?php echo esc($socialNetwork['smName']) ?>"
                                                        placeholder="facebook"
                                                        required>
                                                </div>
                                                <div class="col-md-5 form-group">
                                                    <label for=""><?php echo lang('Settings.socialMediaLink') ?></label>
                                                    <input type="text" class="form-control" name="link"
                                                        value="<?php echo $socialNetwork['link'] ?>" required>
                                                </div>
                                                <div class="col-md-1 form-group d-flex m-auto">
                                                    <button data-repeater-delete type="button"
                                                        class="btn btn-danger w-100"><?php echo lang('Backend.delete') ?></button>
                                                </div>
                                            </div>
                                        <?php endforeach;
                                    else: ?>
                                        <div class="row border-bottom" data-repeater-item>
                                            <div class="col-md-6 form-group">
                                                <label for=""><?php echo lang('Settings.socialMedia') ?></label>
                                                <input type="text" class="form-control" name="smName" placeholder="facebook"
                                                    required>
                                            </div>
                                            <div class="col-md-5 form-group">
                                                <label for=""><?php echo lang('Settings.socialMediaLink') ?></label>
                                                <input type="text" class="form-control" name="link" required>
                                            </div>
                                            <div class="col-md-1 form-group">
                                                <input data-repeater-delete type="button"
                                                    class="btn btn-danger w-100 mt-md-4" value="Sil" />
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-6 form-group">
                                        <button data-repeater-create type="button" class="btn btn-secondary"><?php echo lang('Backend.add') ?></button>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <button class="btn btn-success float-right"><?php echo lang('Backend.update') ?></button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="tab-pane fade" id="vert-tabs-mailSettings" role="tabpanel"
                            aria-labelledby="vert-tabs-mailSettings-tab">
                            <form action="<?php echo route_to('mailSettingsPost') ?>" method="post" class="form-row">
                                <?php echo csrf_field() ?>
                                <div class="col-md-6 form-group">
                                    <label for="">Mail Server</label>
                                    <input type="text" name="mServer" class="form-control"
                                        value="<?php echo old('mServer', empty($settings->mail->server) ? '' : esc($settings->mail->server)) ?>"
                                        required>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="">Mail Port</label>
                                    <input type="text" name="mPort" class="form-control"
                                        value="<?php echo old('mPort', empty($settings->mail->port) ? '' : esc($settings->mail->port)) ?>"
                                        required>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for=""><?php echo lang('Settings.mailAddress') ?></label>
                                    <input type="text" name="mAddress" class="form-control"
                                        value="<?php echo old('mAddress', empty($settings->mail->address) ? '' : esc($settings->mail->address)) ?>"
                                        required>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for=""><?php echo lang('Settings.mailPassword') ?></label>
                                    <input type="text" name="mPwd" class="form-control"
                                        required>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for=""><?php echo lang('Settings.mailProtocol') ?></label>
                                    <select name="mProtocol" id="" class="form-control" required>
                                        <option value="smtp" <?php echo set_select('mProtocol', 'smtp', isset($settings->mail->protocol) && $settings->mail->protocol === 'smtp') ?>>
                                            SMTP
                                        </option>
                                        <option value="pop3" <?php echo set_select('mProtocol', 'pop3', isset($settings->mail->protocol) && $settings->mail->protocol === 'pop3') ?>>
                                            POP3
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for=""><?php echo lang('Settings.isTLSactive') ?> </label>
                                    <input type="checkbox" name="mTls"
                                        id="" <?php echo set_checkbox('mTls', '1', (!empty($settings->mail->tls) && $settings->mail->tls === true)) ?>>
                                </div>
                                <div class="col-md-6 form-group">
                                    <div class="input-group">
                                        <input type="text" id="testemail" name="testemail" class="form-control" placeholder="simple@domain.com">
                                        <button class="btn btn-success" id="sendtest"><?php echo lang('Backend.send') ?></button>
                                    </div>
                                </div>
                                <div class="col-md-6 form-group">
                                    <button class="btn btn-success float-right"><?php echo lang('Backend.update') ?></button>
                                </div>
                            </form>
                        </div>
                        <div class="tab-pane fade" id="vert-tabs-media" role="tabpanel"
                            aria-labelledby="vert-tabs-social-tab">
                            <form action="<?php echo route_to('saveAllowedFiles') ?>" class="row" method="post">
                                <?php echo csrf_field() ?>
                                <div class="col-md-12 form-group">
                                    <textarea name="allowedFiles" rows="10"
                                        class="form-control"><?php echo implode(',', (array)$settings->allowedFiles) ?></textarea>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label><?php echo lang('Settings.elfinderConvertWebp') ?></label>
                                    <input type="checkbox" name="elfinderConvertWebp" id="elfinderConvertWebp"
                                        class="bswitch" <?php echo ((bool)$settings->convertWebp->scalar === true) ? 'checked' : '' ?>
                                        data-off-color="danger"
                                        data-on-color="success">
                                </div>
                                <div class="col-md-6 form-group">
                                    <button class="btn btn-success float-right"><?php echo lang('Settings.allowedFiles') ?></button>
                                </div>
                            </form>
                            <hr>
                            <div class="w-100">
                                <h2><?php echo lang('Settings.fileTypes') ?></h2>
                                <div id="accordion">
                                    <?php foreach ($mimes as $key => $mime) :; ?>
                                        <div class="card">
                                            <div class="card-header" id="heading<?php echo $key ?>">
                                                <h5 class="mb-0">
                                                    <button class="btn btn-link" data-toggle="collapse"
                                                        data-target="#collapse<?php echo $key ?>" aria-expanded="true"
                                                        aria-controls="collapse<?php echo $key ?>">
                                                        <?php echo $key ?>
                                                    </button>
                                                </h5>
                                            </div>

                                            <div id="collapse<?php echo $key ?>" class="collapse"
                                                aria-labelledby="heading<?php echo $key ?>" data-parent="#accordion">
                                                <div class="card-body">
                                                    <ul>
                                                        <?php if (is_string($mime)): ?>
                                                            <li><?php echo $mime ?></li>
                                                            <?php else:
                                                            foreach ($mimes[$key] as $m) : ?>
                                                                <li><?php echo $m ?></li>
                                                        <?php endforeach;
                                                        endif; ?>
                                                    </ul>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->

</section>
<!-- /.content -->
<?php echo $this->endSection() ?>

<?php echo $this->section('javascript') ?>
<?php echo script_tag("be-assets/plugins/jquery-ui/jquery-ui.js") ?>
<?php echo script_tag("be-assets/node_modules/jquery.repeater/jquery.repeater.js") ?>
<!-- Bootstrap Switch -->
<?php echo script_tag("be-assets/plugins/bootstrap-switch/js/bootstrap-switch.min.js") ?>
<?php echo script_tag("be-assets/plugins/elFinder/js/elfinder.min.js") ?>
<?php echo script_tag("be-assets/plugins/elFinder/js/i18n/elfinder.tr.js") ?>
<?php echo script_tag("be-assets/plugins/elFinder/js/extras/editors.default.js") ?>
<?php echo script_tag("be-assets/js/ci4ms.js") ?>
<script {csp-script-nonce}>
    $('.repeater').repeater({
        defaultValues: {},
        isFirstItemUndeletable: true,
        show: function() {
            $(this).slideDown();
        },
        hide: function(deleteElement) {
            Swal.fire({
                title: 'Bu sosyal ağı listeden silmek istediğinizden emin misiniz?',
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

    function chooseTemplate(path, templateName) {
        $.post('<?php echo route_to('setTemplate') ?>', {
            "path": path,
            "tName": templateName
        }).done(function(data) {
            if (data.result === true) {
                Swal.fire('Tema Seçimi Başarılı!', '', 'success').then((result) => {
                    if (result.isConfirmed) location.reload();
                });
            } else Swal.fire('Tema Seçimi Sağlamanadı tekrar deneyiniz.', '', 'warning');
        });
    }

    $('.bswitch').bootstrapSwitch();
    $('#my-checkbox').on('switchChange.bootstrapSwitch', function() {
        var id = $(this).data('id'),
            isActive;
        if ($(this).prop('checked')) isActive = 1;
        else isActive = 0;
        $.post('<?php echo route_to('maintenance') ?>', {
            'isActive': isActive
        }, 'json').done(function(data) {
            if (data.result === true) Swal.fire('Bakım Aşaması Sayfası yayına alındı.', '', 'success');
            else Swal.fire('Bakım Aşaması Sayfası devre dışı bırakıldı.', '', 'warning');
            if (data.pr === false) Swal.fire('Bakım Aşaması Sayfası Aktif edilemedi.', '', 'error');
        });
    });

    $('#elfinderConvertWebp').on('switchChange.bootstrapSwitch', function() {
        var id = $(this).data('id'),
            isActive;
        if ($(this).prop('checked')) isActive = 1;
        else isActive = 0;
        $.post('<?php echo route_to('elfinderConvertWebp') ?>', {
            'isActive': isActive
        }, 'json').done(function(data) {
            if (data.result === true) Swal.fire('Elfinder ile webp formatına çevirme ektif edildi.', '', 'success');
            else Swal.fire('Elfinder ile webp formatına çevirme ektif durumdan çıkarıldı.', '', 'warning');
            if (data.pr === false) Swal.fire('Elfinder ile webp formatına çevirme ektif edilemedi.', '', 'error');
        });
    });

    $('#sendtest').on('click', function(e) {
        e.preventDefault();
        var email = $('#testemail').val();
        if (email === '') {
            Swal.fire('Lütfen test e-mail adresini giriniz.', '', 'warning');
            return false;
        }
        $.ajax({
            type: 'POST',
            url: '<?php echo route_to('testMail') ?>',
            data: {
                'testemail': email
            },
            beforeSend: function() {
                Swal.fire({
                    title: 'Test e-mail gönderiliyor...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            },
            success: function(data) {
                if (data.result === true) {
                    Swal.fire('Test e-mail başarıyla gönderildi.', '', 'success');
                } else {
                    Swal.fire('Test e-mail gönderilemedi. Lütfen ayarları kontrol ediniz.', '', 'error');
                }
            },
            error: function() {
                Swal.fire('Bir hata oluştu. Lütfen tekrar deneyiniz.', '', 'error');
            }
        });
    });
</script>
<?php echo $this->endSection() ?>
