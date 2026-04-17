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
                <div>
                    <div class="m-stat-value"><?php echo esc($settings->siteName ?? 'CI4MS') ?></div>
                    <div class="m-stat-label"><?php echo lang('Settings.siteName') ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="m-stat-card">
                <div class="m-stat-icon st-version"><i class="fas fa-code-branch"></i></div>
                <div>
                    <div class="m-stat-value"><?php echo env('app.version') ?></div>
                    <div class="m-stat-label"><?php echo lang('Settings.version') ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="m-stat-card">
                <div class="m-stat-icon st-template"><i class="fas fa-paint-brush"></i></div>
                <div>
                    <div class="m-stat-value"><?php echo esc($settings->templateInfos->name ?? 'Default') ?></div>
                    <div class="m-stat-label"><?php echo lang('Settings.activeTheme') ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="card premium-card">
        <div class="card-header d-flex align-items-center">
            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-cog mr-2 text-primary"></i> <?php echo lang('Settings.siteSettings') ?></h3>
            <div class="ml-auto">
                <button class="btn btn-sm btn-outline-secondary mr-2" id="listBackups" style="border-radius:10px">
                    <i class="fas fa-history mr-1"></i> <?php echo lang('Settings.backups') ?>
                </button>
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
                                        <input type="text" name="cName" class="form-control" value="<?php echo old('cName', esc($settings->siteName ?? '')) ?>">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label><?php echo lang('Settings.companySlogan') ?></label>
                                        <input type="text" name="cSlogan" class="form-control" value="<?php echo old('cSlogan', esc($settings->slogan ?? '')) ?>">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label><?php echo lang('Settings.companyAddress') ?></label>
                                        <input type="text" name="cAddress" class="form-control" value="<?php echo old('cAddress', esc($settings->contact->address ?? '')) ?>">
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label><?php echo lang('Settings.companyPhone') ?></label>
                                        <input type="text" name="cPhone" class="form-control" value="<?php echo old('cPhone', $settings->contact->phone ?? '') ?>">
                                    </div>
                                    <div class="col-md-3 form-group">
                                        <label><?php echo lang('Settings.companyGsm') ?></label>
                                        <input type="text" name="cGSM" class="form-control" value="<?php echo old('cGSM', esc($settings->contact->gsm ?? '')) ?>">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label><?php echo lang('Settings.companyEmail') ?></label>
                                        <input type="email" name="cMail" class="form-control" value="<?php echo old('cMail', esc($settings->contact->email ?? '')) ?>">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label><?php echo lang('Settings.gmapIframe') ?></label>
                                        <input type="text" name="cMap" class="form-control" value='<?php echo esc($settings->map_iframe ?? '') ?>'>
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
                                                    <label class="mb-0"><i class="fas fa-globe mr-2 text-muted"></i> <?php echo lang('Settings.languageModeMulti') ?></label>
                                                    <input type="checkbox" id="language-mode" class="bswitch" <?php echo setting('App.siteLanguageMode') === 'multi' ? 'checked' : '' ?> data-size="mini">
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-md-6 text-center">
                                        <div class="bg-dark p-3 rounded mb-2 d-inline-block" style="min-width: 200px">
                                            <img src="<?php echo esc($settings->logo ?? '') ?>" class="img-fluid pageimg" style="max-height: 80px">
                                        </div>
                                        <br>
                                        <button type="button" class="btn btn-sm btn-outline-primary pageIMG"><i class="fas fa-image mr-1"></i> <?php echo lang('Settings.changeLogo') ?></button>
                                        <input hidden class="pageimg-input" name="cLogo" value="<?php echo esc($settings->logo ?? '') ?>">
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
                                    <a href="<?php echo route_to('backendThemes') ?>" class="btn btn-sm btn-outline-success"><i class="fas fa-plus mr-1"></i> <?php echo lang('Settings.addNewTheme') ?></a>
                                </div>
                                <?php foreach ($templates as $key => $template):
                                    $infoPath = ROOTPATH . 'public/templates/' . $key . 'info.xml';
                                    if (is_file($infoPath)):
                                        $data = simplexml_load_file($infoPath, 'SimpleXMLElement', LIBXML_NOCDATA);
                                        $isActive = ($settings->templateInfos->path == $data->slug); ?>
                                        <div class="col-md-4 mb-4">
                                            <div class="card h-100 <?php echo $isActive ? 'border-primary shadow' : 'border-0 bg-light' ?>" style="border-radius:12px; overflow:hidden">
                                                <img class="card-img-top" src="<?php echo site_url('templates/' . $data->screenshotPNG) ?>" style="height: 150px; object-fit: cover;">
                                                <div class="card-body p-3">
                                                    <h6 class="font-weight-bold mb-1"><?php echo $data->name ?></h6>
                                                    <p class="small text-muted mb-3">By <?php echo $data->author ?></p>
                                                    <p class="small text-muted mb-3"><?php echo $data->description ?></p>
                                                    <p class="small text-muted mb-3">Version <?php echo $data->version ?></p>
                                                    <?php if (!$isActive): ?>
                                                        <button type="button" class="btn btn-sm btn-primary btn-block mb-2" onclick="chooseTemplate('<?php echo $data->slug ?>','<?php echo $data->name ?>')"><?php echo lang('Settings.activate') ?></button>
                                                        <a href="<?php echo route_to('deleteThemeConfirm', $data->slug) ?>" class="btn btn-sm btn-outline-danger btn-block"><i class="fas fa-trash mr-1"></i> <?php echo lang('Backend.delete') ?></a>
                                                    <?php else: ?>
                                                        <a href="<?php echo route_to('templateSettings') ?>" class="btn btn-sm btn-outline-primary btn-block"><i class="fas fa-sliders-h mr-1"></i> <?php echo lang('Settings.settings') ?></a>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                <?php endif;
                                endforeach; ?>
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
                                                    <label><?php echo lang('Settings.socialNetworkName') ?></label>
                                                    <input type="text" class="form-control" name="smName" value="<?php echo esc($sn['smName']) ?>" required>
                                                </div>
                                                <div class="col-md-5">
                                                    <label><?php echo lang('Settings.socialNetworkLink') ?></label>
                                                    <input type="url" class="form-control" name="link" value="<?php echo esc($sn['link']) ?>" required>
                                                </div>
                                                <div class="col-md-2">
                                                    <button data-repeater-delete type="button" class="btn btn-outline-danger btn-block"><i class="fas fa-trash"></i></button>
                                                </div>
                                            </div>
                                        <?php endforeach;
                                    else: ?>
                                        <div class="row align-items-end mb-3" data-repeater-item>
                                            <div class="col-md-5">
                                                <label><?php echo lang('Settings.socialNetworkName') ?></label>
                                                <input type="text" class="form-control" name="smName" required>
                                            </div>
                                            <div class="col-md-5">
                                                <label><?php echo lang('Settings.socialNetworkLink') ?></label>
                                                <input type="url" class="form-control" name="link" required>
                                            </div>
                                            <div class="col-md-2">
                                                <button data-repeater-delete type="button" class="btn btn-outline-danger btn-block"><i class="fas fa-trash"></i></button>
                                            </div>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="d-flex justify-content-between mt-4">
                                    <button data-repeater-create type="button" class="btn btn-sm btn-outline-secondary"><i class="fas fa-plus mr-1"></i> <?php echo lang('Settings.addNew') ?></button>
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
                                        <label><?php echo lang('Settings.smtpServer') ?></label>
                                        <input type="text" name="mServer" class="form-control" value="<?php echo old('mServer', $settings->mail->server ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-4 form-group">
                                        <label><?php echo lang('Settings.port') ?></label>
                                        <input type="text" name="mPort" class="form-control" value="<?php echo old('mPort', $settings->mail->port ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label><?php echo lang('Settings.mailAddress') ?></label>
                                        <input type="email" name="mAddress" class="form-control" value="<?php echo old('mAddress', $settings->mail->address ?? '') ?>" required>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label><?php echo lang('Settings.password') ?></label>
                                        <input type="password" name="mPwd" class="form-control" placeholder="••••••••">
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <label><?php echo lang('Settings.protocol') ?></label>
                                        <select name="mProtocol" class="form-control">
                                            <option value="smtp" <?php echo ($settings->mail->protocol ?? '') === 'smtp' ? 'selected' : '' ?>>SMTP</option>
                                            <option value="pop3" <?php echo ($settings->mail->protocol ?? '') === 'pop3' ? 'selected' : '' ?>>POP3</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 form-group d-flex align-items-center pt-4">
                                        <div class="custom-control custom-switch">
                                            <input type="checkbox" name="mTls" class="custom-control-input" id="tlsSwitch" <?php echo ($settings->mail->tls ?? false) ? 'checked' : '' ?>>
                                            <label class="custom-control-label" for="tlsSwitch"><?php echo lang('Settings.tlsActive') ?></label>
                                        </div>
                                    </div>
                                </div>
                                <hr>
                                <div class="row align-items-end">
                                    <div class="col-md-8">
                                        <label><?php echo lang('Settings.testMailSend') ?></label>
                                        <div class="input-group">
                                            <input type="email" id="testemail" class="form-control" placeholder="test@ci4ms.pro">
                                            <div class="input-group-append">
                                                <button class="btn btn-info" id="sendtest" type="button"><i class="fas fa-paper-plane mr-1"></i> <?php echo lang('Settings.send') ?></button>
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
                                    <label><?php echo lang('Settings.allowedFilesNote') ?></label>
                                    <textarea name="allowedFiles" rows="6" class="form-control"><?php echo implode(',', (array)($settings->allowedFiles ?? [])) ?></textarea>
                                </div>
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <div class="d-flex align-items-center">
                                            <label class="mb-0 mr-3"><?php echo lang('Settings.webpConvert') ?></label>
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
        show: function() {
            $(this).slideDown();
        },
        hide: function(deleteElement) {
            Swal.fire({
                title: '<?php echo lang('Settings.areYouSure') ?>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: '<?php echo lang('Settings.delete') ?>',
                cancelButtonText: '<?php echo lang('Settings.cancel') ?>'
            }).then((result) => {
                if (result.isConfirmed) $(this).slideUp(deleteElement);
            });
        }
    });

    $('.bswitch').bootstrapSwitch();

    $('#maintenance-mode').on('switchChange.bootstrapSwitch', function(e, state) {
        $.post('<?php echo route_to('maintenance') ?>', {
            isActive: state ? 1 : 0
        }, 'json').done(data => {
            showToast(state ? '<?php echo lang('Settings.maintenanceActive') ?>' : '<?php echo lang('Settings.maintenanceDisabled') ?>');
        });
    });

    $('#language-mode').on('switchChange.bootstrapSwitch', function(e, state) {
        $.post('<?php echo route_to('saveLanguageMode') ?>', {
            mode: state ? 'multi' : 'single'
        }, 'json').done(data => {
            showToast('<?php echo lang('Settings.languageModeUpdated') ?>'.replace('{0}', state ? 'Multi' : 'Single'));
        });
    });

    $('#webp-convert').on('switchChange.bootstrapSwitch', function(e, state) {
        $.post('<?php echo route_to('elfinderConvertWebp') ?>', {
            isActive: state ? 1 : 0
        }, 'json').done(data => {
            showToast(state ? '<?php echo lang('Settings.webpActive') ?>' : '<?php echo lang('Settings.webpDisabled') ?>');
        });
    });

    function chooseTemplate(path, templateName) {
        Swal.fire({
            title: '<?php echo lang('Settings.changeToTheme') ?>'.replace('{0}', templateName),
            icon: 'question',
            showCancelButton: true,
            confirmButtonText: '<?php echo lang('Settings.yes') ?>',
            cancelButtonText: '<?php echo lang('Settings.no') ?>'
        }).then(res => {
            if (res.isConfirmed) {
                $.post('<?php echo route_to('setTemplate') ?>', {
                    path: path,
                    tName: templateName
                }).done(data => {
                    if (data.result) location.reload();
                });
            }
        });
    }

    $('#sendtest').click(function() {
        let email = $('#testemail').val();
        if (!email) {
            showToast('<?php echo lang('Settings.enterEmail') ?>', 'error');
            return;
        }
        let btn = $(this);
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i>');
        $.post('<?php echo route_to('testMail') ?>', {
            testemail: email
        }).done(r => {
            showToast(r.message, r.result ? 'success' : 'error');
            btn.prop('disabled', false).html('<i class="fas fa-paper-plane mr-1"></i> <?php echo lang('Settings.send') ?>');
        });
    });

    $('#updateVersion').click(function() {
        Swal.fire({
            title: '<?php echo lang('Settings.checkVersionProgress') ?>',
            didOpen: () => Swal.showLoading()
        });
        $.post('<?php echo route_to('updateVersion') ?>').done(r => {
            Swal.close();
            if (r.result) {
                if (r.update_available) {
                    let filesList = '';
                    if (r.changed_count > 0) {
                        filesList = `<div class="mt-2 text-left small" style="max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 5px; background: #f9f9f9;">
                            <strong><?php echo lang('Settings.changedFiles') ?> (${r.changed_count}):</strong><br>
                            ${r.changed_files.slice(0, 10).map(f => `• ${f.filename}`).join('<br>')}
                            ${r.changed_count > 10 ? '<br>... <?php echo lang('Settings.andMore') ?>' : ''}
                        </div>`;
                    }
                    
                    Swal.fire({
                        title: '<?php echo lang('Settings.updateAvailableTitle') ?>',
                        html: `<?php echo lang('Settings.currentVersion') ?>: <b>${r.current_version}</b><br>
                               <?php echo lang('Settings.newVersion') ?>: <span class="badge badge-success" style="font-size: 1.1em">${r.new_version}</span><br>
                               ${filesList}
                                <div class="mt-4 d-flex flex-column gap-2">
                                   <button type="button" class="btn btn-primary mb-2" onclick="autoUpdate('${r.new_version}')">
                                       <i class="fas fa-magic mr-1"></i> <?php echo lang('Settings.autoUpdate') ?>
                                   </button>
                                   <button type="button" class="btn btn-success mb-2" onclick="downloadPatch('${r.new_version}')">
                                       <i class="fas fa-file-archive mr-1"></i> <?php echo lang('Settings.downloadOnlyChanges') ?>
                                   </button>
                                   <button type="button" class="btn btn-outline-success mb-2" onclick="window.location.href='${r.download_url}'">
                                       <i class="fas fa-download mr-1"></i> <?php echo lang('Settings.downloadAll') ?>
                                   </button>
                                   <button type="button" class="btn btn-info mb-2" onclick="window.open('${r.compare_url}', '_blank')">
                                       <i class="fas fa-external-link-alt mr-1"></i> <?php echo lang('Settings.viewChanges') ?>
                                   </button>
                               </div>`,
                        icon: 'info',
                        showConfirmButton: false,
                        showCancelButton: true,
                        cancelButtonText: '<?php echo lang('Backend.close') ?>',
                    });
                } else {
                    Swal.fire('<?php echo lang('Backend.success') ?>', r.message, 'success');
                }
            } else {
                Swal.fire('<?php echo lang('Backend.error') ?>', r.error || r.message, 'error');
            }
        });
    });

    function downloadPatch(latestVersion) {
        let form = document.createElement('form');
        form.method = 'POST';
        form.action = '<?php echo route_to('downloadPatch') ?>';
        
        let csrf = document.createElement('input');
        csrf.type = 'hidden';
        csrf.name = '<?php echo csrf_token() ?>';
        csrf.value = '<?php echo csrf_hash() ?>';
        form.appendChild(csrf);

        let latest = document.createElement('input');
        latest.type = 'hidden';
        latest.name = 'latest';
        latest.value = latestVersion;
        form.appendChild(latest);

        document.body.appendChild(form);
        form.submit();
        document.body.removeChild(form);
    }

    function autoUpdate(latestVersion) {
        Swal.fire({
            title: '<?php echo lang('Settings.areYouSure') ?>',
            text: '<?php echo lang('Settings.autoUpdateConfirm') ?>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<?php echo lang('Settings.yes') ?>',
            cancelButtonText: '<?php echo lang('Settings.cancel') ?>'
        }).then(res => {
            if (res.isConfirmed) {
                Swal.fire({
                    title: '<?php echo lang('Settings.updatingWait') ?>',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                $.post('<?php echo route_to('autoUpdate') ?>', {
                    latest: latestVersion
                }).done(r => {
                    if (r.result) {
                        Swal.fire('<?php echo lang('Backend.success') ?>', r.message, 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('<?php echo lang('Backend.error') ?>', r.message, 'error');
                    }
                }).fail(e => {
                    Swal.fire('<?php echo lang('Backend.error') ?>', e.responseJSON?.message || 'Update failed', 'error');
                });
            }
        });
    }

    $('#listBackups').click(function() {
        Swal.fire({
            title: '<?php echo lang('Settings.loadingBackups') ?>',
            didOpen: () => Swal.showLoading()
        });

        $.post('<?php echo route_to('listBackups') ?>').done(r => {
            Swal.close();
            if (r.result && r.backups.length > 0) {
                let listHtml = `<div class="table-responsive">
                    <table class="table table-sm table-hover text-left">
                        <thead>
                            <tr>
                                <th><?php echo lang('Settings.backupName') ?></th>
                                <th><?php echo lang('Settings.date') ?></th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            ${r.backups.map(b => `
                                <tr>
                                    <td><small>${b.name}</small></td>
                                    <td><small>${b.date}</small></td>
                                    <td class="text-right">
                                        <button class="btn btn-xs btn-danger" onclick="rollbackUpdate('${b.name}')">
                                            <i class="fas fa-undo mr-1"></i> <?php echo lang('Settings.rollback') ?>
                                        </button>
                                    </td>
                                </tr>
                            `).join('')}
                        </tbody>
                    </table>
                </div>`;

                Swal.fire({
                    title: '<?php echo lang('Settings.systemBackups') ?>',
                    html: listHtml,
                    width: '600px',
                    showConfirmButton: false,
                    showCancelButton: true,
                    cancelButtonText: '<?php echo lang('Backend.close') ?>'
                });
            } else {
                Swal.fire('<?php echo lang('Settings.noBackupsFound') ?>', '', 'info');
            }
        });
    });

    function rollbackUpdate(backupName) {
        Swal.fire({
            title: '<?php echo lang('Settings.areYouSure') ?>',
            text: '<?php echo lang('Settings.rollbackConfirm') ?>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: '<?php echo lang('Settings.yes') ?>',
            cancelButtonText: '<?php echo lang('Settings.cancel') ?>'
        }).then(res => {
            if (res.isConfirmed) {
                Swal.fire({
                    title: '<?php echo lang('Settings.rollbackWait') ?>',
                    allowOutsideClick: false,
                    didOpen: () => Swal.showLoading()
                });

                $.post('<?php echo route_to('rollbackUpdate') ?>', {
                    backup_name: backupName
                }).done(r => {
                    if (r.result) {
                        Swal.fire('<?php echo lang('Backend.success') ?>', r.message, 'success').then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire('<?php echo lang('Backend.error') ?>', r.message, 'error');
                    }
                }).fail(e => {
                    Swal.fire('<?php echo lang('Backend.error') ?>', e.responseJSON?.message || 'Rollback failed', 'error');
                });
            }
        });
    }
</script>
<?php echo $this->endSection() ?>
