<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('title');
echo lang($title->pagename);
echo $this->endSection();
echo $this->section('head');
echo link_tag("be-assets/plugins/nestable2/jquery.nestable.min.css");
echo $this->endSection();
echo $this->section('content'); ?>
<section class="content pt-3">
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="m-stat-card">
                <div class="m-stat-icon st-menu"><i class="fas fa-bars"></i></div>
                <div>
                    <div class="m-stat-value" id="stat-menu-count"><?php echo count($nestable2 ?? []) ?></div>
                    <div class="m-stat-label"><?php echo lang('Menu.statActiveMenus') ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="m-stat-card">
                <div class="m-stat-icon st-pages"><i class="fas fa-file-alt"></i></div>
                <div>
                    <div class="m-stat-value" id="stat-pages-count"><?php echo count($pages ?? []) ?></div>
                    <div class="m-stat-label"><?php echo lang('Menu.statPendingPages') ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="m-stat-card">
                <div class="m-stat-icon st-blogs"><i class="fas fa-rss"></i></div>
                <div>
                    <div class="m-stat-value" id="stat-blogs-count"><?php echo count($blogs ?? []) ?></div>
                    <div class="m-stat-label"><?php echo lang('Menu.statPendingPosts') ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-5">
            <div class="card premium-card">
                <div class="card-header">
                    <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-plus-circle mr-2 text-success"></i> <?php echo lang('Menu.menuObjects') ?></h3>
                </div>
                <div class="card-body p-4" id="list">
                    <?php echo view('\Modules\Menu\Views\list') ?>
                </div>
            </div>
        </div>
        <div class="col-md-7">
            <div class="card premium-card">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-sitemap mr-2 text-info"></i> <?php echo lang('Menu.menuStructure') ?></h3>
                    <div class="ml-auto">
                        <button class="btn btn-sm btn-primary px-4" onclick="saveMenu()" style="border-radius:10px">
                            <i class="fas fa-save mr-1"></i><?php echo lang('Backend.save') ?>
                        </button>
                    </div>
                </div>
                <div class="card-body p-4">
                    <div class="dd" id="nestable-menu">
                        <ol class="dd-list">
                            <?php if (!empty($nestable2)) nestable($nestable2); ?>
                        </ol>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php echo $this->endSection();
echo $this->section('javascript');
echo script_tag("be-assets/plugins/nestable2/jquery.nestable.min.js"); ?>
<script type="text/javascript" <?php echo csp_script_nonce(); ?>>
    $(function() {
        $('#nestable-menu').nestable();
    });

    function updateNestableUI(data) {
        $('#nestable-menu').nestable('destroy');
        $('#nestable-menu').html(data);
        $('#nestable-menu').nestable();
        // Update stats
        $('#stat-menu-count').text($('#nestable-menu li').length);
    }

    function refreshLeftList() {
        $.get('<?php echo route_to('menuList') ?>').done(function(data) {
            $('#list').html(data);
            var parsed = $(data);
            $('#stat-pages-count').text(parsed.find('[id^="page-"]').length);
            $('#stat-blogs-count').text(parsed.find('[id^="blog-"]').length);
        });
    }

    function saveMenu() {
        $.post('<?php echo route_to('queueMenuAjax') ?>', {
            "queue": $('#nestable-menu').nestable('serialize'),
            [CI4MS_CSRF.name]: CI4MS_CSRF.getHash()
        }).done(data => {
            updateNestableUI(data);
            showToast('<?php echo lang('Menu.toastMenuSaved') ?>');
        });
    }

    function addPages(id) {
        $.post('<?php echo route_to('createMenu') ?>', {
            "id": id,
            'where': 'pages_langs',
            [CI4MS_CSRF.name]: CI4MS_CSRF.getHash()
        }).done(data => {
            updateNestableUI(data);
            showToast('<?php echo lang('Menu.toastPageAdded') ?>');
            refreshLeftList();
        });
    }

    function addCheckedPages() {
        var formData = $('#addCheckedPages').serializeArray();
        formData.push({
            name: "where",
            value: "pages_langs"
        });
        formData.push({
            name: "type",
            value: "pages_langs"
        });
        formData.push({
            name: CI4MS_CSRF.name,
            value: CI4MS_CSRF.getHash()
        });
        $.post('<?php echo route_to('addMultipleMenu') ?>', formData).done(data => {
            updateNestableUI(data);
            showToast('<?php echo lang('Menu.toastPagesAdded') ?>');
            refreshLeftList();
        });
    }

    function addBlog(id) {
        $.post('<?php echo route_to('createMenu') ?>', {
            "id": id,
            'where': 'blog_langs',
            [CI4MS_CSRF.name]: CI4MS_CSRF.getHash()
        }).done(data => {
            updateNestableUI(data);
            showToast('<?php echo lang('Menu.toastPostAdded') ?>');
            refreshLeftList();
        });
    }

    function addCheckedBlog() {
        var formData = $('#addCheckedBlog').serializeArray();
        formData.push({
            name: "where",
            value: "blog_langs"
        });
        formData.push({
            name: "type",
            value: "blog_langs"
        });
        formData.push({
            name: CI4MS_CSRF.name,
            value: CI4MS_CSRF.getHash()
        });
        $.post('<?php echo route_to('addMultipleMenu') ?>', formData).done(data => {
            updateNestableUI(data);
            showToast('<?php echo lang('Menu.toastPostsAdded') ?>');
            refreshLeftList();
        });
    }

    function addURL() {
        var formData = $('#addUrls').serializeArray();
        formData.push({
            name: "type",
            value: "url"
        });
        $.post('<?php echo route_to('createMenu') ?>', formData).done(data => {
            updateNestableUI(data);
            showToast('<?php echo lang('Menu.toastUrlAdded') ?>');
            refreshLeftList();
            $('#addUrls')[0].reset();
        });
    }

    function removeFromMenu(id, type) {
        Swal.fire({
            title: '<?php echo lang('Menu.confirmRemoveTitle') ?>',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e53e3e',
            confirmButtonText: '<?php echo lang('Menu.confirmRemoveBtn') ?>',
            cancelButtonText: '<?php echo lang('Menu.confirmCancelBtn') ?>'
        }).then(res => {
            if (res.isConfirmed) {
                $.post('<?php echo route_to('deleteMenuAjax') ?>', {
                    "id": id,
                    "type": type,
                    [CI4MS_CSRF.name]: CI4MS_CSRF.getHash()
                }).done(data => {
                    updateNestableUI(data);
                    showToast('<?php echo lang('Menu.toastMenuRemoved') ?>');
                    refreshLeftList();
                });
            }
        });
    }
</script>
<?php echo $this->endSection() ?>
