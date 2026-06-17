<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noimageindex, nofollow, nosnippet">

    <title>Kun-CMS/ERP | <?php echo $this->renderSection('title') ?> - <?php echo getenv('app.version') ?></title>
    <?php echo link_tag("be-assets/img/favicon.ico", "shortcut icon", "image/x-icon") ?>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link href="/be-assets/plugins/fontawesome-free/css/all.min.css" rel="stylesheet" type="text/css">
    <link href="/be-assets/plugins/sweetalert2-theme-bootstrap-4/bootstrap-4.min.css" rel="stylesheet" type="text/css">
    <link href="/be-assets/css/adminlte.min.css" rel="stylesheet" type="text/css">
    <link href="/be-assets/css/ci4ms-premium.css" rel="stylesheet" type="text/css">
    <link href="/be-assets/custom.css" rel="stylesheet" type="text/css">

    <?php echo csrf_meta();
    echo '<meta name="csrf-token-name" content="' . csrf_token() . '">';
    echo $this->renderSection('head') ?>
</head>

<body class="hold-transition sidebar-mini">
    <!-- Site wrapper -->
    <div class="wrapper">
        <!-- Navbar -->
        <nav class="main-header navbar navbar-expand navbar-dark navbar-ci4ms border-0">
            <!-- Left navbar links -->
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
                </li>
            </ul>
        </nav>
        <!-- /.navbar -->

        <!-- Main Sidebar Container -->
        <aside class="main-sidebar sidebar-light-olive elevation-1">
            <!-- Brand Logo -->
            <a href="<?php echo base_url('backend') ?>" class="brand-link navbar-ci4ms text-center">
                <img src="/be-assets/img/logo-w.png" alt="" class="img-fluid">
            </a>

            <!-- Sidebar -->
            <div class="sidebar">
                <!-- Sidebar user (optional) -->
                <div class="user-panel mt-md-3 pb-md-3 mb-md-3 d-flex">
                    <div class="image d-flex align-items-center p-sm-0">
                        <img src="<?php echo $logged_in_user->profileIMG ?>" id="profileIMGsidebar"
                            class="img-circle elevation-2" alt="User Image">
                    </div>
                    <div class="info d-block w-100">
                        <button class="btn btn-light btn-sm btn-block text-center" type="button" data-toggle="collapse"
                            data-target="#collapseExample" aria-expanded="false" aria-controls="collapseExample">
                            <span
                                class="d-block font-weight-bold"><?php echo $logged_in_user->firstname . ' ' . $logged_in_user->surname ?></span>
                            <small class="text-success font-weight-bold">{
                                <?php echo implode(', ', $logged_in_user->getGroups()); ?> }</small>
                        </button>
                    </div>
                </div>
                <div class="collapse mb-2 border-bottom" id="collapseExample">
                    <div class="card card-body">
                        <span><i class="fas fa-user"></i> <a class="link-black"
                                href="<?php echo route_to('profile') ?>">Profile</a></span>
                        <div class="dropdown-divider"></div>
                        <span><i class="fas fa-sign-out-alt"></i> <a class="link-black"
                                href="<?php echo route_to('logout') ?>">Logout</a></span>
                    </div>
                </div>

                <!-- Sidebar Menu -->
                <nav class="mt-2">
                    <ul class="nav nav-pills nav-sidebar flex-column nav-flat nav-child-indent" data-widget="treeview"
                        role="menu" data-accordion="false">
                        <?php
                        // 1. Indexing for O(1) lookups and grouping Children
                        $nav_by_id = [];
                        $nav_by_parent = [];
                        foreach ($navigation as $item) {
                            if ($item->sefLink === 'profile' || empty($item->sefLink))
                                continue;
                            $nav_by_id[(int) $item->id] = $item;
                            $nav_by_parent[(int) $item->parent_pk][] = $item;
                        }

                        // 2. Identify Active IDs (O(N) path lookup)
                        $active_ids = [];
                        $current_id = isset($title->id) ? (int) $title->id : null;

                        // Fallback search if title doesn't match a specific nav item directly
                        if (!$current_id || !isset($nav_by_id[$current_id])) {
                            $matched_item = null;
                            foreach ($navigation as $item) {
                                if ($item->sefLink === 'profile' || empty($item->sefLink))
                                    continue;
                                if ($item->sefLink === $uri || str_starts_with($uri, $item->sefLink . '/')) {
                                    if ($matched_item === null || strlen($item->sefLink) > strlen($matched_item->sefLink)) {
                                        $matched_item = $item;
                                    }
                                }
                            }
                            if ($matched_item)
                                $current_id = (int) $matched_item->id;
                        }

                        while ($current_id && isset($nav_by_id[$current_id])) {
                            $active_ids[$current_id] = true;
                            $current_id = (int) $nav_by_id[$current_id]->parent_pk;
                        }

                        if (!function_exists('render_sidebar_menu')) {
                            function render_sidebar_menu($grouped_nav, $active_ids, $parent_id = 0)
                            {
                                if (!isset($grouped_nav[$parent_id]))
                                    return;

                                foreach ($grouped_nav[$parent_id] as $nav) {
                                    $id = (int) $nav->id;
                                    $is_active = isset($active_ids[$id]);
                                    $has_child = (bool) $nav->hasChild;

                                    // Maintenance state (annotated per-request in generateSidebar)
                                    $maintenance_badge = !empty($nav->maintenanceBadge);
                                    $maintenance_disabled = !empty($nav->maintenanceDisabled);

                                    // Calculate CSS classes
                                    $li_class = ($is_active && $has_child) ? 'menu-is-opening menu-open' : '';
                                    $link_class = $is_active ? 'active' : '';
                                    if ($maintenance_disabled)
                                        $link_class = trim($link_class . ' disabled');

                                    // Pre-calculate URL
                                    $u = explode('/', $nav->sefLink);
                                    $href = empty($u[1]) ? route_to($u[0]) : route_to($u[0], $u[1]);
                                    if ($maintenance_disabled)
                                        $href = 'javascript:void(0)';
                                    ?>
                                    <li class="nav-item <?php echo $li_class ?>">
                                        <a href="<?php echo $href ?>" class="nav-link <?php echo $link_class ?>" <?php echo $maintenance_disabled ? 'tabindex="-1" aria-disabled="true"' : '' ?>>
                                            <i class="nav-icon <?php echo $nav->symbol ?>"></i>
                                            <p>
                                                <?php echo lang($nav->pagename) ?>
                                                <?php if ($maintenance_badge): ?>
                                                    <span class="right badge badge-warning" title="<?php echo lang('Backend.menuInMaintenance') ?>"><i class="fas fa-tools"></i></span>
                                                <?php endif; ?>
                                                <?php echo $has_child ? '<i class="right fas fa-angle-left"></i>' : '' ?>
                                            </p>
                                        </a>
                                        <?php if ($has_child): ?>
                                            <ul class="nav nav-treeview">
                                                <?php render_sidebar_menu($grouped_nav, $active_ids, $id); ?>
                                            </ul>
                                        <?php endif; ?>
                                    </li>
                                    <?php
                                }
                            }
                        }

                        render_sidebar_menu($nav_by_parent, $active_ids, 0);
                        ?>
                    </ul>
                </nav>
                <!-- /.sidebar-menu -->
            </div>
            <!-- /.sidebar -->
        </aside>

        <!-- Content Wrapper. Contains page content -->
        <div class="content-wrapper">
            <?php echo $this->renderSection('content') ?>
        </div>
        <!-- /.content-wrapper -->

        <footer class="main-footer">
            <div class="float-right d-none d-sm-block">
                <b>Version</b> <?php echo getenv('app.version') ?>
            </div>
            <strong>Copyright &copy; <?php echo date('Y') ?>.</strong> All rights reserved.
            <a href="https://patreon.com/cw/bertugfahriozer" target="_blank" class="text-danger">
                <i class="fas fa-heart"></i> Support me on Patreon
            </a>
        </footer>

        <!-- Control Sidebar -->
        <aside class="control-sidebar control-sidebar-dark">
            <!-- Control sidebar content goes here -->
        </aside>
        <!-- /.control-sidebar -->
    </div>
    <!-- ./wrapper -->

    <!-- jQuery -->
    <?php echo script_tag("be-assets/plugins/jquery/jquery.min.js");
    echo script_tag("be-assets/plugins/bootstrap/js/bootstrap.bundle.min.js");
    echo script_tag("be-assets/js/adminlte.min.js");
    echo script_tag("be-assets/plugins/sweetalert2/sweetalert2.min.js"); ?>
    <script type="text/javascript" <?php echo csp_script_nonce(); ?>>
        window.CI4MS_LOCALE = '<?php echo env('app.defaultLocale', 'tr') ?>';
        window.CI4MS_IDLE_ENABLED = <?php echo (setting('Auth.idleTimeoutEnabled') !== false) ? 'true' : 'false' ?>;
        window.CI4MS_IDLE_MINUTES = <?php echo (int) (setting('Auth.idleTimeoutMinutes') ?: 15) ?>;
    </script>
    <?php echo script_tag("be-assets/js/ci4ms.js");
    echo view('Modules\Backend\Views\sweetalert_message_block', [], ['debug' => false]);
    echo $this->renderSection('javascript'); ?>
</body>

</html>
