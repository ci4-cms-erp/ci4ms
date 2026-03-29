<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('title');
echo lang($title->pagename);
echo $this->endSection();
echo $this->section('head');
echo link_tag("be-assets/plugins/jquery-ui/jquery-ui.css");
echo link_tag("be-assets/plugins/jquery-ui/themes/smoothness/jquery-ui.min.css");
echo link_tag("be-assets/plugins/elFinder/css/elfinder.full.css");
echo link_tag("be-assets/plugins/elFinder/css/theme.css");
echo $this->endSection();
echo $this->section('content'); ?>

<section class="content pt-3">
    <!-- Stats Row -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="m-stat-card">
                <div class="m-stat-icon st-app"><i class="fas fa-desktop"></i></div>
                <div><div class="m-stat-value"><?php echo esc($settings->siteName ?? 'CI4MS') ?></div><div class="m-stat-label">Site Adı</div></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="m-stat-card">
                <div class="m-stat-icon st-version"><i class="fas fa-code-branch"></i></div>
                <div><div class="m-stat-value"><?php echo env('app.version') ?></div><div class="m-stat-label">Sürüm</div></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="m-stat-card">
                <div class="m-stat-icon st-template"><i class="fas fa-paint-brush"></i></div>
                <div><div class="m-stat-value"><?php echo esc($settings->templateInfos->name ?? 'Default') ?></div><div class="m-stat-label">Aktif Tema</div></div>
            </div>
        </div>
    </div>

    <div class="card premium-card">
        <div class="card-header d-flex align-items-center">
            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-cog mr-2 text-primary"></i> <?php echo lang('Settings.siteSettings') ?></h3>
            <div class="ml-auto">
                <button class="btn btn-sm btn-outline-info" id="updateVersion" style="border-radius:10px">
                    <i class="fas fa-sync-alt mr-1"></i> <?php echo lang('Settings.updateVersion') ?>
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="row no-gutters">
                <div class="col-md-3 v-tabs-column">
                    <div class="nav flex-column nav-pills" id="settings-tabs" role="tablist">
                        <a class="nav-link active" data-toggle="pill" href="#tab-company"><i class="fas fa-building mr-2"></i> <?php echo lang('Settings.companyInfos') ?></a>
                        <a class="nav-link" data-toggle="pill" href="#tab-template"><i class="fas fa-palette mr-2"></i> <?php echo lang('Settings.templateSelect') ?></a>
                        <a class="nav-link" data-toggle="pill" href="#tab-social"><i class="fas fa-share-alt mr-2"></i> <?php echo lang('Settings.socialMedia') ?></a>
                        <a class="nav-link" data-toggle="pill" href="#tab-mail"><i class="fas fa-envelope mr-2"></i> <?php echo lang('Settings.mailSettings') ?></a>
                        <a class="nav-link" data-toggle="pill" href="#tab-media"><i class="fas fa-images mr-2"></i> <?php echo lang('Media.media') ?></a>
                    </div>
                </div>
                <div class="col-md-9 p-4">
                    <div class="tab-content">
                        <!-- Company Tab -->
                        <div class="tab-pane fade show active" id="tab-company">
                            <form action="<?php echo route_to('compInfosPost') ?>" method="post">
                                <?php echo csrf_field() ?>
                                <div class="row">
                                    <div class="col-md-6 form-group">
                                        <label><?php echo lang('Settings.companyName') ?></label>
                                        <input type="text" name="cName" class="form-control" value="<?php echo old('cName', $settings->siteName ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label><?php echo lang('Settings.companySlogan') ?></label>
                                        <input type="text" name="cSlogan" class="form-control" value="<?php echo old('cSlogan', $settings->slogan ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label><?php echo lang('Settings.companyAddress') ?></label>
                                        <input type="text" name="cAddress" class="form-control" value="<?php echo old('cAddress', $settings->contact->address ?? '') ?>">
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label><?php echo lang('Settings.companyPhone') ?></label>
                                        <input type="text" name="cPhone" class="form-control" value="<?php echo old('cPhone', $settings->contact->phone ?? '') ?>">
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label><?php echo lang('Settings.companyGsm') ?></label>
                                        <input type="text" name="cGSM" class="form-control" value="<?php echo old('cGSM', $settings->contact->gsm ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label><?php echo lang('Settings.companyEmail') ?></label>
                                        <input type="email" name="cMail" class="form-control" value="<?php echo old('cMail', $settings->contact->email ?? '') ?>">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label><?php echo lang('Settings.gmapIframe') ?></label>
                                        <input type="text" name="cMap" class="form-control" value='<?php echo $settings->map_iframe ?? '' ?>'>
                                    </div>
                                </div>
                                <hr>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="card bg-light border-0" style="border-radius:12px">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-center mb-3">
                                                    <label class="mb-0"><i class="fas fa-tools mr-2 text-muted"></i> <?php echo lang('Settings.maintenanceMode') ?></label>
                                                    <input type="checkbox" id="maintenance-mode" class="bswitch" <?php echo setting('App.maintenanceMode') ? 'checked' : '' ?> data-size="mini">
                                                </div>
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <label class="mb-0"><i class="fas fa-globe mr-2 text-muted"></i> Dil Modu (Multi)</label>
                                                    <input type="checkbox" id="language-mode" class="bswitch" <?php echo setting('App.siteLanguageMode') === 'multi' ? 'checked' : '' ?> data-size="mini">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 text-center">
                                        <div class="bg-dark p-3 rounded mb-2 d-inline-block" style="min-width: 200px">
                                            <img src="<?php echo $settings->logo ?? '' ?>" class="img-fluid pageimg" style="max-height: 80px">
                                        </div>
                                        <br>
                                        <button type="button" class="btn btn-sm btn-outline-primary pageIMG"><i class="fas fa-image mr-1"></i> Logo Değiştir</button>
                                        <input hidden class="pageimg-input" name="cLogo" value="<?php echo $settings->logo ?? '' ?>">
                                    </div>
                                </div>
                                <div class="mt-4 text-right">
                                    <button class="btn btn-success px-5" style="border-radius:10px"><?php echo lang('Backend.update') ?></button>
                                </div>
                            </form>
                        </div>

                        <!-- Template Tab -->
                        <div class="tab-pane fade" id="tab-template">
                            <div class="row">
                                <div class="col-12 text-right mb-4">
                                    <a href="<?php echo route_to('backendThemes') ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-plus mr-1"></i> Yeni Tema Ekle</a>
                                </div>
                                <?php foreach ($templates as $key => $template):
                                    $infoPath = ROOTPATH . 'public/templates/' . $key . 'info.xml';
                                    if (is_file($infoPath)):
                                        $data = simplexml_load_file($infoPath, 'SimpleXMLElement', LIBXML_NOCDATA);
                                        $isActive = ($settings->templateInfos->path == $data->defPath); ?>
                                        <div class="col-md-4 mb-4">
                                            <div class="card h-100 <?php echo $isActive ? 'border-primary shadow' : 'border-0 bg-light' ?>" style="border-radius:12px; overflow:hidden">
                                                <img class="card-img-top" src="<?php echo site_url('templates/' . $data->screenshotPNG) ?>" style="height: 150px; object-fit: cover;">
                                                <div class="card-body p-3">
                                                    <h6 class="font-weight-bold mb-1"><?php echo $data->templateName ?></h6>
                                                    <p class="small text-muted mb-3">By <?php echo $data->codedBy ?></p>
                                                    <?php if (!$isActive): ?>
                                                        <button class="btn btn-sm btn-primary btn-block" onclick="chooseTemplate('<?php echo $data->defPath ?>','<?php echo $data->templateName ?>')">Aktif Et</button>
                                                    <?php else: ?>
                                                        <a href="<?php echo route_to('templateSettings') ?>" class="btn btn-sm btn-outline-primary btn-block"><i class="fas fa-sliders-h mr-1"></i> Ayarlar</a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                <?php endif; endforeach; ?>
                            </div>
                        </div>

                        <!-- Social Tab -->
                        <div class="tab-pane fade" id="tab-social">
                            <form action="<?php echo route_to('socialMediaPost') ?>" class="repeater" method="post">
                                <?php echo csrf_field() ?>
                                <div data-repeater-list="socialNetwork">
                                    <?php if (!empty($settings->socialNetwork)): foreach ($settings->socialNetwork as $sn) : ?>
                                        <div class="row align-items-end mb-3 pb-3 border-bottom" data-repeater-item>
                                            <div class="col-md-5">
                                                <label>Ağ Adı (örn: instagram)</label>
                                                <input type="text" class="form-control" name="smName" value="<?php echo esc($sn['smName']) ?>" required>
                                            </div>
                                            <div class="col-md-5">
                                                <label>Profil Linki</label>
                                                <input type="url" class="form-control" name="link" value="<?php echo esc($sn['link']) ?>" required>
                                            </div>
                                            <div class="col-md-2">
                                                <button data-repeater-delete type="button" class="btn btn-outline-danger btn-block"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </div>
                                    <?php endforeach; else: ?>
                                        <div class="row align-items-end mb-3" data-repeater-item>
                                            <div class="col-md-5">
                                                <label>Ağ Adı</label>
                                                <input type="text" class="form-control" name="smName" required>
                                            </div>
                                            <div class="col-md-5">
                                                <label>Profil Linki</label>
                                                <input type="url" class="form-control" name="link" required>
                                            </div>
                                            <div class="col-md-2">
                                                <button data-repeater-delete type="button" class="btn btn-outline-danger btn-block"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex justify-content-between mt-4">
                                    <button data-repeater-create type="button" class="btn btn-sm btn-outline-secondary"><i class="fas fa-plus mr-1"></i> Yeni Ekle</button>
                                    <button class="btn btn-success px-5" style="border-radius:10px"><?php echo lang('Backend.update') ?></button>
                                </div>
                            </form>
                        </div>

                        <!-- Mail Tab -->
                        <div class="tab-pane fade" id="tab-mail">
                            <form action="<?php echo route_to('mailSettingsPost') ?>" method="post">
                                <?php echo csrf_field() ?>
                                <div class="row">
                                    <div class="col-md-8 form-group">
                                        <label>SMTP Server</label>
                                        <input type="text" name="mServer" class="form-control" value="<?php echo old('mServer', $settings->mail->server ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label>Port</label>
                                        <input type="text" name="mPort" class="form-control" value="<?php echo old('mPort', $settings->mail->port ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label>Mail Adresi</label>
                                        <input type="email" name="mAddress" class="form-control" value="<?php echo old('mAddress', $settings->mail->address ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label>Şifre</label>
                                        <input type="password" name="mPwd" class="form-control" placeholder="••••••••">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label>Protokol</label>
                                        <select name="mProtocol" class="form-control">
                                            <option value="smtp" <?php echo ($settings->mail->protocol ?? '') === 'smtp' ? 'selected' : '' ?>>SMTP</option>
                                            <option value="pop3" <?php echo ($settings->mail->protocol ?? '') === 'pop3' ? 'selected' : '' ?>>POP3</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 form-group d-flex align-items-center pt-4">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" name="mTls" class="custom-control-input" id="tlsSwitch" <?php echo ($settings->mail->tls ?? false) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="tlsSwitch">TLS Aktif</label>
                                        </div>
                                    </div>
                                </div>
                                <hr>
                                <div class="row align-items-end">
                                    <div class="col-md-8">
                                        <label>Test Maili Gönder</label>
                                        <div class="input-group">
                                            <input type="email" id="testemail" class="form-control" placeholder="test@adresi.com">
                                            <div class="input-group-append">
                                                <button class="btn btn-info" id="sendtest" type="button"><i class="fas fa-paper-plane mr-1"></i> Gönder</button>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-right">
                                        <button class="btn btn-success px-5" style="border-radius:10px"><?php echo lang('Backend.update') ?></button>
                                    </div>
                                </div>
                            </form>
                        </div>

                        <!-- Media Tab -->
                        <div class="tab-pane fade" id="tab-media">
                            <form action="<?php echo route_to('saveAllowedFiles') ?>" method="post">
                                <?php echo csrf_field() ?>
                                <div class="form-group">
                                    <label>İzin Verilen Dosya Uzantıları (Virgül ile ayırın)</label>
                                    <textarea name="allowedFiles" rows="6" class="form-control"><?php echo implode(',', (array)($settings->allowedFiles ?? [])) ?></textarea>
                                </div>
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <div class="d-flex align-items-center">
                                            <label class="mb-0 mr-3">Elfinder WebP Dönüştürme</label>
                                            <input type="checkbox" id="webp-convert" class="bswitch" <?php echo ($settings->convertWebp ?? false) ? 'checked' : '' ?> data-size="mini">
                                        </div>
                                    </div>
                                    <div class="col-md-4 text-right">
                                        <button class="btn btn-success px-5" style="border-radius:10px"><?php echo lang('Backend.update') ?></button>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>


