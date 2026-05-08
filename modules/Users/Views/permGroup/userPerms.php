<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('title');
echo lang($title->pagename);
echo $this->endSection();
echo $this->section('head');
echo link_tag('be-assets/css/method-manager.css');
echo $this->endSection();
echo $this->section('content'); ?>
<section class="content pt-3">
    <div class="card card-outline shadow-sm">
        <div class="card-header">
            <h3 class="card-title font-weight-bold">
                <?php echo '<span class="text-light bg-success p-1 rounded">' . $userInfos->firstname . ' ' . $userInfos->surname . "</span> - " . lang('Users.permUpdate') ?>
            </h3>

            <div class="card-tools">
                <a href="<?php echo route_to('users', 1) ?>"
                    class="btn btn-sm btn-outline-info"><?php echo lang('Backend.backToList') ?></a>
            </div>
        </div>

        <div class="card-body">
            <form action="<?php echo route_to('user_perms', $userInfos->id) ?>" method="post">
                <?php echo csrf_field() ?>
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
                <div class="m-search-bar">
                    <div class="m-search-input-wrap">
                        <i class="fas fa-search"></i>
                        <input type="text" class="m-search-input" id="liveSearch"
                            placeholder="<?php echo lang('Backend.search') ?>... (modül, sayfa, handler)"
                            autocomplete="off">
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
                        // getActiveModules() sadece aktif modül+sayfaları döndürür;
                        // array_filter ile tekrar filtrelemeye gerek yok.

                        // Kullanıcıya özel yetki durumunu hesapla
                        $grantedPages = 0;
                        foreach ($module->pages as $_p) {
                            if (!empty($perms)) {
                                foreach ($perms as $_perm) {
                                    if (strpos($_perm, strtolower($_p->pagename) . '.') === 0) {
                                        $grantedPages++;
                                        break;
                                    }
                                }
                            }
                        }
                        $totalModulePages   = count($module->pages);
                        $moduleFullyGranted = $totalModulePages > 0 && $grantedPages === $totalModulePages;
                        $modulePartial      = $grantedPages > 0 && $grantedPages < $totalModulePages;
                        $moduleGranted      = $grantedPages > 0;
                        ?>
                        <div class="m-module-card module-card <?php echo !$moduleGranted ? 'inactive' : '' ?>"
                            data-module-id="<?php echo $module->id ?>"
                            data-module-name="<?php echo htmlspecialchars($module->name) ?>"
                            data-status="<?php echo $moduleGranted ? 'active' : 'inactive' ?>">

                            <!-- Header (clickable to toggle) -->
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
                                            <?php if ($grantedPages > 0): ?><span
                                                    class="m-mtag m-mtag-active"><?php echo $grantedPages ?>
                                                    <?php echo lang('Backend.active') ?></span><?php endif; ?>
                                            <?php if ($totalModulePages - $grantedPages > 0): ?><span
                                                    class="m-mtag m-mtag-inactive"><?php echo $totalModulePages - $grantedPages ?>
                                                    <?php echo lang('Backend.passive') ?></span><?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <!-- Actions zone (clicks stop here) -->
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
                                <?php if (empty($module->pages)): ?>
                                    <div class="m-empty-state"><i
                                            class="fas fa-inbox"></i><?php echo lang('Methods.noPagesFound') ?></div>
                                <?php else: ?>
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
                                                // Kullanıcının bu sayfa için yetkisi var mı? (ön-hesap)
                                                $isChecked = false;
                                                if (!empty($perms)) {
                                                    foreach ($perms as $_p) {
                                                        if (strpos($_p, strtolower($page->pagename) . '.') === 0) {
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
                                                            <?php if ($page->inNavigation): ?><span
                                                                    class="badge bg-info"><?php echo lang('Methods.navigation') ?></span><?php endif; ?>
                                                            <?php if ($page->hasChild): ?><span
                                                                    class="badge bg-warning text-dark"><?php echo lang('Methods.hasChildPages') ?></span><?php endif; ?>
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
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="col-md-12">
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
        // Modül Toggle Senkronizasyonu
        // Kural: 0 sayfa checked → pasif | kısmi → indeterminate | tamamı → aktif
        // ═══════════════════════════════════════════════════════════
        function syncModuleToggle(moduleCard) {
            var $card       = $(moduleCard);
            var pageInputs  = $card.find('.page-toggle input[type="checkbox"]');
            var total       = pageInputs.length;
            var checkedCnt  = pageInputs.filter(':checked').length;
            var toggleEl    = $card.find('.module-toggle-input')[0];

            if (total === 0) return;

            if (checkedCnt === 0) {
                // Hiç yetki yok → pasif
                toggleEl.checked       = false;
                toggleEl.indeterminate = false;
                $card.addClass('inactive').data('status', 'inactive').attr('data-status', 'inactive');
            } else if (checkedCnt === total) {
                // Tümü yetkili → aktif
                toggleEl.checked       = true;
                toggleEl.indeterminate = false;
                $card.removeClass('inactive').data('status', 'active').attr('data-status', 'active');
            } else {
                // Kısmi yetki → indeterminate (turuncu)
                toggleEl.checked       = true;
                toggleEl.indeterminate = true;
                $card.removeClass('inactive').data('status', 'active').attr('data-status', 'active');
            }
        }

        // Sayfa yüklenince indeterminate durumlarını uygula
        $('.module-card').each(function () {
            var toggleEl = $(this).find('.module-toggle-input')[0];
            if (toggleEl && $(toggleEl).data('partial') == '1') {
                toggleEl.indeterminate = true;
            }
        });

        // ═══════════════════════════════════════════════════════════
        // Module Toggle Tıklama: Tümü Aç / Tümü Kapat
        // ═══════════════════════════════════════════════════════════
        $('.module-toggle-input').on('change', function (e) {
            e.stopPropagation();
            var $this = $(this);
            var moduleCard = $this.closest('.module-card');

            // indeterminate durumu temizle — artık kullanıcı net seçim yaptı
            this.indeterminate = false;

            if (this.checked) {
                // Tüm sayfa toggle'larını aktif yap
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
                // Tüm sayfa toggle'larını pasif yap
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
            // Sayfa toggle değişti — üst modül toggle'ını senkronize et
            syncModuleToggle($(this).closest('.module-card'));
        });
    })();
</script>
<?php $this->endSection(); ?>

