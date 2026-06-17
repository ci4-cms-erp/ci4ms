<?php echo $this->extend('Modules\Backend\Views\base');
echo $this->section('title');
echo lang($title->pagename);
echo $this->endSection();
echo $this->section('head'); ?>
<link rel="stylesheet" href="/be-assets/plugins/tagify/tagify.css">
<link rel="stylesheet" href="/be-assets/plugins/summernote/summernote-bs4.css">
<link rel="stylesheet" href="/be-assets/plugins/jquery-ui/jquery-ui.css">
<link rel="stylesheet" href="/be-assets/plugins/elFinder/css/elfinder.full.css">
<link rel="stylesheet" type="text/css" href="/be-assets/plugins/jquery-ui/themes/smoothness/jquery-ui.min.css">
<?php echo $this->endSection();
echo $this->section('content');
$isEdit      = isset($pageInfo);
$formAction  = $isEdit
    ? route_to('pageUpdate', $pageInfo->id)
    : route_to('pageCreate');
$defaultLocale = setting('App.defaultLocale') ?: 'tr'; ?>
<section class="content pt-3">
    <div class="card card-outline card-shl">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><?php echo lang($title->pagename) ?></h3>
            <div class="card-tools">
                <a href="<?php echo route_to('pages', 1) ?>" class="btn btn-outline-info btn-sm"><?php echo lang('Backend.backToList') ?></a>
            </div>
        </div>
        <div class="card-body">

            <?php /* Hata mesajları */ ?>
            <?php if (session()->has('errors')): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <ul class="mb-0">
                        <?php foreach (session('errors') as $error): ?>
                            <li><?php echo esc($error) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form action="<?php echo $formAction ?>" class="form-row" method="post">
                <?php echo csrf_field() ?>

                <!-- ======================== Sol Kolon: Dil İçerikleri ======================== -->
                <div class="col-md-8 form-group row">
                    <?php if (setting('App.siteLanguageMode') === 'multi' && !empty($languages)): ?>
                        <div class="col-md-12">
                            <ul class="nav nav-tabs" id="custom-tabs-lang" role="tablist">
                                <?php $i = 0;
                                foreach ($languages as $lang): ?>
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo $i === 0 ? 'active' : '' ?>"
                                           id="custom-tabs-lang-<?php echo $lang->code ?>-tab"
                                           data-toggle="pill"
                                           href="#custom-tabs-lang-<?php echo $lang->code ?>"
                                           role="tab"
                                           aria-controls="custom-tabs-lang-<?php echo $lang->code ?>"
                                           aria-selected="<?php echo $i === 0 ? 'true' : 'false' ?>">
                                            <?php echo esc($lang->name) ?>
                                        </a>
                                    </li>
                                <?php $i++;
                                endforeach; ?>
                            </ul>
                            <div class="tab-content" id="custom-tabs-lang-tabContent">
                                <?php $i = 0;
                                foreach ($languages as $lang): ?>
                                    <div class="tab-pane fade <?php echo $i === 0 ? 'show active' : '' ?>"
                                         id="custom-tabs-lang-<?php echo $lang->code ?>"
                                         role="tabpanel"
                                         aria-labelledby="custom-tabs-lang-<?php echo $lang->code ?>-tab">

                                        <div class="form-group mt-3">
                                            <label><?php echo lang('Backend.title') . ' ' . lang('Backend.required') ?></label>
                                            <input type="text"
                                                   name="lang[<?php echo $lang->code ?>][title]"
                                                   class="form-control ptitle"
                                                   data-lang="<?php echo $lang->code ?>"
                                                   placeholder="<?php echo lang('Backend.title') ?>"
                                                   value="<?php echo old("lang.{$lang->code}.title", $isEdit ? ($langsData[$lang->code]->title ?? '') : '') ?>"
                                                   required>
                                        </div>

                                        <div class="form-group">
                                            <label><?php echo lang('Backend.url') . ' ' . lang('Backend.required') ?></label>
                                            <input type="text"
                                                   class="form-control seflink"
                                                   data-lang="<?php echo $lang->code ?>"
                                                   name="lang[<?php echo $lang->code ?>][seflink]"
                                                   value="<?php echo old("lang.{$lang->code}.seflink", $isEdit ? ($langsData[$lang->code]->seflink ?? '') : '') ?>"
                                                   required>
                                        </div>

                                        <div class="form-group">
                                            <label><?php echo lang('Backend.content') . ' ' . lang('Backend.required') ?></label>
                                            <textarea name="lang[<?php echo $lang->code ?>][content]"
                                                      rows="60"
                                                      class="form-control editor"
                                                      required><?php echo old("lang.{$lang->code}.content", $isEdit ? ($langsData[$lang->code]->content ?? '') : '') ?></textarea>
                                        </div>
                                    </div>
                                <?php $i++;
                                endforeach; ?>
                            </div>
                        </div>

                    <?php else: ?>
                        <!-- Tek Dil Modu -->
                        <div class="form-group col-md-12">
                            <label><?php echo lang('Backend.title') . ' ' . lang('Backend.required') ?></label>
                            <input type="text"
                                   name="lang[<?php echo $defaultLocale ?>][title]"
                                   data-lang="<?php echo $defaultLocale ?>"
                                   class="form-control ptitle"
                                   placeholder="<?php echo lang('Backend.title') ?>"
                                   value="<?php echo old("lang.{$defaultLocale}.title", $isEdit ? ($langsData[$defaultLocale]->title ?? '') : '') ?>"
                                   required>
                        </div>

                        <div class="form-group col-md-12">
                            <label><?php echo lang('Backend.url') . ' ' . lang('Backend.required') ?></label>
                            <input type="text"
                                   class="form-control seflink"
                                   data-lang="<?php echo $defaultLocale ?>"
                                   name="lang[<?php echo $defaultLocale ?>][seflink]"
                                   value="<?php echo old("lang.{$defaultLocale}.seflink", $isEdit ? ($langsData[$defaultLocale]->seflink ?? '') : '') ?>"
                                   required>
                        </div>

                        <div class="form-group col-md-12">
                            <label><?php echo lang('Backend.content') . ' ' . lang('Backend.required') ?></label>
                            <textarea name="lang[<?php echo $defaultLocale ?>][content]"
                                      rows="60"
                                      class="form-control editor"
                                      required><?php echo old("lang.{$defaultLocale}.content", $isEdit ? ($langsData[$defaultLocale]->content ?? '') : '') ?></textarea>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ======================== Sağ Kolon: Durum & SEO ======================== -->
                <div class="col-md-4 form-group row">

                    <!-- Yayın Durumu -->
                    <div class="form-group col-md-12">
                        <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                            <?php
                            // create modunda varsayılan "Yayında" (1), edit modunda mevcut değer
                            $currentActive = $isEdit ? (bool) $pageInfo->isActive : true;
                            ?>
                            <label class="btn btn-outline-secondary <?php echo $currentActive === false ? 'active' : '' ?>">
                                <input type="radio"
                                       name="isActive"
                                       id="option1"
                                       autocomplete="off"
                                       value="0"
                                       <?php echo set_radio('isActive', '0', $currentActive === false) ?>>
                                <?php echo lang('Backend.draft') ?>
                            </label>
                            <label class="btn btn-outline-secondary <?php echo $currentActive === true ? 'active' : '' ?>">
                                <input type="radio"
                                       name="isActive"
                                       id="option2"
                                       autocomplete="off"
                                       value="1"
                                       <?php echo set_radio('isActive', '1', $currentActive === true) ?>>
                                <?php echo lang('Backend.publish') ?>
                            </label>
                        </div>
                    </div>

                    <!-- Kapak Görseli -->
                    <div class="form-group col-md-12 row">
                        <div class="col-md-12 form-group">
                            <label><?php echo lang('Backend.coverImage') ?></label>
                            <?php
                            $coverImg = $isEdit && !empty($pageInfo->seo->coverImage)
                                ? $pageInfo->seo->coverImage
                                : old('pageimg', '');
                            ?>
                            <img src="<?php echo esc($coverImg) ?>" class="pageimg img-fluid">
                        </div>

                        <div class="col-md-12 form-group">
                            <label><?php echo lang('Backend.coverImgURL') ?></label>
                            <input type="text"
                                   name="pageimg"
                                   class="form-control pageimg-input"
                                   value="<?php echo old('pageimg', $isEdit && !empty($pageInfo->seo->coverImage) ? $pageInfo->seo->coverImage : '') ?>"
                                   placeholder="<?php echo lang('Backend.coverImgURL') ?>">
                        </div>

                        <div class="col-md-12 row form-group">
                            <div class="col-sm-6">
                                <label><?php echo lang('Backend.coverImgWith') ?></label>
                                <input type="number"
                                       name="pageIMGWidth"
                                       class="form-control"
                                       id="pageIMGWidth"
                                       value="<?php echo old('pageIMGWidth', $isEdit && !empty($pageInfo->seo->IMGWidth) ? $pageInfo->seo->IMGWidth : '') ?>"
                                       readonly>
                            </div>
                            <div class="col-sm-6">
                                <label><?php echo lang('Backend.coverImgHeight') ?></label>
                                <input type="number"
                                       name="pageIMGHeight"
                                       class="form-control"
                                       id="pageIMGHeight"
                                       value="<?php echo old('pageIMGHeight', $isEdit && !empty($pageInfo->seo->IMGHeight) ? $pageInfo->seo->IMGHeight : '') ?>"
                                       readonly>
                            </div>
                        </div>

                        <div class="col-md-12 form-group">
                            <button type="button" class="pageIMG btn btn-info w-100">
                                <?php echo lang('Backend.selectCoverImg') ?>
                            </button>
                        </div>
                    </div>

                    <!-- SEO Açıklaması -->
                    <div class="form-group col-md-12">
                        <label><?php echo lang('Backend.seoDescription') ?></label>
                        <textarea class="form-control" name="description"><?php echo old('description', $isEdit && !empty($pageInfo->seo->description) ? $pageInfo->seo->description : '') ?></textarea>
                    </div>

                    <!-- SEO Anahtar Kelimeler -->
                    <div class="form-group col-md-12">
                        <label><?php echo lang('Backend.seoKeywords') ?></label>
                        <textarea name="keywords"
                                  class="keywords"
                                  placeholder="<?php echo lang('Backend.tagPlaceholder') ?>"><?php echo old('keywords', $isEdit && !empty($pageInfo->seo->keywords) ? json_encode($pageInfo->seo->keywords) : '') ?></textarea>
                    </div>
                </div>

                <!-- ======================== Kaydet / Güncelle Butonu ======================== -->
                <div class="form-group col-md-12">
                    <button type="submit" class="btn btn-success float-right">
                        <?php echo $isEdit ? lang('Backend.update') : lang('Backend.add') ?>
                    </button>
                </div>

            </form>
        </div>
    </div>
