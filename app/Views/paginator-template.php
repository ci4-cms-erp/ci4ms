<?php if ($paginator->getNumPages() > 1): ?>
    <div class="w-100">
        <ul class="pagination pagination-sm m-0 float-right">
            <?php if ($paginator->getPrevUrl()): ?>
                <li class="page-item"><a class="page-link"
                                         href="<?php echo $paginator->getPrevUrl(); ?>">&laquo;</a>
                </li>
            <?php endif; ?>

            <?php foreach ($paginator->getPages() as $page): ?>
                <?php if ($page['url']): ?>
                    <li class="page-item <?php echo $page['isCurrent'] ? 'active' : ''; ?>">
                        <a class="page-link"
                           href="<?php echo $page['url']; ?>"><?php echo $page['num']; ?></a>
                    </li>
                <?php else: ?>
                    <li class="disabled page-item"><span><?php echo $page['num']; ?></span></li>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($paginator->getNextUrl()): ?>
                <li class="page-item"><a class="page-link"
                                         href="<?php echo $paginator->getNextUrl(); ?>">&raquo;</a>
                </li>
            <?php endif; ?>
        </ul>
    </div>
<?php endif; ?>