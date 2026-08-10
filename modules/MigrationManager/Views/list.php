<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('title');
echo lang($title->pagename);
echo $this->endSection();
echo $this->section('head');
echo link_tag('be-assets/plugins/datatables-bs4/css/dataTables.bootstrap4.min.css');
echo link_tag('be-assets/plugins/datatables-responsive/css/responsive.bootstrap4.min.css');
echo $this->endSection();
echo $this->section('content'); ?>

<section class="content pt-3">

    <div id="migrationRegressedBanner" class="alert alert-danger d-none" role="alert"></div>

    <!-- ═══════════════════════════════════════════════════════════
         Migrations
         ═══════════════════════════════════════════════════════════ -->
    <div class="card premium-card mb-4">
        <div class="card-header d-flex align-items-center flex-wrap">
            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-database mr-2 text-info"></i> <?php echo esc(lang('MigrationManager.migrationManager')) ?></h3>
            <div class="ml-auto">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRunSelectedMigrations" style="border-radius:10px">
                    <i class="fas fa-play mr-1"></i> <?php echo esc(lang('MigrationManager.runSelectedMigrations')) ?>
                </button>
                <button type="button" class="btn btn-sm btn-success" id="btnRunAllMigrations" style="border-radius:10px">
                    <i class="fas fa-play-circle mr-1"></i> <?php echo esc(lang('MigrationManager.runAllMigrations')) ?>
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table id="migrationsTable" class="table table-hover w-100 mb-0">
                    <caption class="sr-only"><?php echo esc(lang('MigrationManager.migrationManager')) ?></caption>
                    <thead>
                        <tr>
                            <th scope="col" style="width:2.5rem">
                                <div class="custom-control custom-checkbox">
                                    <input type="checkbox" class="custom-control-input" id="selectAllMigrations"
                                           aria-label="<?php echo esc(lang('MigrationManager.selectAllMigrationsAria'), 'attr') ?>">
                                    <label class="custom-control-label" for="selectAllMigrations"></label>
                                </div>
                            </th>
                            <th scope="col"><?php echo esc(lang('MigrationManager.module')) ?></th>
                            <th scope="col" class="text-center"><?php echo esc(lang('MigrationManager.totalMigrations')) ?></th>
                            <th scope="col" class="text-center"><?php echo esc(lang('MigrationManager.appliedMigrations')) ?></th>
                            <th scope="col" class="text-center"><?php echo esc(lang('MigrationManager.lastBatch')) ?></th>
                            <th scope="col"><?php echo esc(lang('MigrationManager.lastRun')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($statusReport)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4"><?php echo esc(lang('Backend.emptyTable')) ?></td>
                            </tr>
                        <?php else: foreach ($statusReport as $i => $row):
                            $namespace = (string) $row['namespace'];
                            $readOnly  = (bool) $row['readOnly'];
                            $chkId     = 'ns-' . (int) $i;
                        ?>
                            <tr class="<?php echo $readOnly ? 'text-muted bg-light' : '' ?>">
                                <td>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox"
                                               class="custom-control-input migration-namespace-checkbox"
                                               id="<?php echo esc($chkId, 'attr') ?>"
                                               value="<?php echo esc($namespace, 'attr') ?>"
                                               aria-label="<?php echo esc(lang('MigrationManager.selectNamespaceAria', [$namespace]), 'attr') ?>"
                                               <?php echo $readOnly ? 'disabled' : '' ?>>
                                        <label class="custom-control-label" for="<?php echo esc($chkId, 'attr') ?>"></label>
                                    </div>
                                </td>
                                <td>
                                    <code><?php echo esc($namespace) ?></code>
                                    <?php if ($readOnly): ?>
                                        <div class="small text-muted"><?php echo esc(lang('MigrationManager.vendorNamespaceReadOnly')) ?></div>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center"><?php echo (int) $row['total_count'] ?></td>
                                <td class="text-center"><?php echo (int) $row['applied_count'] ?></td>
                                <td class="text-center"><?php echo $row['last_batch'] !== null ? (int) $row['last_batch'] : '—' ?></td>
                                <td><?php echo $row['last_date'] !== null ? esc((string) $row['last_date']) : '—' ?></td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="p-3 border-top">
                <h6 class="font-weight-bold small text-uppercase text-muted"><?php echo esc(lang('MigrationManager.executionLog')) ?></h6>
                <div id="migrationRunSummary" class="alert alert-info d-none py-2 small mb-2" role="status"></div>
                <div id="migrationRunLog" aria-live="polite" style="max-height:220px;overflow-y:auto"></div>
            </div>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
         Seeds
         ═══════════════════════════════════════════════════════════ -->
    <div class="card premium-card mb-4">
        <div class="card-header d-flex align-items-center flex-wrap">
            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-seedling mr-2 text-success"></i> <?php echo esc(lang('MigrationManager.seeder')) ?></h3>
            <?php if (!empty($seeders)): ?>
                <div class="ml-auto">
                    <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRunSelectedSeeds" style="border-radius:10px">
                        <i class="fas fa-play mr-1"></i> <?php echo esc(lang('MigrationManager.runSelectedSeeds')) ?>
                    </button>
                    <button type="button" class="btn btn-sm btn-success" id="btnRunAllSeeds" style="border-radius:10px">
                        <i class="fas fa-play-circle mr-1"></i> <?php echo esc(lang('MigrationManager.runAllSeeds')) ?>
                    </button>
                </div>
            <?php endif; ?>
        </div>
        <div class="card-body p-0">
            <?php if (empty($seeders)): ?>
                <div class="alert alert-warning m-3 mb-0"><?php echo esc(lang('MigrationManager.noWebRunnableSeeders')) ?></div>
            <?php else: ?>
                <div class="table-responsive">
                    <table id="seedersTable" class="table table-hover w-100 mb-0">
                        <caption class="sr-only"><?php echo esc(lang('MigrationManager.seeder')) ?></caption>
                        <thead>
                            <tr>
                                <th scope="col" style="width:2.5rem">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="selectAllSeeds"
                                               aria-label="<?php echo esc(lang('MigrationManager.selectAllSeedsAria'), 'attr') ?>">
                                        <label class="custom-control-label" for="selectAllSeeds"></label>
                                    </div>
                                </th>
                                <th scope="col"><?php echo esc(lang('MigrationManager.seeder')) ?></th>
                                <th scope="col"><?php echo esc(lang('MigrationManager.repeatable')) ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($seeders as $i => $seeder):
                                $seederClass = (string) $seeder['class'];
                                $label       = lang('MigrationManager.' . $seeder['label']);
                                $chkId       = 'seed-' . (int) $i;
                            ?>
                                <tr>
                                    <td>
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox"
                                                   class="custom-control-input seed-checkbox"
                                                   id="<?php echo esc($chkId, 'attr') ?>"
                                                   value="<?php echo esc($seederClass, 'attr') ?>"
                                                   aria-label="<?php echo esc(lang('MigrationManager.selectSeederAria', [$label]), 'attr') ?>">
                                            <label class="custom-control-label" for="<?php echo esc($chkId, 'attr') ?>"></label>
                                        </div>
                                    </td>
                                    <td>
                                        <?php echo esc($label) ?>
                                        <div class="small text-muted"><code><?php echo esc($seederClass) ?></code></div>
                                    </td>
                                    <td>
                                        <?php if (!empty($seeder['repeatable'])): ?>
                                            <span class="badge badge-info"><?php echo esc(lang('MigrationManager.repeatable')) ?></span>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="p-3 border-top">
                    <h6 class="font-weight-bold small text-uppercase text-muted"><?php echo esc(lang('MigrationManager.executionLog')) ?></h6>
                    <div id="seedRunSummary" class="alert alert-info d-none py-2 small mb-2" role="status"></div>
                    <div id="seedRunLog" aria-live="polite" style="max-height:220px;overflow-y:auto"></div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══════════════════════════════════════════════════════════
         Run History
         ═══════════════════════════════════════════════════════════ -->
    <div class="card premium-card">
        <div class="card-header d-flex align-items-center">
            <h3 class="card-title font-weight-bold mb-0"><i class="fas fa-history mr-2 text-secondary"></i> <?php echo esc(lang('MigrationManager.runHistory')) ?></h3>
            <div class="ml-auto">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="btnRefreshHistory" style="border-radius:10px" title="<?php echo esc(lang('Backend.refresh'), 'attr') ?>">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="p-3">
                <table id="historyTable" class="table table-hover w-100">
                    <caption class="sr-only"><?php echo esc(lang('MigrationManager.runHistory')) ?></caption>
                    <thead>
                        <tr>
                            <th scope="col"><?php echo esc(lang('MigrationManager.kind')) ?></th>
                            <th scope="col"><?php echo esc(lang('MigrationManager.target')) ?></th>
                            <th scope="col"><?php echo esc(lang('Backend.status')) ?></th>
                            <th scope="col" class="text-center"><?php echo esc(lang('MigrationManager.appliedMigrations')) ?></th>
                            <th scope="col" class="text-center"><?php echo esc(lang('MigrationManager.batch')) ?></th>
                            <th scope="col" class="text-center"><?php echo esc(lang('MigrationManager.durationMs')) ?></th>
                            <th scope="col"><?php echo esc(lang('MigrationManager.runBy')) ?></th>
                            <th scope="col"><?php echo esc(lang('Backend.ipAddress')) ?></th>
                            <th scope="col"><?php echo esc(lang('Backend.createdAt')) ?></th>
                            <th scope="col"><?php echo esc(lang('MigrationManager.message')) ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentRuns)): ?>
                            <tr>
                                <td colspan="10" class="text-center text-muted py-4"><?php echo esc(lang('Backend.emptyTable')) ?></td>
                            </tr>
                        <?php else: foreach ($recentRuns as $run):
                            $isSeed       = ($run->kind ?? '') === 'seed';
                            $isSuccess    = ($run->status ?? '') === 'success';
                            $kindLabel    = $isSeed ? lang('MigrationManager.kindSeedLabel') : lang('MigrationManager.kindMigrationLabel');
                            $statusLabel  = $isSuccess ? lang('MigrationManager.statusSuccessLabel') : lang('MigrationManager.statusFailedLabel');
                            $statusClass  = $isSuccess ? 'badge-success' : 'badge-danger';
                            $message      = (string) ($run->message ?? '');
                            $truncated    = mb_strlen($message) > 80 ? mb_substr($message, 0, 80) . '…' : $message;
                            $runSource    = $run->run_source ?? 'web';
                            $runByDisplay = $run->run_by_username
                                ?? ($runSource === 'cli' ? lang('MigrationManager.runSourceCli') : lang('MigrationManager.deletedUser'));
                            $isTargetAll  = ($run->target ?? '') === '*';
                        ?>
                            <tr>
                                <td><?php echo esc($kindLabel) ?></td>
                                <td>
                                    <?php if ($isTargetAll): ?>
                                        <span class="text-muted"><?php echo esc(lang('MigrationManager.targetAllNamespaces')) ?></span>
                                    <?php else: ?>
                                        <code><?php echo esc((string) ($run->target ?? '')) ?></code>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge <?php echo $statusClass ?>"><?php echo esc($statusLabel) ?></span></td>
                                <td class="text-center"><?php echo (int) ($run->applied_count ?? 0) ?></td>
                                <td class="text-center"><?php echo $run->batch !== null ? (int) $run->batch : '—' ?></td>
                                <td class="text-center"><?php echo (int) ($run->duration_ms ?? 0) ?></td>
                                <td><?php echo esc((string) $runByDisplay) ?></td>
                                <td><?php echo esc((string) ($run->ip ?? '—')) ?></td>
                                <td><?php echo esc((string) ($run->created_at ?? '')) ?></td>
                                <td>
                                    <span class="small text-monospace" title="<?php echo esc($message, 'attr') ?>"><?php echo esc($truncated) ?></span>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</section>

