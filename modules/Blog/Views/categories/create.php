<?php echo $this->extend('Modules\Backend\Views\base') ?>

<?php echo $this->section('title') ?>
<?php echo lang($title->pagename) ?>
<?php echo $this->endSection() ?>

<?php echo $this->section('head') ?>
<?php echo link_tag("be-assets/node_modules/@yaireo/tagify/dist/tagify.css") ?>
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
                    <a href="<?php echo route_to('categories', 1) ?>" class="btn btn-outline-info"><?php echo lang('Backend.backToList') ?></a>
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
            <form action="<?php echo route_to('categoryCreate') ?>" class="form-row" method="post">
                <?php echo csrf_field() ?>
                <div class="col-md-8">
                    <div class="form-group">
                        <label for=""><?php echo lang('Backend.title') ?></label>
                        <input type="text" class="form-control ptitle" required name="title" value="<?php echo old('title') ?>">
                    </div>
                    <div class="form-group">
                        <label for=""><?php echo lang('Backend.url') ?></label>
                        <input type="text" class="form-control seflink" name="seflink" value="<?php echo old('seflink') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for=""><?php echo lang('Backend.seoDescription') ?></label>
                        <textarea name="description" class="form-control" rows="10"><?php echo old('description') ?></textarea>
                    </div>
                </div>
                <div class="col-md-4 row">
                    <div class="form-group col-md-12">
                        <div class="btn-group btn-group-toggle w-100" data-toggle="buttons">
                            <label class="btn btn-outline-secondary">
                                <input type="radio" name="isActive" id="option1" autocomplete="off" value="0" <?php echo set_radio('isActive', 0) ?>> <?php echo lang('Backend.draft') ?>
                            </label>
                            <label class="btn btn-outline-secondary active">
                                <input type="radio" name="isActive" id="option2" autocomplete="off" value="1" <?php echo set_radio('isActive', 1, true) ?>> <?php echo lang('Backend.publish') ?>
                            </label>
                        </div>
                    </div>
                    <div class="col-md-12 form-group">
                        <label for=""><?php echo lang('Blog.parentCategory') ?></label>
                        <select name="parent" id="" class="form-control select2bs4" data-placeholder="Select a Category">
                            <option value=""><?php echo lang('Backend.select') ?></option>
                            <?php foreach ($categories as $category) : ?>
                                <option value="<?php echo $category->id ?>" <?php echo set_select('parent', $category->id) ?>><?php echo esc($category->title) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-12 form-group">
                        <label for=""><?php echo lang('Backend.coverImage') ?></label>
                        <img src="<?php echo old('pageimg') ?>" class="pageimg img-fluid">
                    </div>
                    <div class="col-md-12 form-group">
                        <label for=""><?php echo lang('Backend.coverImgURL') ?></label>
                        <input type="text" name="pageimg" class="form-control pageimg-input" value="<?php echo old('pageimg') ?>"
                            placeholder="<?php echo lang('Backend.coverImgURL') ?>">
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
                    <div class="col-md-12 form-group">
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
<?php echo $this->endSection() ?>

<?php echo $this->section('javascript') ?>
<?php echo script_tag("be-assets/plugins/jquery-ui/jquery-ui.js") ?>
<?php echo script_tag("be-assets/node_modules/@yaireo/tagify/dist/jQuery.tagify.min.js") ?>
<?php echo script_tag("be-assets/plugins/elFinder/js/elfinder.full.js") ?>
<?php echo script_tag("be-assets/plugins/elFinder/js/i18n/elfinder.tr.js") ?>
<?php echo script_tag("be-assets/plugins/elFinder/js/extras/editors.default.js") ?>
<?php echo script_tag("be-assets/plugins/summernote/plugin/elfinder/summernote-ext-elfinder.js") ?>
<!-- Select2 -->
<?php echo script_tag("be-assets/plugins/select2/js/select2.full.min.js") ?>
<?php echo script_tag("be-assets/js/ci4ms.js") ?>
<script {csp-script-nonce}>
    tags([]);

    $('.ptitle').on('change', function() {
        $.post('<?php echo route_to('checkSeflink') ?>', {
            "<?php echo csrf_token() ?>": "<?php echo csrf_hash() ?>",
            'makeSeflink': $(this).val(),
            'where': 'categories'
        }, 'json').done(function(data) {
            $('.seflink').val(data.seflink);
        });
    });

    $('.seflink').on('change', function() {
        $.post('<?php echo route_to('checkSeflink') ?>', {
            "<?php echo csrf_token() ?>": "<?php echo csrf_hash() ?>",
            'makeSeflink': $(this).val(),
            'where': 'categories'
        }, 'json').done(function(data) {
            $('.seflink').val(data.seflink);
        });
    });

    //Initialize Select2 Elements
    $('.select2bs4').select2({
        theme: 'bootstrap4'
    })
</script>
<?php echo $this->endSection() ?>
