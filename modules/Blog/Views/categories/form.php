<?php
/**
 * Blog/Categories - Birleşik Form View (Oluştur & Güncelle)
 *
 * $isEdit değişkeni controller tarafından gönderilir:
 *   - new()  → $isEdit = false, $infos yok
 *   - edit() → $isEdit = true,  $infos ve $langsData dolu
 */
$isEdit        = isset($infos);
$formAction    = $isEdit
    ? route_to('categoryUpdate', $infos->id)
    : route_to('categoryCreate');
$defaultLocale = setting('App.defaultLocale') ?: 'tr';

// Edit modunda SEO verisi: default locale veya ilk dil
$langInfo = null;
if ($isEdit && !empty($langsData)) {
    $langInfo = $langsData[$defaultLocale] ?? current($langsData);
}
$seoInfo = $langInfo->seo ?? null;
?>
<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('title');
echo lang($title->pagename);
echo $this->endSection();
echo $this->section('head'); ?>
<link rel="stylesheet" href="/be-assets/plugins/tagify/tagify.css">
<link rel="stylesheet" href="/be-assets/plugins/jquery-ui/jquery-ui.css">
<link rel="stylesheet" href="/be-assets/plugins/jquery-ui/themes/smoothness/jquery-ui.min.css">
<link rel="stylesheet" href="/be-assets/plugins/elFinder/css/elfinder.full.css">
<link rel="stylesheet" href="/be-assets/plugins/elFinder/css/theme.css">
<link rel="stylesheet" href="/be-assets/plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="/be-assets/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
<?php echo $this->endSection();
echo $this->section('content'); ?>
<section class="content pt-3">
    <div class="card card-outline shadow-sm">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><?php echo lang($title->pagename) ?></h3>
            <div class="card-tools">
                <a href="<?php echo route_to('categories', 1) ?>" class="btn btn-sm btn-outline-info"><?php echo lang('Backend.backToList') ?></a>
            </div>
        </div>
        <div class="card-body">

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
                <div class="col-md-8">
                    <?php if (setting('App.siteLanguageMode') === 'multi' && !empty($languages)): ?>
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
                            foreach ($languages as $lang):
                                $curLangInfo = $isEdit ? ($langsData[$lang->code] ?? null) : null;
                            ?>
                                <div class="tab-pane fade <?php echo $i === 0 ? 'show active' : '' ?>"
                                     id="custom-tabs-lang-<?php echo $lang->code ?>"
                                     role="tabpanel"
                                     aria-labelledby="custom-tabs-lang-<?php echo $lang->code ?>-tab">

                                    <div class="form-group mt-3">
                                        <label><?php echo lang('Backend.title') . ' ' . lang('Backend.required') ?></label>
                                        <input type="text"
                                               name="lang[<?php echo $lang->code ?>][title]"
                                               data-lang="<?php echo $lang->code ?>"
                                               class="form-control ptitle"
                                               placeholder="<?php echo lang('Backend.title') ?>"
                                               value="<?php echo old("lang.{$lang->code}.title", $curLangInfo ? $curLangInfo->title : '') ?>"
                                               required>
                                    </div>
                                    <div class="form-group">
                                        <label><?php echo lang('Backend.url') . ' ' . lang('Backend.required') ?></label>
                                        <input type="text"
                                               class="form-control seflink"
                                               data-lang="<?php echo $lang->code ?>"
                                               name="lang[<?php echo $lang->code ?>][seflink]"
                                               value="<?php echo old("lang.{$lang->code}.seflink", $curLangInfo ? $curLangInfo->seflink : '') ?>"
                                               required>
                                    </div>
                                </div>
                            <?php $i++;
                            endforeach; ?>
                        </div>

                    <?php else: ?>
                        <?php $curLangInfo = $isEdit ? ($langsData[$defaultLocale] ?? null) : null; ?>
                        <div class="form-group mt-3">
                            <label><?php echo lang('Backend.title') . ' ' . lang('Backend.required') ?></label>
                            <input type="text"
                                   name="lang[<?php echo $defaultLocale ?>][title]"
                                   data-lang="<?php echo $defaultLocale ?>"
                                   class="form-control ptitle"
                                   placeholder="<?php echo lang('Backend.title') ?>"
                                   value="<?php echo old("lang.{$defaultLocale}.title", $curLangInfo ? $curLangInfo->title : '') ?>"
                                   required>
                        </div>
                        <div class="form-group">
                            <label><?php echo lang('Backend.url') . ' ' . lang('Backend.required') ?></label>
                            <input type="text"
                                   class="form-control seflink"
                                   data-lang="<?php echo $defaultLocale ?>"
                                   name="lang[<?php echo $defaultLocale ?>][seflink]"
                                   value="<?php echo old("lang.{$defaultLocale}.seflink", $curLangInfo ? $curLangInfo->seflink : '') ?>"
                                   required>
                        </div>
                    <?php endif; ?>

                    <!-- SEO Açıklaması (Sol kolonda) -->
                    <div class="form-group">
                        <label><?php echo lang('Backend.seoDescription') ?></label>
                        <textarea name="description" class="form-control" rows="10"><?php echo old('description', !empty($seoInfo->description) ? $seoInfo->description : '') ?></textarea>
                    </div>
                </div>

                <!-- ======================== Sağ Kolon: Durum & Görsel & SEO ======================== -->
                <div class="col-md-4 row">

                    <!-- Yayın Durumu -->
                    <div class="form-group col-md-12">
                        <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                            <?php $currentActive = $isEdit ? (bool)$infos->isActive : true; ?>
                            <label class="btn btn-outline-secondary <?php echo $currentActive === false ? 'active' : '' ?>">
                                <input type="radio" name="isActive" id="option1" autocomplete="off" value="0"
                                       <?php echo set_radio('isActive', '0', $currentActive === false) ?>>
                                <?php echo lang('Backend.draft') ?>
                            </label>
                            <label class="btn btn-outline-secondary <?php echo $currentActive === true ? 'active' : '' ?>">
                                <input type="radio" name="isActive" id="option2" autocomplete="off" value="1"
                                       <?php echo set_radio('isActive', '1', $currentActive === true) ?>>
                                <?php echo lang('Backend.publish') ?>
                            </label>
                        </div>
                    </div>

                    <!-- Üst Kategori -->
                    <div class="col-md-12 form-group">
                        <label><?php echo lang('Blog.parentCategory') ?></label>
                        <select name="parent" class="form-control select2bs4" data-placeholder="<?php echo lang('Backend.select') ?>">
                            <option value=""><?php echo lang('Backend.select') ?></option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category->id ?>"
                                    <?php echo set_select('parent', $category->id, $isEdit && $infos->parent == $category->id) ?>>
                                    <?php echo esc($category->title) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Kapak Görseli -->
                    <div class="col-md-12 form-group">
                        <label><?php echo lang('Backend.coverImage') ?></label>
                        <img src="<?php echo esc(old('pageimg', !empty($seoInfo->coverImage) ? $seoInfo->coverImage : '')) ?>"
                             class="pageimg img-fluid">
                    </div>
                    <div class="col-md-12 form-group">
                        <label><?php echo lang('Backend.coverImgURL') ?></label>
                        <input type="text" name="pageimg" class="form-control pageimg-input"
                               placeholder="<?php echo lang('Backend.coverImgURL') ?>"
                               value="<?php echo old('pageimg', !empty($seoInfo->coverImage) ? $seoInfo->coverImage : '') ?>">
                    </div>
                    <div class="col-md-12 row form-group">
                        <div class="col-sm-6">
                            <label><?php echo lang('Backend.coverImgWith') ?></label>
                            <input type="number" name="pageIMGWidth" class="form-control" id="pageIMGWidth"
                                   readonly value="<?php echo old('pageIMGWidth', !empty($seoInfo->IMGWidth) ? $seoInfo->IMGWidth : '') ?>">
                        </div>
                        <div class="col-sm-6">
                            <label><?php echo lang('Backend.coverImgHeight') ?></label>
                            <input type="number" name="pageIMGHeight" class="form-control" id="pageIMGHeight"
                                   readonly value="<?php echo old('pageIMGHeight', !empty($seoInfo->IMGHeight) ? $seoInfo->IMGHeight : '') ?>">
                        </div>
                    </div>
                    <div class="col-md-12 form-group">
                        <button type="button" class="pageIMG btn btn-info w-100"><?php echo lang('Backend.selectCoverImg') ?></button>
                    </div>

                    <!-- SEO Anahtar Kelimeler -->
                    <div class="col-md-12 form-group">
                        <label><?php echo lang('Backend.seoKeywords') ?></label>
                        <textarea name="keywords" class="keywords"
                                  placeholder="<?php echo lang('Backend.tagPlaceholder') ?>"><?php echo old('keywords', !empty($seoInfo->keywords) ? $seoInfo->keywords : '') ?></textarea>
                    </div>
                </div>

                <!-- ======================== Kaydet / Güncelle ======================== -->
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
echo script_tag("be-assets/plugins/elFinder/js/elfinder.full.js");
echo script_tag("be-assets/plugins/elFinder/js/i18n/elfinder." . env('app.defaultLocale', 'tr') . ".js");
echo script_tag("be-assets/plugins/elFinder/js/extras/editors.default.js");
echo script_tag("be-assets/plugins/summernote/plugin/elfinder/summernote-ext-elfinder.js");
echo script_tag("be-assets/plugins/select2/js/select2.full.min.js");
echo script_tag("be-assets/js/ci4ms.js"); ?>
<script type="text/javascript" <?php echo csp_script_nonce(); ?>>
    tags([]);

    $('.ptitle').on('change', function () {
        let lang = $(this).data('lang');
        $.post('<?php echo route_to('checkSeflink') ?>', {
            [CI4MS_CSRF.name]: CI4MS_CSRF.getHash(),
            'makeSeflink': $(this).val(),
            'where': '<?php echo $isEdit ? 'categories_langs' : 'categories' ?>'
            <?php if ($isEdit): ?>
            , 'update': 1
            , 'id': <?php echo $infos->id ?>
            , 'locale': lang
            <?php endif; ?>
        }, 'json').done(function (data) {
            $('.seflink[data-lang="' + lang + '"]').val(data.seflink);
        });
    });

    $('.seflink').on('change', function () {
        let lang = $(this).data('lang');
        $.post('<?php echo route_to('checkSeflink') ?>', {
            [CI4MS_CSRF.name]: CI4MS_CSRF.getHash(),
            'makeSeflink': $(this).val(),
            'where': '<?php echo $isEdit ? 'categories_langs' : 'categories' ?>'
            <?php if ($isEdit): ?>
            , 'update': 1
            , 'id': <?php echo $infos->id ?>
            , 'locale': lang
            <?php endif; ?>
        }, 'json').done(function (data) {
            $('.seflink[data-lang="' + lang + '"]').val(data.seflink);
        });
    });

    $('.select2bs4').select2({ theme: 'bootstrap4' });
</script>
<?php echo $this->endSection() ?>
