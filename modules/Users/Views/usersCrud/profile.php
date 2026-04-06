<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('title');
echo lang($title->pagename);
echo $this->endSection();
echo $this->section('content'); ?>
<section class="content-header">
    <div class="container-fluid">
        <div class="row pb-3 border-bottom">
            <div class="col-sm-6">
                <h1><?php echo lang($title->pagename) ?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">

                </ol>
            </div>
        </div>
    </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">
    <div class="card card-outline shadow-sm">
        <div class="card-body">
            <form action="<?php echo route_to('profile') ?>" method="post" class="form-row" enctype="multipart/form-data">
                <?php echo csrf_field() ?>
                <div class="col-md-6">
                    <div class="text-center mb-3">
                        <div class="position-relative d-inline-block">
                            <img id="profileImagePreview"
                                src="<?php echo esc($user->profileIMG) ?>"
                                alt="Profil Resmi"
                                class="img-circle elevation-2"
                                style="width: 150px; height: 150px; object-fit: cover; border: 3px solid #dee2e6;">
                            <label for="avatarInput"
                                class="btn btn-sm btn-outline-secondary position-absolute"
                                style="bottom: 5px; right: 5px; border-radius: 50%; width: 36px; height: 36px; display: flex; align-items: center; justify-content: center; cursor: pointer; background: #fff;">
                                <i class="fas fa-camera"></i>
                            </label>
                        </div>
                        <input type="file" id="avatarInput" name="profileIMG" accept="image/jpeg,image/png,image/webp"
                            class="d-none">
                        <div class="mt-2">
                            <small class="text-muted">JPG, PNG veya WEBP — Maks. 2MB — 150x150px</small>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for=""><?php echo lang('Backend.fullName') ?> <?php echo lang('Backend.required') ?></label>
                        <div class="input-group">
                            <input type="text" aria-label="<?php echo lang('Backend.name') ?>" name="firstname" value="<?php echo old('firstname', esc($user->firstname)) ?>" class="form-control" placeholder="<?php echo lang('Backend.name') ?>"
                                required>
                            <input type="text" aria-label="<?php echo lang('Backend.surname') ?>" name="surname" value="<?php echo old('surname', esc($user->surname)) ?>" class="form-control"
                                placeholder="<?php echo lang('Backend.surname') ?>" required>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for=""><?php echo lang('Backend.email') ?> <?php echo lang('Backend.required') ?></label>
                        <input type="email" name="email" class="form-control" value="<?php echo old('email', esc($user->email)) ?>" required>
                        <small class="text-info"><?php echo lang('Backend.profileUpdateEmail') ?></small>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label for=""><?php echo lang('Auth.password') ?> <?php echo lang('Backend.takeNotePassword') ?></label>
                        <input type="text" class="form-control" name="password" minlength="8">
                    </div>
                </div>
                <div class="col-md-12">
                    <button class="btn btn-outline-success float-right"><?php echo lang('Backend.update') ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="card card-outline card-primary mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div>
                <h3 class="card-title font-weight-bold">
                    <i class="fas fa-shield-alt mr-2 text-primary"></i><?php echo lang('Users.activeSessions') ?>
                </h3>
                <br>
                <small class="text-muted">
                    <?php echo lang('Users.accountAccessDevices') ?>
                </small>
            </div>
            <div class="card-tools ml-auto">
                <?php if (count(array_filter($activeSessions, fn($s) => ! $s['is_current'])) > 0): ?>
                    <form method="POST" action="<?= route_to('terminateOtherSessions') ?>" class="m-0">
                        <?= csrf_field() ?>
                        <button type="submit"
                            class="btn btn-sm btn-outline-danger"
                            onclick="return confirm('<?php echo lang('Users.terminateOtherSessionsConfirm') ?>')">
                            <i class="fas fa-times-circle mr-1"></i> <?php echo lang('Users.terminateOthers') ?>
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-body p-0">
            <?php if (empty($activeSessions)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-inbox fa-3x d-block mb-3"></i>
                    <?php echo lang('Users.noActiveSessions') ?>
                </div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($activeSessions as $s): ?>
                        <li class="list-group-item px-4 py-3">
                            <div class="d-flex align-items-center">
                                <!-- Cihaz İkonu -->
                                <div class="text-center mr-4" style="width: 50px;">
                                    <i class="<?= device_icon($s['device_type']) ?> fa-2x text-secondary"></i>
                                </div>

                                <!-- Bilgiler -->
                                <div class="flex-grow-1 overflow-hidden">
                                    <div class="d-flex align-items-center mb-1">
                                        <span class="font-weight-bold text-truncate mr-2" style="font-size: 1.1rem;">
                                            <i class="<?= browser_icon($s['browser']) ?> mr-1 text-info"></i>
                                            <?= esc($s['browser']) ?>
                                            <?= $s['browser_version'] ? esc($s['browser_version']) : '' ?>
                                        </span>

                                        <?php if ($s['is_current']): ?>
                                            <span class="badge badge-success font-weight-normal px-2 py-1">
                                                <i class="fas fa-check-circle mr-1"></i><?php echo lang('Users.thisDevice') ?>
                                            </span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="text-muted small d-flex flex-wrap">
                                        <span class="mr-4 mb-1">
                                            <i class="fas fa-desktop mr-1"></i>
                                            <?= esc($s['os']) ?>
                                        </span>
                                        <span class="mr-4 mb-1">
                                            <i class="fas fa-map-marker-alt mr-1"></i>
                                            <?= esc($s['ip_address']) ?>
                                            <?php if ($s['city']): ?>
                                                — <?= esc($s['city']) ?><?= $s['country'] ? ', ' . esc($s['country']) : '' ?>
                                            <?php endif; ?>
                                        </span>
                                        <span class="mb-1 text-primary">
                                            <i class="fas fa-clock mr-1"></i>
                                            <?php echo lang('Users.lastActivity') ?>
                                            <?= date('d.m.Y H:i', strtotime($s['last_activity'])) ?>
                                        </span>
                                    </div>
                                </div>

                                <!-- Kapat Butonu -->
                                <?php if (! $s['is_current']): ?>
                                    <div class="ml-3">
                                        <form action="<?= route_to('terminateSession', urlencode($s['session_id'])) ?>" method="post" class="m-0">
                                            <?= csrf_field() ?>
                                            <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('<?php echo lang('Users.terminateConfirm') ?>')" title="<?php echo lang('Users.terminateSession') ?>">
                                                <i class="fas fa-sign-out-alt"></i>
                                            </button>
                                        </form>
                                    </div>
                                <?php else: ?>
                                    <div class="ml-3">
                                        <span class="text-success font-weight-bold small"><i class="fas fa-plug mr-1"></i><?php echo lang('Users.currentSession') ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>


    <?php
    $pastSessions = array_filter($allSessions, fn($s) => ! $s['is_active']);
    ?>

    <?php if (! empty($pastSessions)): ?>
        <div class="card card-outline card-secondary">
            <div class="card-header">
                <h3 class="card-title font-weight-bold text-muted">
                    <i class="fas fa-history mr-2"></i><?php echo lang('Users.sessionHistory') ?>
                </h3>
            </div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <?php foreach (array_slice($pastSessions, 0, 10) as $s): ?>
                        <li class="list-group-item px-4 py-3" style="opacity: 0.8;">
                            <div class="d-flex align-items-center">
                                <div class="text-center mr-4" style="width: 50px;">
                                    <i class="<?= device_icon($s['device_type']) ?> fa-2x text-muted"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="font-weight-bold text-dark mb-1">
                                        <i class="<?= browser_icon($s['browser']) ?> mr-1"></i>
                                        <?= esc($s['browser']) ?> — <?= esc($s['os']) ?>
                                    </div>
                                    <div class="text-muted small">
                                        <span class="mr-3"><i class="fas fa-map-marker-alt mr-1"></i><?= esc($s['ip_address']) ?></span>
                                        <span class="mr-3"><i class="fas fa-calendar-alt mr-1"></i><?php echo lang('Users.opened') ?>: <strong class="text-dark"><?= date('d.m.Y H:i', strtotime($s['created_at'])) ?></strong></span>
                                        <span><i class="fas fa-sign-out-alt mr-1"></i><?php echo lang('Users.closed') ?>: <strong class="text-dark"><?= $s['terminated_at'] ? date('d.m.Y H:i', strtotime($s['terminated_at'])) : '—' ?></strong></span>
                                    </div>
                                </div>
                                <div class="ml-3">
                                    <span class="badge badge-secondary px-2 py-1"><i class="fas fa-ban mr-1"></i><?php echo lang('Users.closedBadge') ?></span>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="card-footer text-center text-muted small">
                <?php echo lang('Users.last10PastSessions') ?>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php echo $this->endSection();
echo $this->section('javascript'); ?>
<script>
    document.getElementById('avatarInput').addEventListener('change', function(e) {
        const file = e.target.files[0];
        if (!file) return;
        if (file.size > 2 * 1024 * 1024) {
            alert("<?php echo lang('Users.avatarSizeExceeded') ?>");
            this.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = function(ev) {
            document.getElementById('profileImagePreview').src = ev.target.result;
        };
        reader.readAsDataURL(file);
    });
</script>
<?php echo $this->endSection() ?>
