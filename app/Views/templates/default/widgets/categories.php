<!-- Categories widget-->
<div class="card mb-4">
    <div class="card-header">Categories</div>
    <div class="card-body">
        <div class="row">
            <?php $c = 0;
            foreach ($categories as $category):
                if ($c == 0):?>
                    <div class="col-sm-6">
                    <ul class="list-unstyled mb-0">
                <?php endif; ?>
                <li>
                    <a href="<?= site_url('category/' . $category->seflink) ?>"><?= $category->title ?></a>
                </li>
                <?php if ($c == 0): ?>
                </ul>
                </div>
            <?php endif;
                $c++;
                if ($c == 3) $c = 0;
            endforeach; ?>
        </div>
    </div>
</div>