<?php echo $this->extend($backConfig->viewLayout) ?>

<?php echo $this->section('title') ?>
<?php echo lang($title->pagename) ?>
<?php echo $this->endSection() ?>

<?php echo $this->section('head') ?>
<?php echo link_tag("be-assets/plugins/tagify/tagify.css") ?>
<?php echo link_tag("be-assets/plugins/summernote/summernote-bs4.css") ?>
<?php echo link_tag("be-assets/plugins/jquery-ui/jquery-ui.css") ?>
<?php echo link_tag("be-assets/plugins/jquery-ui/themes/smoothness/jquery-ui.min.css") ?>
<?php echo link_tag("be-assets/plugins/elFinder/css/elfinder.full.css") ?>
<?php echo link_tag("be-assets/plugins/elFinder/css/theme.css") ?>
<!-- Select2 -->
<?php echo link_tag("be-assets/plugins/select2/css/select2.min.css") ?>
<?php echo link_tag("be-assets/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css") ?>
<?php echo $this->endSection() ?>

<?php echo $this->section('content') ?>
<!-- Main content -->
<section class="content pt-3">
    <!-- Default box -->
    <div class="card card-outline shadow-sm">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><?php echo lang($title->pagename) ?></h3>

            <div class="card-tools">
                <a href="<?php echo route_to('blogs', 1) ?>"
                    class="btn btn-sm btn-outline-info"><?php echo lang('Backend.backToList') ?></a>
            </div>
        </div>
        <div class="card-body">
            <form action="<?php echo route_to('blogUpdate', $infos->id) ?>" class="form-row" method="post">
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
                                foreach ($languages as $lang):
                                    $langData = isset($langsData[$lang->code]) ? $langsData[$lang->code] : null;
                                ?>
                                    <div class="tab-pane fade <?php echo $i === 0 ? 'show active' : '' ?>" id="custom-tabs-lang-<?php echo $lang->code ?>" role="tabpanel" aria-labelledby="custom-tabs-lang-<?php echo $lang->code ?>-tab">
                                        <div class="form-group mt-3">
                                            <label for=""><?php echo lang('Backend.title') . ' ' . lang('Backend.required') ?></label>
                                            <input type="text" name="lang[<?php echo $lang->code ?>][title]" data-lang="<?php echo $lang->code ?>" class="form-control ptitle" placeholder="<?php echo lang('Backend.title') ?>" value="<?php echo old("lang.{$lang->code}.title", $langData ? esc($langData->title) : '') ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for=""><?php echo lang('Backend.url') . ' ' . lang('Backend.required') ?></label>
                                            <input type="text" class="form-control seflink" data-lang="<?php echo $lang->code ?>" name="lang[<?php echo $lang->code ?>][seflink]" value="<?php echo old("lang.{$lang->code}.seflink", $langData ? esc($langData->seflink) : '') ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for=""><?php echo lang('Backend.content') . ' ' . lang('Backend.required') ?></label>
                                            <textarea name="lang[<?php echo $lang->code ?>][content]" rows="60" class="form-control editor" required><?php echo old("lang.{$lang->code}.content", $langData ? esc($langData->content) : '') ?></textarea>
                                        </div>
                                    </div>
                                <?php $i++;
                                endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php
                        $defaultLocale = setting('App.defaultLocale') ?: 'tr';
                        $langData = isset($langsData[$defaultLocale]) ? $langsData[$defaultLocale] : null;
                        ?>
                        <div class="form-group col-md-12">
                            <label for=""><?php echo lang('Backend.title') . ' ' . lang('Backend.required') ?></label>
                            <input type="text" name="lang[<?php echo $defaultLocale ?>][title]" data-lang="<?php echo $defaultLocale ?>" class="form-control ptitle" placeholder="<?php echo lang('Backend.title') ?>" value="<?php echo old("lang.{$defaultLocale}.title", $langData ? esc($langData->title) : '') ?>" required>
                        </div>
                        <div class="form-group col-md-12">
                            <label for=""><?php echo lang('Backend.url') . ' ' . lang('Backend.required') ?></label>
                            <input type="text" class="form-control seflink" data-lang="<?php echo $defaultLocale ?>" name="lang[<?php echo $defaultLocale ?>][seflink]" value="<?php echo old("lang.{$defaultLocale}.seflink", $langData ? esc($langData->seflink) : '') ?>" required>
                        </div>
                        <div class="form-group col-md-12">
                            <label for=""><?php echo lang('Backend.content') . ' ' . lang('Backend.required') ?></label>
                            <textarea name="lang[<?php echo $defaultLocale ?>][content]" rows="60" class="form-control editor" required><?php echo old("lang.{$defaultLocale}.content", $langData ? esc($langData->content) : '') ?></textarea>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 form-group row">
                    <div class="form-group col-md-12">
                        <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                            <label class="btn btn-outline-secondary">
                                <input type="radio" name="isActive" id="option1"
                                    autocomplete="off" <?php echo set_checkbox('isActive', '0', ($infos->isActive == 0)) ?>
                                    value="0"> <?php echo lang('Backend.draft') ?>
                            </label>
                            <label class="btn btn-outline-secondary active">
                                <input type="radio" name="isActive" id="option2"
                                    autocomplete="off" <?php echo set_checkbox('isActive', '1', ($infos->isActive == 1)) ?>
                                    value="1">
                                <?php echo lang('Backend.publish') ?>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-12 form-group">
                        <label for=""><?php echo lang('Blog.author') . ' ' . lang('Backend.required') ?></label>
                        <select name="author" id="" class="form-control" required>
                            <option value=""><?php echo lang('Blog.author') ?></option>
                            <?php foreach ($authors as $author): ?>
                                <option value="<?php echo $author->id ?>" <?php echo set_select('author', $author->id, $author->id == $infos->author) ?>><?php echo esc($author->firstname . ' ' . $author->surname) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-12 form-group">
                        <label for=""><?php echo lang('Backend.createdAt') . ' ' . lang('Backend.required') ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                            </div>
                            <input type="text" class="form-control" id="datemask" name="created_at"
                                value="<?php echo date('d.m.Y H:i:s', strtotime($infos->created_at)) ?>" required>
                        </div>
                    </div>
                    <div class="col-md-12 form-group">
                        <label for=""><?php echo lang('Blog.categories') . ' ' . lang('Backend.required') ?></label>
                        <select name="categories[]" id="" class="form-control select2bs4" multiple="multiple"
                            data-placeholder="<?php echo lang('Backend.selectOption', [lang('Blog.categories')]) ?>">
                            <?php $selected = [];
                            if (!empty($infos->categories)):
                                foreach ($infos->categories as $icategory):
                                    foreach ($categories as $key => $category):
                                        if ($icategory->categories_id == $category->id):
                                            $selected[] = $category;
                                            unset($categories[$key]);
                                        endif;
                                    endforeach;
                                endforeach;
                                foreach ($selected as $select): ?>
                                    <option value="<?php echo $select->id ?>" selected><?php echo esc($select->title) ?></option>
                                <?php endforeach;
                            endif;
                            foreach ($categories as $category): ?>
                                <option value="<?php echo $category->id ?>"><?php echo esc($category->title) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-12 row">
                        <?php
                        $defaultLocale = setting('App.defaultLocale') ?: 'tr';
                        $defLangData = isset($langsData[$defaultLocale]) ? $langsData[$defaultLocale] : current($langsData);
                        $seoInfo = $defLangData && isset($defLangData->seo) ? $defLangData->seo : null;
                        ?>
                        <div class="col-md-12 form-group">
                            <label for=""><?php echo lang('Backend.coverImage') ?></label>
                            <img src="<?php echo old('pageimg', (!empty($seoInfo->coverImage)) ? esc($seoInfo->coverImage) : '') ?>" alt="" class="pageimg img-fluid">
                        </div>
                        <div class="col-md-12 form-group">
                            <label for=""><?php echo lang('Backend.coverImgURL') ?></label>
                            <input type="text" name="pageimg" class="form-control pageimg-input"
                                placeholder="Görsel URL" value="<?php echo old('pageimg', (!empty($seoInfo->coverImage)) ? esc($seoInfo->coverImage) : '') ?>">
                        </div>
                        <div class="col-md-12 row form-group">
                            <div class="col-sm-6">
                                <label for=""><?php echo lang('Backend.coverImgWith') ?></label>
                                <input type="number" name="pageIMGWidth" class="form-control" id="pageIMGWidth"
                                    readonly value="<?php echo old('pageIMGWidth', (!empty($seoInfo->IMGWidth)) ? esc($seoInfo->IMGWidth) : '') ?>">
                            </div>
                            <div class="col-sm-6">
                                <label for=""><?php echo lang('Backend.coverImgHeight') ?></label>
                                <input type="number" name="pageIMGHeight" class="form-control" id="pageIMGHeight"
                                    readonly value="<?php echo old('pageIMGHeight', (!empty($seoInfo->IMGHeight)) ? esc($seoInfo->IMGHeight) : '') ?>">
                            </div>
                        </div>
                        <div class="col-md-12 form-group">
                            <button type="button"
                                class="pageIMG btn btn-info w-100"><?php echo lang('Backend.selectCoverImg') ?></button>
                        </div>
                    </div>
                    <div class="form-group col-md-12">
                        <label for=""><?php echo lang('Backend.seoDescription') ?></label>
                        <textarea class="form-control" name="description"><?php echo old('description', (!empty($seoInfo->description)) ? esc($seoInfo->description) : '') ?></textarea>
                    </div>
                    <div class="form-group col-md-12">
                        <label for=""><?php echo lang('Backend.seoKeywords') ?></label>
                        <textarea name="keywords" class="keywords" placeholder="<?php echo lang('Backend.tagPlaceholder') ?>"><?php echo $tags ?></textarea>
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
<?php echo $this->endSection() ?>

