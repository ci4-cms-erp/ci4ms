<?= $this->extend('Modules\Backend\Views\base') ?>

<?= $this->section('title') ?>
<?=lang('Backend.'.$title->pagename)?>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<!-- Content Header (Page header) -->
<section class="content-header">
    <div class="container-fluid">
        <div class="row mb-2">
            <div class="col-sm-6">
                <h1><?=lang('Backend.'.$title->pagename)?></h1>
            </div>
            <div class="col-sm-6">
                <ol class="breadcrumb float-sm-right">
                    <a href="<?= route_to('group_create') ?>" class="btn btn-outline-success"><?=lang('Backend.add')?></a>
                </ol>
            </div>
        </div>
    </div><!-- /.container-fluid -->
</section>

<!-- Main content -->
<section class="content">

    <!-- Default box -->
    <div class="card card-outline card-shl">
        <div class="card-header">
            <h3 class="card-title font-weight-bold"><?=lang('Backend.permGroupList')?></h3>

            <div class="card-tools">
                <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                    <i class="fas fa-minus"></i>
                </button>
            </div>
        </div>

        <div class="card-body">
            <?= view('Modules\Auth\Views\_message_block') ?>
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                    <tr>
                        <th><?=lang('Backend.permGroupList')?></th>
                        <th><?=lang('Backend.transactions')?></th>
                    </tr>
                    <tbody>
                    <?php foreach ($groups as $group):
                        //if ($group->name != 'super user'):?>
                            <tr>
                                <td><?= $group->name ?></td>
                                <td>
                                    <!--TODO: Silme adımı için yetkiye sahip olanların yetkisinin değiştirilmesi istenilecek-->
                                    <a href="<?=route_to('group_update',$group->_id)?>" class="btn btn-outline-info btn-sm"><?=lang('Backend.update')?></a>
                                </td>
                            </tr>
                        <?php //endif;
                        endforeach; ?>
                    </tbody>
                    </thead>
                </table>
            </div>
        </div>
        <?php if ($paginator->getNumPages() > 1): ?>
        <div class="card-footer clearfix">
                <ul class="pagination pagination-sm m-0 float-right">
                <?php if ($paginator->getPrevUrl()): ?>
                    <li class="page-item"><a class="page-link" href="<?php echo $paginator->getPrevUrl(); ?>">&laquo;</a></li>
                <?php endif; ?>

                <?php foreach ($paginator->getPages() as $page): ?>
                    <?php if ($page['url']): ?>
                        <li class="page-item <?php echo $page['isCurrent'] ? 'active' : ''; ?>">
                            <a class="page-link" href="<?php echo $page['url']; ?>"><?php echo $page['num']; ?></a>
                        </li>
                    <?php else: ?>
                        <li class="disabled page-item"><span><?php echo $page['num']; ?></span></li>
                    <?php endif; ?>
                <?php endforeach; ?>

                <?php if ($paginator->getNextUrl()): ?>
                    <li class="page-item"><a class="page-link" href="<?php echo $paginator->getNextUrl(); ?>">&raquo;</a></li>
                <?php endif; ?>
            </ul>
        </div>
        <?php endif; ?>
        <!-- /.card-body -->
    </div>
    <!-- /.card -->

</section>
<!-- /.content -->
<?= $this->endSection() ?>

<?= $this->section('javascript') ?>
<!-- jQuery Knob -->
<?=script_tag("be-assets/plugins/jquery-knob/jquery.knob.min.js")?>
<!-- Sparkline -->
<?=script_tag("be-assets/plugins/sparklines/sparkline.js")?>
<script>
    $(function () {
        /* jQueryKnob */
        $('.knob').knob({
            draw: function () {
                // "tron" case
                if (this.$.data('skin') == 'tron') {

                    var a = this.angle(this.cv)  // Angle
                        ,
                        sa = this.startAngle          // Previous start angle
                        ,
                        sat = this.startAngle         // Start angle
                        ,
                        ea                            // Previous end angle
                        ,
                        eat = sat + a                 // End angle
                        ,
                        r = true

                    this.g.lineWidth = this.lineWidth
                    this.o.cursor
                    && (sat = eat - 0.3)
                    && (eat = eat + 0.3)
                    if (this.o.displayPrevious) {
                        ea = this.startAngle + this.angle(this.value)
                        this.o.cursor
                        && (sa = ea - 0.3)
                        && (ea = ea + 0.3)
                        this.g.beginPath()
                        this.g.strokeStyle = this.previousColor
                        this.g.arc(this.xy, this.xy, this.radius - this.lineWidth, sa, ea, false)
                        this.g.stroke()
                    }
                    this.g.beginPath()
                    this.g.strokeStyle = r ? this.o.fgColor : this.fgColor
                    this.g.arc(this.xy, this.xy, this.radius - this.lineWidth, sat, eat, false)
                    this.g.stroke()
                    this.g.lineWidth = 2
                    this.g.beginPath()
                    this.g.strokeStyle = this.o.fgColor
                    this.g.arc(this.xy, this.xy, this.radius - this.lineWidth + 1 + this.lineWidth * 2 / 3, 0, 2 * Math.PI, false)
                    this.g.stroke()
                    return false
                }
            }
        })
        /* END JQUERY KNOB */
    })
</script>
<?= $this->endSection() ?>