<?php echo $this->endSection();
echo $this->section('javascript');
echo script_tag("be-assets/plugins/jquery-ui/jquery-ui.js");
echo script_tag("be-assets/plugins/jquery-repeater/jquery.repeater.js");
echo script_tag("be-assets/plugins/bootstrap-switch/js/bootstrap-switch.min.js");
echo script_tag("be-assets/plugins/elFinder/js/elfinder.min.js");
echo script_tag("be-assets/plugins/elFinder/js/i18n/elfinder." . env('app.defaultLocale', 'tr') . ".js");
echo script_tag("be-assets/plugins/elFinder/js/extras/editors.default.js");
echo script_tag("be-assets/js/ci4ms.js") ?>
<script {csp-script-nonce}>

    $('.repeater').repeater({
        isFirstItemUndeletable: true,
        show: function() { $(this).slideDown(); },
        hide: function(deleteElement) {
            Swal.fire({
                title: 'Emin misiniz?',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Sil',
                cancelButtonText: 'Vazgeç'
            }).then((result) => {
                if (result.isConfirmed) $(this).slideUp(deleteElement);
            });
        }
    });

    $('.bswitch').bootstrapSwitch();

    $('#maintenance-mode').on('switchChange.bootstrapSwitch', function(e, state) {
        $.post('<?php echo route_to('maintenance') ?>', { isActive: state ? 1 : 0 }, 'json').done(data => {
            showToast(state ? 'Bakım modu aktif.' : 'Bakım modu kapatıldı.');
        });
    });

    $('#language-mode').on('switchChange.bootstrapSwitch', function(e, state) {
        $.post('<?php echo route_to('saveLanguageMode') ?>', { mode: state ? 'multi' : 'single' }, 'json').done(data => {
            showToast('Dil modu ' + (state ? 'Multi' : 'Single') + ' olarak güncellendi.');
        });
    });

    $('#webp-convert').on('switchChange.bootstrapSwitch', function(e, state) {
        $.post('<?php echo route_to('elfinderConvertWebp') ?>', { isActive: state ? 1 : 0 }, 'json').done(data => {
            showToast('WebP dönüşümü ' + (state ? 'aktif.' : 'kapalı.'));
        });
    });

    function chooseTemplate(path, templateName) {
        Swal.fire({
            title: templateName + ' temasına geçilsin mi?',
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: 'Evet',
            cancelButtonText: 'Hayır'
        }).then(res => {
            if(res.isConfirmed) {
                $.post('<?php echo route_to('setTemplate') ?>', { path: path, tName: templateName }).done(data => {
                    if(data.result) location.reload();
                });
            }
        });
    }

    $('#sendtest').click(function() {
        let email = $('#testemail').val();
        if(!email) { showToast('Email giriniz', 'error'); return; }
        let btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        $.post('<?php echo route_to('testMail') ?>', { testemail: email }).done(r => {
            showToast(r.message, r.result ? 'success' : 'error');
            btn.prop('disabled', false).html('<i class="fas fa-paper-plane mr-1"></i> Gönder');
        });
    });

    $('#updateVersion').click(function() {
        Swal.fire({ title: 'Sürüm kontrol ediliyor...', didOpen: () => Swal.showLoading() });
        $.post('<?php echo route_to('updateVersion') ?>').done(r => {
            Swal.close();
            if(r.result) Swal.fire('Başarılı', r.message, 'success');
            else Swal.fire('Hata', r.error || r.message, 'error');
        });
    });
</script>
<?php echo $this->endSection() ?>
