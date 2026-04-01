<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('title');
echo lang($title->pagename);
echo $this->endSection();
echo $this->section('head'); ?>
<style>
    /* Drag handle — default hidden, shown in edit mode */
    .drag-handle {
        position: absolute;
        top: 6px;
        left: 6px;
        z-index: 10;
        cursor: grab;
        padding: 4px 6px;
        border-radius: 4px;
        background: rgba(0, 0, 0, .15);
        color: #fff;
        font-size: 14px;
        display: none;
    }

    .drag-handle:active {
        cursor: grabbing;
    }

    /* Hide button — default hidden, shown in edit mode */
    .btn-widget-hide {
        position: absolute;
        top: 6px;
        right: 6px;
        display: none;
        z-index: 10;
        background: rgba(0, 0, 0, .15);
        color: #fff;
        border: none;
        border-radius: 4px;
        padding: 3px 7px;
        font-size: 12px;
        opacity: .7;
        transition: opacity .2s;
    }

    .btn-widget-hide:hover {
        opacity: 1;
        color: #fff;
    }

    /* Edit mode visual cues */
    .edit-mode .widget-box {
        outline: 2px dashed rgba(0, 123, 255, .4);
        outline-offset: -2px;
    }

    .edit-mode .widget-item {
        transition: transform .15s ease;
    }

    /* SortableJS ghost */
    .sortable-ghost {
        opacity: .4;
    }

    .sortable-chosen {
        box-shadow: 0 8px 25px rgba(0, 0, 0, .2);
    }

    /* Widget modal list */
    .widget-toggle-item {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 14px;
        border-bottom: 1px solid #eee;
        transition: background .15s;
    }

    .widget-toggle-item:hover {
        background: #f8f9fa;
    }

    .widget-toggle-item:last-child {
        border-bottom: none;
    }

    .widget-toggle-item .widget-info {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .widget-toggle-item .widget-info i {
        font-size: 18px;
        width: 24px;
        text-align: center;
    }
</style>
<?php echo $this->endSection();
echo $this->section('content'); ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><?php echo lang($title->pagename) ?></h1>
            </div>
            <div class="col-sm-6 text-right">
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-toggle-edit" title="Düzenleme Modu">
                        <i class="fas fa-grip-horizontal mr-1"></i> <?php echo lang('DashboardWidgets.editMode') ?? 'Düzenle' ?>
                    </button>
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-add-widget" title="Widget Ekle" style="display:none;">
                        <i class="fas fa-plus mr-1"></i> <?php echo lang('DashboardWidgets.addWidget') ?? 'Widget Ekle' ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Main content -->
<section class="content">
    <div class="row" id="dashboard-sortable">
        <?php if (!empty($widgets)):
            foreach ($widgets as $widget) : ?>
                <div class="<?php echo esc($widget->display_size) ?> widget-item mb-4" data-id="<?php echo $widget->id ?>" data-size="<?php echo esc($widget->display_size) ?>">
                    <?php if ($widget->type === 'stat'): ?>
                        <!-- Stat / Counter Widget -->
                        <div class="small-box bg-<?php echo esc($widget->color) ?> shadow h-100 mb-0 position-relative widget-box">
                            <div class="drag-handle">
                                <i class="fas fa-grip-vertical"></i>
                            </div>
                            <button class="btn btn-xs btn-widget-hide" data-id="<?php echo $widget->id ?>" title="Gizle">
                                <i class="fas fa-eye-slash"></i>
                            </button>
                            <div class="inner">
                                <h3><?php echo esc($widget->data['value'] ?? 0) ?></h3>
                                <p><?php echo esc($widget->title) ?></p>
                            </div>
                            <div class="icon">
                                <i class="<?php echo esc($widget->icon) ?>"></i>
                            </div>
                            <?php if (!empty($widget->url)): ?>
                                <a href="<?php echo route_to($widget->url) ?>" class="small-box-footer">
                                    <?php echo lang('Backend.more_info'); ?> <i class="fas fa-arrow-circle-right"></i>
                                </a>
                            <?php else: ?>
                                <div class="small-box-footer p-2"></div>
                            <?php endif; ?>
                        </div>

                    <?php elseif ($widget->type === 'table'): ?>
                        <!-- Table Widget -->
                        <div class="card card-<?php echo esc($widget->color) ?> shadow h-100 mb-0 widget-box position-relative">
                            <div class="drag-handle">
                                <i class="fas fa-grip-vertical"></i>
                            </div>
                            <button class="btn btn-xs btn-widget-hide" data-id="<?php echo $widget->id ?>" title="Gizle">
                                <i class="fas fa-eye-slash"></i>
                            </button>
                            <div class="card-header border-0">
                                <h3 class="card-title">
                                    <i class="<?php echo esc($widget->icon) ?> mr-1"></i>
                                    <?php echo esc($widget->title) ?>
                                </h3>
                            </div>
                            <div class="card-body table-responsive p-0">
                                <table class="table table-striped table-valign-middle mb-0">
                                    <tbody>
                                        <?php if (!empty($widget->data['rows'])):
                                            foreach ($widget->data['rows'] as $row): ?>
                                                <tr>
                                                    <?php foreach ((array)$row as $val): ?>
                                                        <td><?php echo esc($val) ?></td>
                                                    <?php endforeach; ?>
                                                </tr>
                                            <?php endforeach;
                                        else: ?>
                                            <tr>
                                                <td class="text-center text-muted p-3">No data</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    <?php elseif ($widget->type === 'html'): ?>
                        <!-- HTML Widget -->
                        <div class="card card-<?php echo esc($widget->color) ?> shadow h-100 mb-0 widget-box position-relative">
                            <div class="drag-handle">
                                <i class="fas fa-grip-vertical"></i>
                            </div>
                            <button class="btn btn-xs btn-widget-hide" data-id="<?php echo $widget->id ?>" title="Gizle">
                                <i class="fas fa-eye-slash"></i>
                            </button>
                            <div class="card-header border-0">
                                <h3 class="card-title">
                                    <i class="<?php echo esc($widget->icon) ?> mr-1"></i>
                                    <?php echo esc($widget->title) ?>
                                </h3>
                            </div>
                            <div class="card-body">
                                <?php echo $widget->data['value'] ?? '' ?>
                            </div>
                        </div>

                    <?php elseif ($widget->type === 'chart'): ?>
                        <!-- Chart Widget -->
                        <div class="card card-<?php echo esc($widget->color) ?> shadow h-100 mb-0 widget-box position-relative">
                            <div class="drag-handle">
                                <i class="fas fa-grip-vertical"></i>
                            </div>
                            <button class="btn btn-xs btn-widget-hide" data-id="<?php echo $widget->id ?>" title="Gizle">
                                <i class="fas fa-eye-slash"></i>
                            </button>
                            <div class="card-header border-0">
                                <h3 class="card-title">
                                    <i class="<?php echo esc($widget->icon) ?> mr-1"></i>
                                    <?php echo esc($widget->title) ?>
                                </h3>
                            </div>
                            <div class="card-body">
                                <canvas id="chart_<?php echo $widget->id ?>" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach;
        else: ?>
            <div class="col-12 text-center text-muted py-5">
                <i class="fas fa-cubes fa-3x mb-3 text-light"></i>
                <h5>No Widgets Configured</h5>
            </div>
        <?php endif; ?>
    </div>
</section>
<!-- /.content -->

<!-- Widget Ekleme Modal -->
<div class="modal fade" id="widgetModal" tabindex="-1" role="dialog" aria-labelledby="widgetModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header bg-primary">
                <h5 class="modal-title" id="widgetModalLabel">
                    <i class="fas fa-th-large mr-2"></i> Widget Yönetimi
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Kapat">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body" id="widgetModalBody">
                <div class="text-center py-4">
                    <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<?php echo $this->endSection();

echo $this->section('javascript');
echo script_tag('be-assets/plugins/sortable/sortable.min.js'); ?>
<script>
    $(function() {
        var $container = $('#dashboard-sortable');
        var $btnEdit = $('#btn-toggle-edit');
        var $btnAdd = $('#btn-add-widget');
        var editMode = false;
        var sortable = null;
        var csrfName = '<?php echo csrf_token() ?>';
        var csrfHash = '<?php echo csrf_hash() ?>';

        if (!$container.length || !$btnEdit.length) return;

        // ── Toggle Edit Mode ──
        $btnEdit.on('click', function() {
            editMode = !editMode;
            $container.closest('section.content').toggleClass('edit-mode', editMode);
            $('.drag-handle, .btn-widget-hide').css('display', editMode ? 'block' : 'none');
            $btnAdd.toggle(editMode);

            if (editMode) {
                $btnEdit.removeClass('btn-outline-secondary').addClass('btn-secondary')
                    .html('<i class="fas fa-check mr-1"></i> Bitti');
                initSortable();
            } else {
                $btnEdit.addClass('btn-outline-secondary').removeClass('btn-secondary')
                    .html('<i class="fas fa-grip-horizontal mr-1"></i> Düzenle');
                if (sortable) {
                    sortable.destroy();
                    sortable = null;
                }
                saveCurrentLayout();
            }
        });

        // ── SortableJS Init ──
        function initSortable() {
            if (sortable) sortable.destroy();
            sortable = new Sortable($container[0], {
                animation: 150,
                handle: '.drag-handle',
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                draggable: '.widget-item',
                forceFallback: true,
                fallbackOnBody: true,
                swapThreshold: 0.65,
                onEnd: function() {}
            });
        }

        // ── Save Layout (jQuery AJAX — form-data) ──
        function saveCurrentLayout() {
            var layout = [];
            $container.find('.widget-item').each(function(index) {
                var $el = $(this);
                layout.push({
                    widget_id: $el.data('id'),
                    position: index,
                    size: $el.data('size') || ($el.attr('class').match(/col-lg-\d+/) || ['col-lg-3'])[0]
                });
            });

            if (!layout.length) return;

            var postData = {};
            postData[csrfName] = csrfHash;
            postData['layout'] = JSON.stringify(layout);

            $.ajax({
                url: '<?php echo route_to("dashboardWidgetSaveLayout") ?>',
                type: 'POST',
                data: postData,
                dataType: 'json',
                success: function(res) {
                    if (res[csrfName]) csrfHash = res[csrfName];
                    if (res.status === 'success') {
                        toastr && toastr.success(res.message || 'Layout kaydedildi');
                    }
                },
                error: function(xhr) {
                    console.error('Layout kaydetme hatası:', xhr.responseText);
                }
            });
        }

        // ── Widget Gizle (inline — edit mode, eye-slash buton) ──
        $(document).on('click', '.btn-widget-hide', function() {
            var widgetId = $(this).data('id');
            var $widgetEl = $(this).closest('.widget-item');
            var postData = {};
            postData[csrfName] = csrfHash;

            $.ajax({
                url: '/backend/dashboard-widgets/toggle-visibility/' + widgetId,
                type: 'POST',
                data: postData,
                dataType: 'json',
                success: function(res) {
                    if (res[csrfName]) csrfHash = res[csrfName];
                    if (res.status === 'success') {
                        $widgetEl.animate({
                            opacity: 0
                        }, 300, function() {
                            $(this).remove();
                        });
                        toastr && toastr.info(res.message || '<?= lang('Backend.widgetHidden') ?>');
                    }
                },
                error: function(xhr) {
                    console.error('Toggle hatası:', xhr.responseText);
                }
            });
        });

        // ── Widget Ekle Modal ──
        $btnAdd.on('click', function() {
            var $body = $('#widgetModalBody');
            $body.html('<div class="text-center py-4"><i class="fas fa-spinner fa-spin fa-2x text-muted"></i></div>');
            $('#widgetModal').modal('show');

            $.ajax({
                url: '<?php echo route_to("dashboardWidgetAvailable") ?>',
                type: 'GET',
                dataType: 'json',
                success: function(res) {
                    if (!res.widgets || !res.widgets.length) {
                        $body.html('<p class="text-center text-muted py-3"><?= lang('Backend.noWidgetsFound') ?></p>');
                        return;
                    }

                    var html = '<div class="list-group list-group-flush">';
                    $.each(res.widgets, function(i, w) {
                        // Use actual visibility state from DB (don't rely on DOM!)
                        var isVisible = parseInt(w.visible) === 1;
                        var badgeClass = isVisible ? 'badge-success' : 'badge-secondary';
                        var badgeText = isVisible ? 'Visible' : 'Hidden';
                        var btnClass = isVisible ? 'btn-outline-danger' : 'btn-outline-success';
                        var btnIcon = isVisible ? 'fa-eye-slash' : 'fa-eye';
                        var btnLabel = isVisible ? 'Hide' : 'Show';

                        html += '<div class="widget-toggle-item" data-widget-id="' + w.id + '">' +
                            '<div class="widget-info">' +
                            '<i class="' + w.icon + ' text-' + w.color + '"></i>' +
                            '<div><strong>' + w.title + '</strong>' +
                            '<small class="d-block text-muted">' + w.type + ' &middot; ' + w.default_size + '</small></div>' +
                            '</div>' +
                            '<div>' +
                            '<span class="badge ' + badgeClass + ' mr-2">' + badgeText + '</span>' +
                            '<button class="btn btn-sm ' + btnClass + ' btn-modal-toggle" data-id="' + w.id + '">' +
                            '<i class="fas ' + btnIcon + ' mr-1"></i>' + btnLabel +
                            '</button>' +
                            '</div>' +
                            '</div>';
                    });
                    html += '</div>';
                    $body.html(html);
                },
                error: function() {
                    $body.html('<p class="text-center text-danger py-3">Bir hata oluştu.</p>');
                }
            });
        });

        // ── Modal: Toggle widget visibility ──
        $(document).on('click', '.btn-modal-toggle', function() {
            var $btn = $(this);
            var widgetId = $btn.data('id');
            $btn.prop('disabled', true);

            var postData = {};
            postData[csrfName] = csrfHash;

            $.ajax({
                url: '/backend/dashboard-widgets/toggle-visibility/' + widgetId,
                type: 'POST',
                data: postData,
                dataType: 'json',
                success: function(res) {
                    if (res[csrfName]) csrfHash = res[csrfName];
                    if (res.status === 'success') {
                        toastr && toastr.success(res.message || 'Güncellendi');
                        setTimeout(function() {
                            location.reload();
                        }, 600);
                    }
                },
                error: function() {
                    $btn.prop('disabled', false);
                }
            });
        });
    });
</script>
<?php echo $this->endSection() ?>
