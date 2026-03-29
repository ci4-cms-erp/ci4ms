<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('title');
echo lang($title->pagename);
echo $this->endSection();
echo $this->section('head');
echo link_tag("be-assets/plugins/nestable2/jquery.nestable.min.css"); ?>
<style {csp-style-nonce}>
/* Nestable Premium Style */
.dd-item > button { height: 32px; font-size: 18px; color: #718096; }
.dd-handle { 
    height: 45px; padding: 10px 15px; background: #fff; border: 1px solid #edf2f7; 
    border-radius: 8px; font-weight: 500; color: #2d3748; 
    box-shadow: 0 2px 5px rgba(0,0,0,.02);
}
.dd-handle:hover { background: #f8fafc; color: #667eea; }
.dd-content { 
    display: flex; align-items: center; justify-content: space-between;
    position: absolute; right: 10px; top: 7px;
}
</style>
<?php echo $this->endSection();
echo $this->section('content'); ?>

<section class="content pt-3">
    <!-- Stats Row -->
    <div class="row mb-4">
        <div class="col-md-4">
            <div class="m-stat-card">
                <div class="m-stat-icon st-menu"><i class="fas fa-bars"></i></div>
                <div><div class="m-stat-value" id="stat-menu-count"><?php echo count($nestable2 ?? []) ?></div><div class="m-stat-label">Aktif Menü Sayısı</div></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="m-stat-card">
                <div class="m-stat-icon st-pages"><i class="fas fa-file-alt"></i></div>
                <div><div class="m-stat-value"><?php echo count($pages ?? []) ?></div><div class="m-stat-label">Bekleyen Sayfalar</div></div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="m-stat-card">
                <div class="m-stat-icon st-blogs"><i class="fas fa-rss"></i></div>
                <div><div class="m-stat-value"><?php echo count($blogs ?? []) ?></div><div class="m-stat-label">Bekleyen Yazılar</div></div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Available Items -->
        <div class="col-md-5">
            <div class="card premium-card">
                <div class="card-header"><h3 class="card-title font-weight-bold mb-0"><i class="fas fa-plus-circle mr-2 text-success"></i> Menü Nesneleri</h3></div>
                <div class="card-body p-4" id="list">
                    <?php echo view('\Modules\Menu\Views\list') ?>
                </div>
            </div>
        </div>

        <!-- Menu Structure -->
        <div class="col-md-7">
            <div class="card premium-card">
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-sitemap mr-2 text-info"></i> Menü Yapısı</h3>
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
<script type="text/javascript" {csp-script-nonce}>

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
        $.post('<?php echo route_to('menuList') ?>', { "<?php echo csrf_token() ?>": "<?php echo csrf_hash() ?>" }).done(data => $('#list').html(data));
    }

    function saveMenu() {
        $.post('<?php echo route_to('queueMenuAjax') ?>', { "queue": $('#nestable-menu').nestable('serialize') }).done(data => {
            updateNestableUI(data);
            showToast('Menü sıralaması kaydedildi.');
        });
    }

    function addPages(id) {
        $.post('<?php echo route_to('createMenu') ?>', { "id": id, 'where': 'pages_langs' }).done(data => {
            updateNestableUI(data);
            showToast('Sayfa menüye eklendi.');
            refreshLeftList();
        });
    }

    function addCheckedPages() {
        var formData = $('#addCheckedPages').serializeArray();
        formData.push({ name: "where", value: "pages_langs" });
        formData.push({ name: "type", value: "pages_langs" });
        $.post('<?php echo route_to('addMultipleMenu') ?>', formData).done(data => {
            updateNestableUI(data);
            showToast('Seçilen sayfalar eklendi.');
            refreshLeftList();
        });
    }

    function addBlog(id) {
        $.post('<?php echo route_to('createMenu') ?>', { "id": id, 'where': 'blog_langs' }).done(data => {
            updateNestableUI(data);
            showToast('Yazı menüye eklendi.');
            refreshLeftList();
        });
    }

    function addCheckedBlog() {
        var formData = $('#addCheckedBlog').serializeArray();
        formData.push({ name: "where", value: "blog_langs" });
        formData.push({ name: "type", value: "blog_langs" });
        $.post('<?php echo route_to('addMultipleMenu') ?>', formData).done(data => {
            updateNestableUI(data);
            showToast('Seçilen yazılar eklendi.');
            refreshLeftList();
        });
    }

    function addURL() {
        var formData = $('#addUrls').serializeArray();
        formData.push({ name: "type", value: "url" });
        $.post('<?php echo route_to('createMenu') ?>', formData).done(data => {
            updateNestableUI(data);
            showToast('Özel bağlantı eklendi.');
            refreshLeftList();
            $('#addUrls')[0].reset();
        });
    }

    function removeFromMenu(id, type) {
        Swal.fire({
            title: 'Menüden çıkarılsın mı?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e53e3e',
            confirmButtonText: 'Evet, çıkar',
            cancelButtonText: 'Vazgeç'
        }).then(res => {
            if(res.isConfirmed) {
                $.post('<?php echo route_to('deleteMenuAjax') ?>', { "id": id, "type": type }).done(data => {
                    updateNestableUI(data);
                    showToast('Menüden çıkarıldı.');
                    refreshLeftList();
                });
            }
        });
    }
</script>
<?php echo $this->endSection() ?>