<?php echo $this->endSection();
echo $this->section('javascript');
echo script_tag('be-assets/plugins/datatables/jquery.dataTables.min.js');
echo script_tag('be-assets/plugins/datatables-bs4/js/dataTables.bootstrap4.min.js');
echo script_tag('be-assets/plugins/datatables-responsive/js/dataTables.responsive.min.js');
echo script_tag('be-assets/plugins/datatables-responsive/js/responsive.bootstrap4.min.js'); ?>
<script type="text/javascript" <?php echo csp_script_nonce(); ?>>
    (function () {
        var LANG = {
            noNamespacesSelected: <?php echo json_encode(lang('MigrationManager.noNamespacesSelected')) ?>,
            noSeedsSelected: <?php echo json_encode(lang('MigrationManager.noSeedsSelected')) ?>,
            confirmRunMigrationsTitle: <?php echo json_encode(lang('MigrationManager.confirmRunMigrationsTitle')) ?>,
            confirmRunSeedsTitle: <?php echo json_encode(lang('MigrationManager.confirmRunSeedsTitle')) ?>,
            runSummary: <?php echo json_encode(lang('MigrationManager.runSummary')) ?>,
            run: <?php echo json_encode(lang('MigrationManager.run')) ?>,
            cancel: <?php echo json_encode(lang('Backend.cancel')) ?>,
            genericError: <?php echo json_encode(lang('Backend.error')) ?>,
            kindSeedLabel: <?php echo json_encode(lang('MigrationManager.kindSeedLabel')) ?>,
            kindMigrationLabel: <?php echo json_encode(lang('MigrationManager.kindMigrationLabel')) ?>,
            statusSuccessLabel: <?php echo json_encode(lang('MigrationManager.statusSuccessLabel')) ?>,
            statusFailedLabel: <?php echo json_encode(lang('MigrationManager.statusFailedLabel')) ?>,
            deletedUserLabel: <?php echo json_encode(lang('MigrationManager.deletedUser')) ?>,
            runSourceCliLabel: <?php echo json_encode(lang('MigrationManager.runSourceCli')) ?>,
            targetAllNamespacesLabel: <?php echo json_encode(lang('MigrationManager.targetAllNamespaces')) ?>
        };

        var RUN_MIGRATION_URL = <?php echo json_encode(route_to('migrationManagerRunMigration')) ?>;
        var RUN_SEED_URL = <?php echo json_encode(route_to('migrationManagerRunSeed')) ?>;
        var HISTORY_URL = <?php echo json_encode(route_to('migrationManagerHistory')) ?>;

        function escapeHtml(value) {
            return $('<div>').text(value === null || value === undefined ? '' : String(value)).html();
        }

        function toggleSelectAll(masterSelector, itemSelector) {
            $(masterSelector).on('change', function () {
                $(itemSelector).not(':disabled').prop('checked', $(this).is(':checked'));
            });
        }

        function selectedValues(itemSelector) {
            var values = [];
            $(itemSelector + ':checked').each(function () {
                values.push($(this).val());
            });
            return values;
        }

        function appendLogEntry(logSelector, label, ok, message) {
            var icon = ok ? 'fa-check-circle text-success' : 'fa-exclamation-circle text-danger';
            var entry = $('<div/>', { 'class': 'small py-1 border-bottom' });
            entry.append($('<i/>', { 'class': 'fas ' + icon + ' mr-2', 'aria-hidden': 'true' }));
            entry.append($('<strong/>').text(label));
            entry.append(document.createTextNode(' — ' + (message || '')));
            $(logSelector).append(entry);
        }

        function showRegressedBanner(message) {
            $('#migrationRegressedBanner').removeClass('d-none').text(message);
        }

        function handleRunResponse(item, response, logSelector, results, forcedFailure) {
            var ok = !forcedFailure && !!response.success;
            var message = response.message || response.error || LANG.genericError;
            results.push({ item: item, ok: ok });
            appendLogEntry(logSelector, item, ok, message);
            if (response.regressed) {
                showRegressedBanner(message);
            }
        }

        function runSequentially(items, requestFn, logSelector, onDone) {
            var results = [];

            // jQuery'nin global $.ajaxSetup({complete:...}) token-yenilemesi bu isteğin
            // yanıtından SONRA (completeDeferred.fireWith) çalışır — ama .always() (ve
            // dolayısıyla step(i+1)) o güncellemeden ÖNCE tetiklenir, çünkü .done()/.fail()
            // ile bağlı LOKAL zincir global complete callback'inden önce resolve/reject
            // edilir. Sıradaki istek bu yüzden her zaman bir önceki (bayat) token'la
            // gidiyordu. Burada, .always() step(i+1)'i çağırmadan ÖNCE, senkron olarak
            // yanıtın X-CSRF-TOKEN header'ını okuyup token'ı güncelliyoruz.
            function refreshCsrfFromResponse(jqXHR) {
                var newToken = jqXHR && jqXHR.getResponseHeader && jqXHR.getResponseHeader('X-CSRF-TOKEN');
                if (newToken) {
                    CI4MS_CSRF.setHash(newToken);
                }
            }

            function step(i) {
                if (i >= items.length) {
                    onDone(results);
                    return;
                }
                var item = items[i];
                requestFn(item)
                    .done(function (response, textStatus, jqXHR) {
                        refreshCsrfFromResponse(jqXHR);
                        handleRunResponse(item, response || {}, logSelector, results, false);
                    })
                    .fail(function (jqXHR) {
                        // Başarısız CSRF doğrulaması sunucu tarafındaki token'ı değiştirmez
                        // (Security::verify() regenerate'e ulaşmadan exception fırlatır), bu
                        // yüzden 403 yanıtında X-CSRF-TOKEN header'ı da olmaz — bu durumda
                        // mevcut token'a dokunulmaz.
                        refreshCsrfFromResponse(jqXHR);
                        var response = (jqXHR && jqXHR.responseJSON) || {};
                        handleRunResponse(item, response, logSelector, results, true);
                    })
                    .always(function () {
                        step(i + 1);
                    });
            }

            step(0);
        }

        function showSummary(summarySelector, results) {
            var success = 0;
            var failed = 0;
            results.forEach(function (r) {
                if (r.ok) {
                    success++;
                } else {
                    failed++;
                }
            });
            var text = LANG.runSummary.replace('{success}', success).replace('{failed}', failed);
            $(summarySelector).removeClass('d-none').text(text);
        }

        function confirmAndRun(title, onConfirm) {
            Swal.fire({
                title: title,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: LANG.run,
                cancelButtonText: LANG.cancel
            }).then(function (result) {
                if (result.isConfirmed) {
                    onConfirm();
                }
            });
        }

        // ── Migrations ──
        toggleSelectAll('#selectAllMigrations', '.migration-namespace-checkbox');

        function startMigrationRun(namespaces) {
            if (namespaces.length === 0) {
                showToast(LANG.noNamespacesSelected, 'error');
                return;
            }
            $('#migrationRunLog').empty();
            $('#migrationRunSummary').addClass('d-none');
            $('#migrationRegressedBanner').addClass('d-none');
            $('#btnRunAllMigrations, #btnRunSelectedMigrations').prop('disabled', true);
            runSequentially(namespaces, function (ns) {
                return $.post(RUN_MIGRATION_URL, { namespace: ns, [CI4MS_CSRF.name]: CI4MS_CSRF.getHash() }, 'json');
            }, '#migrationRunLog', function (results) {
                showSummary('#migrationRunSummary', results);
                $('#btnRunAllMigrations, #btnRunSelectedMigrations').prop('disabled', false);
            });
        }

        $('#btnRunAllMigrations').on('click', function () {
            var namespaces = [];
            $('.migration-namespace-checkbox').not(':disabled').each(function () {
                namespaces.push($(this).val());
            });
            if (namespaces.length === 0) {
                showToast(LANG.noNamespacesSelected, 'error');
                return;
            }
            confirmAndRun(LANG.confirmRunMigrationsTitle.replace('{count}', namespaces.length), function () {
                startMigrationRun(namespaces);
            });
        });

        $('#btnRunSelectedMigrations').on('click', function () {
            var namespaces = selectedValues('.migration-namespace-checkbox');
            if (namespaces.length === 0) {
                showToast(LANG.noNamespacesSelected, 'error');
                return;
            }
            confirmAndRun(LANG.confirmRunMigrationsTitle.replace('{count}', namespaces.length), function () {
                startMigrationRun(namespaces);
            });
        });

        // ── Seeds ──
        toggleSelectAll('#selectAllSeeds', '.seed-checkbox');

        function startSeedRun(seederClasses) {
            if (seederClasses.length === 0) {
                showToast(LANG.noSeedsSelected, 'error');
                return;
            }
            $('#seedRunLog').empty();
            $('#seedRunSummary').addClass('d-none');
            $('#btnRunAllSeeds, #btnRunSelectedSeeds').prop('disabled', true);
            runSequentially(seederClasses, function (cls) {
                return $.post(RUN_SEED_URL, { seeder: cls, [CI4MS_CSRF.name]: CI4MS_CSRF.getHash() }, 'json');
            }, '#seedRunLog', function (results) {
                showSummary('#seedRunSummary', results);
                $('#btnRunAllSeeds, #btnRunSelectedSeeds').prop('disabled', false);
            });
        }

        $('#btnRunAllSeeds').on('click', function () {
            var seederClasses = [];
            $('.seed-checkbox').each(function () {
                seederClasses.push($(this).val());
            });
            if (seederClasses.length === 0) {
                showToast(LANG.noSeedsSelected, 'error');
                return;
            }
            confirmAndRun(LANG.confirmRunSeedsTitle.replace('{count}', seederClasses.length), function () {
                startSeedRun(seederClasses);
            });
        });

        $('#btnRunSelectedSeeds').on('click', function () {
            var seederClasses = selectedValues('.seed-checkbox');
            if (seederClasses.length === 0) {
                showToast(LANG.noSeedsSelected, 'error');
                return;
            }
            confirmAndRun(LANG.confirmRunSeedsTitle.replace('{count}', seederClasses.length), function () {
                startSeedRun(seederClasses);
            });
        });

        // ── History DataTable ──
        var historyTable = $('#historyTable').DataTable({
            processing: true,
            serverSide: true,
            order: [[8, 'desc']],
            ajax: {
                url: HISTORY_URL,
                type: 'POST',
                data: function (d) {
                    d[CI4MS_CSRF.name] = CI4MS_CSRF.getHash();
                }
            },
            columns: [
                {
                    data: 'kind',
                    render: function (d) {
                        return escapeHtml(d === 'seed' ? LANG.kindSeedLabel : LANG.kindMigrationLabel);
                    }
                },
                {
                    data: 'target',
                    render: function (d) {
                        // Sunucu ham veri döner (MigrationManager::history()),
                        // kaçışlama burada yapılır — diğer tüm kolonlarla aynı kural.
                        if (d === '*') {
                            return '<span class="text-muted">' + escapeHtml(LANG.targetAllNamespacesLabel) + '</span>';
                        }
                        return '<code>' + escapeHtml(d || '') + '</code>';
                    }
                },
                {
                    data: 'status',
                    render: function (d) {
                        var cls = d === 'success' ? 'badge-success' : 'badge-danger';
                        var label = d === 'success' ? LANG.statusSuccessLabel : LANG.statusFailedLabel;
                        return '<span class="badge ' + cls + '">' + escapeHtml(label) + '</span>';
                    }
                },
                { data: 'applied_count' },
                {
                    data: 'batch',
                    render: function (d) {
                        return (d === null || d === undefined) ? '—' : escapeHtml(d);
                    }
                },
                { data: 'duration_ms' },
                {
                    data: 'run_by_username',
                    render: function (d, type, row) {
                        if (d !== null && d !== undefined) {
                            return escapeHtml(d);
                        }
                        var isCli = row && row.run_source === 'cli';
                        return escapeHtml(isCli ? LANG.runSourceCliLabel : LANG.deletedUserLabel);
                    }
                },
                {
                    data: 'ip',
                    render: function (d) {
                        return escapeHtml(d);
                    }
                },
                {
                    data: 'created_at',
                    render: function (d) {
                        return escapeHtml(d);
                    }
                },
                {
                    data: 'message',
                    render: function (d) {
                        var text = String(d || '');
                        var truncated = text.length > 80 ? text.slice(0, 80) + '…' : text;
                        return '<span class="small text-monospace" title="' + escapeHtml(text) + '">' + escapeHtml(truncated) + '</span>';
                    }
                }
            ],
            language: ci4msDtLanguage('<?php echo lang('MigrationManager.searchPlaceholder') ?>')
        });

        $('#btnRefreshHistory').on('click', function () {
            historyTable.ajax.reload();
        });
    })();
</script>
<?php echo $this->endSection() ?>
