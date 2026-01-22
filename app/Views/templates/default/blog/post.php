<?= $this->extend('Views/templates/default/base') ?>
<?= $this->section('metatags') ?>
<?= $this->endSection() ?>
<?= $this->section('head') ?>
<?= link_tag('templates/' . $settings->templateInfos->path . '/assets/node_modules/sweetalert2/dist/sweetalert2.min.css') ?>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<section class="py-5">
    <div class="container px-5 my-5">
        <div class="row gx-5">
            <div class="<?= (!empty($settings->templateInfos->widgets->sidebar)) ? 'col-md-9' : 'col-md-12' ?>">
                <!-- Post content-->
                <article>
                    <!-- Post header-->
                    <div onload=""></div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <?php foreach ($breadcrumbs as $breadcrumb) { ?>
                                <li class="breadcrumb-item<?= ($breadcrumb['url'] == current_url()) ? ' active' : '' ?>"
                                    <?= (empty($breadcrumb['url'])) ? 'aria-current="page"' : '' ?>>
                                    <?php if ($breadcrumb['url'] == site_url('blog/'.esc($infos->seflink))) { ?>
                                        <?= esc($breadcrumb['title']) ?>
                                    <?php } else { ?>
                                        <a href="<?= esc($breadcrumb['url']) ?>">
                                            <?= esc($breadcrumb['title']) ?>
                                        </a>
                                    <?php } ?>
                                </li>
                            <?php } ?>
                        </ol>
                    </nav>
                    <header class="mb-4">
                        <!-- Post title-->
                        <h1 class="fw-bolder mb-1"><?= esc($infos->title) ?></h1>
                        <!-- Post meta content-->
                        <? if ($infos->created_at != '0000-00-00 00:00:00'): ?>
                            <div class="text-muted fst-italic mb-2"><?= $dateI18n->createFromTimestamp(strtotime($infos->created_at), app_timezone(), 'tr_TR')->toFormattedDateString(); ?></div>
                        <?php endif; ?>
                        <!-- Post categories-->
                        <?php foreach ($tags as $tag): ?>
                            <a class="badge bg-secondary text-decoration-none link-light"
                                href="<?= route_to('tag', $tag->seflink) ?>"><?= esc($tag->tag) ?></a>
                        <?php endforeach; ?>
                    </header>
                    <!-- Preview image figure-->
                    <figure class="mb-4">
                        <?php if (!empty($infos->seo->coverImage)) { ?><img class="img-fluid rounded" src="<?= esc($infos->seo->coverImage) ?>"
                                alt="<?= esc($infos->title) ?>" /><?php } ?>
                    </figure>
                    <!-- Post content-->
                    <section class="mb-5">
                        <?= $infos->content ?>
                    </section>
                    <hr>
                    <div class="d-flex align-items-center mt-lg-5 mb-4">
                        <?php if (empty($infos->profileIMG)): ?>
                            <img class="img-fluid rounded-circle" src="https://dummyimage.com/50x50/ced4da/6c757d.jpg"
                                alt="<?= esc($infos->author) ?>" />
                        <?php else: ?>
                            <img class="img-fluid rounded-circle" src="<?= esc($infos->profileIMG) ?>"
                                alt="<?= esc($infos->author) ?>" />
                        <?php endif; ?>
                        <div class="ms-3">
                            <div class="fw-bold"><?= esc($infos->author) ?></div>
                            <div class="text-muted"><?= esc($infos->groupName) ?></div>
                        </div>
                    </div>
                </article>
                <hr>
                <!-- Comments section -->
                <section>
                    <div class="card bg-light">
                        <div class="card-body">
                            <!-- Comment form-->
                            <form class="mb-4 row">
                                <div class="col-md-6 form-group mb-3">
                                    <input type="text" class="form-control" name="comFullName" placeholder="Full name"
                                        value="<?= old('comFullName') ?>">
                                </div>
                                <div class="col-md-6 form-group mb-3">
                                    <input type="email" class="form-control" name="comEmail" placeholder="E-mail"
                                        value="<?= old('comEmail') ?>">
                                </div>
                                <div class="col-12 form-group mb-3">
                                    <textarea class="form-control" rows="3" name="comMessage"
                                        placeholder="Join the discussion and leave a comment!"><?= old('comMessage') ?></textarea>
                                </div>
                                <div class="col-6 form-group">
                                    <div class="input-group">
                                        <img src="" class="captcha" alt="captcha">
                                        <input type="text" placeholder="captcha" name="captcha" class="form-control">
                                        <button class="btn btn-secondary" onclick="captchaF()" type="button">New Captcha</button>
                                    </div>
                                </div>
                                <div class="col-6 form-group text-end">
                                    <button class="btn btn-primary btn-sm sendComment" type="button" data-id=""
                                        data-blogid="<?= $infos->id ?>">Send
                                    </button>
                                </div>
                            </form>
                            <?php if (!empty($comments)) { ?>
                                <hr>
                                <div id="comments">
                                    <?= comments($comments, $infos->id); ?>
                                </div>
                                <div class="d-flex">
                                    <div class="w-100">
                                        <button class="btn btn-warning w-100" onclick="loadMore('<?= $infos->id ?>')" id="loadMore" data-skip="5" data-defskip="5"><i class=""></i> Load More</button>
                                    </div>
                                </div>
                            <?php } ?>
                        </div>
                    </div>
                </section>
            </div>
            <?php if (!empty($settings->templateInfos->widgets->sidebar)):
                echo view('templates/default/widgets/sidebar');
            endif; ?>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
<?= $this->section('javascript') ?>
<?= script_tag('templates/' . $settings->templateInfos->path . '/assets/node_modules/sweetalert2/dist/sweetalert2.all.min.js') ?>
<?= $this->endSection() ?>
