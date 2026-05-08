<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('title');
echo lang($title->pagename);
echo $this->endSection();
echo $this->section('head');
echo link_tag('be-assets/plugins/dropzone/min/dropzone.min.css');
echo link_tag('be-assets/css/method-manager.css');
echo $this->endSection();
echo $this->section('content');
$protectedModules = $protectedModules ?? ['Auth', 'Backend', 'Install', 'Methods', 'Settings', 'LanguageManager', 'Pages', 'Blog', 'Theme', 'Users', 'DashboardWidgets', 'Media', 'Menu'];
$totalPages = array_sum(array_map(fn($m) => count($m->pages), $modules));
$activeCount = count(array_filter($modules, fn($m) => $m->active));
$navCount = 0;
foreach ($modules as $m) {
    foreach ($m->pages as $p) {
        if ($p->inNavigation)
            $navCount++;
    }
} ?>
<section class="content pt-3">
    <div class="row mb-4">
        <div class="col-xl-3 col-sm-6 mb-3 mb-xl-0">
            <div class="m-stat-card">
                <div class="m-stat-icon st-total"><i class="fas fa-cubes"></i></div>
                <div>
                    <div class="m-stat-value"><?php echo count($modules) ?></div>
                    <div class="m-stat-label"><?php echo lang('Methods.totalModules') ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-3 mb-xl-0">
            <div class="m-stat-card">
                <div class="m-stat-icon st-active"><i class="fas fa-check-circle"></i></div>
                <div>
                    <div class="m-stat-value"><?php echo $activeCount ?></div>
                    <div class="m-stat-label"><?php echo lang('Methods.activeModules') ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6 mb-3 mb-sm-0">
            <div class="m-stat-card">
                <div class="m-stat-icon st-pages"><i class="fas fa-file-alt"></i></div>
                <div>
                    <div class="m-stat-value"><?php echo $totalPages ?></div>
                    <div class="m-stat-label"><?php echo lang('Methods.totalPages') ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-sm-6">
            <div class="m-stat-card">
                <div class="m-stat-icon st-nav"><i class="fas fa-sitemap"></i></div>
                <div>
                    <div class="m-stat-value"><?php echo $navCount ?></div>
                    <div class="m-stat-label"><?php echo lang('Methods.inNavigation') ?></div>
                </div>
            </div>
        </div>
    </div>
    <div class="m-toolbar">
        <button class="btn btn-sm btn-outline-info" id="moduleScan">
            <i class="fas fa-sync-alt mr-1"></i> <?php echo lang('Methods.scanModules') ?>
        </button>
        <button type="button" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#modal-create-module">
            <i class="fas fa-plus mr-1"></i> <?php echo lang('Methods.createModule') ?>
        </button>
        <button type="button" class="btn btn-sm btn-outline-success" data-toggle="modal" data-target="#modal-default">
            <i class="fas fa-upload mr-1"></i> <?php echo lang('Methods.uploadModule') ?>
        </button>
        <a href="<?php echo route_to('methodCreate') ?>" class="btn btn-sm btn-outline-secondary">
            <?php echo lang('Backend.add') ?>
        </a>
        <div class="ml-auto d-flex gap-5">
            <button class="btn btn-sm btn-outline-dark" id="expandAll" title="Tümünü Aç">
                <i class="fas fa-expand-alt"></i>
            </button>
            <button class="btn btn-sm btn-outline-dark" id="collapseAll" title="Tümünü Kapat">
                <i class="fas fa-compress-alt"></i>
            </button>
        </div>
    </div>
    <div class="m-search-bar">
        <div class="m-search-input-wrap">
            <i class="fas fa-search"></i>
            <input type="text" class="m-search-input" id="liveSearch"
                placeholder="<?php echo lang('Backend.search') ?>... (modül, sayfa, handler)" autocomplete="off">
        </div>
        <select class="m-search-select" id="moduleFilter">
            <option value=""><?php echo lang('Methods.allModules') ?></option>
            <?php foreach ($modules as $module) { ?>
                <option value="<?php echo $module->id ?>"><?php echo esc($module->name) ?></option>
            <?php } ?>
        </select>
        <select class="m-search-select" id="statusFilter">
            <option value=""><?php echo lang('Backend.status') ?></option>
            <option value="active"><?php echo lang('Backend.active') ?></option>
            <option value="inactive"><?php echo lang('Backend.passive') ?></option>
        </select>
        <span class="m-search-count" id="searchCount"><?php echo count($modules) ?>
            <?php echo lang('Methods.modules') ?></span>
    </div>
    <div id="modulesContainer">
        <?php foreach ($modules as $module):
            $isProtected = in_array($module->name, $protectedModules);
            $activePages = count(array_filter($module->pages, fn($p) => $p->isActive));
            $inactivePages = count($module->pages) - $activePages;
            ?>
            <div class="m-module-card module-card <?php echo !$module->active ? 'inactive' : '' ?>"
                data-module-id="<?php echo $module->id ?>" data-module-name="<?php echo htmlspecialchars($module->name) ?>"
                data-status="<?php echo $module->active ? 'active' : 'inactive' ?>">

                <div class="m-module-header" data-toggle-card>
                    <div class="m-module-info">
                        <div class="m-module-icon"><i class="<?php echo $module->icon ?>"></i></div>
                        <div style="min-width:0">
                            <h5 class="m-module-name"><?php echo htmlspecialchars($module->name) ?></h5>
                            <div class="m-module-meta">
                                <span class="m-mtag m-mtag-date"><i
                                        class="far fa-calendar-alt mr-1"></i><?php echo date('d.m.Y', strtotime($module->created)) ?></span>
                                <span class="m-mtag m-mtag-count"><i
                                        class="fas fa-layer-group mr-1"></i><?php echo lang('Methods.methodCount', [count($module->pages)]) ?></span>
                                <?php if ($activePages > 0): ?><span class="m-mtag m-mtag-active"><?php echo $activePages ?>
                                        <?php echo lang('Backend.active') ?></span><?php endif; ?>
                                <?php if ($inactivePages > 0): ?><span
                                        class="m-mtag m-mtag-inactive"><?php echo $inactivePages ?>
                                        <?php echo lang('Backend.passive') ?></span><?php endif; ?>
                                <?php if ($isProtected): ?><span class="m-mtag m-mtag-core"><i
                                            class="fas fa-shield-alt mr-1"></i>Core</span><?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="m-module-actions" data-stop-propagation>
                        <div class="m-toggle-group">
                            <span
                                class="m-toggle-label module-toggle-label"><?php echo $module->active ? lang('Backend.active') : lang('Backend.passive') ?></span>
                            <label class="toggle-switch m-0">
                                <input type="checkbox" class="module-toggle-input" <?php echo $module->active ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        <?php if (!$isProtected): ?>
                            <button class="m-btn-delete btn-delete-module" data-module-id="<?php echo $module->id ?>"
                                title="<?php echo lang('Methods.deleteModule') ?>">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        <?php endif; ?>
                    </div>

                    <div class="m-chevron"><i class="fas fa-chevron-down"></i></div>
                </div>

                <!-- Collapsible Body -->
                <div class="m-pages-body">
                    <?php if (empty($module->pages)): ?>
                        <div class="m-empty-state"><i class="fas fa-inbox"></i><?php echo lang('Methods.noPagesFound') ?></div>
                    <?php else: ?>
                        <table class="m-pages-table">
                            <thead>
                                <tr>
                                    <th><?php echo lang('Methods.pageName') ?></th>
                                    <th class="col-handler">Handler</th>
                                    <th>SEF Link</th>
                                    <th class="text-center"><?php echo lang('Backend.status') ?></th>
                                    <th class="text-right"><?php echo lang('Backend.operations') ?? 'İşlemler' ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($module->pages as $page): ?>
                                    <tr class="page-item <?php echo !$page->isActive ? 'row-inactive' : '' ?>"
                                        data-page-id="<?php echo $page->id ?>"
                                        data-status="<?php echo $page->isActive ? 'active' : 'inactive' ?>"
                                        data-content="<?php echo htmlspecialchars($page->description ?? '') ?>">
                                        <td>
                                            <span class="m-page-name-cell">
                                                <?php echo htmlspecialchars($page->pagename) ?>
                                                <?php if ($page->inNavigation): ?><span
                                                        class="badge bg-info"><?php echo lang('Methods.navigation') ?></span><?php endif; ?>
                                                <?php if ($page->hasChild): ?><span
                                                        class="badge bg-warning text-dark"><?php echo lang('Methods.hasChildPages') ?></span><?php endif; ?>
                                            </span>
                                            <?php if (!empty($page->description)): ?>
                                                <div class="text-muted" style="font-size:.78rem;margin-top:1px">
                                                    <?php echo htmlspecialchars($page->description) ?>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="col-handler">
                                            <span class="m-copy-code"
                                                data-copy="<?php echo htmlspecialchars(str_replace('-', '\\', $page->className)) ?>::<?php echo htmlspecialchars($page->methodName) ?>"
                                                title="<?php echo lang('Methods.clickToCopy') ?? 'Kopyalamak için tıkla' ?>">
                                                <span
                                                    class="m-copy-text"><?php echo htmlspecialchars(str_replace('-', '\\', $page->className)) ?>::<?php echo htmlspecialchars($page->methodName) ?></span>
                                                <i class="far fa-copy m-copy-icon"></i>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="m-sef-link m-copy-code"
                                                data-copy="<?php echo htmlspecialchars($page->sefLink) ?>"
                                                title="<?php echo lang('Methods.clickToCopy') ?? 'Kopyalamak için tıkla' ?>"
                                                style="color:#718096;background:rgba(113,128,150,.08)">
                                                <i class="fas fa-link" style="font-size:.65rem"></i>
                                                <span class="m-copy-text"><?php echo htmlspecialchars($page->sefLink) ?></span>
                                                <i class="far fa-copy m-copy-icon"></i>
                                            </span>
                                        </td>
                                        <td style="text-align:center">
                                            <span
                                                class="m-status-pill <?php echo $page->isActive ? 'm-status-active' : 'm-status-inactive' ?> status-badge">
                                                <?php echo $page->isActive ? lang('Backend.active') : lang('Backend.passive') ?>
                                            </span>
                                            <label class="toggle-switch page-toggle">
                                                <input type="checkbox" <?php echo $page->isActive ? 'checked' : '' ?>>
                                                <span class="toggle-slider"></span>
                                            </label>
                                        </td>
                                        <td>
                                            <div class="m-page-actions">
                                                <a href="<?php echo !empty($page->id) ? route_to('methodUpdate', $page->id) : '#' ?>"
                                                    class="m-btn-edit">
                                                    <?php echo lang('Backend.update') ?>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div id="noResultsMessage" class="m-empty-state" style="display:none"><i
            class="fas fa-search"></i><?php echo lang('Methods.noResults') ?></div>
    <div class="modal fade" id="modal-default">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content" style="border-radius:14px;overflow:hidden">
                <div class="modal-header"
                    style="background:linear-gradient(135deg,#f8fafc,#edf2f7);border-bottom:1px solid #e2e8f0">
                    <h5 class="modal-title font-weight-bold"><i
                            class="fas fa-upload mr-2 text-success"></i><?php echo lang('Methods.uploadModule') ?></h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div id="actions" class="row">
                        <div class="col-lg-12">
                            <div class="btn-group w-100">
                                <span class="btn btn-success col fileinput-button" style="border-radius:8px 0 0 8px"><i
                                        class="fas fa-plus mr-1"></i><span><?php echo lang('Methods.addFiles') ?></span></span>
                                <button type="submit" class="btn btn-primary col start"><i
                                        class="fas fa-upload mr-1"></i><span><?php echo lang('Methods.startUpload') ?></span></button>
                                <button type="reset" class="btn btn-warning col cancel"
                                    style="border-radius:0 8px 8px 0"><i
                                        class="fas fa-times-circle mr-1"></i><span><?php echo lang('Methods.cancelUpload') ?></span></button>
                            </div>
                        </div>
                        <div class="col-lg-12 d-flex align-items-center mt-2">
                            <div class="fileupload-process w-100">
                                <div id="total-progress" class="progress" role="progressbar"
                                    style="border-radius:8px;height:6px">
                                    <div class="progress-bar progress-bar-success" style="width:0%;"
                                        data-dz-uploadprogress></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="table table-striped files" id="previews">
                        <div id="template" class="row mt-2">
                            <div class="col-auto"><span class="preview"><img src="data:," alt=""
                                        data-dz-thumbnail /></span></div>
                            <div class="col d-flex align-items-center">
                                <p class="mb-0"><span class="lead" data-dz-name></span> (<span data-dz-size></span>)</p>
                                <strong class="error text-danger" data-dz-errormessage></strong>
                            </div>
                            <div class="col-4 d-flex align-items-center">
                                <div class="progress w-100" role="progressbar">
                                    <div class="progress-bar progress-bar-success" style="width:0%;"
                                        data-dz-uploadprogress></div>
                                </div>
                            </div>
                            <div class="col-auto d-flex align-items-center">
                                <div class="btn-group">
                                    <button class="btn btn-sm btn-primary start"><i class="fas fa-upload"></i></button>
                                    <button data-dz-remove class="btn btn-sm btn-warning cancel"><i
                                            class="fas fa-times-circle"></i></button>
                                    <button data-dz-remove class="btn btn-sm btn-danger delete"><i
                                            class="fas fa-trash"></i></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-dismiss="modal"
                        style="border-radius:8px"><?php echo lang('Backend.cancel') ?></button></div>
            </div>
        </div>
    </div>
    <div class="modal fade" id="modal-create-module">
        <div class="modal-dialog modal-dialog-centered">
            <form id="form-create-module" class="modal-content" style="border-radius:14px;overflow:hidden">
                <div class="modal-header"
                    style="background:linear-gradient(135deg,#f8fafc,#edf2f7);border-bottom:1px solid #e2e8f0">
                    <h5 class="modal-title font-weight-bold"><i
                            class="fas fa-plus-circle mr-2 text-primary"></i><?php echo lang('Methods.createModule') ?>
                    </h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span
                            aria-hidden="true">&times;</span></button>
                </div>
                <div class="modal-body">
                    <div class="form-group">
                        <label for="module_name"
                            class="font-weight-bold"><?php echo lang('Methods.moduleName') ?></label>
                        <input type="text" name="module_name" id="module_name" class="form-control"
                            placeholder="Örn: Blog, Product, Admin" style="border-radius:8px" required>
                        <small class="form-text text-muted"><?php echo lang('Methods.moduleNameDesc') ?></small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-dismiss="modal"
                        style="border-radius:8px"><?php echo lang('Backend.cancel') ?></button>
                    <button type="submit" class="btn btn-primary" style="border-radius:8px"><i
                            class="fas fa-check mr-1"></i> <?php echo lang('Backend.add') ?></button>
                </div>
            </form>
        </div>
    </div>
