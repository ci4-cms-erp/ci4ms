<?php if (is_null($logs)): ?>
    <div>
        <br><br>
        <strong>Log file > 50MB, please download it.</strong>
        <br><br>
    </div>
<?php else: ?>
    <table id="table-log" class="table table-responsive table-striped table-hover">
        <thead>
            <tr>
                <th>Level</th>
                <th>Date</th>
                <th>Content</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $key => $log): ?>
                <tr data-display="stack<?php echo $key; ?>">

                    <td class="text-<?php echo $log['class']; ?>">
                        <span class="<?php echo $log['icon']; ?>" aria-hidden="true"></span>
                        &nbsp;<?php echo $log['level']; ?>
                    </td>
                    <td class="date"><?php echo $log['date']; ?></td>
                    <td class="text">
                        <?php if (array_key_exists("extra", $log)): ?>
                            <a class="pull-right expand btn btn-default btn-xs"
                                data-display="stack<?php echo $key; ?>">
                                <span class="far fa-search"></span>
                            </a>
                        <?php endif; ?>
                        <?php echo esc($log['content']); ?>
                        <?php if (array_key_exists("extra", $log)): ?>
                            <div class="stack" id="stack<?php echo $key; ?>"
                                style="display: none; white-space: pre-wrap;">
                                <?php echo esc($log['extra']) ?>
                            </div>
                        <?php endif; ?>

                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
