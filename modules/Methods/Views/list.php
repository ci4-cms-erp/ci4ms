<?php echo $this->extend('Modules\Backend\Views\base') ?>

<?php echo $this->section('title') ?>
<?php echo lang($title->pagename) ?>
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
                <div class="btn-group float-sm-right" role="group" aria-label="Basic example">
                    <button class="btn btn-outline-info" id="moduleScan">
                        <i class="fas fa-recycle"></i> <?php echo lang('Methods.scanModules') ?>
                    </button>
                    <a href="<?php echo route_to('methodCreate') ?>" class="btn btn-outline-success">
                        <?php echo lang('Backend.add') ?>
                    </a>
                </div>
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
            <div class="row mb-4">
                <div class="col-md-3 col-sm-6 col-12">
                    <div class="info-box">
                        <span class="info-box-icon bg-primary"><i class="fas fa-cubes"></i></span>

                        <div class="info-box-content">
                            <span class="info-box-text"><?php echo lang('Methods.totalModules') ?></span>
                            <span class="info-box-number"><?php echo count($modules) ?></span>
                        </div>
                        <!-- /.info-box-content -->
                    </div>
                    <!-- /.info-box -->
                </div>

                <div class="col-md-3 col-sm-6 col-12">
                    <div class="info-box">
                        <span class="info-box-icon bg-success"><i class="fas fa-check-circle"></i></span>

                        <div class="info-box-content">
                            <span class="info-box-text"><?php echo lang('Methods.activeModules') ?></span>
                            <span class="info-box-number"><?php echo count(array_filter($modules, fn($m) => $m->active)) ?></span>
                        </div>
                        <!-- /.info-box-content -->
                    </div>
                    <!-- /.info-box -->
                </div>

                <div class="col-md-3 col-sm-6 col-12">
                    <div class="info-box">
                        <span class="info-box-icon bg-warning"><i class="fas fa-file-alt text-light"></i></span>

                        <div class="info-box-content">
                            <span class="info-box-text"><?php echo lang('Methods.totalPages') ?></span>
                            <span class="info-box-number"><?php echo array_sum(array_map(fn($m) => count($m->pages), $modules)) ?></span>
                        </div>
                        <!-- /.info-box-content -->
                    </div>
                    <!-- /.info-box -->
                </div>

                <div class="col-md-3 col-sm-6 col-12">
                    <div class="info-box">
                        <span class="info-box-icon bg-info"><i class="fas fa-sitemap"></i></span>

                        <div class="info-box-content">
                            <span class="info-box-text"><?php echo lang('Methods.inNavigation') ?></span>
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
                            <label class="form-label"><?php echo lang('Methods.moduleName') ?></label>
                            <select class="form-control" id="moduleFilter">
                                <option value=""><?php echo lang('Methods.allModules') ?></option>
                                <?php foreach ($modules as $module) { ?>
                                    <option value="<?php echo $module->id ?>"><?php echo $module->name ?></option>
                                <?php } ?>
                            </select>
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="form-label"><?php echo lang('Methods.pageName') ?></label>
                            <input type="text" class="form-control" id="pageFilter" placeholder="<?php echo lang('Backend.search') ?>">
                        </div>
                        <div class="col-md-4 form-group">
                            <label class="form-label"><?php echo lang('Backend.status') ?></label>
                            <select class="form-control" id="statusFilter">
                                <option value=""><?php echo lang('Backend.select') ?></option>
                                <option value="active"><?php echo lang('Backend.active') ?></option>
                                <option value="inactive"><?php echo lang('Backend.passive') ?></option>
                            </select>
                        </div>
                        <div class="col-12 form-group">
                            <div class="d-flex justify-content-end">
                                <button id="resetFilters" class="btn btn-outline-secondary me-2">
                                    <i class="fas fa-undo me-1"></i> <?php echo lang('Backend.reset') ?>
                                </button>
                                <button id="applyFilters" class="btn btn-primary">
                                    <i class="fas fa-filter me-1"></i> <?php echo lang('Backend.filter') ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-12">
                    <?php foreach ($modules as $module): ?>
                        <div class="card module-card" data-module-id="<?php echo $module->id ?>" data-status="<?php echo $module->active ? 'active' : 'inactive' ?>">
                            <div class="card-header">
                                <div class="d-flex align-items-center">
                                    <div class="module-title">
                                        <div class="icon bg-secondary">
                                            <i class="<?php echo $module->icon ?>"></i>
                                        </div>
                                        <div>
                                            <h5 class="mb-0"><?php echo htmlspecialchars($module->name) ?></h5>
                                            <small class="text-muted"><?php echo date('d.m.Y', strtotime($module->created)) ?></small>
                                            <span class="badge bg-primary"><?php echo lang('Methods.methodCount', [count($module->pages)]) ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div class="w-100 text-right">
                                    <div class="module-toggle float-right">
                                        <span class="module-toggle-label"><?php echo $module->active ? lang('Backend.active') : lang('Backend.passive') ?></span>
                                        <label class="toggle-switch">
                                            <input type="checkbox" class="module-toggle-input" <?php echo $module->active ? 'checked' : '' ?>>
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            <div class="card-body p-0">
                                <?php if (empty($module->pages)): ?>
                                    <div class="alert alert-warning"><?php echo lang('Methods.noPagesFound') ?></div>
                                    <?php else:
                                    foreach ($module->pages as $page): ?>
                                        <div class="page-item" data-page-id="<?php echo $page->id ?>" data-status="<?php echo $page->isActive ? 'active' : 'inactive' ?>" data-content="<?php echo htmlspecialchars($page->description) ?>">
                                            <div class="d-flex">
                                                <div class="page-name w-100 d-flex"><?php echo htmlspecialchars($page->pagename) ?>
                                                    <?php if ($page->inNavigation): ?>
                                                        <span class="ml-2 badge bg-info d-flex align-items-center"><?php echo lang('Methods.navigation') ?></span>
                                                    <?php endif; ?>
                                                    <?php if ($page->hasChild): ?>
                                                        <span class="ml-2 badge bg-warning d-flex align-items-center"><?php echo lang('Methods.hasChildPages') ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="text-right w-100">
                                                    <span class="status-badge status-<?php echo $page->isActive == true ? 'active' : 'inactive' ?>"><?php echo $page->isActive == true ? lang('Backend.active') : lang('Backend.passive') ?></span>
                                                </div>
                                            </div>
                                            <div class="page-description"><?php echo htmlspecialchars($page->description) ?></div>
                                            <div class="page-meta">
                                                <div class="meta-item">
                                                    <i class="fas fa-code me-1"></i>
                                                    <?php echo htmlspecialchars(str_replace('-', '\\', $page->className)) ?>::<?php echo htmlspecialchars($page->methodName) ?>
                                                </div>
                                                <div class="meta-item">
                                                    <i class="fas fa-link me-1"></i>
                                                    <?php echo htmlspecialchars($page->sefLink) ?>
                                                </div>
                                            </div>
                                            <label class="toggle-switch page-toggle">
                                                <input type="checkbox" <?php echo $page->isActive ? 'checked' : '' ?>>
                                                <span class="toggle-slider"></span>
                                                <a href="<?php echo route_to('methodUpdate', $page->id) ?>" class="btn btn-info float-right mt-3 btn-sm"><?php echo lang('Backend.update') ?></a>
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
<?php echo $this->endSection() ?>

