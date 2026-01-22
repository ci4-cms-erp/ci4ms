<?= $this->extend('Views/templates/default/base') ?>
<?= $this->section('metatags') ?>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<?php if ($pageInfo->seflink != '/'): ?>
    <header class="py-5 bg-light border-bottom mb-4">
        <div class="container">
            <div class="text-center my-5">
                <h1 class="fw-bolder"><?= esc($pageInfo->title) ?></h1>
            </div>
            <div onload=""></div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <?php foreach ($breadcrumbs as $breadcrumb) { ?>
                        <li class="breadcrumb-item<?= ($breadcrumb['url'] == site_url(esc($pageInfo->seflink))) ? ' active' : '' ?>"
                            <?= (empty($breadcrumb['url'])) ? 'aria-current="page"' : '' ?>>
                            <?php if ($breadcrumb['url'] == site_url(esc($pageInfo->seflink))) { ?>
                                <?= esc($breadcrumb['title']) ?>
                            <?php } else { ?>
                                <a href="<?= site_url($breadcrumb['url']) ?>">
                                    <?= esc($breadcrumb['title']) ?>
                                </a>
                            <?php } ?>
                        </li>
                    <?php } ?>
                </ol>
            </nav>
        </div>
    </header>
<?php endif; ?>
<?= $pageInfo->content ?>
<?= $this->endSection() ?>
