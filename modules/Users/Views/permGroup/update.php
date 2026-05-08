<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('title');
echo lang($title->pagename);
echo $this->endSection();
echo $this->section('head');
echo link_tag('be-assets/css/method-manager.css');
echo $this->endSection();
echo $this->section('content'); ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><?php echo lang($title->pagename) ?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <a href="<?php echo route_to('groupList', 1) ?>" class="btn btn-sm btn-outline-info"><?php echo lang('Backend.backToList') ?></a>
                </ol>
            </div>
        </div>
    </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">

    <!-- Default box -->
    <div class="card card-outline shadow-sm">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><?php echo lang($title->pagename) ?></h3>

            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>

        <div class="card-body">
            <form action="<?php echo route_to('group_update', $group_name->id) ?>" method="post">
                <?php echo csrf_field() ?>
                <div class="row">
                    <div class="col-md-6">
                        <label for=""><?php echo lang('Users.permGroupName') ?></label>
                        <input type="text" class="form-control" value="<?php echo old('groupName', esc($group_name->group)) ?>" name="groupName" required>
                    </div>
                    <div class="col-md-6">
                        <label for="">Seflink</label>
                        <input type="text" class="form-control" value="<?php echo old('seflink', esc($group_name->redirect)) ?>" name="seflink" required>
                    </div>
                    <div class="col-md-12 mt-2">
                        <label for=""><?php echo lang('Backend.content') ?></label>
                        <textarea name="description" cols="30" rows="5"
                            class="form-control" required><?php echo old('description', esc($group_name->description)) ?></textarea>
                    </div>
                </div>

                <!-- Modüller -->
                <div class="row mt-3">
                    <div class="col-12 mb-2">
                        <h5 class="font-weight-bold"><?php echo lang('Methods.modules') ?></h5>
                    </div>
                </div>

                <!-- Toolbar -->
                <div class="m-toolbar">
                    <div class="ml-auto d-flex gap-5">
                        <button type="button" class="btn btn-sm btn-outline-dark" id="expandAll" title="Tümünü Aç">
                            <i class="fas fa-expand-alt"></i>
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-dark" id="collapseAll" title="Tümünü Kapat">
                            <i class="fas fa-compress-alt"></i>
                        </button>
                    </div>
                </div>

                <!-- Arama Barı -->
                <div class="m-search-bar">
                    <div class="m-search-input-wrap">
                        <i class="fas fa-search"></i>
                        <input type="text" class="m-search-input" id="liveSearch"
                            placeholder="<?php echo lang('Backend.search') ?>... (modül, sayfa, handler)"
                            autocomplete="off">
                    </div>
                    <select class="m-search-select" id="moduleFilter">
                        <option value=""><?php echo lang('Methods.allModules') ?></option>
                        <?php foreach ($modules as $module): ?>
                            <option value="<?php echo $module->id ?>"><?php echo esc($module->name) ?></option>
                        <?php endforeach; ?>

                    </select>
                    <select class="m-search-select" id="statusFilter">
                        <option value=""><?php echo lang('Backend.status') ?></option>
                        <option value="active"><?php echo lang('Backend.active') ?></option>
                        <option value="inactive"><?php echo lang('Backend.passive') ?></option>
                    </select>
                    <span class="m-search-count" id="searchCount">
                        <?php echo count($modules); ?> <?php echo lang('Methods.modules') ?>

                    </span>
                </div>

                <div id="modulesContainer">
                    <?php foreach ($modules as $module):
                        $activePageCount = count($module->pages);

                        // Mevcut yetki durumunu hesapla
                        $grantedPages = 0;
                        foreach ($module->pages as $_p) {

                            if (!empty($perms)) {
                                foreach ($perms as $_perm) {
                                    if (isset($_perm['page_id']) && $_perm['page_id'] == $_p->id
                                        && ($_perm['create_r'] || $_perm['read_r'] || $_perm['update_r'] || $_perm['delete_r'])) {
                                        $grantedPages++;
                                        break;
                                    }
                                }
                            }
                        }

                        $moduleFullyGranted = $activePageCount > 0 && $grantedPages === $activePageCount;
                        $modulePartial      = $grantedPages > 0 && $grantedPages < $activePageCount;
                        $moduleGranted      = $grantedPages > 0;

                        // data-status: en az bir yetki varsa active, yoksa inactive
                        $moduleStatus = $moduleGranted ? 'active' : 'inactive';
                    ?>
                        <div class="m-module-card module-card <?php echo !$moduleGranted ? 'inactive' : '' ?>"
                            data-module-id="<?php echo $module->id ?>"
                            data-module-name="<?php echo htmlspecialchars($module->name) ?>"
                            data-status="<?php echo $moduleStatus ?>">

                            <!-- Header (tıklanabilir) -->
                            <div class="m-module-header" data-toggle-card>
                                <div class="m-module-info">
                                    <div class="m-module-icon"><i class="<?php echo $module->icon ?>"></i></div>
                                    <div style="min-width:0">
                                        <h5 class="m-module-name"><?php echo htmlspecialchars($module->name) ?></h5>
                                        <div class="m-module-meta">
                                            <span class="m-mtag m-mtag-date">
                                                <i class="far fa-calendar-alt mr-1"></i><?php echo date('d.m.Y', strtotime($module->created)) ?>
                                            </span>
                                            <span class="m-mtag m-mtag-count">
                                                <i class="fas fa-layer-group mr-1"></i><?php echo lang('Methods.methodCount', [$activePageCount]) ?>
                                            </span>
                                            <?php if ($grantedPages > 0): ?>
                                                <span class="m-mtag m-mtag-active"><?php echo $grantedPages ?> <?php echo lang('Backend.active') ?></span>
                                            <?php endif; ?>
                                            <?php if ($activePageCount - $grantedPages > 0): ?>
                                                <span class="m-mtag m-mtag-inactive"><?php echo $activePageCount - $grantedPages ?> <?php echo lang('Backend.passive') ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Module Toggle -->
                                <div class="m-module-actions" data-stop-propagation>
                                    <div class="m-toggle-group">
                                        <label class="toggle-switch m-0">
                                            <input type="checkbox"
                                                class="module-toggle-input"
                                                <?php echo $moduleGranted ? 'checked' : '' ?>
                                                data-partial="<?php echo $modulePartial ? '1' : '0' ?>">
                                            <span class="toggle-slider"></span>
                                        </label>
                                    </div>
                                </div>

                                <div class="m-chevron"><i class="fas fa-chevron-down"></i></div>
                            </div>

                            <!-- Collapsible Body -->
                            <div class="m-pages-body">
                                <table class="m-pages-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo lang('Methods.pageName') ?></th>
                                            <th class="col-handler">Handler</th>
                                            <th>SEF Link</th>
                                            <th style="text-align:center"><?php echo lang('Backend.status') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($module->pages as $page):

                                            // Sayfanın mevcut yetki durumu
                                            $isChecked = false;
                                            if (!empty($perms)) {
                                                foreach ($perms as $p) {
                                                    if (isset($p['page_id']) && $p['page_id'] == $page->id
                                                        && ($p['create_r'] || $p['read_r'] || $p['update_r'] || $p['delete_r'])) {
                                                        $isChecked = true;
                                                        break;
                                                    }
                                                }
                                            }
                                        ?>
                                            <tr class="page-item <?php echo !$isChecked ? 'row-inactive' : '' ?>"
                                                data-page-id="<?php echo $page->id ?>"
                                                data-status="<?php echo $isChecked ? 'active' : 'inactive' ?>"
                                                data-content="<?php echo esc($page->description ?? '') ?>">
                                                <td>
                                                    <span class="m-page-name-cell">
                                                        <?php echo esc($page->pagename) ?>
                                                        <?php if ($page->inNavigation): ?><span class="badge bg-info"><?php echo lang('Methods.navigation') ?></span><?php endif; ?>
                                                        <?php if ($page->hasChild): ?><span class="badge bg-warning text-dark"><?php echo lang('Methods.hasChildPages') ?></span><?php endif; ?>
                                                    </span>
                                                    <?php if (!empty($page->description)): ?>
                                                        <div class="text-muted" style="font-size:.78rem;margin-top:1px">
                                                            <?php echo esc($page->description) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="col-handler">
                                                    <span class="m-copy-code"
                                                        data-copy="<?php echo esc(str_replace('-', '\\', $page->className)) ?>::<?php echo esc($page->methodName) ?>"
                                                        title="<?php echo lang('Methods.clickToCopy') ?>">
                                                        <span class="m-copy-text"><?php echo esc(str_replace('-', '\\', $page->className)) ?>::<?php echo esc($page->methodName) ?></span>
                                                        <i class="far fa-copy m-copy-icon"></i>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="m-sef-link m-copy-code"
                                                        data-copy="<?php echo esc($page->sefLink) ?>"
                                                        title="<?php echo lang('Methods.clickToCopy') ?>"
                                                        style="color:#718096;background:rgba(113,128,150,.08)">
                                                        <i class="fas fa-link" style="font-size:.65rem"></i>
                                                        <span class="m-copy-text"><?php echo esc($page->sefLink) ?></span>
                                                        <i class="far fa-copy m-copy-icon"></i>
                                                    </span>
                                                </td>
                                                <td style="text-align:center">
                                                    <span class="m-status-pill <?php echo $isChecked ? 'm-status-active' : 'm-status-inactive' ?> status-badge">
                                                        <?php echo $isChecked ? lang('Backend.active') : lang('Backend.passive') ?>
                                                    </span>
                                                    <label class="toggle-switch page-toggle">
                                                        <input type="checkbox"
                                                            name="perms[<?php echo $page->id ?>][roles]"
                                                            <?php echo $isChecked ? 'checked' : '' ?>
                                                            value="<?php echo $page->typeOfPermissions ?>">
                                                        <span class="toggle-slider"></span>
                                                    </label>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <div id="noResultsMessage" style="display:none;text-align:center;padding:2rem;color:#a0aec0">
                        <i class="fas fa-search" style="font-size:2rem;display:block;margin-bottom:.5rem"></i>
                        <?php echo lang('Backend.noResults') ?? 'Sonuç bulunamadı' ?>
                    </div>
                </div>

                <div class="col-md-12 mt-3">
                    <button class="btn btn-success float-right"><?php echo lang('Backend.update') ?></button>
                </div>
            </form>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->
