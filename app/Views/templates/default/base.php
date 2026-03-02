<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <?php echo $this->renderSection('metatags') ?>
    <!-- Favicon-->
    <link rel="icon" type="image/x-icon" href="/templates/default/assets/vendor/modern-business/favicon.ico" />

    <!-- ── Google Font (from theme settings) ── -->
    <?php if (!empty($settings->templateInfos->fonts['googleFont'])): ?>
        <?php $gf = urlencode($settings->templateInfos->fonts['googleFont']);
        $gw = $settings->templateInfos->fonts['weights'] ?? '400,600,700'; ?>
        <link href="https://fonts.googleapis.com/css2?family=<?php echo $gf ?>:wght@<?php echo esc($gw) ?>&display=swap" rel="stylesheet">
    <?php endif; ?>

    <!-- ── Theme CSS (from settings, with fallback) ── -->
    <?php if (!empty($settings->templateInfos->theme_assets['styles'])): ?>
        <?php foreach ($settings->templateInfos->theme_assets['styles'] as $styleUrl): ?>
            <link href="<?php echo esc($styleUrl) ?>" rel="stylesheet" />
        <?php endforeach; ?>
    <?php else: ?>
        <link href="/templates/default/assets/vendor/modern-business/styles.css" rel="stylesheet" />
        <link href="/templates/default/assets/ci4ms.css" rel="stylesheet" />
    <?php endif; ?>

    <link href="/be-assets/plugins/jquery-ui/jquery-ui.min.css" rel="stylesheet" />

    <!-- ── Custom CSS (from settings) ── -->
    <?php if (!empty($settings->templateInfos->customCss)): ?>
        <style>
            <?php echo $settings->templateInfos->customCss ?>
        </style>
    <?php endif; ?>

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
                    <div class="small m-0 text-white">
                        <?php
                        $copyrightText = $settings->templateInfos->footer['copyright'] ?? null;
                        echo 'Copyright &copy; ' . ($copyrightText ? esc($copyrightText) : esc($settings->siteName) . ' ' . date('Y'));
                        ?>
                    </div>
                </div>
                <div class="col-auto">
                    <?php
                    $footerLinks = $settings->templateInfos->footer['links'] ?? [];
                    if (!empty($footerLinks)):
                        $first = true;
                        foreach ($footerLinks as $link):
                            if (!$first) echo '<span class="text-white mx-1">&middot;</span>';
                            echo '<a class="link-light small" href="' . esc($link['url'] ?? '#') . '">' . esc($link['label'] ?? '') . '</a>';
                            $first = false;
                        endforeach;
                    else: ?>
                        <a class="link-light small" href="#!">Privacy</a>
                        <span class="text-white mx-1">&middot;</span>
                        <a class="link-light small" href="#!">Terms</a>
                        <span class="text-white mx-1">&middot;</span>
                        <a class="link-light small" href="#!">Contact</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </footer>

    <!-- Search Modal -->
    <div class="modal fade modal-search" id="searchModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-body">
                    <input type="text" id="product-search" class="form-control" placeholder="Type to search...">
                </div>
            </div>
        </div>
    </div>

    <!-- ── Theme JS (from settings, with fallback) ── -->
    <?php if (!empty($settings->templateInfos->theme_assets['scripts'])): ?>
        <?php foreach ($settings->templateInfos->theme_assets['scripts'] as $scriptUrl): ?>
            <script src="<?php echo esc($scriptUrl) ?>"></script>
        <?php endforeach; ?>
    <?php else: ?>
        <script src="/templates/default/assets/vendor/popperjs/popper.min.js"></script>
        <script src="/templates/default/assets/vendor/bootstrap/bootstrap.bundle.min.js"></script>
        <script src="<?php echo base_url("templates/default/assets/vendor/jquery/jquery.min.js") ?>"></script>
    <?php endif; ?>

    <script src="/be-assets/plugins/jquery-ui/jquery-ui.min.js"></script>

    <!-- ── Admin Edit Button ── -->
    <?php if (auth()->loggedIn() && (auth()->user()->inGroup('admin', 'superadmin'))): ?>
        <?php $editId = $pageInfo->id ?? $infos->id ?? 0; ?>
        <?php $editModule = isset($pageInfo) ? 'pages' : 'blog'; ?>
        <?php if ($editId > 0): ?>
            <a href="<?php echo route_to('grapesUpdate', $editId) ?>?module=<?php echo $editModule ?>"
                class="btn btn-primary shadow-lg position-fixed bottom-0 end-0 m-4 rounded d-flex align-items-center justify-content-center"
                style="width:50px;height:50px;z-index:1050;transition:transform .2s;"
                onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'"
                target="_blank"
                title="GrapesJS ile Düzenle">
                <i class="bi bi-pencil-square fs-4"></i>
            </a>
        <?php endif; ?>
    <?php endif; ?>

    <!-- ── Back to Top ── -->
    <?php if (!empty($settings->templateInfos->display['backToTop'])): ?>
        <button id="back-to-top" style="position:fixed;bottom:90px;right:28px;z-index:1040;width:40px;height:40px;border-radius:50%;background:#804f7b;color:#fff;border:none;cursor:pointer;display:none;align-items:center;justify-content:center;box-shadow:0 2px 8px rgba(0,0,0,.3);font-size:18px;" title="Üste Dön" onclick="window.scrollTo({top:0,behavior:'smooth'})">↑</button>
        <script>
            window.addEventListener('scroll', function() {
                var btn = document.getElementById('back-to-top');
                if (btn) btn.style.display = window.scrollY > 300 ? 'flex' : 'none';
            });
        </script>
    <?php endif; ?>

    <script src="<?php echo base_url("templates/default/assets/ci4ms.js") ?>"></script>

    <!-- ── Custom JS (from settings) ── -->
    <?php if (!empty($settings->templateInfos->customJs)): ?>
        <script>
            <?php echo $settings->templateInfos->customJs ?>
        </script>
    <?php endif; ?>

    <?php echo $this->renderSection('javascript') ?>
</body>

</html>
