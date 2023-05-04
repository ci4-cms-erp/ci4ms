<?= $this->extend('Views/templates/default/base') ?>
<?= $this->section('head') ?>
<?= $this->endSection() ?>
<?= $this->section('content') ?>
    <header class="py-5 bg-light border-bottom mb-4">
        <div class="container">
            <div class="text-center my-5">
                <h1 class="fw-bolder">Search Result</h1>
            </div>
        </div>
    </header>
    <!-- About section one-->
    <section class="py-5 bg-light" id="scroll-target">
        <div class="container px-5 my-5">
            <div class="row gx-5 align-items-center">
                <div class="col-lg-4 card">
                    <div class="card-img">

                    </div>
                    <div class="card-body">

                    </div>
                    <div class="card-footer">

                    </div>
                </div>
            </div>
        </div>
    </section>
<?= $this->endSection() ?>
<?= $this->section('javascript') ?>
<?= $this->endSection() ?>