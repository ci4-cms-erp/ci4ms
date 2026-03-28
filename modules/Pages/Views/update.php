<?php echo $this->extend('Modules\Backend\Views\base');
echo $this->section('title');
echo lang($title->pagename);
echo $this->endSection();
echo $this->section('head');
echo link_tag("be-assets/plugins/tagify/tagify.css");
echo link_tag("be-assets/plugins/summernote/summernote-bs4.css");
echo link_tag("be-assets/plugins/jquery-ui/jquery-ui.css");
echo link_tag("be-assets/plugins/elFinder/css/elfinder.full.css"); ?>
<link rel="stylesheet" type="text/css"
    href="//cdnjs.cloudflare.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css">
<?php echo $this->endSection();
echo $this->section('content'); ?>
<!-- Main content -->
<section class="content">
    <!-- Default box -->
    <div class="card card-outline card-shl">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><?php echo lang($title->pagename) ?></h3>

            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <a href="<?php echo route_to('pages', 1) ?>" class="btn btn-outline-info"><?php echo lang('Backend.backToList') ?></a>
                </button>
            </div>
        </div>
        <div class="card-body">
            <form action="<?php echo route_to('pageUpdate', $pageInfo->id) ?>" class="form-row" method="post">
                <?php echo csrf_field() ?>
                <div class="col-md-8 form-group row">
                    <?php if (setting('App.siteLanguageMode') === 'multi' && !empty($languages)): ?>
                        <div class="col-md-12">
                            <ul class="nav nav-tabs" id="custom-tabs-lang" role="tablist">
                                <?php $i = 0;
                                foreach ($languages as $lang): ?>
                                    <li class="nav-item">
                                        <a class="nav-link <?php echo $i === 0 ? 'active' : '' ?>" id="custom-tabs-lang-<?php echo $lang->code ?>-tab" data-toggle="pill" href="#custom-tabs-lang-<?php echo $lang->code ?>" role="tab" aria-controls="custom-tabs-lang-<?php echo $lang->code ?>" aria-selected="<?php echo $i === 0 ? 'true' : 'false' ?>"><?php echo esc($lang->name) ?></a>
                                    </li>
                                <?php $i++;
                                endforeach; ?>
                            </ul>
                            <div class="tab-content" id="custom-tabs-lang-tabContent">
                                <?php $i = 0;
                                foreach ($languages as $lang): ?>
                                    <div class="tab-pane fade <?php echo $i === 0 ? 'show active' : '' ?>" id="custom-tabs-lang-<?php echo $lang->code ?>" role="tabpanel" aria-labelledby="custom-tabs-lang-<?php echo $lang->code ?>-tab">
                                        <div class="form-group mt-3">
                                            <label for=""><?php echo lang('Backend.title') . ' ' . lang('Backend.required') ?></label>
                                            <input type="text" name="lang[<?php echo $lang->code ?>][title]" class="form-control ptitle" data-lang="<?php echo $lang->code ?>" placeholder="<?php echo lang('Backend.title') ?>" value="<?php echo old("lang.{$lang->code}.title", $langsData[$lang->code]->title ?? '') ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for=""><?php echo lang('Backend.url') . ' ' . lang('Backend.required') ?></label>
                                            <input type="text" class="form-control seflink" data-lang="<?php echo $lang->code ?>" name="lang[<?php echo $lang->code ?>][seflink]" value="<?php echo old("lang.{$lang->code}.seflink", $langsData[$lang->code]->seflink ?? '') ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for=""><?php echo lang('Backend.content') . ' ' . lang('Backend.required') ?></label>
                                            <textarea name="lang[<?php echo $lang->code ?>][content]" rows="60" class="form-control editor" required><?php echo old("lang.{$lang->code}.content", $langsData[$lang->code]->content ?? '') ?></textarea>
                                        </div>
                                    </div>
                                <?php $i++;
                                endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php $defaultLocale = setting('App.defaultLocale') ?: 'tr'; ?>
                        <div class="form-group col-md-12">
                            <label for=""><?php echo lang('Backend.title') . ' ' . lang('Backend.required') ?></label>
                            <input type="text" name="lang[<?php echo $defaultLocale ?>][title]" data-lang="<?php echo $defaultLocale ?>" class="form-control ptitle" placeholder="<?php echo lang('Backend.title') ?>" value="<?php echo old("lang.{$defaultLocale}.title", $langsData[$defaultLocale]->title ?? '') ?>" required>
                        </div>
                        <div class="form-group col-md-12">
                            <label for=""><?php echo lang('Backend.url') . ' ' . lang('Backend.required') ?></label>
                            <input type="text" class="form-control seflink" data-lang="<?php echo $defaultLocale ?>" name="lang[<?php echo $defaultLocale ?>][seflink]" value="<?php echo old("lang.{$defaultLocale}.seflink", $langsData[$defaultLocale]->seflink ?? '') ?>" required>
                        </div>
                        <div class="form-group col-md-12">
                            <label for=""><?php echo lang('Backend.content') . ' ' . lang('Backend.required') ?></label>
                            <textarea name="lang[<?php echo $defaultLocale ?>][content]" rows="60" class="form-control editor" required><?php echo old("lang.{$defaultLocale}.content", $langsData[$defaultLocale]->content ?? '') ?></textarea>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 form-group row">
                    <div class="form-group col-md-12">
                        <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                            <label class="btn btn-outline-secondary" <?php echo ((bool)$pageInfo->isActive === false) ? 'active' : '' ?>>
                                <input type="radio" name="isActive" id="option1" autocomplete="off" value="0" <?php echo set_radio('isActive', '0', (bool)$pageInfo->isActive === false) ?>> Taslak
                            </label>
                            <label class="btn btn-outline-secondary <?php echo ((bool)$pageInfo->isActive === true) ? 'active' : '' ?>">
                                <input type="radio" name="isActive" id="option2" autocomplete="off" <?php echo set_radio('isActive', '1', (bool)$pageInfo->isActive === true) ?> value="1"> Yayında
                            </label>
                        </div>
                    </div>
                    <div class="form-group col-md-12 row">
                        <div class="col-md-12 form-group">
                            <label for=""><?php echo lang('Backend.coverImage') ?></label>
                            <img src="<?php echo (!empty($pageInfo->seo->coverImage)) ? $pageInfo->seo->coverImage : '' ?>" class="pageimg img-fluid">
                        </div>
                        <div class="col-md-12 form-group">
                            <label for=""><?php echo lang('Backend.coverImgURL') ?></label>
                            <input type="text" name="pageimg" class="form-control pageimg-input" value="<?php echo old('pageimg', (!empty($pageInfo->seo->coverImage)) ? $pageInfo->seo->coverImage : '') ?>"
                                placeholder="<?php echo lang('Backend.coverImgURL') ?>">
                        </div>
                        <div class="col-md-12 row form-group">
                            <div class="col-sm-6">
                                <label for=""><?php echo lang('Backend.coverImgWith') ?></label>
                                <input type="number" name="pageIMGWidth" class="form-control" id="pageIMGWidth" value="<?php echo old('pageIMGWidth', (!empty($pageInfo->seo->IMGWidth)) ? $pageInfo->seo->IMGWidth : '') ?>"
                                    readonly>
                            </div>
                            <div class="col-sm-6">
                                <label for=""><?php echo lang('Backend.coverImgHeight') ?></label>
                                <input type="number" name="pageIMGHeight" class="form-control" id="pageIMGHeight" value="<?php echo old('pageIMGHeight', (!empty($pageInfo->seo->IMGHeight)) ? $pageInfo->seo->IMGHeight : '') ?>"
                                    readonly>
                            </div>
                        </div>
                        <div class="col-md-12 form-group">
                            <button type="button" class="pageIMG btn btn-info w-100"><?php echo lang('Backend.selectCoverImg') ?></button>
                        </div>
                    </div>
                    <div class="form-group col-md-12">
                        <label for=""><?php echo lang('Backend.seoDescription') ?></label>
                        <textarea class="form-control" name="description"><?php echo old('description', (!empty($pageInfo->seo->description)) ? $pageInfo->seo->description : '') ?></textarea>
                    </div>
                    <div class="form-group col-md-12">
                        <label for=""><?php echo lang('Backend.seoKeywords') ?></label>
                        <textarea name="keywords" class="keywords" placeholder="<?php echo lang('Backend.tagPlaceholder') ?>"><?php echo old('keywords', !empty($pageInfo->seo->keywords) ? json_encode($pageInfo->seo->keywords) : '') ?></textarea>
                    </div>
                </div>
                <div class="form-group col-md-12">
                    <button class="btn btn-success float-right"><?php echo lang('Backend.update') ?></button>
                </div>
            </form>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->
</section>
<!-- /.content -->
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
<script {csp-script-nonce}>
    tags([]);

    $('.ptitle').on('change', function() {
        $.post('<?php echo route_to('checkSeflink') ?>', {
            "<?php echo csrf_token() ?>": "<?php echo csrf_hash() ?>",
            'makeSeflink': $(this).val(),
            'where': 'pages'
        }, 'json').done(function(data) {
            $('.seflink').val(data.seflink);
        });
    });

    $('.seflink').on('change', function() {
        $.post('<?php echo route_to('checkSeflink') ?>', {
            "<?php echo csrf_token() ?>": "<?php echo csrf_hash() ?>",
            'makeSeflink': $(this).val(),
            'where': 'pages'
        }, 'json').done(function(data) {
            $('.seflink').val(data.seflink);
        });
    });
</script>
<?php echo $this->endSection() ?>