<?php echo $this->section('javascript') ?>
<script {csp-script-nonce}>
    $('#moduleScan').on('click', function() {
        $.ajax({
            url: '<?php echo route_to('moduleScan') ?>',
            type: 'POST',
            beforeSend: function() {
                Swal.fire({
                    title: '<?php echo lang('Methods.modulesLoading') ?>',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
            },
            success: function(response) {
                if (response.result === true) {
                    Swal.fire('<?php echo lang('Methods.modulesLoaded') ?>', '', 'success').then((result) => {
                        if (result.isConfirmed) location.reload();
                    });
                } else {
                    Swal.fire('<?php echo lang('Methods.noNewModules') ?>', '<?php echo lang('Methods.noNewModulesError') ?>', 'warning');
                }
            },
            error: function() {
                Swal.fire('<?php echo lang('Methods.modulesLoadError') ?>', '', 'error');
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
                $('#modulesContainer').append('<div id="noResultsMessage" class="no-results"><?php echo lang('Methods.noResults') ?></div>');
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
            moduleStatusLabel.text('<?php echo lang('Backend.active') ?>');
            moduleCard.find('.page-toggle input').each(function() {
                $(this).prop('checked', true);
                $(this).closest('.page-item').removeClass('inactive');
                $(this).closest('.page-item').find('.status-badge').text('<?php echo lang('Backend.active') ?>').attr('class', 'status-badge status-active');
            });
        } else {
            moduleCard.addClass('inactive');
            moduleStatusLabel.text('<?php echo lang('Backend.passive') ?>');
            moduleCard.find('.page-toggle input').each(function() {
                $(this).prop('checked', false);
                $(this).closest('.page-item').addClass('inactive');
                $(this).closest('.page-item').find('.status-badge').text('<?php echo lang('Backend.passive') ?>').attr('class', 'status-badge status-inactive');
            });
        }

        // AJAX isteği gönder
        $.ajax({
            url: '<?php echo route_to('methods') ?>',
            type: 'POST',
            data: {
                module_id: moduleCard.data('module-id'),
                status: this.checked ? 'active' : 'inactive'
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: '<?php echo lang('Backend.success') ?>',
                        text: '<?php echo lang('Methods.moduleStatusUpdated') ?>',
                        icon: 'success',
                        confirmButtonText: '<?php echo lang('Backend.ok') ?>'
                    });
                } else {
                    Swal.fire({
                        title: '<?php echo lang('Backend.error') ?>',
                        text: '<?php echo lang('Backend.moduleStatusUpdateFailed') ?>',
                        icon: 'error',
                        confirmButtonText: '<?php echo lang('Backend.ok') ?>'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: '<?php echo lang('Backend.error') ?>',
                    text: '<?php echo lang('Backend.serverConnectionError') ?>',
                    icon: 'error',
                    confirmButtonText: '<?php echo lang('Backend.ok') ?>'
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
            statusBadge.text('<?php echo lang('Backend.active') ?>').attr('class', 'status-badge status-active');
        } else {
            pageItem.addClass('inactive');
            statusBadge.text('<?php echo lang('Backend.passive') ?>').attr('class', 'status-badge status-inactive');
        }

        // AJAX isteği gönder
        $.ajax({
            url: '<?php echo route_to('methods') ?>',
            type: 'POST',
            data: {
                page_id: pageItem.data('page-id'),
                status: this.checked ? 'active' : 'inactive'
            },
            success: function(response) {
                if (response.success) {
                    Swal.fire({
                        title: '<?php echo lang('Backend.success') ?>',
                        text: '<?php echo lang('Methods.pageStatusUpdated') ?>',
                        icon: 'success',
                        confirmButtonText: '<?php echo lang('Backend.ok') ?>'
                    });
                } else {
                    Swal.fire({
                        title: '<?php echo lang('Backend.error') ?>',
                        text: '<?php echo lang('Methods.pageStatusUpdateFailed') ?>',
                        icon: 'error',
                        confirmButtonText: '<?php echo lang('Backend.ok') ?>'
                    });
                }
            },
            error: function() {
                Swal.fire({
                    title: '<?php echo lang('Backend.error') ?>',
                    text: '<?php echo lang('Methods.serverConnectionError') ?>',
                    icon: 'error',
                    confirmButtonText: '<?php echo lang('Backend.ok') ?>'
                });
            }
        });
    });
</script>
<?php echo $this->endSection() ?>