</section>
<?php echo $this->endSection();
echo $this->section('javascript');
echo script_tag("be-assets/plugins/dropzone/min/dropzone.min.js"); ?>
<script type="text/javascript" <?php echo csp_script_nonce(); ?>>
    (function () {
        'use strict';

        // showToast is now global (ci4ms.js)

        // ═══════════════════════════════════════════════════════════
        // Accordion Toggle
        // ═══════════════════════════════════════════════════════════
        $(document).on('click', '[data-toggle-card]', function (e) {
            // Don't toggle if clicking action buttons
            if ($(e.target).closest('[data-stop-propagation]').length) return;
            $(this).closest('.m-module-card').toggleClass('m-expanded');
        });

        $('#expandAll').on('click', function () {
            $('.m-module-card').addClass('m-expanded');
        });
        $('#collapseAll').on('click', function () {
            $('.m-module-card').removeClass('m-expanded');
        });

        // ═══════════════════════════════════════════════════════════
        // Click-to-Copy
        // ═══════════════════════════════════════════════════════════
        $(document).on('click', '.m-copy-code', function (e) {
            e.stopPropagation();
            var text = $(this).data('copy');
            var $el = $(this);
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function () {
                    $el.addClass('m-copied');
                    showToast('<?php echo lang('Methods.copiedToClipboard') ?? 'Panoya kopyalandı' ?>');
                    setTimeout(function () {
                        $el.removeClass('m-copied');
                    }, 1200);
                });
            } else {
                // Fallback
                var ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                $el.addClass('m-copied');
                showToast('<?php echo lang('Methods.copiedToClipboard') ?? 'Panoya kopyalandı' ?>');
                setTimeout(function () {
                    $el.removeClass('m-copied');
                }, 1200);
            }
        });

        // ═══════════════════════════════════════════════════════════
        // Live Search (debounced)
        // ═══════════════════════════════════════════════════════════
        var searchTimer = null;

        function runFilter() {
            var q = ($('#liveSearch').val() || '').toLowerCase().trim();
            var modFilter = $('#moduleFilter').val();
            var statusFilter = $('#statusFilter').val();
            var visibleCount = 0;

            $('.m-module-card').each(function () {
                var $card = $(this);
                var moduleId = $card.data('module-id');
                var moduleName = ($card.data('module-name') || '').toString().toLowerCase();
                var moduleStatus = $card.data('status');
                var show = true;

                // Module dropdown filter
                if (modFilter && moduleId != modFilter) show = false;
                // Status filter (module-level)
                if (statusFilter && moduleStatus !== statusFilter) show = false;

                // Text search: match module name or any page content
                if (q) {
                    var matchFound = moduleName.indexOf(q) !== -1;
                    $card.find('.page-item').each(function () {
                        var rowText = $(this).text().toLowerCase();
                        if (rowText.indexOf(q) !== -1) {
                            matchFound = true;
                            $(this).show();
                        } else {
                            $(this).hide();
                        }
                    });
                    if (!matchFound) show = false;
                } else {
                    $card.find('.page-item').show();
                }

                if (show) {
                    $card.removeClass('m-hidden');
                    visibleCount++;
                } else {
                    $card.addClass('m-hidden');
                }
            });

            $('#searchCount').text(visibleCount + ' <?php echo lang('Methods.modules') ?>');
            if (visibleCount === 0) {
                $('#noResultsMessage').show();
            } else {
                $('#noResultsMessage').hide();
            }
        }

        $('#liveSearch').on('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(runFilter, 200);
        });
        $('#moduleFilter, #statusFilter').on('change', runFilter);

        // ═══════════════════════════════════════════════════════════
        // Dropzone
        // ═══════════════════════════════════════════════════════════
        var previewNode = document.querySelector("#template");
        previewNode.id = "";
        var previewTemplate = previewNode.parentNode.innerHTML;
        previewNode.parentNode.removeChild(previewNode);

        var myDropzone = new Dropzone(document.body, {
            url: "<?php echo route_to('moduleUpload') ?>",
            thumbnailWidth: 80,
            thumbnailHeight: 80,
            parallelUploads: 20,
            uploadMultiple: false,
            paramName: "modules",
            acceptedFiles: "application/zip, application/octet-stream, application/x-zip-compressed, multipart/x-zip",
            previewTemplate: previewTemplate,
            autoQueue: false,
            previewsContainer: "#previews",
            clickable: ".fileinput-button"
        });
        myDropzone.on('successmultiple', function (files, response) {
            Swal.fire({
                icon: response.status == 'success' ? 'success' : 'error',
                title: response.status == 'success' ? '<?php echo lang('Methods.success') ?>' : '<?php echo lang('Backend.error') ?>',
                text: response.message
            });
            myDropzone.removeAllFiles(true);
        });
        myDropzone.on("addedfile", function (file) {
            file.previewElement.querySelector(".start").onclick = function () {
                myDropzone.enqueueFile(file);
            };
        });
        myDropzone.on("totaluploadprogress", function (p) {
            document.querySelector("#total-progress .progress-bar").style.width = p + "%";
        });
        myDropzone.on("sending", function (file, xhr, formData) {
            document.querySelector("#total-progress").style.opacity = "1";
            file.previewElement.querySelector(".start").setAttribute("disabled", "disabled");
            xhr.setRequestHeader('X-CSRF-TOKEN', CI4MS_CSRF.getHash());
            formData.append(CI4MS_CSRF.name, CI4MS_CSRF.getHash());
        });
        myDropzone.on("success", function (file, response, e) {
            var newToken = file.xhr && file.xhr.getResponseHeader('X-CSRF-TOKEN');
            if (newToken) CI4MS_CSRF.setHash(newToken);
        });
        myDropzone.on("queuecomplete", function () {
            document.querySelector("#total-progress").style.opacity = "0";
        });
        document.querySelector("#actions .start").onclick = function () {
            myDropzone.enqueueFiles(myDropzone.getFilesWithStatus(Dropzone.ADDED));
        };
        document.querySelector("#actions .cancel").onclick = function () {
            myDropzone.removeAllFiles(true);
        };

        // ═══════════════════════════════════════════════════════════
        // Module Scan
        // ═══════════════════════════════════════════════════════════
        $('#moduleScan').on('click', function () {
            $.ajax({
                url: '<?php echo route_to('moduleScan') ?>',
                type: 'POST',
                beforeSend: function () {
                    Swal.fire({
                        title: '<?php echo lang('Methods.modulesLoading') ?>',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                },
                success: function (r) {
                    if (r.result === true) {
                        Swal.fire('<?php echo lang('Methods.modulesLoaded') ?>', '', 'success').then((res) => {
                            if (res.isConfirmed) location.reload();
                        });
                    } else {
                        Swal.fire('<?php echo lang('Methods.noNewModules') ?>', '<?php echo lang('Methods.noNewModulesError') ?>', 'warning');
                    }
                },
                error: function () {
                    Swal.fire('<?php echo lang('Methods.modulesLoadError') ?>', '', 'error');
                }
            });
        });

        // ═══════════════════════════════════════════════════════════
        // Module Toggle (with Toast)
        // ═══════════════════════════════════════════════════════════
        $('.module-toggle-input').on('change', function (e) {
            e.stopPropagation();
            var $this = $(this);
            var moduleCard = $this.closest('.module-card');
            var label = moduleCard.find('.module-toggle-label');
            if (this.checked) {
                moduleCard.removeClass('inactive');
                label.text('<?php echo lang('Backend.active') ?>');
                moduleCard.find('.page-toggle input').each(function () {
                    $(this).prop('checked', true);
                    var row = $(this).closest('.page-item');
                    row.removeClass('row-inactive');
                    row.find('.status-badge').text('<?php echo lang('Backend.active') ?>').attr('class', 'm-status-pill m-status-active status-badge');
                });
            } else {
                moduleCard.addClass('inactive');
                label.text('<?php echo lang('Backend.passive') ?>');
                moduleCard.find('.page-toggle input').each(function () {
                    $(this).prop('checked', false);
                    var row = $(this).closest('.page-item');
                    row.addClass('row-inactive');
                    row.find('.status-badge').text('<?php echo lang('Backend.passive') ?>').attr('class', 'm-status-pill m-status-inactive status-badge');
                });
            }
            $.ajax({
                url: '<?php echo route_to('methods') ?>',
                type: 'POST',
                data: {
                    module_id: moduleCard.data('module-id'),
                    status: this.checked ? 'active' : 'inactive'
                },
                success: function (r) {
                    showToast(r.success ? '<?php echo lang('Methods.moduleStatusUpdated') ?>' : '<?php echo lang('Methods.moduleStatusUpdateFailed') ?>', r.success ? 'success' : 'error');
                },
                error: function () {
                    showToast('<?php echo lang('Methods.serverConnectionError') ?>', 'error');
                }
            });
        });

        // ═══════════════════════════════════════════════════════════
        // Page Toggle (with Toast)
        // ═══════════════════════════════════════════════════════════
        $('.page-toggle input[type="checkbox"]').on('change', function () {
            var row = $(this).closest('.page-item');
            var badge = row.find('.status-badge');
            if (this.checked) {
                row.removeClass('row-inactive');
                badge.text('<?php echo lang('Backend.active') ?>').attr('class', 'm-status-pill m-status-active status-badge');
            } else {
                row.addClass('row-inactive');
                badge.text('<?php echo lang('Backend.passive') ?>').attr('class', 'm-status-pill m-status-inactive status-badge');
            }
            $.ajax({
                url: '<?php echo route_to('methods') ?>',
                type: 'POST',
                data: {
                    page_id: row.data('page-id'),
                    status: this.checked ? 'active' : 'inactive'
                },
                success: function (r) {
                    showToast(r.success ? '<?php echo lang('Methods.pageStatusUpdated') ?>' : '<?php echo lang('Methods.pageStatusUpdateFailed') ?>', r.success ? 'success' : 'error');
                },
                error: function () {
                    showToast('<?php echo lang('Methods.serverConnectionError') ?>', 'error');
                }
            });
        });

        // ═══════════════════════════════════════════════════════════
        // Create Module
        // ═══════════════════════════════════════════════════════════
        $('#form-create-module').on('submit', function (e) {
            e.preventDefault();
            var fd = $(this).serialize(),
                btn = $(this).find('button[type="submit"]');
            $.ajax({
                url: '<?php echo route_to('moduleCreate') ?>',
                type: 'POST',
                data: fd,
                beforeSend: function () {
                    btn.prop('disabled', true);
                    Swal.fire({
                        title: '<?php echo lang('Methods.working') ?>',
                        allowOutsideClick: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                },
                success: function (r) {
                    btn.prop('disabled', false);
                    if (r.status === 'success') {
                        $('#modal-create-module').modal('hide');
                        Swal.fire({
                            icon: 'success',
                            title: '<?php echo lang('Backend.success') ?>',
                            text: r.message
                        }).then(() => {
                            location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: '<?php echo lang('Backend.error') ?>',
                            text: r.message
                        });
                    }
                },
                error: function () {
                    btn.prop('disabled', false);
                    Swal.fire({
                        icon: 'error',
                        title: '<?php echo lang('Backend.error') ?>',
                        text: '<?php echo lang('Methods.serverConnectionError') ?>'
                    });
                }
            });
        });

        // ═══════════════════════════════════════════════════════════
        // Module Delete (3-step confirmation)
        // ═══════════════════════════════════════════════════════════
        $(document).on('click', '.btn-delete-module', function (e) {
            e.stopPropagation();
            var moduleId = $(this).data('module-id');
            var moduleCard = $(this).closest('.module-card');
            var moduleName = moduleCard.data('module-name');

            Swal.fire({
                title: '<?php echo lang('Methods.deleteModuleConfirm') ?>',
                html: '<p class="text-danger"><strong>' + moduleName + '</strong></p><p><?php echo lang('Methods.deleteModuleWarning') ?></p>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#e53e3e',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-trash-alt mr-1"></i> <?php echo lang('Methods.deleteModule') ?>',
                cancelButtonText: '<?php echo lang('Backend.cancel') ?>'
            }).then((result) => {
                if (!result.isConfirmed) return;
                Swal.fire({
                    title: '<?php echo lang('Methods.working') ?>',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });

                $.ajax({
                    url: '<?php echo route_to('moduleInfo', 0) ?>'.replace('/0', '/' + moduleId),
                    type: 'GET',
                    dataType: 'json',
                    success: function (response) {
                        if (response.status === 'protected') {
                            Swal.fire('<?php echo lang('Backend.error') ?>', response.message, 'error');
                            return;
                        }

                        var tableHtml = '';
                        if (response.tables && response.tables.length > 0) {
                            tableHtml = '<table class="table table-sm table-bordered mt-3" style="border-radius:8px;overflow:hidden"><thead style="background:#f7f9fc"><tr><th style="font-size:.82rem"><?php echo lang('Methods.deleteModuleTables') ?></th><th style="font-size:.82rem;text-align:center"><?php echo lang('Methods.totalRecords') ?></th></tr></thead><tbody>';
                            response.tables.forEach(function (t) {
                                tableHtml += '<tr><td><code style="color:#667eea">' + t.name + '</code></td><td style="text-align:center;font-weight:600">' + (t.count >= 0 ? t.count : '?') + '</td></tr>';
                            });
                            tableHtml += '</tbody></table>';
                        } else {
                            tableHtml = '<p class="text-muted mt-3"><i class="fas fa-info-circle mr-1"></i><?php echo lang('Methods.noTables') ?></p>';
                        }

                        Swal.fire({
                            title: '<?php echo lang('Methods.confirmDeleteTitle') ?>',
                            html: '<p class="text-danger font-weight-bold" style="font-size:1.1rem">' + response.module_name + '</p>' + tableHtml + '<p class="mt-3" style="font-size:.9rem"><?php echo lang('Methods.deleteModuleTypeName') ?></p>',
                            icon: 'warning',
                            input: 'text',
                            inputPlaceholder: '<?php echo lang('Methods.typeModuleName') ?>',
                            showCancelButton: true,
                            confirmButtonColor: '#e53e3e',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: '<i class="fas fa-trash-alt mr-1"></i> <?php echo lang('Methods.deleteModule') ?>',
                            cancelButtonText: '<?php echo lang('Backend.cancel') ?>',
                            inputValidator: (v) => {
                                if (!v || v !== response.module_name) return '<?php echo lang('Methods.deleteModuleNameMismatch') ?>';
                            }
                        }).then((cr) => {
                            if (!cr.isConfirmed) return;
                            Swal.fire({
                                title: '<?php echo lang('Methods.working') ?>',
                                allowOutsideClick: false,
                                didOpen: () => {
                                    Swal.showLoading();
                                }
                            });
                            $.ajax({
                                url: '<?php echo route_to('moduleDelete') ?>',
                                type: 'POST',
                                data: {
                                    module_id: moduleId,
                                    confirm_name: cr.value
                                },
                                dataType: 'json',
                                success: function (dr) {
                                    if (dr.status === 'success') {
                                        Swal.fire({
                                            icon: 'success',
                                            title: '<?php echo lang('Methods.success') ?>',
                                            text: dr.message
                                        }).then(() => {
                                            location.reload();
                                        });
                                    } else {
                                        Swal.fire('<?php echo lang('Backend.error') ?>', dr.message, 'error');
                                    }
                                },
                                error: function () {
                                    Swal.fire('<?php echo lang('Backend.error') ?>', '<?php echo lang('Methods.serverConnectionError') ?>', 'error');
                                }
                            });
                        });
                    },
                    error: function () {
                        Swal.fire('<?php echo lang('Backend.error') ?>', '<?php echo lang('Methods.serverConnectionError') ?>', 'error');
                    }
                });
            });
        });
    })();
</script>
<?php echo $this->endSection() ?>

