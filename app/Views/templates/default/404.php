<?= $this->extend('Views/templates/default/base') ?>
<?= $this->section('head') ?>
<?=link_tag('templates/default/assets/404.css')?>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
<section class="page_404">
    <div class="container-fluid">
        <div class="row">
            <div class="col-sm-12 ">
                <div class="col-sm-12 col-sm-offset-1  text-center">
                    <div class="four_zero_four_bg">
                        <h1 class="text-center ">404</h1>
                    </div>
                    <div class="contant_box_404">
                        <h3 class="h2">
                            Look like you're lost
                        </h3>
                        <p>the page you are looking for not avaible!</p>
                        <?php if (empty($referer)) : ?>
                            <a href="/" class="link_404">Go to Home</a>
                        <?php else : ?>
                            <a href="<?= esc($referer) ?>" class="link_404">Go Back</a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?= $this->endSection() ?>
