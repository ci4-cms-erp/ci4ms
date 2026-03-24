<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no" />
    <?php echo $this->renderSection('metatags') ?>
    <!-- Favicon-->
    <link rel="icon" type="image/x-icon" href="<?php echo base_url('templates/default/assets/vendor/modern-business/favicon.ico') ?>" />

    <!-- ── Google Font (from theme settings) ── -->
    <?php if (!empty($settings->templateInfos->fonts['googleFont'])):
        $gf = urlencode($settings->templateInfos->fonts['googleFont']);
        $gw = $settings->templateInfos->fonts['weights'] ?? '400,600,700'; ?>
        <link href="https://fonts.googleapis.com/css2?family=<?php echo $gf ?>:wght@<?php echo esc($gw) ?>&display=swap" rel="stylesheet">
        <?php endif;
    if (!empty($settings->templateInfos->theme_assets['styles'])):
        foreach ($settings->templateInfos->theme_assets['styles'] as $styleUrl):
            $styleUrl = (str_starts_with($styleUrl, 'http') || str_starts_with($styleUrl, '//')) ? $styleUrl : base_url(ltrim($styleUrl, '/')); ?>
            <link href="<?php echo esc($styleUrl) ?>" rel="stylesheet" />
        <?php endforeach;
    else: ?>
        <link href="<?php echo base_url('templates/default/assets/vendor/modern-business/styles.css') ?>" rel="stylesheet" />
        <link href="<?php echo base_url('templates/default/assets/ci4ms.css') ?>" rel="stylesheet" />
    <?php endif; ?>
    <link href="<?php echo base_url('templates/default/assets/modern-ci4ms.css') ?>" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <link href="<?php echo base_url('be-assets/plugins/jquery-ui/jquery-ui.min.css') ?>" rel="stylesheet" />
    <?php $isMulti = ($settings->siteLanguageMode ?? 'single') === 'multi';
    if ($isMulti) { ?>
        <link href="<?php echo base_url('be-assets/plugins/flag-icons/css/flag-icons.min.css') ?>" rel="stylesheet" />
    <?php }
    if (!empty($settings->templateInfos->customCss)): ?>
        <style>
            <?php echo $settings->templateInfos->customCss ?>
        </style>
    <?php endif;
    echo $this->renderSection('head') ?>
</head>