</section>
<!-- /.content -->
<?php echo $this->endSection();
echo $this->section('javascript') ?>
<script type="text/javascript" <?php echo csp_script_nonce(); ?>>
    (function () {
        'use strict';

        // ═══════════════════════════════════════════════════════════
        // Accordion Toggle
        // ═══════════════════════════════════════════════════════════
        $(document).on('click', '[data-toggle-card]', function (e) {
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
        // Sayfa yüklenince indeterminate durumlarını uygula
        // ═══════════════════════════════════════════════════════════
        $('.module-card').each(function () {
            var toggleEl = $(this).find('.module-toggle-input')[0];
            if (toggleEl && $(toggleEl).data('partial') == '1') {
                toggleEl.indeterminate = true;
            }
        });

        // ═══════════════════════════════════════════════════════════
        // Click-to-Copy
        // ═══════════════════════════════════════════════════════════
        $(document).on('click', '.m-copy-code', function (e) {
            e.stopPropagation();
            var text = $(this).data('copy');
            var $el  = $(this);
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(function () {
                    $el.addClass('m-copied');
                    if (typeof showToast === 'function') showToast('<?php echo lang('Methods.copiedToClipboard') ?? 'Panoya kopyalandı' ?>');
                    setTimeout(function () { $el.removeClass('m-copied'); }, 1200);
                });
            } else {
                var ta = document.createElement('textarea');
                ta.value = text;
                document.body.appendChild(ta);
                ta.select();
                document.execCommand('copy');
                document.body.removeChild(ta);
                $el.addClass('m-copied');
                if (typeof showToast === 'function') showToast('<?php echo lang('Methods.copiedToClipboard') ?? 'Panoya kopyalandı' ?>');
                setTimeout(function () { $el.removeClass('m-copied'); }, 1200);
            }
        });

        // ═══════════════════════════════════════════════════════════
        // Live Search (debounced)
        // ═══════════════════════════════════════════════════════════
        var searchTimer = null;

        function runFilter() {
            var q            = ($('#liveSearch').val() || '').toLowerCase().trim();
            var modFilter    = $('#moduleFilter').val();
            var statusFilter = $('#statusFilter').val();
            var visibleCount = 0;

            $('.m-module-card').each(function () {
                var $card      = $(this);
                var moduleId   = $card.data('module-id');
                var moduleName = ($card.data('module-name') || '').toString().toLowerCase();
                var moduleStatus = $card.data('status');
                var show       = true;

                if (modFilter && moduleId != modFilter) show = false;
                if (statusFilter && moduleStatus !== statusFilter) show = false;

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
        // Modül Toggle Senkronizasyonu
        // ═══════════════════════════════════════════════════════════
        function syncModuleToggle(moduleCard) {
            var $card      = $(moduleCard);
            var pageInputs = $card.find('.page-toggle input[type="checkbox"]');
            var total      = pageInputs.length;
            var checkedCnt = pageInputs.filter(':checked').length;
            var toggleEl   = $card.find('.module-toggle-input')[0];

            if (total === 0 || !toggleEl) return;

            if (checkedCnt === 0) {
                toggleEl.checked       = false;
                toggleEl.indeterminate = false;
                $card.addClass('inactive').data('status', 'inactive').attr('data-status', 'inactive');
            } else if (checkedCnt === total) {
                toggleEl.checked       = true;
                toggleEl.indeterminate = false;
                $card.removeClass('inactive').data('status', 'active').attr('data-status', 'active');
            } else {
                toggleEl.checked       = true;
                toggleEl.indeterminate = true;
                $card.removeClass('inactive').data('status', 'active').attr('data-status', 'active');
            }
        }

        // ═══════════════════════════════════════════════════════════
        // Module Toggle: Tümü Aç / Kapat
        // ═══════════════════════════════════════════════════════════
        $('.module-toggle-input').on('change', function (e) {
            e.stopPropagation();
            this.indeterminate = false;
            var moduleCard = $(this).closest('.module-card');

            if (this.checked) {
                moduleCard.find('.page-toggle input').each(function () {
                    $(this).prop('checked', true);
                    var row = $(this).closest('.page-item');
                    row.removeClass('row-inactive');
                    row.find('.status-badge')
                        .text('<?php echo lang('Backend.active') ?>')
                        .attr('class', 'm-status-pill m-status-active status-badge');
                });
                moduleCard.removeClass('inactive').attr('data-status', 'active');
            } else {
                moduleCard.find('.page-toggle input').each(function () {
                    $(this).prop('checked', false);
                    var row = $(this).closest('.page-item');
                    row.addClass('row-inactive');
                    row.find('.status-badge')
                        .text('<?php echo lang('Backend.passive') ?>')
                        .attr('class', 'm-status-pill m-status-inactive status-badge');
                });
                moduleCard.addClass('inactive').attr('data-status', 'inactive');
            }
        });

        // ═══════════════════════════════════════════════════════════
        // Page Toggle
        // ═══════════════════════════════════════════════════════════
        $('.page-toggle input[type="checkbox"]').on('change', function () {
            var row   = $(this).closest('.page-item');
            var badge = row.find('.status-badge');
            if (this.checked) {
                row.removeClass('row-inactive');
                badge.text('<?php echo lang('Backend.active') ?>').attr('class', 'm-status-pill m-status-active status-badge');
            } else {
                row.addClass('row-inactive');
                badge.text('<?php echo lang('Backend.passive') ?>').attr('class', 'm-status-pill m-status-inactive status-badge');
            }
            syncModuleToggle($(this).closest('.module-card'));
        });
    })();
</script>
<?php $this->endSection(); ?>
