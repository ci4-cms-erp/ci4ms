<?= $this->extend('Modules\Backend\Views\base') ?>

<?= $this->section('title') ?>
<?= lang($title->pagename) ?>
<?= $this->endSection() ?>

<?= $this->section('head') ?>
<?= link_tag("be-assets/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css") ?>
<?= link_tag('be-assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css') ?>
<?= link_tag('be-assets/plugins/datatables-responsive/css/responsive.bootstrap4.min.css') ?>
<?= link_tag('be-assets/plugins/datatables-buttons/css/buttons.bootstrap4.min.css') ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><?= lang($title->pagename) ?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <li>
                        <button class="btn btn-outline-info" id="moduleScan">
                            <i class="fas fa-repeat"></i>Modül Tara
                        </button>
                    </li>
                    <li>
                        <a href="<?= route_to('methodCreate') ?>" class="btn btn-outline-success">
                            <?= lang('Backend.add') ?>
                        </a>
                    </li>
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
            <h3 class="card-title font-weight-bold"><?= lang($title->pagename) ?></h3>

            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>
        <div class="card-body">
            <?= view('Modules\Auth\Views\_message_block') ?>
            <div class="row mb-4">
                <div class="col-md-3 col-sm-6 col-12">
                    <div class="info-box">
                        <span class="info-box-icon bg-primary"><i class="fas fa-cubes"></i></span>

                        <div class="info-box-content">
                            <span class="info-box-text">Toplam Modül</span>
                            <span class="info-box-number"><?= count($modules) ?></span>
                        </div>
                        <!-- /.info-box-content -->
                    </div>
                    <!-- /.info-box -->
                </div>

                <div class="col-md-3 col-sm-6 col-12">
                    <div class="info-box">
                        <span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span>

                        <div class="info-box-content">
                            <span class="info-box-text">Aktif Modül</span>
                            <span class="info-box-number"><?= count(array_filter($modules, fn($m) => $m->active)) ?></span>
                        </div>
                        <!-- /.info-box-content -->
                    </div>
                    <!-- /.info-box -->
                </div>

                <div class="col-md-3 col-sm-6 col-12">
                    <div class="info-box">
                        <span class="info-box-icon bg-warning"><i class="fas fa-file-alt text-light"></i></span>

                        <div class="info-box-content">
                            <span class="info-box-text">Toplam Sayfa</span>
                            <span class="info-box-number"><?= array_sum(array_map(fn($m) => count($m->pages), $modules)) ?></span>
                        </div>
                        <!-- /.info-box-content -->
                    </div>
                    <!-- /.info-box -->
                </div>

                <div class="col-md-3 col-sm-6 col-12">
                    <div class="info-box">
                        <span class="info-box-icon bg-info"><i class="fas fa-sitemap"></i></span>

                        <div class="info-box-content">
                            <span class="info-box-text">Navigasyonda Olanlar</span>
                            <span class="info-box-number"><?php
                                                            $navCount = 0;
                                                            foreach ($modules as $module) {
                                                                foreach ($module->pages as $page) {
                                                                    if ($page->inNavigation) $navCount++;
                                                                }
                                                            }
                                                            echo $navCount;
                                                            ?></span>
                        </div>
                        <!-- /.info-box-content -->
                    </div>
                    <!-- /.info-box -->
                </div>
            </div>

            <div class="row">
                <!-- Filtreleme Paneli -->
                <div class="col-12 card filter-section">
                    <div class="row card-body g-3">
                        <div class="col-md-4 form-group">
                            <label class="form-label">Modül Adı</label>
                            <select class="form-control" id="moduleFilter">
                                <option value="">Tüm Modüller</option>
                                <?php foreach ($modules as $module) { ?>
                                    <option value="<?= $module->id ?>"><?= $module->name ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="form-label">Sayfa Adı</label>
                            <input type="text" class="form-control" id="pageFilter" placeholder="Ara...">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="form-label">Durum</label>
                            <select class="form-control" id="statusFilter">
                                <option value="">Tümü</option>
                                <option value="active">Aktif</option>
                                <option value="inactive">Pasif</option>
                            </select>
                        </div>
                        <div class="col-12 form-group">
                            <div class="d-flex justify-content-end">
                                <button id="resetFilters" class="btn btn-outline-secondary me-2">
                                    <i class="fas fa-undo me-1"></i> Sıfırla
                                </button>
                                <button id="applyFilters" class="btn btn-primary">
                                    <i class="fas fa-filter me-1"></i> Filtrele
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <?php foreach ($modules as $module): ?>
                        <div class="card module-card" data-module-id="<?= $module->id ?>" data-status="<?= $module->active ? 'active' : 'inactive' ?>">
                            <div class="card-header">
                                <div class="d-flex align-items-center">
                                    <div class="module-title">
                                        <div class="icon bg-secondary">
                                            <i class="<?= $module->icon ?>"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-0"><?= htmlspecialchars($module->name) ?></h5>
                                            <small class="text-muted"><?= date('d.m.Y', strtotime($module->created)) ?></small>
                                            <span class="badge bg-primary"><?= count($module->pages) ?> adet metot</span>
                                        </div>
                                    </div>
                                </div>

                                <div class="w-100 text-right">
                                    <div class="module-toggle float-right">
                                        <span class="module-toggle-label"><?= $module->active ? 'Aktif' : 'Pasif' ?></span>
                                        <label class="toggle-switch">
                                            <input type="checkbox" class="module-toggle-input" <?= $module->active ? 'checked' : '' ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($module->pages)): ?>
                                    <div class="alert alert-warning">Bu modül için tanımlanmış sayfa bulunamadı</div>
                                    <?php else:
                                    foreach ($module->pages as $page): ?>
                                        <div class="page-item" data-page-id="<?= $page->id ?>" data-status="<?= $page->isActive ? 'active' : 'inactive' ?>" data-content="<?= htmlspecialchars($page->description) ?>">
                                            <div class="d-flex">
                                                <div class="page-name w-100 d-flex"><?= htmlspecialchars($page->pagename) ?>
                                                    <?php if ($page->inNavigation): ?>
                                                        <span class="ml-2 badge bg-info d-flex align-items-center">Navigation</span>
                                                    <?php endif; ?>
                                                    <?php if ($page->hasChild): ?>
                                                        <span class="ml-2 badge bg-warning d-flex align-items-center">Alt Sayfa Var</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-right w-100">
                                                    <span class="status-badge status-<?= $page->isActive == true ? 'active' : 'inactive' ?>"><?= $page->isActive == true ? 'Aktif' : 'Pasif' ?></span>
                                                </div>
                                            </div>
                                            <div class="page-description"><?= htmlspecialchars($page->description) ?></div>
                                            <div class="page-meta">
                                                <div class="meta-item">
                                                    <i class="fas fa-code me-1"></i>
                                                    <?= htmlspecialchars(str_replace('-', '\\', $page->className)) ?>::<?= htmlspecialchars($page->methodName) ?>
                                                </div>
                                                <div class="meta-item">
                                                    <i class="fas fa-link me-1"></i>
                                                    <?= htmlspecialchars($page->sefLink) ?>
                                                </div>
                                            </div>
                                            <label class="toggle-switch page-toggle">
                                                <input type="checkbox" <?= $page->isActive ? 'checked' : '' ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </div>
                                <?php endforeach;
                                endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
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
<script>
    $('#moduleScan').on('click', function() {
        $.ajax({
            url: '<?= route_to('moduleScan') ?>',
            type: 'POST',
            data: {
                page_id: pageItem.data('page-id'),
                status: this.checked ? 'active' : 'inactive'
            },
            beforeSend: function() {
                Swal.fire({
                    title: 'Modüller yükleniyor...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            },
            success: function(response) {
                if (response.result === true) {
                    Swal.fire('Modüller başarı ile yüklendi.', '', 'success');
                } else {
                    Swal.fire('Modüller yüklenemedi', '', 'error');
                }
            },
            error: function() {
                Swal.fire('Modüller yüklenirken sorun oluştu.', '', 'error');
            }
        });
    });
    // Filtreleme fonksiyonu
    function applyFilters() {
        var moduleFilter = $('#moduleFilter').val();
        var pageFilter = $('#pageFilter').val().toLowerCase();
        var statusFilter = $('#statusFilter').val();

        var hasResults = false;

        // Tüm modülleri gizle
        $('.module-card').hide();

        // Filtreleme işlemi
        $('.module-card').each(function() {
            var $card = $(this);
            var moduleName = $card.data('module-id');
            var moduleStatus = $card.data('status');
            var moduleMatches = true;
            var hasVisiblePages = false;
            // Modül adı filtresi
            if (moduleFilter && moduleName != moduleFilter) {
                moduleMatches = false;
            }

            // Durum filtresi
            if (statusFilter && moduleStatus !== statusFilter) {
                moduleMatches = false;
            }

            // Sayfa filtreleme
            $card.find('.page-item').each(function() {
                var $page = $(this);
                var pageName = $page.data('page-id');
                var pageContent = $page.data('content').toLowerCase();
                var pageStatus = $page.data('status');
                var pageMatches = true;

                // Sayfa adı/içerik filtresi
                if (pageFilter && pageName != pageFilter && pageContent.indexOf(pageFilter) === -1) {
                    pageMatches = false;
                }

                // Durum filtresi
                if (statusFilter && pageStatus !== statusFilter) {
                    pageMatches = false;
                }

                // Sayfayı göster/gizle
                if (pageMatches) {
                    $page.show();
                    hasVisiblePages = true;
                } else {
                    $page.hide();
                }
            });

            // Modülü göster/gizle
            if (moduleMatches && (hasVisiblePages || !pageFilter)) {
                $card.show();
                hasResults = true;
            }
        });

        // Sonuç yoksa mesaj göster
        if (!hasResults) {
            if ($('#noResultsMessage').length === 0) {
                $('#modulesContainer').append('<div id="noResultsMessage" class="no-results">Filtre kriterlerinize uygun sonuç bulunamadı.</div>');
            }
        } else {
            $('#noResultsMessage').remove();
        }
    }

    // Filtrele butonu
    $('#applyFilters').click(applyFilters);

    // Enter tuşu ile filtreleme
    $('#pageFilter').keypress(function(e) {
        if (e.which === 13) {
            applyFilters();
        }
    });

    // Filtreleri sıfırla
    $('#resetFilters').click(function() {
        $('#moduleFilter').val('');
        $('#pageFilter').val('');
        $('#statusFilter').val('');

        $('.module-card').show();
        $('.page-item').show();
        $('#noResultsMessage').remove();
    });

    // Modül toggle işlevselliği
    $('.module-toggle-input').on('change', function() {
        const moduleCard = $(this).closest('.module-card');
        const moduleStatusLabel = $(this).closest('.module-toggle').find('.module-toggle-label');

        if (this.checked) {
            moduleCard.removeClass('inactive');
            moduleStatusLabel.text('Aktif');
            moduleCard.find('.page-toggle input').each(function() {
                $(this).prop('checked', true);
                $(this).closest('.page-item').removeClass('inactive');
                $(this).closest('.page-item').find('.status-badge').text('Aktif').attr('class', 'status-badge status-active');
            });
        } else {
            moduleCard.addClass('inactive');
            moduleStatusLabel.text('Pasif');
            moduleCard.find('.page-toggle input').each(function() {
                $(this).prop('checked', false);
                $(this).closest('.page-item').addClass('inactive');
                $(this).closest('.page-item').find('.status-badge').text('Pasif').attr('class', 'status-badge status-inactive');
            });
        }

        // AJAX isteği gönder
        $.ajax({
            url: '<?= route_to('methods') ?>',
            type: 'POST',
            data: {
                module_id: moduleCard.data('module-id'),
                status: this.checked ? 'active' : 'inactive'
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: 'Başarılı!',
                        text: 'Modül durumu güncellendi.',
                        icon: 'success',
                        confirmButtonText: 'Tamam'
                    });
                } else {
                    Swal.fire({
                        title: 'Hata!',
                        text: 'Modül durumu güncellenemedi.',
                        icon: 'error',
                        confirmButtonText: 'Tamam'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'Hata!',
                    text: 'Sunucuya bağlanırken bir hata oluştu.',
                    icon: 'error',
                    confirmButtonText: 'Tamam'
                });
            }
        });
    });

    // Sayfa toggle işlevselliği
    $('.page-toggle input[type="checkbox"]').on('change', function() {
        const pageItem = $(this).closest('.page-item');
        const statusBadge = pageItem.find('.status-badge');
        if (this.checked) {
            pageItem.removeClass('inactive');
            statusBadge.text('Aktif').attr('class', 'status-badge status-active');
        } else {
            pageItem.addClass('inactive');
            statusBadge.text('Pasif').attr('class', 'status-badge status-inactive');
        }

        // AJAX isteği gönder
        $.ajax({
            url: '<?= route_to('methods') ?>',
            type: 'POST',
            data: {
                page_id: pageItem.data('page-id'),
                status: this.checked ? 'active' : 'inactive'
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: 'Başarılı!',
                        text: 'Sayfa durumu güncellendi.',
                        icon: 'success',
                        confirmButtonText: 'Tamam'
                    });
                } else {
                    Swal.fire({
                        title: 'Hata!',
                        text: 'Sayfa durumu güncellenemedi.',
                        icon: 'error',
                        confirmButtonText: 'Tamam'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: 'Hata!',
                    text: 'Sunucuya bağlanırken bir hata oluştu.',
                    icon: 'error',
                    confirmButtonText: 'Tamam'
                });
            }
        });
    });
</script>
<?= $this->endSection() ?>
