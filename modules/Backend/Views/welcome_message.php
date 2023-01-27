<?= $this->extend('Modules\Backend\Views\base') ?>
<?= $this->section('title') ?>
<?= lang('Backend.'.$title->pagename) ?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><?= lang('Backend.'.$title->pagename) ?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right"></ol>
            </div>
        </div>
    </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">
    <div class="row">
        <?php foreach ($dashboard as $item) : ?>
        <div class="col-lg-3 col-md-3">
            <!-- small card -->
            <div class="small-box bg-light shadow">
                <div class="inner">
                    <h3><?=$item->count?></h3>

                    <p><?= lang('Backend.'.$item->lang); ?></p>
                </div>
                <div class="icon">
                    <?=$item->icon?>
                </div>
                <a href="<?=route_to($item->lang,1)?>" class="small-box-footer">
                    <?php echo lang('Backend.more_info'); ?>
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<!-- /.content -->
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>

<?= $this->endSection() ?>
