<div class="card">
    <div class="card-body">
        <?php foreach ($replies as $reply) { ?>
            <div class="d-flex mt-4">
                <div class="flex-shrink-0"><img class="rounded-circle"
                                                src="https://dummyimage.com/50x50/ced4da/6c757d.jpg"
                                                alt="<?= esc($reply->comFullName) ?>"/></div>
                <div class="ms-3">
                    <div class="fw-bold"><?= esc($reply->comFullName) ?></div>
                    <?= esc($reply->comMessage) ?>
                </div>
            </div>
        <?php } ?>
        <hr>
        <div class="w-100 mt-2">
            <button class="btn btn-sm btn-outline-primary w-100" id="loadMore<?=$replies[0]->parent_id?>" onclick="loadMore('<?=(string)$replies[0]->blog_id?>','<?=(string)$replies[0]->parent_id?>')" data-skip="3" data-defskip="3"><i class=""></i> Load More</button>
        </div>
    </div>
</div>
