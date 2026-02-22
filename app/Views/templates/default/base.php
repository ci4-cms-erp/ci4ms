<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <?php echo $this->renderSection('metatags') ?>
    <!-- Favicon-->
    <link rel="icon" type="image/x-icon" href="/templates/default/assets/node_modules/startbootstrap-modern-business/dist/assets/favicon.ico" />
    <!-- Bootstrap icons-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.5.0/font/bootstrap-icons.css" rel="stylesheet" />
    <!-- Core theme CSS (includes Bootstrap)-->
    <link href="/templates/default/assets/node_modules/startbootstrap-modern-business/dist/css/styles.css" rel="stylesheet" />
    <link href="/templates/default/assets/ci4ms.css" rel="stylesheet" />
    <link href="/be-assets/plugins/jquery-ui/jquery-ui.min.css" rel="stylesheet" />
    <?php echo $this->renderSection('head') ?>

</head>

<body class="d-flex flex-column h-100">
    <main class="flex-shrink-0">
        <!-- Navigation-->
        <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
            <div class="container px-5">
                <a class="navbar-brand" href="<?php echo base_url() ?>">
                    <?php if (empty($settings->logo)):
                        echo esc($settings->siteName);
                    else: ?>
                        <img src="<?php echo esc($settings->logo) ?>" alt="<?php echo esc($settings->siteName) ?>" class="img-fluid">
                    <?php endif; ?>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0">
                        <?php menu($menus); ?>
                        <li class="nav-item">
                            <button class="btn btn-outline-secondary border-0 fw-bold" data-bs-toggle="modal" data-bs-target="#searchModal">
                                <i class="bi bi-search"></i> Search
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        <?php echo $this->renderSection('content') ?>
    </main>
    <!-- Footer-->
    <footer class="bg-dark py-4 mt-auto">
        <div class="container px-5">
            <div class="row align-items-center justify-content-between flex-column flex-sm-row">
                <div class="col-auto">
                    <div class="small m-0 text-white">Copyright &copy; <?php echo esc($settings->siteName) . ' ' . date('Y') ?> </div>
                </div>
                <div class="col-auto">
                    <a class="link-light small" href="#!">Privacy</a>
                    <span class="text-white mx-1">&middot;</span>
                    <a class="link-light small" href="#!">Terms</a>
                    <span class="text-white mx-1">&middot;</span>
                    <a class="link-light small" href="#!">Contact</a>
                </div>
            </div>
        </div>
    </footer>
    <div class="modal fade modal-search" id="searchModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body">
                    <input type="text" id="product-search" class="form-control" placeholder="Type to search...">
                </div>
            </div>
        </div>
    </div>
    <!-- Bootstrap core JS-->
    <script src="/templates/default/assets/node_modules/@popperjs/core/dist/umd/popper.min.js"></script>
    <script src="/templates/default/assets/node_modules/bootstrap/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Core theme JS-->
    <script src="<?php echo base_url("templates/default/assets/node_modules/jquery/dist/jquery.js") ?>"></script>
    <script src="/be-assets/plugins/jquery-ui/jquery-ui.min.js"></script>
    <script src="<?php echo base_url("templates/default/assets/ci4ms.js") ?>"></script>
    <?php echo $this->renderSection('javascript') ?>
</body>

</html>
