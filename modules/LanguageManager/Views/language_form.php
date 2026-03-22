<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('head');
echo link_tag('be-assets/plugins/flag-icons/css/flag-icons.min.css');
echo $this->endSection();
echo $this->section('content');
$isEdit = isset($language);
$formAction = $isEdit ? site_url('backend/language-manager/languages/update/' . $language->id) : site_url('backend/language-manager/languages/create'); ?>
<section class="content pt-3">
    <div class="row justify-content-center">
        <div class="col-lg-7">
            <div class="card card-outline card-primary shadow-sm">
                <div class="card-header">
                    <h3 class="card-title"><i class="fas fa-<?php echo $isEdit ? 'edit' : 'plus' ?> mr-2"></i><?php echo $isEdit ? lang('LanguageManager.editLanguage') : lang('LanguageManager.createLanguage') ?></h3>
                    <div class="card-tools"><a href="<?php echo site_url('backend/language-manager/languages') ?>" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left mr-1"></i><?php echo lang('LanguageManager.backToList') ?></a></div>
                </div>
                <form id="langForm" action="<?php echo $formAction ?>" method="post"><?php echo csrf_field() ?>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="form-group"><label><?php echo lang('LanguageManager.code') ?> *</label><input type="text" class="form-control" name="code" value="<?php echo esc($isEdit ? $language->code : '') ?>" maxlength="10" placeholder="<?php echo lang('LanguageManager.codePlaceholder') ?>" required>
                                    <div class="invalid-feedback" id="err-code"></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group"><label><?php echo lang('LanguageManager.name') ?> *</label><input type="text" class="form-control" name="name" value="<?php echo esc($isEdit ? $language->name : '') ?>" placeholder="<?php echo lang('LanguageManager.namePlaceholder') ?>" required>
                                    <div class="invalid-feedback" id="err-name"></div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-group"><label><?php echo lang('LanguageManager.nativeName') ?></label><input type="text" class="form-control" name="native_name" value="<?php echo esc($isEdit ? $language->native_name : '') ?>"></div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-3">
                                <div class="form-group">
                                    <label><?php echo lang('LanguageManager.flag') ?></label>
                                    <div class="input-group">
                                        <div class="input-group-prepend">
                                            <span class="input-group-text" id="flag-preview"><i class="<?php echo $isEdit ? $language->flag : 'fas fa-flag' ?>"></i></span>
                                        </div>
                                        <input type="text" class="form-control" name="flag" id="flag_input" value="<?php echo esc($isEdit ? $language->flag : '') ?>" placeholder="fi fi-tr">
                                        <div class="input-group-append">
                                            <button type="button" class="btn btn-outline-primary" data-toggle="modal" data-target="#flagPickerModal">
                                                <i class="fas fa-search"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group"><label><?php echo lang('LanguageManager.direction') ?></label><select class="form-control" name="direction">
                                        <option value="ltr" <?php echo ($isEdit && $language->direction === 'ltr') ? 'selected' : '' ?>><?php echo lang('LanguageManager.ltr') ?></option>
                                        <option value="rtl" <?php echo ($isEdit && $language->direction === 'rtl') ? 'selected' : '' ?>><?php echo lang('LanguageManager.rtl') ?></option>
                                    </select></div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group"><label><?php echo lang('LanguageManager.sortOrder') ?></label><input type="number" class="form-control" name="sort_order" value="<?php echo esc($isEdit ? $language->sort_order : 0) ?>" min="0"></div>
                            </div>
                            <div class="col-md-3">
                                <div class="form-group"><label><?php echo lang('LanguageManager.status') ?></label>
                                    <div class="custom-control custom-switch mt-2"><input type="checkbox" class="custom-control-input" id="is_active" name="is_active" value="1" <?php echo (!$isEdit || $language->is_active) ? 'checked' : '' ?>><label class="custom-control-label" for="is_active"><?php echo lang('LanguageManager.active') ?></label></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer text-right"><a href="<?php echo site_url('backend/language-manager/languages') ?>" class="btn btn-default mr-2"><?php echo lang('LanguageManager.cancel') ?></a><button type="submit" class="btn btn-primary" id="btnSave"><i class="fas fa-save mr-1"></i><?php echo $isEdit ? lang('LanguageManager.update') : lang('LanguageManager.save') ?></button></div>
                </form>
            </div>
        </div>
    </div>
