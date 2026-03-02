<?php $pager->setSurroundCount(2); ?>

<div class="col-xl-6 col-md-6 col-sm-12">
    <nav aria-label="<?php echo lang('Pager.pageNavigation') ?>">
        <ul class="pagination">
            <?php if ($pager->hasPrevious()) : ?>
                <li class="page-item"><a class="page-link" href="<?php echo $pager->getFirst() ?>" aria-label="<?php echo lang('Pager.first') ?>"><span aria-hidden="true"><i class="fa fa-chevron-left" aria-hidden="true"></i></span> <span class="sr-only"><?php echo lang('Pager.first') ?></span></a></li>
                <li class="page-item">
                    <a href="<?php echo $pager->getPrevious() ?>" class="page-link" aria-label="<?php echo lang('Pager.previous') ?>">
                        <span aria-hidden="true"><?php echo lang('Pager.previous') ?></span>
                    </a>
                </li>
            <?php endif;
            foreach ($pager->links() as $link) : ?>
                <li class="page-item <?php echo $link['active'] ? 'active' : '' ?>"><a class="page-link" href="<?php echo $link['uri'] ?>"><?php echo $link['title'] ?></a>
                </li>
            <?php endforeach;
            if ($pager->hasNext()) : ?>
                <li class="page-item"><a class="page-link" href="<?php echo $pager->getNext() ?>" aria-label="<?php echo lang('Pager.next') ?>"><span aria-hidden="true"><i class="fa fa-chevron-right" aria-hidden="true"></i></span> <span class="sr-only"><?php echo lang('Pager.next') ?></span></a></li>
                <li class="page-item">
                    <a href="<?php echo $pager->getLast() ?>" class="page-link" aria-label="<?php echo lang('Pager.last') ?>">
                        <span aria-hidden="true"><?php echo lang('Pager.last') ?></span>
                    </a>
                </li>
            <?php endif ?>
        </ul>
    </nav>
</div>
