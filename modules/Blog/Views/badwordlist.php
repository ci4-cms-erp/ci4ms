<?php echo $this->extend($backConfig->viewLayout);
echo $this->section('title');
echo lang($title->pagename);
echo $this->endSection();
echo $this->section('content'); ?>

<!-- Main content -->
<section class="content pt-3">
    <!-- Default box -->
    <div class="card premium-card">
        <div class="card-header d-flex align-items-center">
            <h3 class="card-title font-weight-bold mb-0">
                <i class="fas fa-ban mr-2 text-primary"></i> <?php echo lang($title->pagename) ?>
            </h3>
        </div>
        <div class="card-body p-4">
            <form action="<?php echo route_to("badwordsAdd") ?>" method="post">
                <?php echo csrf_field() ?>
                
                <div class="alert alert-info" style="border-radius: 10px;">
                    <i class="fas fa-info-circle mr-2"></i> <?php echo lang('Blog.badwordsInfo') ?? 'Buraya girdiğiniz kelimeler yorumlarda filtrelemek veya otomatik engellemek için kullanılır. Her kelimeyi yeni bir satıra yazın.' ?>
                </div>

                <div class="row mb-4">
                    <div class="col-md-12">
                        <div class="p-3" style="background: #f8f9fa; border-radius: 10px; border: 1px solid #e9ecef;">
                            <h6 class="font-weight-bold mb-3"><i class="fas fa-cog mr-2"></i>Filtreleme Ayarları</h6>
                            <div class="d-flex flex-wrap gap-3">
                                <div class="custom-control custom-switch mr-4">
                                    <input type="checkbox" name="status" id="status" class="custom-control-input" <?php echo set_checkbox('status', 'on', (bool)$badwords->status === true) ?>>
                                    <label for="status" class="custom-control-label" style="cursor: pointer;"><?php echo lang('Blog.enableFiltering') ?></label>
                                </div>

                                <div class="custom-control custom-switch mr-4">
                                    <input type="checkbox" name="autoReject" id="autoReject" class="custom-control-input" <?php echo set_checkbox('autoReject', 'on', (bool)$badwords->autoReject === true) ?>>
                                    <label for="autoReject" class="custom-control-label" style="cursor: pointer;"><?php echo lang('Blog.autoReject') ?></label>
                                </div>

                                <div class="custom-control custom-switch">
                                    <input type="checkbox" name="autoAccept" id="autoAccept" class="custom-control-input" <?php echo set_checkbox('autoAccept', 'on', (bool)$badwords->autoAccept === true) ?>>
                                    <label for="autoAccept" class="custom-control-label" style="cursor: pointer;"><?php echo lang('Blog.autoAccept') ?></label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="form-group mb-4">
                    <label for="badwords" class="font-weight-bold"><i class="fas fa-list mr-1"></i> <?php echo lang('Blog.badwordsList') ?? 'Kelime Listesi' ?></label>
                    <textarea name="badwords" id="badwords" cols="30" rows="12" class="form-control" placeholder="Örnek:&#10;kelime1&#10;kelime2&#10;küfür1" style="border-radius: 10px; resize: vertical;"><?php echo old('badwords', $badwords->list) ?></textarea>
                </div>

                <div class="form-group text-right mb-0">
                    <button class="btn btn-success px-4" type="submit" style="border-radius: 8px;">
                        <i class="fas fa-save mr-2"></i> <?php echo lang('Backend.save') ?>
                    </button>
                </div>
            </form>
        </div>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->

</section>
<!-- /.content -->
<?php echo $this->endSection(); ?>
