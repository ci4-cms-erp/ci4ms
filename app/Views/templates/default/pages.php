<?php echo $this->extend('Views/templates/default/base') ?>
<?php echo $this->section('metatags') ?>
<?php echo $this->endSection() ?>
<?php echo $this->section('content') ?>
<?php if ($pageInfo->seflink != '/'): ?>
    <header class="py-5 bg-light border-bottom mb-4">
        <div class="container">
            <div class="text-center my-5">
                <h1 class="fw-bolder"><?php echo esc($pageInfo->title) ?></h1>
            </div>
            <div onload=""></div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <?php foreach ($breadcrumbs as $breadcrumb) { ?>
                        <li class="breadcrumb-item<?php echo ($breadcrumb['url'] == site_url($pageInfo->seflink)) ? ' active' : '' ?>"
                            <?php echo (empty($breadcrumb['url'])) ? 'aria-current="page"' : '' ?>>
                            <?php if ($breadcrumb['url'] == site_url(esc($pageInfo->seflink))) { ?>
                                <?php echo esc($breadcrumb['title']) ?>
                            <?php } else { ?>
                                <a href="<?php echo esc(site_url($breadcrumb['url']), 'url') ?>">
                                    <?php echo esc($breadcrumb['title']) ?>
                                </a>
                            <?php } ?>
                        </li>
                    <?php } ?>
                </ol>
            </nav>
        </div>
    </header>
<?php endif; ?>
<?php echo $pageInfo->content ?>
<?php echo $this->endSection() ?>
