<?php echo $this->extend('Modules\Backend\Views\base') ?>
<?php echo $this->section('title') ?>
<?php echo lang($title->pagename) ?>
<?php echo $this->endSection() ?>

<?php echo $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><?php echo lang($title->pagename) ?></h1>
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
                        <h3><?php echo $item->count ?></h3>

                        <p><?php echo $item->lang ?></p>
                    </div>
                    <div class="icon">
                        <?php echo $item->icon ?>
                    </div>
                    <a href="<?php echo route_to($item->url) ?>" class="small-box-footer">
                        <?php echo lang('Backend.more_info'); ?>
                    </a>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</section>
<!-- /.content -->
<?php echo $this->endSection() ?>

<?php echo $this->section('javascript') ?>

<?php echo $this->endSection() ?>
