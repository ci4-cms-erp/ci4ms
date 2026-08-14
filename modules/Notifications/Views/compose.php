<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('title');
echo lang('Notifications.compose');
echo $this->endSection();

// select2 assets are SPECIFIC to this view (not added to the global layout):
// only this page has remote-source multi-select, no point loading ~100 KB on
// every backend page. Paths are built with link_tag() (NOT a root-relative
// fixed path): on a site installed in a subdirectory, '/be-assets/...' would
// 404. This file's script section also uses script_tag() for the same reason.
echo $this->section('head');
echo link_tag('be-assets/plugins/select2/css/select2.min.css');
echo link_tag('be-assets/plugins/select2-bootstrap4-theme/select2-bootstrap4.min.css');
echo $this->endSection();
echo $this->section('content');

/**
 * @var array<string, string> $composeGroups     Group name => display title (whitelist)
 * @var array<string, string> $composeSeverities Severity slug => lang key
 * @var array<int, string>    $composeOldUsers   ID => label after a failed submission
 */
// Old input comes directly from the client: the array/scalar expectation is
// enforced here, otherwise a submission like `title[]=x` would carry an array
// into esc().
$oldScalars = static fn ($value): array => is_array($value)
    ? array_values(array_filter($value, 'is_scalar'))
    : [];
// old() applies esc($value, 'html') by default; this view ALREADY prints the
// value through esc(). The two together meant double escaping: after a failed
// submission, a 'Tom & Jerry' title would come back into the form as
// 'Tom &amp; Jerry' (not XSS, but data corruption). That's why it's read raw
// here, and escaping stays in the SINGLE place — where it's printed.
$oldText = static function (string $field): string {
    $value = old($field, '', false);

    return is_scalar($value) ? (string) $value : '';
};

$oldMode     = old('mode') === 'targeted' ? 'targeted' : 'broadcast';
$oldGroups   = array_map('strval', $oldScalars(old('groups')));
$oldUsers    = array_map('intval', $oldScalars(old('users')));
$oldExcluded = array_map('intval', $oldScalars(old('exclude_users')));

// Old selections for remote-source boxes: the label only comes from the
// server, NOT text sent by the client — otherwise free text could be written
// into the select box.
$oldUserOptions = static function (array $ids) use ($composeOldUsers): array {
    $options = [];
    foreach ($ids as $id) {
        if (isset($composeOldUsers[$id])) {
            $options[$id] = $composeOldUsers[$id];
        }
    }

    return $options;
};
?>
<section class="content pt-3">
    <div class="card border-0 shadow-sm" style="border-radius:12px">
        <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between">
            <h6 class="mb-0 font-weight-bold">
                <i class="fas fa-paper-plane mr-2 text-primary"></i><?php echo lang('Notifications.compose') ?>
            </h6>
            <a href="<?php echo route_to('notifications') ?>" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-bell mr-1"></i><?php echo lang('Notifications.title') ?>
            </a>
        </div>
        <div class="card-body pt-0">
            <p class="small text-muted"><?php echo lang('Notifications.composeIntro') ?></p>

            <form action="<?php echo route_to('notifComposeSend') ?>" method="post" id="compose-form">
                <?php echo csrf_field() ?>

                <div class="form-group">
                    <label for="compose-title"><?php echo lang('Notifications.composeFieldTitle') ?></label>
                    <input type="text" class="form-control" id="compose-title" name="title"
                           maxlength="255" required value="<?php echo esc($oldText('title'), 'attr') ?>">
                </div>

                <div class="form-group">
                    <label for="compose-body"><?php echo lang('Notifications.composeFieldBody') ?></label>
                    <textarea class="form-control" id="compose-body" name="body" rows="4"><?php echo esc($oldText('body')) ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label for="compose-url"><?php echo lang('Notifications.composeFieldUrl') ?></label>
                            <input type="text" class="form-control" id="compose-url" name="url"
                                   maxlength="255" value="<?php echo esc($oldText('url'), 'attr') ?>">
                            <small class="form-text text-muted"><?php echo lang('Notifications.composeUrlHelp') ?></small>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="form-group">
                            <label for="compose-severity"><?php echo lang('Notifications.composeFieldSeverity') ?></label>
                            <select class="form-control" id="compose-severity" name="severity">
                                <?php foreach ($composeSeverities as $level => $langKey): ?>
                                    <option value="<?php echo esc($level, 'attr') ?>" <?php echo set_select('severity', $level, $level === 'info') ?>>
                                        <?php echo esc(lang($langKey)) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label><?php echo lang('Notifications.composeFieldMode') ?></label>
                    <div>
                        <div class="custom-control custom-radio custom-control-inline">
                            <input type="radio" class="custom-control-input compose-mode" id="compose-mode-broadcast"
                                   name="mode" value="broadcast" <?php echo $oldMode === 'broadcast' ? 'checked' : '' ?>>
                            <label class="custom-control-label" for="compose-mode-broadcast"><?php echo lang('Notifications.composeModeBroadcast') ?></label>
                        </div>
                        <div class="custom-control custom-radio custom-control-inline">
                            <input type="radio" class="custom-control-input compose-mode" id="compose-mode-targeted"
                                   name="mode" value="targeted" <?php echo $oldMode === 'targeted' ? 'checked' : '' ?>>
                            <label class="custom-control-label" for="compose-mode-targeted"><?php echo lang('Notifications.composeModeTargeted') ?></label>
                        </div>
                    </div>
                </div>

                <div id="compose-targets" class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="compose-users"><?php echo lang('Notifications.composeFieldUsers') ?></label>
                            <select class="form-control compose-user-picker" id="compose-users" name="users[]" multiple
                                    data-placeholder="<?php echo esc(lang('Notifications.composeSelectUsers'), 'attr') ?>">
                                <?php foreach ($oldUserOptions($oldUsers) as $userId => $userLabel): ?>
                                    <option value="<?php echo (int) $userId ?>" selected><?php echo esc($userLabel) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="compose-groups"><?php echo lang('Notifications.composeFieldGroups') ?></label>
                            <?php if (empty($composeGroups)): ?>
                                <p class="small text-muted mb-0"><?php echo lang('Notifications.composeNoGroups') ?></p>
                            <?php else: ?>
                                <select class="form-control compose-select" id="compose-groups" name="groups[]" multiple
                                        data-placeholder="<?php echo esc(lang('Notifications.composeSelectGroups'), 'attr') ?>">
                                    <?php foreach ($composeGroups as $groupName => $groupTitle): ?>
                                        <option value="<?php echo esc($groupName, 'attr') ?>" <?php echo in_array((string) $groupName, $oldGroups, true) ? 'selected' : '' ?>>
                                            <?php echo esc($groupTitle) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="compose-exclude"><?php echo lang('Notifications.composeFieldExclude') ?></label>
                    <select class="form-control compose-user-picker" id="compose-exclude" name="exclude_users[]" multiple
                            data-placeholder="<?php echo esc(lang('Notifications.composeSelectUsers'), 'attr') ?>">
                        <?php foreach ($oldUserOptions($oldExcluded) as $userId => $userLabel): ?>
                            <option value="<?php echo (int) $userId ?>" selected><?php echo esc($userLabel) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small class="form-text text-muted"><?php echo lang('Notifications.composeExcludeHelp') ?></small>
                </div>

                <div class="d-flex align-items-center justify-content-between mt-4">
                    <div>
                        <button type="button" id="compose-preview" class="btn btn-sm btn-outline-secondary" style="border-radius:10px">
                            <i class="fas fa-users mr-1"></i><?php echo lang('Notifications.composePreview') ?>
                        </button>
                        <span id="compose-recipients" class="small text-muted ml-2"></span>
                    </div>
                    <button type="submit" class="btn btn-success px-5" style="border-radius:10px">
                        <i class="fas fa-paper-plane mr-1"></i><?php echo lang('Notifications.composeSubmit') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>
