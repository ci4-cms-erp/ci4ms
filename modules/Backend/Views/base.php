<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noimageindex, nofollow, nosnippet">

    <title>Kun-CMS/ERP <?= getGitVersion() ?> | <?=lang('Backend.'.$title->pagename)?></title>

    <!-- Google Font: Source Sans Pro -->
    <link rel="stylesheet"
          href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <?=link_tag("be-assets/plugins/fontawesome-free/css/all.min.css")?>
    <!-- Theme style -->
    <?=link_tag("be-assets/css/adminlte.min.css")?>
    <?=link_tag("be-assets/custom.css")?>
    <?= csrf_meta() ?>
    <?= $this->renderSection('head') ?>
</head>
<body class="hold-transition sidebar-mini">
<!-- Site wrapper -->
<div class="wrapper">
    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-dark navbar-kun-cms border-0">
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
        <a href="<?= base_url('backend') ?>" class="brand-link navbar-kun-cms text-center">
            <img src="/be-assets/img/logo-w.png" alt="" class="img-responsive" height="25">
        </a>

        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Sidebar user (optional) -->
            <div class="user-panel mt-3 pb-3 mb-3 d-flex">
                <div class="image d-flex align-items-center">
                    <img src="/be-assets/img/user2-160x160.jpg" class="img-circle elevation-2" alt="User Image">
                </div>
                <div class="info w-100">
                    <button class="btn btn-light w-100" type="button" data-toggle="collapse"
                            data-target="#collapseExample" aria-expanded="false" aria-controls="collapseExample">
                        <?= $logged_in_user->firstname . ' ' . $logged_in_user->sirname ?> <br>
                        <small class="text-success font-weight-bold">{ <?= $logged_in_user->name ?>
                            }</small>
                    </button>
                </div>
            </div>
            <div class="collapse mb-2 border-bottom" id="collapseExample">
                <div class="card card-body">
                    <span><i class="fas fa-user"></i> <a class="link-black" href="<?= route_to('profile') ?>">Profil</a></span>
                    <div class="dropdown-divider"></div>
                    <span><i class="fas fa-sign-out-alt"></i> <a class="link-black"
                                                                 href="<?= route_to('logout') ?>">Çıkış Yap</a></span>
                </div>
            </div>

            <!-- Sidebar Menu -->
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column nav-flat nav-child-indent"
                    data-widget="treeview" role="menu" data-accordion="false">
                    <?php function navigation($navigation, $uri, $child = null)
                    {
                        foreach ($navigation as $nav) :
                            $p = null;
                            foreach ($navigation as $item) {
                                if ($item->sefLink!='profile' && $item->sefLink === $uri) {
                                    $p = $item;
                                    break;
                                }
                            }
                            if ($nav->parent_pk == $child) : ?>
                                <li class="nav-item <?= (!empty($p) && $p->parent_pk == $nav->id) ? 'menu-is-opening menu-open' : '' ?>">
                                    <a href="<?php
                                    $u = explode('/', $nav->sefLink);
                                    if (empty($u[1])) echo route_to($u[0]);
                                    else
                                        echo route_to($u[0], $u[1]); ?>"
                                       class="nav-link <?php if(!empty($p)){ if($nav->sefLink == $uri || $p->parent_pk == $nav->id) echo 'active'; else echo '';} ?>">
                                        <i class="nav-icon <?= $nav->symbol ?>"></i>
                                        <p><?= lang('Backend.'.$nav->pagename) ?><?= ($nav->hasChild == true) ? '<i class="right fas fa-angle-left"></i>' : '' ?></p>
                                    </a>
                                    <?php if ($nav->hasChild == true): ?>
                                        <ul class="nav nav-treeview">
                                            <?php navigation($navigation, $uri, $nav->id); ?>
                                        </ul>
                                    <?php endif; ?>
                                </li>
                            <?php endif;
                        endforeach;
                    }

                    navigation($navigation, $uri);
                    ?>
                </ul>
            </nav>
            <!-- /.sidebar-menu -->
        </div>
        <!-- /.sidebar -->
    </aside>

    <!-- Content Wrapper. Contains page content -->
    <div class="content-wrapper">
        <?= $this->renderSection('content') ?>
    </div>
    <!-- /.content-wrapper -->

    <footer class="main-footer">
        <div class="float-right d-none d-sm-block">
            <b>Version</b> <?= getGitVersion() ?>
        </div>
        <strong>Copyright &copy; <?= date('Y') ?>.</strong> All rights reserved.
    </footer>

    <!-- Control Sidebar -->
    <aside class="control-sidebar control-sidebar-dark">
        <!-- Control sidebar content goes here -->
    </aside>
    <!-- /.control-sidebar -->
</div>
<!-- ./wrapper -->

<!-- jQuery -->
<?=script_tag("be-assets/plugins/jquery/jquery.min.js")?>
<!-- Bootstrap 4 -->
<?=script_tag("be-assets/plugins/bootstrap/js/bootstrap.bundle.min.js")?>
<!-- AdminLTE App -->
<?=script_tag("be-assets/js/adminlte.min.js")?>
<!-- AdminLTE for demo purposes -->
<?=script_tag("be-assets/js/demo.js")?>
<?= $this->renderSection('javascript') ?>
</body>
</html>