<body class="d-flex flex-column h-100">
    <main class="flex-shrink-0">
        <!-- Navigation-->
        <nav class="navbar navbar-expand-lg sticky-top">
            <div class="container px-5">
                <a class="navbar-brand d-flex align-items-center gap-2" href="<?php echo base_url() ?>">
                    <?php if (empty($settings->logo)) {
                        if (!empty($settings->siteName)) {
                            echo esc($settings->siteName);
                        } else {
                    ?>
                            <div class="bg-success p-2 rounded-3 d-flex align-items-center justify-content-center" style="width:38px; height:38px;">
                                <i class="bi bi-box-seam-fill text-white fs-5"></i>
                            </div>
                            <span class="fw-bold tracking-tight">CI4<span class="text-success">MS</span></span>
                        <?php
                        }
                    } else { ?>
                        <img src="<?php echo esc($settings->logo) ?>" alt="<?php echo esc($settings->siteName) ?>" class="img-fluid">
                    <?php } ?>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse"
                    data-bs-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                    aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarSupportedContent">
                    <ul class="navbar-nav ms-auto mb-2 mb-lg-0 align-items-center">
                        <?php menu($menus);

                        if (!empty($languages) && $isMulti): ?>
                            <li class="nav-item dropdown ms-lg-3">
                                <a class="nav-link dropdown-toggle d-flex align-items-center gap-2" href="#" id="langDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                                    <?php
                                    $currentLocale = service('request')->getLocale();
                                    $activeLang = null;
                                    foreach ($languages as $lang) {
                                        if ($lang->code === $currentLocale) {
                                            $activeLang = $lang;
                                            break;
                                        }
                                    }
                                    if (!$activeLang) $activeLang = reset($languages);
                                    if ($activeLang): ?>
                                        <span class="fi fi-<?php echo ($activeLang->code === 'en' ? 'gb' : $activeLang->code) ?> rounded-1"></span>
                                        <span class="d-none d-lg-inline fw-600 text-uppercase small"><?php echo $activeLang->code ?></span>
                                    <?php endif; ?>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end shadow-lg border-0 mt-2" aria-labelledby="langDropdown">
                                    <?php
                                    $currentSegments = service('request')->getUri()->getSegments();
                                    if (!empty($currentSegments) && in_array($currentSegments[0], array_column((array)$languages, 'code'))) {
                                        array_shift($currentSegments);
                                    }
                                    $remainingPath = implode('/', $currentSegments);
                                    foreach ($languages as $lang):
                                        $fallbackUrl = site_url($lang->code . ($remainingPath ? '/' . $remainingPath : '/'));
                                        $targetUrl   = $alternateLinks[$lang->code] ?? $fallbackUrl;
                                        if ($activeLang->code != $lang->code) { ?>
                                            <li>
                                                <a class="dropdown-item d-flex align-items-center gap-2 py-2" href="<?php echo $targetUrl ?>">
                                                    <span class="fi fi-<?php echo ($lang->code === 'en' ? 'gb' : $lang->code) ?> rounded-1"></span> <?php echo esc($lang->title) ?>
                                                </a>
                                            </li>
                                    <?php }
                                    endforeach; ?>
                                </ul>
                            </li>
                        <?php endif; ?>

                        <li class="nav-item ms-lg-2">
                            <button class="btn btn-primary d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#searchModal">
                                <i class="bi bi-search"></i> <span class="d-none d-xl-inline"><?php echo lang('Frontend.search') ?></span>
                            </button>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        <?php echo $this->renderSection('content') ?>
    </main>

    <!-- Footer-->
    <footer class="py-5 bg-dark mt-auto text-white">
        <div class="container px-5">
            <div class="row align-items-center justify-content-between flex-column flex-sm-row">
                <div class="col-auto">
                    <div class="small m-0">
                        <?php echo lang('Frontend.footer_copyright') ?>
                    </div>
                </div>
                <div class="col-auto">
                    <div class="d-flex gap-4">
                        <a class="link-light small text-decoration-none" href="#!"><?php echo lang('Frontend.learn_more') ?></a>
                        <a class="link-light small text-decoration-none" href="#!">Terms</a>
                        <a class="link-light small text-decoration-none" href="<?php echo base_url('contact') ?>"><?php echo lang('Frontend.contact') ?></a>
                    </div>
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
    <?php if (!empty($settings->templateInfos->theme_assets['scripts'])):
        foreach ($settings->templateInfos->theme_assets['scripts'] as $scriptUrl):
            $scriptUrl = (str_starts_with($scriptUrl, 'http') || str_starts_with($scriptUrl, '//')) ? $scriptUrl : base_url(ltrim($scriptUrl, '/')); ?>
            <script src="<?php echo esc($scriptUrl) ?>"></script>
        <?php endforeach;
    else: ?>
        <script src="<?php echo base_url('templates/default/assets/vendor/jquery/jquery.min.js') ?>"></script>
        <script src="<?php echo base_url('templates/default/assets/vendor/popperjs/popper.min.js') ?>"></script>
        <script src="<?php echo base_url('templates/default/assets/vendor/bootstrap/bootstrap.bundle.min.js') ?>"></script>
    <?php endif; ?>

    <script src="<?php echo base_url('be-assets/plugins/jquery-ui/jquery-ui.min.js') ?>"></script>

    <!-- ── Admin Edit Button ── -->
    <?php if (auth()->loggedIn() && (auth()->user()->inGroup('admin', 'superadmin', 'editor'))):
        $editId = $pageInfo->id ?? $infos->id ?? 0;
        $editModule = isset($pageInfo) ? 'pages' : 'blog';
        if ($editId > 0): ?>
            <a href="<?php echo route_to('grapesUpdate', $editId) ?>?module=<?php echo $editModule ?>"
                class="btn btn-primary shadow-lg position-fixed bottom-0 end-0 m-4 rounded d-flex align-items-center justify-content-center"
                style="width:50px;height:50px;z-index:1050;transition:transform .2s;"
                onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'"
                target="_blank"
                title="GrapesJS ile Düzenle">
                <i class="bi bi-pencil-square fs-4"></i>
            </a>
        <?php endif;
    endif;
    if (!empty($settings->templateInfos->display['backToTop'])): ?>
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
    <?php endif;

    echo $this->renderSection('javascript') ?>
</body>

</html>
