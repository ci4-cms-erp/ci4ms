<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('content'); ?>
<!-- Main content -->
<section class="content pt-3">
    <div class="card card-outline card-primary shadow-sm">
        <div class="card-header">
            <h3 class="card-title"><i class="fas fa-language mr-2"></i><?php echo lang('LanguageManager.translations') ?></h3>
            <div class="card-tools">
                <a href="<?php echo site_url('backend/language-manager/languages') ?>" class="btn btn-sm btn-outline-secondary mr-1"><i class="fas fa-globe mr-1"></i><?php echo lang('LanguageManager.languages') ?></a>
                <?php foreach ($languages as $like): ?>
                    <a href="<?php echo site_url('backend/language-manager/translations/export/' . $like->code) ?>" class="btn btn-sm btn-outline-success mr-1" title="<?php echo lang('LanguageManager.exportJson') ?> (<?php echo esc($like->code) ?>)"><i class="fas fa-download mr-1"></i><?php echo esc($like->code) ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="card-body">
            <!-- Filters -->
            <form class="row align-items-end mb-3" method="get" action="<?php echo site_url('backend/language-manager/translations') ?>">
                <div class="col-md-3">
                    <label class="text-sm"><?php echo lang('LanguageManager.group') ?></label>
                    <select class="form-control form-control-sm" name="group">
                        <option value=""><?php echo lang('LanguageManager.allGroups') ?></option>
                        <?php foreach ($groups as $g): ?>
                            <option value="<?php echo esc($g->group_name) ?>" <?php echo $currentGroup === $g->group_name ? 'selected' : '' ?>><?php echo esc($g->group_name) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="text-sm"><?php echo lang('LanguageManager.key') ?></label>
                    <input type="text" class="form-control form-control-sm" name="search" value="<?php echo esc($search) ?>" placeholder="<?php echo lang('LanguageManager.searchPlaceholder') ?>">
                </div>
                <div class="col-md-2">
                    <button type="submit" class="btn btn-sm btn-primary btn-block"><i class="fas fa-search mr-1"></i>Ara</button>
                </div>
            </form>

            <!-- Add Key -->
            <?php if (!empty($currentGroup)): ?>
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" id="newKeyName" placeholder="<?php echo lang('LanguageManager.newKeyPlaceholder') ?>">
                            <div class="input-group-append">
                                <button class="btn btn-success" id="btnAddKey"><i class="fas fa-plus mr-1"></i><?php echo lang('LanguageManager.addKey') ?></button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif;
            if (!empty($result) && !empty($result['keys'])): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-sm table-striped">
                        <thead class="thead-dark">
                            <tr>
                                <th width="30%"><?php echo lang('LanguageManager.key') ?></th>
                                <?php foreach ($languages as $like): ?>
                                    <th><?php echo esc($like->flag ?? '') ?> <?php echo esc($like->code) ?></th>
                                <?php endforeach; ?>
                                <th width="40"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($result['keys'] as $key): ?>
                                <tr>
                                    <td><code class="text-dark"><?php echo esc($key->key_name) ?></code></td>
                                    <?php foreach ($languages as $like): ?>
                                        <td>
                                            <input type="text" class="form-control form-control-sm translation-input"
                                                data-key-id="<?php echo $key->id ?>"
                                                data-lang="<?php echo esc($like->code) ?>"
                                                value="<?php echo esc($result['translations'][$key->id][$like->code] ?? '') ?>"
                                                placeholder="—">
                                        </td>
                                    <?php endforeach; ?>
                                    <td><button class="btn btn-xs btn-outline-danger btn-delete-key" data-id="<?php echo $key->id ?>"><i class="fas fa-trash"></i></button></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination -->
                <?php if ($result['totalPages'] > 1): ?>
                    <nav>
                        <ul class="pagination pagination-sm justify-content-center">
                            <?php for ($i = 1; $i <= $result['totalPages']; $i++): ?>
                                <li class="page-item <?php echo $result['page'] == $i ? 'active' : '' ?>">
                                    <a class="page-link" href="<?php echo site_url('backend/language-manager/translations?group=' . urlencode($currentGroup) . '&search=' . urlencode($search) . '&page=' . $i) ?>"><?php echo $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif;
            elseif (!empty($currentGroup)): ?>
                <div class="text-center text-muted py-4"><i class="fas fa-inbox fa-3x mb-2 d-block"></i><?php echo lang('LanguageManager.noTranslations') ?></div>
            <?php else: ?>
                <div class="text-center text-muted py-4"><i class="fas fa-hand-pointer fa-3x mb-2 d-block"></i>Bir grup seçin</div>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php echo $this->endSection();
echo $this->section('javascript'); ?>
<script type="text/javascript" {csp-script-nonce}>
    $(function() {
        // Inline save on blur
        var saveTimer;
        $('.translation-input').on('input', function() {
            var el = $(this);
            clearTimeout(saveTimer);
            saveTimer = setTimeout(function() {
                $.post('<?php echo site_url('backend/language-manager/translations/save') ?>', {
                    '<?php echo csrf_token() ?>': '<?php echo csrf_hash() ?>',
                    key_id: el.data('key-id'),
                    language_code: el.data('lang'),
                    value: el.val()
                }, function(r) {
                    el.css('border-color', '#28a745');
                    setTimeout(function() {
                        el.css('border-color', '');
                    }, 1000);
                }, 'json');
            }, 600);
        });

        // Add key
        $('#btnAddKey').on('click', function() {
            var keyName = $('#newKeyName').val().trim();
            if (!keyName) return;
            $.post('<?php echo site_url('backend/language-manager/translations/add-key') ?>', {
                '<?php echo csrf_token() ?>': '<?php echo csrf_hash() ?>',
                group: '<?php echo esc($currentGroup) ?>',
                key_name: keyName
            }, function(r) {
                if (r.status === 'success') location.reload();
                else Swal.fire({
                    title: r.message,
                    icon: 'error'
                });
            }, 'json');
        });

        // Delete key
        $('.btn-delete-key').on('click', function() {
            var id = $(this).data('id');
            Swal.fire({
                title: '<?php echo lang('LanguageManager.deleteKeyConfirm') ?>',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#d33',
                confirmButtonText: '<?php echo lang('LanguageManager.delete') ?>',
                cancelButtonText: '<?php echo lang('LanguageManager.cancel') ?>'
            }).then(function(r) {
                if (r.isConfirmed) {
                    $.post('<?php echo site_url('backend/language-manager/translations/delete-key/') ?>' + id, {
                        '<?php echo csrf_token() ?>': '<?php echo csrf_hash() ?>'
                    }, function() {
                        location.reload();
                    }, 'json');
                }
            });
        });
    });
</script>
<?php echo $this->endSection() ?>