</section>
<?php echo $this->endSection();
echo $this->section('javascript');
echo script_tag("be-assets/plugins/select2/js/select2.full.min.js"); ?>
<script type="text/javascript" <?php echo csp_script_nonce(); ?>>
    // CSRF: the token is carried in the body (CI4MS_CSRF is defined in be-assets/js/ci4ms.js and
    // is loaded by the layout BEFORE this section). The global $csrfExcept is left untouched.
    var COMPOSE_PREVIEW_URL = '<?php echo route_to('notifComposePreview') ?>';
    var COMPOSE_USERS_URL = '<?php echo route_to('notifComposeUsers') ?>';
    var COMPOSE_RECIPIENTS_TPL = '<?php echo esc(lang('Notifications.composeRecipients'), 'js') ?>';
    var COMPOSE_PREVIEW_FAILED = '<?php echo esc(lang('Notifications.composeRecipientsFailed'), 'js') ?>';

    $('.compose-select').select2({ theme: 'bootstrap4', width: '100%' });

    $('.compose-user-picker').select2({
        theme: 'bootstrap4',
        width: '100%',
        minimumInputLength: 0,
        ajax: {
            url: COMPOSE_USERS_URL,
            dataType: 'json',
            delay: 250,
            data: function (params) {
                return { q: params.term || '' };
            },
            processResults: function (data) {
                return { results: (data && data.results) || [] };
            },
            cache: true
        }
    });

    // UX only: the target boxes are disabled while broadcast is selected. The server makes its
    // own decision (send() reads the mode itself), so bypassing this lock changes nothing.
    function composeSyncMode() {
        var broadcast = $('#compose-mode-broadcast').is(':checked');
        $('#compose-targets').toggle(!broadcast);
        $('#compose-users, #compose-groups').prop('disabled', broadcast).trigger('change.select2');
    }

    $('.compose-mode').on('change', composeSyncMode);
    composeSyncMode();

    $('#compose-preview').on('click', function () {
        var payload = {
            mode: $('input.compose-mode:checked').val() || 'broadcast',
            users: $('#compose-users').val() || [],
            groups: $('#compose-groups').val() || [],
            exclude_users: $('#compose-exclude').val() || []
        };
        payload[CI4MS_CSRF.name] = CI4MS_CSRF.getHash();

        $.ajax({
            url: COMPOSE_PREVIEW_URL,
            type: 'POST',
            data: payload,
            dataType: 'json'
        }).done(function (data) {
            $('#compose-recipients').text(COMPOSE_RECIPIENTS_TPL.replace('{0}', data.count));
        }).fail(function () {
            showToast(COMPOSE_PREVIEW_FAILED, 'error');
        });
    });
</script>
<?php echo $this->endSection(); ?>
