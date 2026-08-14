<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('title');
echo lang('Notifications.preferences');
echo $this->endSection();
echo $this->section('content');

/**
 * @var bool                  $prefReady    Whether the preferences table has been migrated
 * @var array<string, string> $prefTypes    Type/type-prefix => lang key (whitelist)
 * @var list<string>          $prefChannels Selectable channel keys (whitelist)
 * @var list<string>          $prefMuted    Muted '{type}|{channel}' keys
 */
$isMuted = static fn (string $type, string $channel): bool => in_array($type . '|' . $channel, $prefMuted, true);
$channelLabel = static fn (string $channel): string => $channel === '*'
    ? lang('Notifications.prefChannelAll')
    : $channel;
?>
<section class="content pt-3">
    <div class="card border-0 shadow-sm" style="border-radius:12px">
        <div class="card-header bg-transparent border-0 d-flex align-items-center justify-content-between">
            <h6 class="mb-0 font-weight-bold">
                <i class="fas fa-sliders-h mr-2 text-primary"></i><?php echo lang('Notifications.preferences') ?>
            </h6>
            <a href="<?php echo route_to('notifications') ?>" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-bell mr-1"></i><?php echo lang('Notifications.title') ?>
            </a>
        </div>
        <div class="card-body pt-0">
            <p class="small text-muted"><?php echo lang('Notifications.prefIntro') ?></p>

            <?php if (empty($prefReady)): ?>
                <div class="alert alert-warning small mb-0"><?php echo lang('Notifications.prefTableMissing') ?></div>
            <?php elseif (empty($prefTypes)): ?>
                <p class="small text-muted mb-0"><?php echo lang('Notifications.prefNoTypes') ?></p>
            <?php else: ?>
                <form action="<?php echo route_to('notifPrefsSave') ?>" method="post">
                    <?php echo csrf_field() ?>
                    <div class="table-responsive">
                        <table class="table table-sm table-borderless mb-2">
                            <thead>
                                <tr>
                                    <th class="small text-muted"><?php echo lang('Notifications.prefType') ?></th>
                                    <?php foreach ($prefChannels as $channel): ?>
                                        <th class="small text-muted text-center"><?php echo esc($channelLabel($channel)) ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($prefTypes as $type => $langKey): ?>
                                    <tr>
                                        <td>
                                            <?php echo esc(lang($langKey)) ?>
                                            <div class="small text-muted"><?php echo esc($type) ?></div>
                                        </td>
                                        <?php foreach ($prefChannels as $channel): ?>
                                            <td class="text-center">
                                                <div class="custom-control custom-switch d-inline-block">
                                                    <input type="checkbox" class="custom-control-input"
                                                           id="mute-<?php echo esc($type . '-' . $channel, 'attr') ?>"
                                                           name="mute[<?php echo esc($type, 'attr') ?>][<?php echo esc($channel, 'attr') ?>]"
                                                           value="1" <?php echo $isMuted($type, $channel) ? 'checked' : '' ?>>
                                                    <label class="custom-control-label small"
                                                           for="mute-<?php echo esc($type . '-' . $channel, 'attr') ?>"><?php echo lang('Notifications.prefMuted') ?></label>
                                                </div>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <p class="small text-muted"><?php echo lang('Notifications.prefCriticalNote') ?></p>
                    <button type="submit" class="btn btn-sm btn-primary">
                        <i class="fas fa-save mr-1"></i><?php echo lang('Notifications.prefSave') ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</section>
<?php echo $this->endSection(); ?>