</section>
<?php echo $this->endSection();
echo $this->section('javascript');
echo script_tag("be-assets/plugins/jquery-ui/jquery-ui.js");
echo script_tag("be-assets/plugins/tagify/jQuery.tagify.min.js");
echo script_tag("be-assets/plugins/summernote/summernote-bs4.js");
echo script_tag("be-assets/plugins/elFinder/js/elfinder.full.js");
echo script_tag("be-assets/plugins/elFinder/js/i18n/elfinder." . env('app.defaultLocale', 'tr') . ".js");
echo script_tag("be-assets/plugins/elFinder/js/extras/editors.default.js");
echo script_tag("be-assets/plugins/summernote/plugin/elfinder/summernote-ext-elfinder.js");
echo script_tag("be-assets/js/ci4ms.js"); ?>
<script type="text/javascript" <?php echo csp_script_nonce(); ?>>
    tags([]);

    <?php if (!$isEdit): ?>
    // Yeni kayıt modunda: başlık yazıldığında seflink otomatik üretilir
    <?php endif; ?>

    $('.ptitle').on('change', function () {
        $.post('<?php echo route_to('checkSeflink') ?>', {
            [CI4MS_CSRF.name]: CI4MS_CSRF.getHash(),
            'makeSeflink': $(this).val(),
            'where': 'pages'
        }, 'json').done(function (data) {
            $('.seflink[data-lang="' + this.dataset?.lang + '"]').val(data.seflink);
        }.bind(this));
    });

    $('.seflink').on('change', function () {
        $.post('<?php echo route_to('checkSeflink') ?>', {
            [CI4MS_CSRF.name]: CI4MS_CSRF.getHash(),
            'makeSeflink': $(this).val(),
            'where': 'pages'
        }, 'json').done(function (data) {
            $(this).val(data.seflink);
        }.bind(this));
    });
</script>
<?php echo $this->endSection() ?>
