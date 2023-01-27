<?= $this->extend('Modules\Backend\Views\base') ?>

<?= $this->section('title') ?>
<?=lang('Backend.'.$title->pagename)?>
<?= $this->endSection() ?>

<?= $this->section('head') ?>
<?=link_tag("be-assets/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css")?>
<?=link_tag("be-assets/plugins/jquery-ui/jquery-ui.css")?>
<link rel="stylesheet" type="text/css"
      href="//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css">
<?=link_tag("be-assets/plugins/elFinder/css/elfinder.full.css")?>
<?=link_tag("be-assets/plugins/elFinder/css/theme.css")?>
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
            <h3 class="card-title font-weight-bold"><?=lang('Backend.siteSettings')?></h3>

            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <?= view('Modules\Auth\Views\_message_block') ?>
            <div class="row">
                <div class="col-md-3">
                    <div class="nav flex-column nav-tabs h-100" id="vert-tabs-tab" role="tablist"
                         aria-orientation="vertical">
                        <a class="nav-link active" id="vert-tabs-home-tab" data-toggle="pill" href="#vert-tabs-home"
                           role="tab" aria-controls="vert-tabs-home" aria-selected="true"><?=lang('Backend.companyInfos')?></a>
                        <a class="nav-link" id="vert-tabs-templates-tab" data-toggle="pill"
                           href="#vert-tabs-templates"
                           role="tab" aria-controls="vert-tabs-templates" aria-selected="false"><?=lang('Backend.templateSelect')?></a>
                        <a class="nav-link" id="vert-tabs-social-tab" data-toggle="pill" href="#vert-tabs-social"
                           role="tab" aria-controls="vert-tabs-social" aria-selected="false"><?=lang('Backend.socialMedia')?></a>
                        <a class="nav-link" id="vert-tabs-mailSettings-tab" data-toggle="pill"
                           href="#vert-tabs-mailSettings"
                           role="tab" aria-controls="vert-tabs-mailSettings" aria-selected="false"><?=lang('Backend.mailSettings')?></a>
                        <a class="nav-link" id="vert-tabs-media-tab" data-toggle="pill"
                           href="#vert-tabs-media"
                           role="tab" aria-controls="vert-tabs-media" aria-selected="false"><?=lang('Backend.media')?></a>
                        <a class="nav-link" id="vert-tabs-login-tab" data-toggle="pill" href="#vert-tabs-login"
                           role="tab" aria-controls="vert-tabs-login" aria-selected="false"><?=lang('Backend.lockedSettings')?></a>
                    </div>
                </div>
                <div class="col-md-9">
                    <div class="tab-content" id="vert-tabs-tabContent">
                        <div class="tab-pane text-left fade active show" id="vert-tabs-home" role="tabpanel"
                             aria-labelledby="vert-tabs-home-tab">
                            <form action="<?= route_to('compInfosPost') ?>" method="post" class="form-row">
                                <?= csrf_field() ?>
                                <div class="col-md-6 form-group">
                                    <label for=""><?=lang('Backend.companyName')?></label>
                                    <input type="text" name="cName" class="form-control"
                                           value="<?= (!empty($settings->siteName)) ? $settings->siteName : '' ?>">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for=""><?=lang('Backend.websiteUrl')?></label>
                                    <input type="text" name="cUrl" class="form-control"
                                           value="<?= (!empty($settings->siteURL)) ? $settings->siteURL : '' ?>">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for=""><?=lang('Backend.companySlogan')?></label>
                                    <input type="text" name="cSlogan" class="form-control"
                                           value="<?= (!empty($settings->slogan)) ? $settings->slogan : '' ?>">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for=""><?=lang('Backend.companyAddress')?></label>
                                    <input type="text" name="cAddress" class="form-control"
                                           value="<?= (!empty($settings->companyAddress)) ? $settings->companyAddress : '' ?>">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for=""><?=lang('Backend.companyPhone')?></label>
                                    <input type="text" name="cPhone" class="form-control"
                                           value="<?= (!empty($settings->companyPhone)) ? $settings->companyPhone : '' ?>">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for=""><?=lang('Backend.companyGsm')?></label>
                                    <input type="text" name="cGSM" class="form-control"
                                           value="<?= (!empty($settings->companyGSM)) ? $settings->companyGSM : '' ?>">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for=""><?=lang('Backend.companyEmail')?></label>
                                    <input type="text" name="cMail" class="form-control"
                                           value="<?= (!empty($settings->companyEMail)) ? $settings->companyEMail : '' ?>">
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for=""><?=lang('Backend.gmapIframe')?></label>
                                    <input type="text" name="cMap" class="form-control"
                                           value='<?= (!empty($settings->map_iframe)) ? $settings->map_iframe : '' ?>'>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for=""><?=lang('Backend.companyLogo')?></label>
                                    <button type="button" class="pageIMG btn btn-info w-100"><?=lang('Backend.selectCoverImg')?></button>
                                    <input hidden class="pageimg-input" name="cLogo">
                                </div>
                                <div class="col-md-6 form-group rounded bg-dark p-3">
                                    <img src="<?= (!empty($settings->logo)) ? $settings->logo : '' ?>"
                                         class="img-fluid pageimg">
                                </div>
                                <div class="col-md-12 form-group">
                                    <button class="btn btn-success float-right mt-5"><?=lang('Backend.update')?></button>
                                </div>
                            </form>
                            <div class="w-100">
                                <label><?=lang('Backend.maintenanceMode')?></label>
                                <input type="checkbox" name="my-checkbox" class="bswitch" <?=($settings->maintenanceMode===true)?'checked':''?> data-id="<?=$settings->_id?>" data-off-color="danger" data-on-color="success">
                            </div>
                        </div>
                        <div class="tab-pane fade" id="vert-tabs-templates" role="tabpanel"
                             aria-labelledby="vert-tabs-templates-tab">
                            <div class="row">
                                <?php foreach ($templates as $key => $template):
                                    $arrContextOptions = [];
                                    if ($request->getServer('HTTPS') == 'on') $arrContextOptions = ["ssl" => ["verify_peer" => false, "verify_peer_name" => false,]];
                                    if (is_file(ROOTPATH . 'public/templates/' . $key . 'info.xml') === true):
                                        $data = simplexml_load_string(file_get_contents(ROOTPATH . 'public/templates/' . $key . 'info.xml', false, stream_context_create($arrContextOptions)), 'SimpleXMLElement', LIBXML_NOCDATA); ?>
                                        <div class="col-md-4">
                                            <div class="card bg-light rounded <?=($settings->templateInfos->path==$data->defPath)?'border border-success shadow':''?>">
                                                <div class="car-img">
                                                    <img class="img-fluid" src="<?= site_url('templates/'. $data->screenshotPNG) ?>">
                                                </div>
                                                <div class="card-body">
                                                    <div class="row">
                                                        <div class="col-md-12">
                                                            <h4><?=$data->templateName?></h4>
                                                            <p>Coded By <?=$data->codedBy?></p>
                                                        </div>
                                                        <div class="col-md-12">
                                                            <?php if($settings->templateInfos->path!=$data->defPath):?>
                                                            <button class="btn btn-outline-success" id="<?=$data->defPath?>" type="button" onclick="chooseTemplate('<?=$data->defPath?>','<?=$data->templateName?>')">Seç</button>
                                                            <?php else: ?>
                                                            <a href="<?=route_to('templateSettings')?>" class="btn btn-outline-primary">Ayarlar</a>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endif; endforeach; ?>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="vert-tabs-social" role="tabpanel"
                             aria-labelledby="vert-tabs-social-tab">
                            <form action="<?= route_to('socialMediaPost') ?>" class="repeater" method="post">
                                <?= csrf_field() ?>
                                <div data-repeater-list="socialNetwork" class="col-md-12">
                                    <?php if (!empty($settings->socialNetwork)):
                                        foreach ($settings->socialNetwork as $socialNetwork) : ?>
                                            <div class="row border-bottom" data-repeater-item>
                                                <div class="col-md-6 form-group">
                                                    <label for=""><?=lang('Backend.socialMedia')?></label>
                                                    <input type="text" class="form-control" name="smName"
                                                           value="<?= $socialNetwork->smName ?>" placeholder="facebook"
                                                           required>
                                                </div>
                                                <div class="col-md-5 form-group">
                                                    <label for=""><?=lang('Backend.socialMediaLink')?></label>
                                                    <input type="text" class="form-control" name="link"
                                                           value="<?= $socialNetwork->link ?>" required>
                                                </div>
                                                <div class="col-md-1 form-group">
                                                    <input data-repeater-delete type="button"
                                                           class="btn btn-danger w-100" value="<?=lang('Backend.deleteText')?>"/>
                                                </div>
                                            </div>
                                        <?php endforeach;
                                    endif; ?>
                                    <div class="row border-bottom" data-repeater-item>
                                        <div class="col-md-6 form-group">
                                            <label for=""><?=lang('Backend.socialMedia')?></label>
                                            <input type="text" class="form-control" name="smName" placeholder="facebook"
                                                   required>
                                        </div>
                                        <div class="col-md-5 form-group">
                                            <label for=""><?=lang('Backend.socialMediaLink')?></label>
                                            <input type="text" class="form-control" name="link" required>
                                        </div>
                                        <div class="col-md-1 form-group">
                                            <input data-repeater-delete type="button"
                                                   class="btn btn-danger w-100 mt-md-4" value="Sil"/>
                                        </div>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-md-6 form-group">
                                        <input data-repeater-create type="button" class="btn btn-secondary"
                                               value="<?=lang('Backend.addText')?>"/>
                                    </div>
                                    <div class="col-md-6 form-group">
                                        <button class="btn btn-success float-right"><?=lang('Backend.update')?></button>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="tab-pane fade" id="vert-tabs-mailSettings" role="tabpanel"
                             aria-labelledby="vert-tabs-mailSettings-tab">
                            <form action="<?= route_to('mailSettingsPost') ?>" method="post" class="form-row">
                                <?= csrf_field() ?>
                                <div class="col-md-6 form-group">
                                    <label for="">Mail Server</label>
                                    <input type="text" name="mServer" class="form-control"
                                           value="<?= empty($settings->mailServer) ? '' : $settings->mailServer ?>"
                                           required>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for="">Mail Port</label>
                                    <input type="text" name="mPort" class="form-control"
                                           value="<?= empty($settings->mailPort) ? '' : $settings->mailPort ?>"
                                           required>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for=""><?=lang('Backend.mailAddress')?></label>
                                    <input type="text" name="mAddress" class="form-control"
                                           value="<?= empty($settings->mailAddress) ? '' : $settings->mailAddress ?>"
                                           required>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for=""><?=lang('Backend.mailPassword')?></label>
                                    <input type="text" name="mPwd" class="form-control"
                                           value="<?= empty($settings->mailPassword) ? '' : $settings->mailPassword ?>"
                                           required>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for=""><?=lang('Backend.mailProtocol')?></label>
                                    <select name="mProtocol" id="" class="form-control" required>
                                        <option value="smtp" <?= (isset($settings->mailProtocol) && $settings->mailProtocol === 'smtp') ? 'selected' : '' ?>>
                                            SMTP
                                        </option>
                                        <option value="pop3" <?= (isset($settings->mailProtocol) && $settings->mailProtocol === 'pop3') ? 'selected' : '' ?>>
                                            POP3
                                        </option>
                                    </select>
                                </div>
                                <div class="col-md-6 form-group">
                                    <label for=""><?=lang('Backend.isTLSactive')?> </label>
                                    <input type="checkbox" name="mTls"
                                           id="" <?= (!empty($settings->mailTLS) && $settings->mailTLS === true) ? 'checked' : '' ?>>
                                </div>
                                <div class="col-md-12 form-group">
                                    <button class="btn btn-success float-right"><?=lang('Backend.update')?></button>
                                </div>
                            </form>
                        </div>
                        <div class="tab-pane fade" id="vert-tabs-media" role="tabpanel"
                             aria-labelledby="vert-tabs-social-tab">
                            <form action="<?= route_to('saveAllowedFiles') ?>" class="row" method="post">
                                <?= csrf_field() ?>
                                <div class="col-md-12 form-group">
                                <textarea name="allowedFiles" rows="10" class="form-control"><?= implode(',',(array)$settings->allowedFiles) ?></textarea>
                                </div>
                                <div class="col-md-12 form-group">
                                <button class="btn btn-success float-right"><?=lang('Backend.allowedFiles')?></button>
                                </div>
                            </form>
                            <hr>
                            <div class="w-100">
                                <h2><?=lang('Backend.fileTypes')?></h2>
                                <?php foreach ($mimes as $key=>$mime) :; ?>
                                    <h5 for=""><?=$key?></h5>
                                <ul>
                                    <?php if(is_string($mime)):?>
                                    <li><?=$mime?></li>
                                    <?php else:
                                   foreach($mimes[$key] as $m) : ?>
                                    <li><?=$m?></li>
                                    <?php endforeach;
                                    endif; ?>
                                </ul>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="tab-pane fade" id="vert-tabs-login" role="tabpanel"
                             aria-labelledby="vert-tabs-login-tab">
                            <form action="<?= route_to('loginSettingsPost') ?>" method="post" class="form-row">
                                <?= csrf_field() ?>

                                <div class="col-md-6">
                                    <div class="col-md-12 form-group">
                                        <label for=""><?=lang('Backend.lockingCounter') ?></label>
                                        <input type="number" name="lockedRecord" class="form-control"
                                               value="<?= empty($settings->lockedRecord) ? '' : $settings->lockedRecord ?>"
                                               required>
                                    </div>
                                    <div class="col-md-12 form-group">
                                        <label for=""><?=lang('Backend.blockedTime')?></label>
                                        <input type="number" name="lockedMin" class="form-control"
                                               value="<?= empty($settings->lockedMin) ? '' : $settings->lockedMin ?>"
                                               required>
                                    </div>
                                    <div class="col-md-12 form-group">
                                        <label for=""><?=lang('Backend.tryCounter')?></label>
                                        <input type="number" name="lockedTry" class="form-control"
                                               value="<?= empty($settings->lockedTry) ? '' : $settings->lockedTry ?>"
                                               required>
                                    </div>
                                </div>

                                <div class="col-md-6">
                                    <div class="col-md-12 form-group">
                                        <label for=""><?=lang('Backend.lockedSettings')?></label>
                                        <input type="checkbox" name="lockedIsActive"
                                            <?= (!empty($settings->lockedIsActive) && $settings->lockedIsActive === true) ? 'checked' : '' ?>>
                                    </div>

                                    <div class="col-md-12 form-group">
                                        <label for=""><?=lang('Backend.lockedUserNotification')?></label>
                                        <input type="checkbox" name="lockedUserNotification"
                                            <?= (!empty($settings->lockedUserNotification) && $settings->lockedUserNotification === true) ? 'checked' : '' ?>>

                                    </div>
                                    <div class="col-md-12 form-group">
                                        <label for=""><?=lang('Backend.locketAdminNotification')?> </label>
                                        <input type="checkbox" name="lockedAdminNotification"
                                            <?= (!empty($settings->lockedAdminNotification) && $settings->lockedAdminNotification === true) ? 'checked' : '' ?>>

                                    </div>
                                </div>

                                <div class="col-md-4 form-group">
                                    <label><?=lang('Backend.blockIps')?> <?=lang('Backend.separateWithComma')?></label>
                                    <textarea class="form-control border-danger" rows="5" name="blackListRange"
                                              placeholder="Ör : 222.175.223.123 - 222.175.223.123"><?= $blacklistRange ?? '' ?></textarea>

                                </div>
                                <div class="col-md-4 form-group">
                                    <label><?=lang('Backend.BlockIp')?> <?=lang('Backend.separateWithComma')?></label>
                                    <textarea class="form-control border-danger" rows="5" name="blacklistLine"
                                              placeholder="Ör : 255.255.255.255"><?= $blacklistLine ?? '' ?></textarea>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label><?=lang('Backend.blockUsername')?> <?=lang('Backend.separateWithComma')?></label>
                                    <textarea class="form-control border-danger" rows="5" name="blacklistUsername"
                                              placeholder="Ör : qwe@asd.com"><?= $blacklistUsername ?? '' ?></textarea>
                                </div>

                                <div class="col-md-4 form-group">
                                    <label><?=lang('Backend.trustedIps')?> <?=lang('Backend.separateWithComma')?></label>
                                    <textarea class="form-control border-success" rows="5" name="whitelistRange"
                                              placeholder="Ör : 222.175.223.123 - 222.175.223.123"><?= $whitelistRange ?? '' ?></textarea>

                                </div>
                                <div class="col-md-4 form-group">
                                    <label><?=lang('Backend.trustedIp')?> <?=lang('Backend.separateWithComma')?></label>
                                    <textarea class="form-control  border-success" rows="5" name="whitelistLine"
                                              placeholder="Ör : 8.8.8.8"><?= $whitelistLine ?? '' ?></textarea>
                                </div>
                                <div class="col-md-4 form-group">
                                    <label><?=lang('Backend.trustedUsername')?> <?=lang('Backend.separateWithComma')?></label>
                                    <textarea class="form-control  border-success" rows="5" name="whitelistUsername"
                                              placeholder="Ör : qwe@asd.com"><?= $whitelistUsername ?? '' ?></textarea>
                                </div>

                                <div class="col-md-12 form-group">
                                    <button class="btn btn-success float-right"><?=lang('Backend.update')?></button>
                                </div>
                            </form>
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
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>
<?=script_tag("be-assets/plugins/jquery-ui/jquery-ui.js")?>
<?=script_tag("be-assets/plugins/sweetalert2/sweetalert2.min.js")?>
<?=script_tag("be-assets/node_modules/jquery.repeater/jquery.repeater.js")?>
<!-- Bootstrap Switch -->
<?=script_tag("be-assets/plugins/bootstrap-switch/js/bootstrap-switch.min.js")?>
<?=script_tag("be-assets/plugins/elFinder/js/elfinder.full.js")?>
<?=script_tag("be-assets/plugins/elFinder/js/i18n/elfinder.tr.js")?>
<?=script_tag("be-assets/plugins/elFinder/js/extras/editors.default.js")?>
<?=script_tag("be-assets/js/ci4ms.js")?>
<script>
    $(document).ready(function () {
        'use strict';

        $('.repeater').repeater({
            defaultValues: {},
            isFirstItemUndeletable: true,
            show: function () {
                $(this).slideDown();
            },
            hide: function (deleteElement) {
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
    });

    function chooseTemplate(path,templateName){
        $.post('<?=route_to('setTemplate')?>',{
            "<?=csrf_token()?>": "<?=csrf_hash()?>",
            "path":path,"tName":templateName
        }).done(function (data) {
           if(data.result===true){
               Swal.fire('Tema Seçimi Başarılı!', '', 'success');
               $('#'+path).remove();
           }else{
               Swal.fire('Tema Seçimi Sağlamanadı tekrar deneyiniz.','','warning')
           }
        });
    }
    $('.bswitch').bootstrapSwitch();
    $('.bswitch').on('switchChange.bootstrapSwitch',function(){
        var id=$(this).data('id'), isActive;

        if($(this).prop('checked'))
            isActive=1;
        else
            isActive=0;

        $.post('<?=route_to('maintenance')?>',
            {"<?=csrf_token()?>": "<?=csrf_hash()?>",
                "id":id,
                'isActive':isActive},'json').done(function (data) {
            if (data.result === true) {
                Swal.fire('Bakım Aşaması Sasyfası Aktif edildi.', '', 'success');
            } else {
                Swal.fire('Bakım Aşaması Sasyfası Aktif edilemedi.', '', 'erroe')
            }
        });
    });
</script>
<?= $this->endSection() ?>
