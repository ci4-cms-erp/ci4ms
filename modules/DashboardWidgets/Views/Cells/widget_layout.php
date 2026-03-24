<?php if (!empty($widgets)):
    foreach ($widgets as $widget) : ?>
        <div class="<?php echo esc($widget->display_size) ?> widget-item mb-4" data-id="<?php echo $widget->id ?>">
            <?php if ($widget->type === 'stat'): ?>
                <!-- Stat / Counter Widget -->
                <div class="small-box bg-<?php echo esc($widget->color) ?> shadow h-100 mb-0">
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
                <div class="card card-<?php echo esc($widget->color) ?> shadow h-100 mb-0">
                    <div class="card-header border-0">
                        <h3 class="card-title">
                            <i class="<?php echo esc($widget->icon) ?> mr-1"></i>
                            <?php echo esc($widget->title) ?>
                        </h3>
                    </div>
                    <div class="card-body table-responsive p-0">
                        <table class="table table-striped table-valign-middle mb-0">
                            <tbody>
                                <?php if (!empty($widget->data['rows'])): ?>
                                    <?php foreach ($widget->data['rows'] as $row): ?>
                                        <tr>
                                            <?php foreach ((array)$row as $val): ?>
                                                <td><?php echo esc($val) ?></td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
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
                <div class="card card-<?php echo esc($widget->color) ?> shadow h-100 mb-0">
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
                <div class="card card-<?php echo esc($widget->color) ?> shadow h-100 mb-0">
                    <div class="card-header border-0">
                        <h3 class="card-title">
                            <i class="<?php echo esc($widget->icon) ?> mr-1"></i>
                            <?php echo esc($widget->title) ?>
                        </h3>
                    </div>
                    <div class="card-body">
                        <canvas id="chart_<?php echo $widget->id ?>" style="min-height: 250px; height: 250px; max-height: 250px; max-width: 100%;"></canvas>
                        <!-- Note: Chart rendering data logic requires JS which should be added via JSON -->
                    </div>
                </div>
            <?php endif; ?>
        </div>
    <?php endforeach;
else: ?>
    <!-- Fallback to old hardcoded dashboard widgets if no DB widgets are found -->
    <div class="col-12 text-center text-muted py-5">
        <i class="fas fa-cubes fa-3x mb-3 text-light"></i>
        <h5>No Widgets Configured</h5>
    </div>
<?php endif; ?>