</section>

<!-- Bayrak Seçici Modal -->
<div class="modal fade" id="flagPickerModal" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Bayrak Seçin</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <input type="text" id="flagSearch" class="form-control" placeholder="Ülke adı veya koduyla arayın... (Örn: Turkey, TR)">
                </div>
                <hr>
                <div class="row text-center" id="flagListContainer" style="max-height: 400px; overflow-y: auto;">
                    <!-- Bayraklar buraya JS ile yüklenecek -->
                </div>
            </div>
        </div>
    </div>
</div>
<?php echo $this->endSection();
echo $this->section('javascript'); ?>
<script type="text/javascript" {csp-script-nonce}>
    $(function() {
        let flags = [];

        function loadFlags() {
            const cachedFlags = localStorage.getItem('ci4ms_flags');
            if (cachedFlags) {
                flags = JSON.parse(cachedFlags);
                renderFlags();
            }

            $.getJSON('<?php echo base_url('be-assets/plugins/flag-icons/countries.json') ?>', function(data) {
                flags = data;
                localStorage.setItem('ci4ms_flags', JSON.stringify(data));
                renderFlags();
            });
        }

        function renderFlags(filter = '') {
            let html = '';
            flags.forEach(f => {
                if (f.name.toLowerCase().includes(filter.toLowerCase()) || f.code.includes(filter.toLowerCase())) {
                    html += `<div class="col-3 col-md-2 mb-3 flag-item" style="cursor:pointer" data-code="fi fi-${f.code}">
                                <div class="p-2 border rounded hover-shadow">
                                    <span class="fi fi-${f.code}"></span><br>
                                    <small class="text-uppercase">${f.code}</small>
                                </div>
                             </div>`;
                }
            });
            $('#flagListContainer').html(html);
        }

        loadFlags();

        $('#flagSearch').on('keyup', function() {
            renderFlags($(this).val());
        });

        $(document).on('click', '.flag-item', function() {
            let code = $(this).data('code');
            $('#flag_input').val(code);
            $('#flag-preview i').attr('class', code);
            $('#flagPickerModal').modal('hide');
        });

        // Flag input alanına manuel veri girişi
        $('#flag_input').on('input blur', function() {
            var val = $(this).val().trim();
            if (val.indexOf('<') !== -1) {
                var match = val.match(/class=["']([^"']+)["']/);
                if (match && match[1]) {
                    val = match[1];
                    $(this).val(val);
                }
            }
            $('#flag-preview i').attr('class', val || 'fas fa-flag');
        });

        $('#langForm').on('submit', function(e) {
            e.preventDefault();
            var form = $(this),
                btn = $('#btnSave');
            form.find('.is-invalid').removeClass('is-invalid');
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
                        }).then(function() {
                            window.location.href = '<?php echo site_url('backend/language-manager/languages') ?>';
                        });
                    }
                },
                error: function(xhr) {
                    btn.prop('disabled', false).html('<i class="fas fa-save mr-1"></i><?php echo $isEdit ? lang('LanguageManager.update') : lang('LanguageManager.save') ?>');
                    if (xhr.status === 422 && xhr.responseJSON && xhr.responseJSON.errors) {
                        for (var f in xhr.responseJSON.errors) {
                            $('[name="' + f + '"]').addClass('is-invalid');
                            $('#err-' + f).text(xhr.responseJSON.errors[f]);
                        }
                    }
                }
            });
        });
    });
</script>
<?php echo $this->endSection() ?>