<?php echo $this->section('javascript') ?>
<?php echo script_tag("be-assets/plugins/jquery-ui/jquery-ui.js") ?>
<?php echo script_tag("be-assets/plugins/tagify/jQuery.tagify.min.js") ?>
<?php echo script_tag("be-assets/plugins/summernote/summernote-bs4.js") ?>
<?php echo script_tag("be-assets/plugins/elFinder/js/elfinder.full.js") ?>
<?php echo script_tag("be-assets/plugins/elFinder/js/i18n/elfinder.tr.js") ?>
<?php echo script_tag("be-assets/plugins/elFinder/js/extras/editors.default.js") ?>
<?php echo script_tag("be-assets/plugins/summernote/plugin/elfinder/summernote-ext-elfinder.js") ?>
<?php echo script_tag("be-assets/plugins/select2/js/select2.full.min.js") ?>
<?php echo script_tag("be-assets/js/ci4ms.js") ?>
<!-- InputMask -->
<?php echo script_tag("be-assets/plugins/moment/moment.min.js") ?>
<?php echo script_tag("be-assets/plugins/inputmask/jquery.inputmask.min.js") ?>
<script {csp-script-nonce}>
    $.post('<?php echo route_to('tagify') ?>', {
        "<?php echo csrf_token() ?>": "<?php echo csrf_hash() ?>",
        'type': 'blog'
    }, 'json').done(function(data) {
        tags(data);
    });

    $('.ptitle').on('change', function() {
        let lang = $(this).data('lang');
        $.post('<?php echo route_to('checkSeflink') ?>', {
            "<?php echo csrf_token() ?>": "<?php echo csrf_hash() ?>",
            'makeSeflink': $(this).val(),
            'where': 'blog_langs',
            'update': 1,
            'id': <?php echo $infos->id ?>,
            'locale': lang
        }, 'json').done(function(data) {
            $('.seflink[data-lang="' + lang + '"]').val(data.seflink);
        });
    });

    $('.seflink').on('change', function() {
        let lang = $(this).data('lang');
        $.post('<?php echo route_to('checkSeflink') ?>', {
            "<?php echo csrf_token() ?>": "<?php echo csrf_hash() ?>",
            'makeSeflink': $(this).val(),
            'where': 'blog_langs',
            'update': 1,
            'id': <?php echo $infos->id ?>,
            'locale': lang
        }, 'json').done(function(data) {
            $('.seflink[data-lang="' + lang + '"]').val(data.seflink);
        });
    });

    //Initialize Select2 Elements
    $('.select2bs4').select2({
        theme: 'bootstrap4'
    });

    $('#datemask').inputmask("datetime", {
        inputFormat: 'dd.mm.yyyy HH:MM:ss'
    });
</script>
<?php echo $this->endSection() ?>
