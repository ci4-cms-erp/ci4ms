<?php echo $this->extend('Modules\Backend\Views\base') ?>
<?php echo $this->section('title') ?>
<?php echo lang($title->pagename) ?>
<?php echo $this->endSection() ?>
<?php echo $this->section('head') ?>
<?php echo link_tag("be-assets/node_modules/@yaireo/tagify/dist/tagify.css") ?>
<?php echo link_tag("be-assets/plugins/summernote/summernote-bs4.css") ?>
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
                <ol class="breadcrumb float-sm-right">
                    <a href="<?php echo route_to('pages', 1) ?>" class="btn btn-outline-info"><?php echo lang('Backend.backToList') ?></a>
                </ol>
            </div>
        </div>
    </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">
    <!-- Default box -->
    <div class="card card-outline card-shl">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><?php echo lang($title->pagename) ?></h3>

            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <form action="<?php echo route_to('pageUpdate', $pageInfo->id) ?>" class="form-row" method="post">
                <?php echo csrf_field() ?>
                <div class="col-md-8 form-group row">
                    <div class="form-group col-md-12">
                        <label for=""><?php echo lang('Backend.title') ?></label>
                        <input type="text" name="title" class="form-control ptitle" placeholder="<?php echo lang('Backend.title') ?>" value="<?php echo old('title', $pageInfo->title) ?>"
                            required>
                    </div>
                    <div class="form-group col-md-12">
                        <label for=""><?php echo lang('Backend.url') ?></label>
                        <input type="text" class="form-control seflink" name="seflink" required value="<?php echo old('seflink', $pageInfo->seflink) ?>">
                    </div>
                    <div class="form-group col-md-12">
                        <label for=""><?php echo lang('Backend.content') ?></label>
                        <textarea name="content" rows="60" class="form-control editor" required><?php echo old('content', $pageInfo->content) ?></textarea>
                    </div>
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
<?php echo $this->endSection() ?>
<?php echo $this->section('javascript') ?>
<?php echo script_tag("be-assets/plugins/jquery-ui/jquery-ui.js") ?>
<?php echo script_tag("be-assets/node_modules/@yaireo/tagify/dist/jQuery.tagify.min.js") ?>
<?php echo script_tag("be-assets/plugins/summernote/summernote-bs4.js") ?>
<?php echo script_tag("be-assets/plugins/elFinder/js/elfinder.full.js") ?>
<?php echo script_tag("be-assets/plugins/elFinder/js/i18n/elfinder.tr.js") ?>
<?php echo script_tag("be-assets/plugins/elFinder/js/extras/editors.default.js") ?>
<?php echo script_tag("be-assets/plugins/summernote/plugin/elfinder/summernote-ext-elfinder.js") ?>
<?php echo script_tag("be-assets/js/ci4ms.js") ?>
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
