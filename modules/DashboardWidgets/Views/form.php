<?php echo $this->extend($backConfig->viewLayout) ?>
<?php echo $this->section('content') ?>

<?php
$isEdit = isset($widget);
$pageTitle = $isEdit ? lang('DashboardWidgets.editWidget') : lang('DashboardWidgets.createWidget');
$formAction = $isEdit
    ? site_url('backend/dashboard-widgets/update/' . $widget->id)
    : site_url('backend/dashboard-widgets/create');
?>

<div class="row justify-content-center">
    <div class="col-lg-8">
        <div class="card card-outline card-primary shadow-sm">
            <div class="card-header">
                <h3 class="card-title"><i class="fas fa-<?php echo $isEdit ? 'edit' : 'plus' ?> mr-2"></i><?php echo $pageTitle ?></h3>
                <div class="card-tools">
                    <a href="<?php echo site_url('backend/dashboard-widgets') ?>" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-arrow-left mr-1"></i><?php echo lang('Backend.backToList') ?>
                    </a>
                </div>
            </div>
            <form id="widgetForm" action="<?php echo $formAction ?>" method="post">
                <?php echo csrf_field() ?>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?php echo lang('DashboardWidgets.slug') ?> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="slug" value="<?php echo esc($isEdit ? $widget->slug : '') ?>"
                                    placeholder="<?php echo lang('DashboardWidgets.slugPlaceholder') ?>" <?php echo ($isEdit && $widget->is_system) ? 'readonly' : '' ?> required>
                                <div class="invalid-feedback" id="err-slug"></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label><?php echo lang('Backend.title') ?> <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="title" value="<?php echo esc($isEdit ? $widget->title : '') ?>"
                                    placeholder="<?php echo lang('DashboardWidgets.titlePlaceholder') ?>" required>
                                <div class="invalid-feedback" id="err-title"></div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label><?php echo lang('Backend.description') ?></label>
                        <input type="text" class="form-control" name="description" value="<?php echo esc($isEdit ? $widget->description : '') ?>"
                            placeholder="<?php echo lang('DashboardWidgets.descPlaceholder') ?>">
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?php echo lang('DashboardWidgets.type') ?></label>
                                <select class="form-control" name="type">
                                    <?php foreach (['stat', 'chart', 'table', 'list', 'html'] as $t): ?>
                                        <option value="<?php echo $t ?>" <?php echo ($isEdit && $widget->type === $t) ? 'selected' : '' ?>><?php echo lang('DashboardWidgets.type' . ucfirst($t)) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?php echo lang('DashboardWidgets.color') ?></label>
                                <select class="form-control" name="color">
                                    <?php foreach (['primary', 'success', 'info', 'warning', 'danger', 'secondary', 'dark'] as $c): ?>
                                        <option value="<?php echo $c ?>" <?php echo ($isEdit && $widget->color === $c) ? 'selected' : '' ?>><?php echo ucfirst($c) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?php echo lang('DashboardWidgets.defaultSize') ?></label>
                                <select class="form-control" name="default_size">
                                    <?php
                                    $sizes = ['col-lg-3' => lang('DashboardWidgets.sizeCol3'), 'col-lg-4' => lang('DashboardWidgets.sizeCol4'), 'col-lg-6' => lang('DashboardWidgets.sizeCol6'), 'col-lg-12' => lang('DashboardWidgets.sizeCol12')];
                                    foreach ($sizes as $v => $label):
                                    ?>
                                        <option value="<?php echo $v ?>" <?php echo ($isEdit && $widget->default_size === $v) ? 'selected' : '' ?>><?php echo $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?php echo lang('DashboardWidgets.icon') ?></label>
                                <input type="text" class="form-control" name="icon" value="<?php echo esc($isEdit ? $widget->icon : 'fas fa-chart-bar') ?>"
                                    placeholder="fas fa-chart-bar">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?php echo lang('DashboardWidgets.dataSource') ?></label>
                                <input type="text" class="form-control" name="data_source" value="<?php echo esc($isEdit ? $widget->data_source : '') ?>"
                                    placeholder="<?php echo lang('DashboardWidgets.sourcePlaceholder') ?>">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label><?php echo lang('DashboardWidgets.refreshSeconds') ?></label>
                                <input type="number" class="form-control" name="refresh_seconds" value="<?php echo esc($isEdit ? $widget->refresh_seconds : 0) ?>" min="0">
                            </div>
                        </div>
                    </div>

                    <div class="custom-control custom-switch">
                        <input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1"
                            <?php echo (!$isEdit || $widget->is_active) ? 'checked' : '' ?>>
                        <label class="custom-control-label" for="is_active"><?php echo lang('DashboardWidgets.isActive') ?></label>
                    </div>
                </div>
                <div class="card-footer text-right">
                    <a href="<?php echo site_url('backend/dashboard-widgets') ?>" class="btn btn-default mr-2"><?php echo lang('Backend.cancel') ?></a>
                    <button type="submit" class="btn btn-primary" id="btnSave">
                        <?php echo $isEdit ? lang('Backend.update') : lang('Backend.save') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php echo $this->endSection() ?>

<?php echo $this->section('javascript') ?>
<script type="text/javascript" {csp-script-nonce}>
    $(function() {
        $('#widgetForm').on('submit', function(e) {
            e.preventDefault();
            var form = $(this),
                btn = $('#btnSave');
            form.find('.is-invalid').removeClass('is-invalid');
            form.find('[id^="err-"]').text('');
            btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>...');

            $.ajax({
                url: form.attr('action'),
                type: 'POST',
                data: form.serialize(),
                dataType: 'json',
                success: function(r) {
                    if (r.status === 'success') {
                        Swal.fire({
                                title: r.message,
                                icon: 'success',
                                timer: 2000,
                                showConfirmButton: false
                            })
                            .then(function() {
                                window.location.href = '<?php echo site_url('backend/dashboard-widgets') ?>';
                            });
                    }
                },
                error: function(xhr) {
                    btn.prop('disabled', false).html('<?php echo $isEdit ? lang('Backend.update') : lang('Backend.save') ?>');
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        for (var f in xhr.responseJSON.errors) {
                            $('[name="' + f + '"]').addClass('is-invalid');
                            $('#err-' + f).text(xhr.responseJSON.errors[f]);
                        }
                    } else {
                        Swal.fire({
                            title: '<?php echo lang('Backend.saveFailed') ?>',
                            icon: 'error'
                        });
                    }
                }
            });
        });
    });
</script>
<?php echo $this->endSection() ?>
