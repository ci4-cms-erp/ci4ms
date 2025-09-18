<div class="row">
    <div class="col-sm-3 col-md-2 sidebar">
        <div class="list-group">
            <?php if (empty($files)): ?>
                <a class="list-group-item active">No Log Files Found</a>
            <?php else: ?>
                <?php foreach ($files as $file): ?>
                    <a href="?f=<?= base64_encode($file); ?>"
                        class="list-group-item list-group-item-light <?= ($currentFile == $file) ? "active" : "" ?>">
                        <?= $file; ?>
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
                        <tr data-display="stack<?= $key; ?>">

                            <td class="text-<?= $log['class']; ?>">
                                <span class="<?= $log['icon']; ?>" aria-hidden="true"></span>
                                &nbsp;<?= $log['level']; ?>
                            </td>
                            <td class="date"><?= $log['date']; ?></td>
                            <td class="text">
                                <?php if (array_key_exists("extra", $log)): ?>
                                    <a class="pull-right expand btn btn-default btn-xs"
                                        data-display="stack<?= $key; ?>">
                                        <span class="far fa-search"></span>
                                    </a>
                                <?php endif; ?>
                                <?= $log['content']; ?>
                                <?php if (array_key_exists("extra", $log)): ?>
                                    <div class="stack" id="stack<?= $key; ?>"
                                        style="display: none; white-space: pre-wrap;">
                                        <?= $log['extra'] ?>
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
                <a href="?dl=<?= base64_encode($currentFile); ?>" class="btn btn-light">
                    <span class="far fa-download"></span>
                    Download file
                </a>
                <a id="delete-log" href="?del=<?= base64_encode($currentFile); ?>" class="btn btn-light"><span
                        class="far fa-trash"></span> Delete file</a>
                <?php if (count($files) > 1): ?>
                    <a id="delete-all-log" href="?del=<?= base64_encode("all"); ?>" class="btn btn-light"><span class="far fa-trash"></span> Delete all files</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
</div>
