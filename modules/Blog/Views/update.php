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
<!-- Select2 -->
<?php echo link_tag("be-assets/plugins/select2/css/select2.min.css") ?>
<?php echo link_tag("be-assets/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css") ?>
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
                    <a href="<?php echo route_to('blogs', 1) ?>"
                        class="btn btn-outline-info"><?php echo lang('Backend.backToList') ?></a>
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
            <form action="<?php echo route_to('blogUpdate', $infos->id) ?>" class="form-row" method="post">
                <?php echo csrf_field() ?>
                <div class="col-md-8 form-group row">
                    <div class="form-group col-md-12">
                        <label for=""><?php echo lang('Backend.title') ?></label>
                        <input type="text" name="title" class="form-control ptitle" placeholder="<?php echo lang('Backend.title') ?>"
                            required value="<?php echo old('title',esc($infos->title)) ?>">
                    </div>
                    <div class="form-group col-md-12">
                        <label for=""><?php echo lang('Backend.url') ?></label>
                        <input type="text" class="form-control seflink" name="seflink" required
                            value="<?php echo old('seflink',esc($infos->seflink)) ?>">
                    </div>
                    <div class="form-group col-md-12">
                        <label for=""><?php echo lang('Backend.content') ?></label>
                        <textarea name="content" rows="60" class="form-control editor"
                            required><?php echo old('content',esc($infos->content)) ?></textarea>
                    </div>
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
                        <label for=""><?php echo lang('Blog.author') ?></label>
                        <select name="author" id="" class="form-control" required>
                            <option value=""><?php echo lang('Blog.author') ?></option>
                            <?php foreach ($authors as $author): ?>
                                <option value="<?php echo $author->id ?>" <?php echo set_select('author', $author->id, $author->id == $infos->author) ?>><?php echo esc($author->firstname . ' ' . $author->surname) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-12 form-group">
                        <label for=""><?php echo lang('Backend.createdAt') ?></label>
                        <div class="input-group">
                            <div class="input-group-prepend">
                                <span class="input-group-text"><i class="far fa-calendar-alt"></i></span>
                            </div>
                            <input type="text" class="form-control" id="datemask" name="created_at"
                                value="<?php echo date('d.m.Y H:i:s', strtotime($infos->created_at)) ?>" required>
                        </div>
                    </div>
                    <div class="col-md-12 form-group">
                        <label for=""><?php echo lang('Blog.categories') ?></label>
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
                        <div class="col-md-12 form-group">
                            <label for=""><?php echo lang('Backend.coverImage') ?></label>
                            <img src="<?php echo (!empty($infos->seo->coverImage)) ? esc($infos->seo->coverImage) : '' ?>" alt="" class="pageimg img-fluid">
                        </div>
                        <div class="col-md-12 form-group">
                            <label for=""><?php echo lang('Backend.coverImgURL') ?></label>
                            <input type="text" name="pageimg" class="form-control pageimg-input"
                                placeholder="Görsel URL" value="<?php echo (!empty($infos->seo->coverImage)) ? esc($infos->seo->coverImage) : '' ?>">
                        </div>
                        <div class="col-md-12 row form-group">
                            <div class="col-sm-6">
                                <label for=""><?php echo lang('Backend.coverImgWith') ?></label>
                                <input type="number" name="pageIMGWidth" class="form-control" id="pageIMGWidth"
                                    readonly value="<?php echo (!empty($infos->seo->IMGWidth)) ? esc($infos->seo->IMGWidth) : '' ?>">
                            </div>
                            <div class="col-sm-6">
                                <label for=""><?php echo lang('Backend.coverImgHeight') ?></label>
                                <input type="number" name="pageIMGHeight" class="form-control" id="pageIMGHeight"
                                    readonly value="<?php echo (!empty($infos->seo->IMGHeight)) ? esc($infos->seo->IMGHeight) : '' ?>">
                            </div>
                        </div>
                        <div class="col-md-12 form-group">
                            <button type="button"
                                class="pageIMG btn btn-info w-100"><?php echo lang('Backend.selectCoverImg') ?></button>
                        </div>
                    </div>
                    <div class="form-group col-md-12">
                        <label for=""><?php echo lang('Backend.seoDescription') ?></label>
                        <textarea class="form-control" name="description"><?php echo (!empty($infos->seo->description)) ? esc($infos->seo->description) : '' ?></textarea>
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
<?php echo script_tag("be-assets/node_modules/@yaireo/tagify/dist/jQuery.tagify.min.js") ?>
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
        $.post('<?php echo route_to('checkSeflink') ?>', {
            "<?php echo csrf_token() ?>": "<?php echo csrf_hash() ?>",
            'makeSeflink': $(this).val(),
            'where': 'blog'
        }, 'json').done(function(data) {
            $('.seflink').val(data.seflink);
        });
    });

    $('.seflink').on('change', function() {
        $.post('<?php echo route_to('checkSeflink') ?>', {
            "<?php echo csrf_token() ?>": "<?php echo csrf_hash() ?>",
            'makeSeflink': $(this).val(),
            'where': 'blog'
        }, 'json').done(function(data) {
            $('.seflink').val(data.seflink);
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
