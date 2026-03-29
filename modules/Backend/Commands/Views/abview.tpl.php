<@= $this->extend($backConfig->viewLayout) ?>

    <@= $this->section('title') ?>
        <@=lang($title->pagename)?>
            <@= $this->endSection() ?>

                <@= $this->section('head') ?>
                    <@= $this->endSection() ?>

                        <@= $this->section('content') ?>
                            <!-- Content Header (Page header) -->
                            <section class="content-header">
                                <div class="container-fluid">
                                    <div class="row mb-2">
                                        <div class="col-sm-6">
                                            <h1><@=lang($title->pagename)?></h1>
                                        </div>
                                        <div class="col-sm-6">
                                            <ol class="breadcrumb float-sm-right">
                                                <a href="<@= route_to('') ?>" class="btn btn-sm btn-outline-info"><i
                                                        class="fas fa-arrow-circle-left"></i> Listeye Dön</a>
                                            </ol>
                                        </div>
                                    </div>
                                </div><!-- /.container-fluid -->
                            </section>

                            <!-- Main content -->
                            <section class="content">

                                <!-- Default box -->
                                <div class="card card-outline shadow-sm">
                                    <div class="card-header">
                                        <h3 class="card-title font-weight-bold">
                                            <@= lang($title->pagename) ?>
                                        </h3>

                                        <div class="card-tools">
                                            <button type="button" class="btn btn-tool" data-card-widget="collapse" title="Collapse">
                                                <i class="fas fa-minus"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="card-body">
                                    </div>
                                    <!-- /.card-body -->
                                </div>
                                <!-- /.card -->

                            </section>
                            <!-- /.content -->
                            <@= $this->endSection() ?>

                                <@= $this->section('javascript') ?>
                                    <@= $this->endSection() ?>
