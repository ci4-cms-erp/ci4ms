<?= $this->extend('Views/templates/default/base') ?>
<?= $this->section('head')?>
    <link href="<?=site_url('templates/default/assets/404.css')?>" rel="stylesheet" />
<?=$this->endSection()?>
<?=$this->section('content')?>
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
                        <a href="<?=$referer?>" class="link_404">Go to Home</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?=$this->endSection()?>