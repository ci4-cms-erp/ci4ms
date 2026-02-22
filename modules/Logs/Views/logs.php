<div class="row">
    <div class="col-sm-3 col-md-2 sidebar">
        <div class="list-group">
            <?php if (empty($files)): ?>
                <a class="list-group-item active">No Log Files Found</a>
            <?php else: ?>
                <?php foreach ($files as $file): ?>
                    <a href="?f=<?php echo base64_encode($file); ?>"
                        class="list-group-item list-group-item-light <?php echo ($currentFile == $file) ? "active" : "" ?>">
                        <?php echo $file; ?>
                    </a>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <div class="col-sm-9 col-md-10 table-container">
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
        <div class="button-group">
            <?php if ($currentFile): ?>
                <a href="?dl=<?php echo base64_encode($currentFile); ?>" class="btn btn-light">
                    <span class="far fa-download"></span>
                    Download file
                </a>
                <button type="button" onclick="deleteItem('<?php echo base64_encode($currentFile); ?>')" class="btn btn-light"><?php echo lang('Backend.delete') ?></button>
                <?php if (count($files) > 1): ?>
                    <button type="button" onclick="deleteItem('<?php echo base64_encode("all"); ?>')" class="btn btn-light"><span class="far fa-trash"></span> Delete all files</button>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
