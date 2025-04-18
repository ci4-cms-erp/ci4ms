<?= $this->extend('Modules\Backend\Views\base') ?>

<?= $this->section('title') ?>
<?= lang('Backend.' . $title->pagename) ?>
<?= $this->endSection() ?>

<?= $this->section('head') ?>
<?= link_tag("be-assets/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css") ?>
<?= link_tag("be-assets/node_modules/nestable2/dist/jquery.nestable.min.css") ?>
<style>
    .dd-content {
        display: block;
        margin: 5px 0;
        padding: 13px 10px 13px 40px;
        color: #333;
        text-decoration: none;
        font-weight: bold;
        border: 1px solid #ccc;
        background: #fafafa;
        background: -webkit-linear-gradient(top, #fafafa 0%, #eee 100%);
        background: -moz-linear-gradient(top, #fafafa 0%, #eee 100%);
        background: linear-gradient(top, #fafafa 0%, #eee 100%);
        -webkit-border-radius: 3px;
        border-radius: 3px;
        box-sizing: border-box;
        -moz-box-sizing: border-box;
    }

    .dd3-handle {
        position: absolute;
        margin: 0;
        left: 0;
        top: 0;
        cursor: pointer;
        width: 30px;
        text-indent: 30px;
        white-space: nowrap;
        overflow: hidden;
        border: 1px solid #aaa;
        background: #ddd;
        background: -webkit-linear-gradient(top, #ddd 0%, #bbb 100%);
        background: -moz-linear-gradient(top, #ddd 0%, #bbb 100%);
        background: linear-gradient(top, #ddd 0%, #bbb 100%);
        border-top-right-radius: 0;
        border-bottom-right-radius: 0;
    }

    .dd3-handle:before {
        content: '≡';
        display: block;
        left: 0;
        top: 3px;
        width: 100%;
        text-align: center;
        text-indent: 0;
        color: #343232;
        font-size: 20px;
        margin-top: 0.2em;
        font-weight: normal;
    }

    .dd3-handle:hover {
        background: #ddd;
    }
</style>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><?= lang('Backend.' . $title->pagename) ?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
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
            <h3 class="card-title font-weight-bold"><?= lang('Backend.' . $title->pagename) ?></h3>

            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <?= view('Modules\Auth\Views\_message_block') ?>
            <div class="row">
                <div class="col-md-6" id="list">
                    <?= view('\Modules\Menu\Views\list') ?>
                </div>
                <div class="col-md-6">
                    <div class="dd">
                        <ol class="dd-list">
                            <?php if (!empty($nestable2)) nestable($nestable2); ?>
                        </ol>
                    </div>
                    <button class="btn btn-success float-right" onclick="saveMenu()"><?= lang('Backend.save') ?></button>
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
<?= script_tag("be-assets/plugins/sweetalert2/sweetalert2.min.js") ?>
<?= script_tag("be-assets/node_modules/nestable2/dist/jquery.nestable.min.js") ?>
<script>
    $('.dd').nestable();

    function saveMenu() {
        $.post('<?= route_to('queueMenuAjax') ?>', {
            "queue": $('.dd').nestable('serialize')
        }).done(function(data) {
            $('.dd').nestable('destroy');
            $('.dd').html(data);
            $('.dd').nestable();
        });
    }

    function addPages(id) {
        $.post('<?= route_to('createMenu') ?>', {
            "id": id,
            'where': 'pages'
        }).done(function(data) {
            $('.dd').nestable('destroy');
            $('.dd').html(data);
            $('.dd').nestable();
            $("#page-" + id + "").remove();
            $.post('<?= route_to('menuList') ?>', {
                "<?= csrf_token() ?>": "<?= csrf_hash() ?>"
            }).done(function(data) {
                $('#list').html(data);
            });
        });
    }

    function addCheckedPages() {
        var formData = $('#addCheckedPages').serializeArray();
        formData.push({
            name: "where",
            value: "pages"
        });
        formData.push({
            name: "type",
            value: "pages"
        });
        $.post('<?= route_to('addMultipleMenu') ?>', formData).done(function(data) {
            $('.dd').nestable('destroy');
            $('.dd').html(data);
            $('.dd').nestable();
            $.post('<?= route_to('menuList') ?>', {
                "<?= csrf_token() ?>": "<?= csrf_hash() ?>"
            }).done(function(data) {
                $('#list').html(data);
            });
        });
    }

    function addBlog(id) {
        $.post('<?= route_to('createMenu') ?>', {
            "id": id,
            'where': 'blog'
        }).done(function(data) {
            $('.dd').nestable('destroy');
            $('.dd').html(data);
            $('.dd').nestable();
            $("#blog-" + id + "").remove();
            $.post('<?= route_to('menuList') ?>', {
                "<?= csrf_token() ?>": "<?= csrf_hash() ?>"
            }).done(function(data) {
                $('#list').html(data);
            });
        });
    }

    function addCheckedBlog() {
        var formData = $('#addCheckedBlog').serializeArray();
        formData.push({
            name: "where",
            value: "blog"
        });
        formData.push({
            name: "type",
            value: "blogs"
        });
        $.post('<?= route_to('addMultipleMenu') ?>', formData).done(function(data) {
            $('.dd').nestable('destroy');
            $('.dd').html(data);
            $('.dd').nestable();
            $.post('<?= route_to('menuList') ?>', {
                "<?= csrf_token() ?>": "<?= csrf_hash() ?>"
            }).done(function(data) {
                $('#list').html(data);
            });
        });
    }

    function addURL() {
        var formData = $('#addUrls').serializeArray();
        formData.push({
            name: "type",
            value: "url"
        });
        $.post('<?= route_to('createMenu') ?>', formData).done(function(data) {
            $('.dd').nestable('destroy');
            $('.dd').html(data);
            $('.dd').nestable();
            $.post('<?= route_to('menuList') ?>', {
                "<?= csrf_token() ?>": "<?= csrf_hash() ?>",
            }).done(function(data) {
                $('#list').html(data);
            });
        });
    }

    function removeFromMenu(id, type) {
        $.post('<?= route_to('deleteMenuAjax') ?>', {
            "id": id,
            "type": type
        }).done(function(data) {
            $('.dd').nestable('destroy');
            $('.dd').html(data);
            $('.dd').nestable();
            $("#menu-" + id + "").remove();
            $.post('<?= route_to('menuList') ?>', {
                "<?= csrf_token() ?>": "<?= csrf_hash() ?>",
                "queue": $('.dd').nestable('serialize')
            }).done(function(data) {
                $('#list').html(data);
            });
        });
    }
</script>
<?= $this->endSection() ?>
