<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('title');
echo lang($title->pagename);
echo $this->endSection();
echo $this->section('head'); ?>
<link rel="stylesheet" href="/be-assets/plugins/tagify/tagify.css">
<link rel="stylesheet" href="/be-assets/plugins/summernote/summernote-bs4.css">
<link rel="stylesheet" href="/be-assets/plugins/jquery-ui/jquery-ui.css">
<link rel="stylesheet" href="/be-assets/plugins/jquery-ui/themes/smoothness/jquery-ui.min.css">
<link rel="stylesheet" href="/be-assets/plugins/elFinder/css/elfinder.full.css">
<link rel="stylesheet" href="/be-assets/plugins/select2/css/select2.min.css">
<link rel="stylesheet" href="/be-assets/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css">
<?php echo $this->endSection();
echo $this->section('content');
$isEdit        = isset($infos);
$formAction    = $isEdit
    ? route_to('blogUpdate', $infos->id)
    : route_to('blogCreate');
$defaultLocale = setting('App.defaultLocale') ?: 'tr';

$seoInfo = null;
if ($isEdit && !empty($langsData)) {
    $defLangData = $langsData[$defaultLocale] ?? current($langsData);
    $seoInfo     = $defLangData->seo ?? null;
} ?>
<section class="content pt-3">
    <div class="card card-outline shadow-sm">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><?php echo lang($title->pagename) ?></h3>
            <div class="card-tools">
                <a href="<?php echo route_to('blogs', 1) ?>" class="btn btn-sm btn-outline-info"><?php echo lang('Backend.backToList') ?></a>
            </div>
        </div>
        <div class="card-body">
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
                                foreach ($languages as $lang):
                                    $langData = $isEdit ? ($langsData[$lang->code] ?? null) : null;
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
                                                   value="<?php echo old("lang.{$lang->code}.title", $langData ? esc($langData->title) : '') ?>"
                                                   required>
                                        </div>
                                        <div class="form-group">
                                            <label><?php echo lang('Backend.url') . ' ' . lang('Backend.required') ?></label>
                                            <input type="text"
                                                   class="form-control seflink"
                                                   data-lang="<?php echo $lang->code ?>"
                                                   name="lang[<?php echo $lang->code ?>][seflink]"
                                                   value="<?php echo old("lang.{$lang->code}.seflink", $langData ? esc($langData->seflink) : '') ?>"
                                                   required>
                                        </div>
                                        <div class="form-group">
                                            <label><?php echo lang('Backend.content') . ' ' . lang('Backend.required') ?></label>
                                            <textarea name="lang[<?php echo $lang->code ?>][content]"
                                                      rows="60"
                                                      class="form-control editor"
                                                      required><?php echo old("lang.{$lang->code}.content", $langData ? esc($langData->content) : '') ?></textarea>
                                        </div>
                                    </div>
                                <?php $i++;
                                endforeach; ?>
                            </div>
                        </div>

                    <?php else: ?>
                        <?php $langData = $isEdit ? ($langsData[$defaultLocale] ?? null) : null; ?>
                        <div class="form-group col-md-12">
                            <label><?php echo lang('Backend.title') . ' ' . lang('Backend.required') ?></label>
                            <input type="text"
                                   name="lang[<?php echo $defaultLocale ?>][title]"
                                   data-lang="<?php echo $defaultLocale ?>"
                                   class="form-control ptitle"
                                   placeholder="<?php echo lang('Backend.title') ?>"
                                   value="<?php echo old("lang.{$defaultLocale}.title", $langData ? esc($langData->title) : '') ?>"
                                   required>
                        </div>
                        <div class="form-group col-md-12">
                            <label><?php echo lang('Backend.url') . ' ' . lang('Backend.required') ?></label>
                            <input type="text"
                                   class="form-control seflink"
                                   data-lang="<?php echo $defaultLocale ?>"
                                   name="lang[<?php echo $defaultLocale ?>][seflink]"
                                   value="<?php echo old("lang.{$defaultLocale}.seflink", $langData ? esc($langData->seflink) : '') ?>"
                                   required>
                        </div>
                        <div class="form-group col-md-12">
                            <label><?php echo lang('Backend.content') . ' ' . lang('Backend.required') ?></label>
                            <textarea name="lang[<?php echo $defaultLocale ?>][content]"
                                      rows="60"
                                      class="form-control editor"
                                      required><?php echo old("lang.{$defaultLocale}.content", $langData ? esc($langData->content) : '') ?></textarea>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- ======================== Sağ Kolon: Meta & SEO ======================== -->
                <div class="col-md-4 form-group row">

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

                    <!-- Yazar -->
                    <div class="col-md-12 form-group">
                        <label><?php echo lang('Blog.author') . ' ' . lang('Backend.required') ?></label>
                        <select name="author" class="form-control" required>
                            <option value=""><?php echo lang('Backend.select') ?></option>
                            <?php foreach ($authors as $author): ?>
                                <option value="<?php echo $author->id ?>"
                                    <?php echo set_select('author', $author->id, $isEdit ? $author->id == $infos->author : $author->id == $logged_in_user->id) ?>>
                                    <?php echo esc($author->firstname . ' ' . $author->surname) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Tarih -->
                    <div class="col-md-12 form-group">
                        <label><?php echo lang('Backend.createdAt') . ' ' . lang('Backend.required') ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                            </div>
                            <input type="text" class="form-control" id="datemask" name="created_at"
                                   value="<?php echo $isEdit
                                       ? date('d.m.Y H:i:s', strtotime($infos->created_at))
                                       : date('d.m.Y H:i:s') ?>"
                                   required>
                        </div>
                    </div>

                    <!-- Kategoriler -->
                    <div class="col-md-12 form-group">
                        <label><?php echo lang('Blog.categories') . ' ' . lang('Backend.required') ?></label>
                        <select name="categories[]" class="form-control select2bs4" multiple="multiple"
                                data-placeholder="<?php echo lang('Backend.selectOption', [lang('Blog.categories')]) ?>">
                            <?php if ($isEdit && !empty($infos->categories)):
                                $selectedIds = array_column((array)$infos->categories, 'categories_id');
                                foreach ($categories as $category):
                                    $isSelected = in_array($category->id, $selectedIds);
                            ?>
                                <option value="<?php echo $category->id ?>" <?php echo $isSelected ? 'selected' : '' ?>>
                                    <?php echo esc($category->title) ?>
                                </option>
                            <?php endforeach;
                            else:
                                foreach ($categories as $category): ?>
                                    <option value="<?php echo $category->id ?>"><?php echo esc($category->title) ?></option>
                                <?php endforeach;
                            endif; ?>
                        </select>
                    </div>

                    <!-- Kapak Görseli -->
                    <div class="form-group col-md-12 row">
                        <div class="col-md-12 form-group">
                            <label><?php echo lang('Backend.coverImage') ?></label>
                            <img src="<?php echo old('pageimg', !empty($seoInfo->coverImage) ? esc($seoInfo->coverImage) : '') ?>"
                                 alt="" class="pageimg img-fluid">
                        </div>
                        <div class="col-md-12 form-group">
                            <label><?php echo lang('Backend.coverImgURL') ?></label>
                            <input type="text" name="pageimg" class="form-control pageimg-input"
                                   placeholder="<?php echo lang('Backend.coverImgURL') ?>"
                                   value="<?php echo old('pageimg', !empty($seoInfo->coverImage) ? esc($seoInfo->coverImage) : '') ?>">
                        </div>
                        <div class="col-md-12 row form-group">
                            <div class="col-sm-6">
                                <label><?php echo lang('Backend.coverImgWith') ?></label>
                                <input type="number" name="pageIMGWidth" class="form-control" id="pageIMGWidth"
                                       readonly value="<?php echo old('pageIMGWidth', !empty($seoInfo->IMGWidth) ? esc($seoInfo->IMGWidth) : '') ?>">
                            </div>
                            <div class="col-sm-6">
                                <label><?php echo lang('Backend.coverImgHeight') ?></label>
                                <input type="number" name="pageIMGHeight" class="form-control" id="pageIMGHeight"
                                       readonly value="<?php echo old('pageIMGHeight', !empty($seoInfo->IMGHeight) ? esc($seoInfo->IMGHeight) : '') ?>">
                            </div>
                        </div>
                        <div class="col-md-12 form-group">
                            <button type="button" class="pageIMG btn btn-info w-100"><?php echo lang('Backend.selectCoverImg') ?></button>
                        </div>
                    </div>

                    <!-- SEO Açıklaması -->
                    <div class="form-group col-md-12">
                        <label><?php echo lang('Backend.seoDescription') ?></label>
                        <textarea class="form-control" name="description"><?php echo old('description', !empty($seoInfo->description) ? esc($seoInfo->description) : '') ?></textarea>
                    </div>

                    <!-- SEO Anahtar Kelimeler -->
                    <div class="form-group col-md-12">
                        <label><?php echo lang('Backend.seoKeywords') ?></label>
                        <textarea name="keywords" class="keywords"
                                  placeholder="<?php echo lang('Backend.tagPlaceholder') ?>"><?php echo $isEdit ? ($tags ?? '') : '' ?></textarea>
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
echo script_tag("be-assets/plugins/summernote/summernote-bs4.js");
echo script_tag("be-assets/plugins/elFinder/js/elfinder.full.js");
echo script_tag("be-assets/plugins/elFinder/js/i18n/elfinder." . env('app.defaultLocale', 'tr') . ".js");
echo script_tag("be-assets/plugins/elFinder/js/extras/editors.default.js");
echo script_tag("be-assets/plugins/summernote/plugin/elfinder/summernote-ext-elfinder.js");
echo script_tag("be-assets/plugins/select2/js/select2.full.min.js");
echo script_tag("be-assets/js/ci4ms.js");
echo script_tag("be-assets/plugins/moment/moment.min.js");
echo script_tag("be-assets/plugins/inputmask/jquery.inputmask.min.js") ?>
<script type="text/javascript" <?php echo csp_script_nonce(); ?>>
    // Create modunda tüm tag'ları yükle
    $.ajax({
        method: "POST",
        url: "<?php echo route_to('tagify') ?>",
        data: {
            [CI4MS_CSRF.name]: CI4MS_CSRF.getHash(),
            "type": 'blogs'
        },
        statusCode: {
            404: function () { tags([]); }
        },
        success: function (data) { tags(data); }
    });

    $('.ptitle').on('change', function () {
        let lang = $(this).data('lang');
        $.post('<?php echo route_to('checkSeflink') ?>', {
            [CI4MS_CSRF.name]: CI4MS_CSRF.getHash(),
            'makeSeflink': $(this).val(),
            'where': 'blog_langs'
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
            'where': '<?php echo $isEdit ? 'blog_langs' : 'blog' ?>'
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

    $('#datemask').inputmask("datetime", { inputFormat: 'dd.mm.yyyy HH:MM:ss' });
</script>
<?php echo $this->endSection() ?>
