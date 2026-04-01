<?php

/**
 * --------------------------------------------------------------------
 * Include Modules Routes Files
 * --------------------------------------------------------------------
 */
if (is_dir(ROOTPATH . 'modules')) {
    $modulesPath = ROOTPATH . 'modules/';
    $modules = scandir($modulesPath);

    foreach ($modules as $module) {
        if ($module === '.' || $module === '..') continue;
        if (is_dir($modulesPath) . '/' . $module) {
            $routesPath = $modulesPath . $module . '/Config/Routes.php';
            if (is_file($routesPath)) require($routesPath);
            else continue;
        }
    }
}

$routes->get('/', 'Home::index', ['filter' => 'ci4ms', 'as' => 'home']);
