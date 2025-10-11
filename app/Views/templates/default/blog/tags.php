<?= $this->extend('Views/templates/default/base') ?>
<?= $this->section('metatags') ?>
<? /*= $seo */ ?>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<header class="py-5 bg-light border-bottom mb-4">
    <div class="container">
        <div class="text-center my-5">
            <h1 class="fw-bolder"><?= $tagInfo->tag ?></h1>
        </div>
        <div onload=""></div>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb">
                <?php foreach ($breadcrumbs as $breadcrumb) { ?>
                    <li class="breadcrumb-item <?= (empty($breadcrumb['url'])) ? 'active' : '' ?>"
                        <?= (empty($breadcrumb['url'])) ? 'aria-current="page"' : '' ?>>
                        <?php if (empty($breadcrumb['url'])) { ?>
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
<section class="py-5">
    <div class="container">
        <div class="row">
            <div class="<?= (!empty($settings->templateInfos->widgets->sidebar)) ? 'col-md-9' : 'col-md-12' ?>">
                <div class="px-5">
                    <div class="row gx-5">
                        <?php foreach ($blogs as $blog):
                            $blog->seo = json_decode($blog->seo);
                            $blog->seo = (object)$blog->seo; ?>
                            <div class="col-lg-6 mb-5">
                                <div class="card h-100 shadow border-0">
                                    <img class="card-img-top"
                                        src="<?= (!empty($blog->seo->coverImage)) ? esc($blog->seo->coverImage) : 'https://dummyimage.com/600x350/ced4da/6c757d' ?>"
                                        alt="<?= $blog->title ?>" />
                                    <div class="card-body p-4">
                                        <?php foreach ($blog->tags as $tag): ?>
                                            <div class="badge bg-primary bg-gradient rounded-pill mb-2"><?= esc($tag->tag) ?></div>
                                        <?php endforeach; ?>
                                        <a class="text-decoration-none link-dark stretched-link"
                                            href="<?= site_url('blog/' . $blog->seflink) ?>">
                                            <div class="h5 card-title mb-3"><?= esc($blog->title) ?></div>
                                        </a>
                                        <p class="card-text mb-0"><?= $blog->seo->description ?></p>
                                    </div>
                                    <div class="card-footer p-4 pt-0 bg-transparent border-top-0">
                                        <div class="d-flex align-items-end justify-content-between">
                                            <div class="d-flex align-items-center">
                                                <img class="rounded-circle me-3"
                                                    src="https://dummyimage.com/40x40/ced4da/6c757d" alt="..." />
                                                <div class="small">
                                                    <div class="fw-bold"><?= esc($blog->author->firstname) . ' ' . esc($blog->author->sirname) ?></div>
                                                    <div class="text-muted"><?= $dateI18n->createFromTimestamp(strtotime($blog->created_at), app_timezone(), 'tr_TR')->toFormattedDateString(); ?></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-end mb-5 mb-xl-0">
                        <?= $pager ?>
                    </div>
                </div>
            </div>
            <?php if (!empty($settings->templateInfos->widgets->sidebar)):
                echo view('templates/default/widgets/sidebar');
            endif; ?>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
