<?php echo $this->extend($backConfig->viewLayout);

echo $this->section('title');
echo lang($title->pagename);
echo $this->endSection();

echo $this->section('head');
echo link_tag("be-assets/plugins/tagify/tagify.css");
echo link_tag("be-assets/plugins/summernote/summernote-bs4.css");
echo link_tag("be-assets/plugins/jquery-ui/jquery-ui.css");
echo link_tag("be-assets/plugins/jquery-ui/themes/smoothness/jquery-ui.min.css");
echo link_tag("be-assets/plugins/elFinder/css/elfinder.full.css");
echo link_tag("be-assets/plugins/select2/css/select2.min.css");
echo link_tag("be-assets/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css");
echo $this->endSection();

echo $this->section('content'); ?>
<!-- Main content -->
<section class="content pt-3">

    <!-- Default box -->
    <div class="card card-outline shadow-sm">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><?php echo lang($title->pagename) ?></h3>

            <div class="card-tools">
                <a href="<?php echo route_to('blogs', 1) ?>" class="btn btn-sm btn-outline-info"><?php echo lang('Backend.backToList') ?></a>
            </div>
        </div>
        <div class="card-body">
            <form action="<?php echo route_to('blogCreate') ?>" class="form-row" method="post">
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
                                            <input type="text" name="lang[<?php echo $lang->code ?>][title]" class="form-control ptitle" data-lang="<?php echo $lang->code ?>" placeholder="<?php echo lang('Backend.title') ?>" value="<?php echo old("lang.{$lang->code}.title") ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for=""><?php echo lang('Backend.url') . ' ' . lang('Backend.required') ?></label>
                                            <input type="text" class="form-control seflink" data-lang="<?php echo $lang->code ?>" name="lang[<?php echo $lang->code ?>][seflink]" value="<?php echo old("lang.{$lang->code}.seflink") ?>" required>
                                        </div>
                                        <div class="form-group">
                                            <label for=""><?php echo lang('Backend.content') . ' ' . lang('Backend.required') ?></label>
                                            <textarea name="lang[<?php echo $lang->code ?>][content]" rows="60" class="form-control editor" required><?php echo old("lang.{$lang->code}.content") ?></textarea>
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
                            <input type="text" name="lang[<?php echo $defaultLocale ?>][title]" data-lang="<?php echo $defaultLocale ?>" class="form-control ptitle" placeholder="<?php echo lang('Backend.title') ?>" value="<?php echo old("lang.{$defaultLocale}.title") ?>" required>
                        </div>
                        <div class="form-group col-md-12">
                            <label for=""><?php echo lang('Backend.url') . ' ' . lang('Backend.required') ?></label>
                            <input type="text" class="form-control seflink" data-lang="<?php echo $defaultLocale ?>" name="lang[<?php echo $defaultLocale ?>][seflink]" value="<?php echo old("lang.{$defaultLocale}.seflink") ?>" required>
                        </div>
                        <div class="form-group col-md-12">
                            <label for=""><?php echo lang('Backend.content') . ' ' . lang('Backend.required') ?></label>
                            <textarea name="lang[<?php echo $defaultLocale ?>][content]" rows="60" class="form-control editor" required><?php echo old("lang.{$defaultLocale}.content") ?></textarea>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="col-md-4 form-group row">
                    <div class="form-group col-md-12">
                        <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                            <label class="btn btn-outline-secondary">
                                <input type="radio" name="isActive" id="option1" autocomplete="off" value="0" <?php echo set_radio('isActive', 0,) ?>> <?php echo lang('Backend.draft') ?>
                            </label>
                            <label class="btn btn-outline-secondary active">
                                <input type="radio" name="isActive" id="option2" autocomplete="off" value="1" <?php echo set_radio('isActive', 1, true) ?>> <?php echo lang('Backend.publish') ?>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-12 form-group">
                        <label for=""><?php echo lang('Blog.author') . ' ' . lang('Backend.required') ?></label>
                        <select name="author" class="form-control" required>
                            <option value=""><?php echo lang('Backend.select') ?></option>
                            <?php foreach ($authors as $author): ?>
                                <option value="<?php echo $author->id ?>" <?php echo set_select('author', $author->id, $author->id == $logged_in_user->id) ?>><?php echo esc($author->firstname) . ' ' . esc($author->surname) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-12 form-group">
                        <label for=""><?php echo lang('Backend.createdAt') . ' ' . lang('Backend.required') ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                            </div>
                            <input type="text" class="form-control" id="datemask" name="created_at" value="<?php echo date('d.m.Y H:i:s') ?>" required>
                        </div>
                    </div>
                    <div class="col-md-12 form-group">
                        <label for=""><?php echo lang('Blog.categories') . ' ' . lang('Backend.required') ?></label>
                        <select name="categories[]" id="" class="form-control select2bs4" multiple="multiple" data-placeholder="<?php echo lang('Backend.selectOption', [lang('Blog.categories')]) ?>">
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo $category->id ?>"><?php echo esc($category->title) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group col-md-12 row">
                        <div class="col-md-12 form-group">
                            <label for=""><?php echo lang('Backend.coverImage') ?></label>
                            <img src="<?php echo old('pageimg') ?>" class="pageimg img-fluid">
                        </div>
                        <div class="col-md-12 form-group">
                            <label for=""><?php echo lang('Backend.coverImgURL') ?></label>
                            <input type="text" name="pageimg" class="form-control pageimg-input"
                                value="<?php echo old('pageimg') ?>" placeholder="<?php echo lang('Backend.coverImgURL') ?>">
                        </div>
                        <div class="col-md-12 row form-group">
                            <div class="col-sm-6">
                                <label for=""><?php echo lang('Backend.coverImgWith') ?></label>
                                <input type="number" name="pageIMGWidth" class="form-control" id="pageIMGWidth" value="<?php echo old('pageIMGWidth') ?>"
                                    readonly>
                            </div>
                            <div class="col-sm-6">
                                <label for=""><?php echo lang('Backend.coverImgHeight') ?></label>
                                <input type="number" name="pageIMGHeight" class="form-control" id="pageIMGHeight" value="<?php echo old('pageIMGHeight') ?>"
                                    readonly>
                            </div>
                        </div>
                        <div class="col-md-12 form-group">
                            <button type="button" class="pageIMG btn btn-info w-100"><?php echo lang('Backend.selectCoverImg') ?></button>
                        </div>
                    </div>
                    <div class="form-group col-md-12">
                        <label for=""><?php echo lang('Backend.seoDescription') ?></label>
                        <textarea class="form-control" name="description"><?php echo old('description') ?></textarea>
                    </div>
                    <div class="form-group col-md-12">
                        <label for=""><?php echo lang('Backend.seoKeywords') ?></label>
                        <textarea name="keywords" class="keywords" placeholder="<?php echo lang('Backend.tagPlaceholder') ?>"><?php echo old('keywords') ?></textarea>
                    </div>
                </div>
                <div class="form-group col-md-12">
                    <button class="btn btn-success float-right"><?php echo lang('Backend.add') ?></button>
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
echo script_tag("be-assets/plugins/select2/js/select2.full.min.js");
echo script_tag("be-assets/js/ci4ms.js");
echo script_tag("be-assets/plugins/moment/moment.min.js");
echo script_tag("be-assets/plugins/inputmask/jquery.inputmask.min.js") ?>
<script {csp-script-nonce}>
    $.ajax({
        method: "POST",
        url: "<?php echo route_to('tagify') ?>",
        data: {
            "<?php echo csrf_token() ?>": "<?php echo csrf_hash() ?>",
            "type": 'blogs'
        },
        statusCode: {
            404: function() {
                tags([]);
            }
        },
        success: function(data) {
            tags(data);
        }
    });

    $('.ptitle').on('change', function() {
        let lang = $(this).data('lang');
        $.post('<?php echo route_to('checkSeflink') ?>', {
            "<?php echo csrf_token() ?>": "<?php echo csrf_hash() ?>",
            'makeSeflink': $(this).val(),
            'where': 'blog_langs'
        }, 'json').done(function(data) {
            $('.seflink[data-lang="' + lang + '"]').val(data.seflink);
        });
    });

    $('.seflink').on('change', function() {
        let lang = $(this).data('lang');
        $.post('<?php echo route_to('checkSeflink') ?>', {
            "<?php echo csrf_token() ?>": "<?php echo csrf_hash() ?>",
            'makeSeflink': $(this).val(),
            'where': 'blog'
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
