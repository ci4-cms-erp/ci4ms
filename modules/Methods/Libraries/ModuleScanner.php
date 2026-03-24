<?php

namespace Modules\Methods\Libraries;

use ci4commonmodel\CommonModel;

class ModuleScanner
{
    /**
     * Tüm modülleri tarar, rotaları bulur, veritabanı (modules ve auth_permissions_pages) ile eşitler.
     * Yeni keşfedilen modüllerin "Migration" ve "Seeder" dosyalarını otomatik çalıştırır.
     *
     * @return bool Değişiklik olup olmadığını döndürür
     */
    public function runScan(): bool
    {
        $commonModel = new CommonModel();
        $collection = service('routes')->loadRoutes();
        $methods = \CodeIgniter\Router\Router::HTTP_METHODS;
        $tbody = [];
        $uriGenerator = new \CodeIgniter\Commands\Utilities\Routes\SampleURIGenerator();
        $filterCollector = new \CodeIgniter\Commands\Utilities\Routes\FilterCollector();
        $definedRouteCollector = new \CodeIgniter\Router\DefinedRouteCollector($collection);
        $findFilter = ['backendGuard'];

        // 1) Defined Routes
        foreach ($definedRouteCollector->collect() as $route) {
            if ($route['route'] === '__hot-reload') continue;
            $filters   = $filterCollector->get($route['method'], $uriGenerator->get($route['route']));
            $beforeFilters = (array)($filters['before'] ?? []);
            if (count(array_intersect($findFilter, $beforeFilters)) < 1) continue;
            
            preg_match('/\\\\Modules\\\\([^\\\\]+)\\\\Controllers\\\\[^:]+::([^\/]+)/', $route['handler'], $m);
            $routeName = ($route['route'] === $route['name']) ? '' : $route['name'];
            $role = $collection->getRoutesOptions(ltrim($route['route'], '\\'), $route['method'])['role'] ?? '';
            $roles = explode(',', $role);
            $r = [
                'create_r' => in_array('create', $roles),
                'update_r' => in_array('update', $roles),
                'read_r' => in_array('read', $roles),
                'delete_r' => in_array('delete', $roles),
            ];
            $rolesStr = json_encode($r, JSON_UNESCAPED_UNICODE);
            $tbody[] = [
                'method' => $route['method'],
                'route' => $route['route'],
                'seflink' => !empty($routeName) ? $routeName : 'backend',
                'pagename' => ($m[1] ?? 'Unknown') . '.' . $routeName,
                'handler' => $route['handler'],
                'className' => str_replace('\\', '-', preg_replace('/::.*$/', '', $route['handler'])),
                'module' => $m[1] ?? 'Unknown',
                'methodName'    =>  $m[2] ?? '',
                'role' => $role,
                'typeOfPermissions' => $rolesStr,
                'isBackoffice' => 1
            ];
        }

        // 2) Auto Routes
        if ($collection->shouldAutoRoute()) {
            $autoRoutesImproved = (config(\Config\Feature::class)->autoRoutesImproved ?? false) === true;
            if ($autoRoutesImproved) {
                $autoRouteCollector = new \CodeIgniter\Commands\Utilities\Routes\AutoRouterImproved\AutoRouteCollector(
                    $collection->getDefaultNamespace(),
                    $collection->getDefaultController(),
                    $collection->getDefaultMethod(),
                    $methods,
                    $collection->getRegisteredControllers('*')
                );
                $autoRoutes = $autoRouteCollector->get();
                $routingConfig = config(\Config\Routing::class);
                if ($routingConfig instanceof \Config\Routing) {
                    foreach ($routingConfig->moduleRoutes as $uri => $namespace) {
                        $autoRouteCollector = new \CodeIgniter\Commands\Utilities\Routes\AutoRouterImproved\AutoRouteCollector(
                            $namespace,
                            $collection->getDefaultController(),
                            $collection->getDefaultMethod(),
                            $methods,
                            $collection->getRegisteredControllers('*'),
                            $uri
                        );
                        $autoRoutes = [...$autoRoutes, ...$autoRouteCollector->get()];
                    }
                }
                foreach ($autoRoutes as $row) {
                    // $row: [0 => Method, 1 => Route, 2 => Name, 3 => Handler]
                    if ($row[1] === '__hot-reload') continue;
                    $filters = $filterCollector->get($row[0], $uriGenerator->get($row[1]));
                    if (count(array_intersect($findFilter, (array)($filters['before'] ?? []))) < 1) continue;
                    preg_match('/\\\\Modules\\\\([^\\\\]+)\\\\Controllers\\\\[^:]+::([^\/]+)/', $row[3], $m);
                    $role = $collection->getRoutesOptions(ltrim($row[1], '\\'), $row[0])['role'] ?? '';
                    $roles = explode(',', $role);
                    $r = [
                        'create_r' => in_array('create', $roles),
                        'update_r' => in_array('update', $roles),
                        'read_r' => in_array('read', $roles),
                        'delete_r' => in_array('delete', $roles),
                    ];
                    $rolesStr = json_encode($r, JSON_UNESCAPED_UNICODE);
                    $tbody[] = [
                        'method' => $row[0],
                        'route' => $row[1],
                        'pagename' => ($m[1] ?? 'Unknown') . '.' . $row[2],
                        'seflink' => !empty($row[2]) ? $row[2] : 'backend',
                        'handler' => $row[3],
                        'className' => str_replace('\\', '-', preg_replace('/::.*$/', '', $row[3])),
                        'module' => $m[1] ?? 'Unknown',
                        'methodName' => $m[2] ?? '',
                        'role' => $role,
                        'typeOfPermissions' => $rolesStr,
                        'isBackoffice' => 1
                    ];
                }
            } else {
                $autoRouteCollector = new \CodeIgniter\Commands\Utilities\Routes\AutoRouteCollector(
                    $collection->getDefaultNamespace(),
                    $collection->getDefaultController(),
                    $collection->getDefaultMethod()
                );
                $autoRoutes = $autoRouteCollector->get();
                foreach ($autoRoutes as $routes) {
                    // $routes: [Method, Route, Name, Handler]
                    if ($routes[1] === '__hot-reload') continue;
                    $filters   = $filterCollector->get($routes[0], $uriGenerator->get($routes[1]));
                    if (count(array_intersect($findFilter, (array)($filters['before'] ?? []))) < 1) continue;
                    preg_match('/\\\\Modules\\\\([^\\\\]+)\\\\Controllers\\\\[^:]+::([^\/]+)/', $routes[3], $m);
                    $role = $collection->getRoutesOptions(ltrim($routes[1], '\\'), $routes[0])['role'] ?? '';
                    $roles = explode(',', $role);
                    $r = [
                        'create_r' => in_array('create', $roles),
                        'update_r' => in_array('update', $roles),
                        'read_r' => in_array('read', $roles),
                        'delete_r' => in_array('delete', $roles),
                    ];
                    $rolesStr = json_encode($r, JSON_UNESCAPED_UNICODE);
                    $tbody[] = [
                        'method' => $routes[0],
                        'route' => $routes[1],
                        'pagename' => ($m[1] ?? 'Unknown') . '.' . $routes[2],
                        'seflink' => !empty($routes[2]) ? $routes[2] : 'backend',
                        'handler' => $routes[3],
                        'className' => str_replace('\\', '-', preg_replace('/::.*$/', '', $routes[3])),
                        'module' => $m[1] ?? 'Unknown',
                        'methodName' => $m[2] ?? '',
                        'role' => $role,
                        'typeOfPermissions' => $rolesStr,
                        'isBackoffice' => 1
                    ];
                }
            }
        }

        // Duplicate handler prevention
        $seen = [];
        $tbody = array_values(array_filter($tbody, function ($row) use (&$seen) {
            $key = strtolower($row['handler']);
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;
            return true;
        }));

        $pages = $commonModel->lists('auth_permissions_pages');
        $existingKeys = array_flip(
            array_map(
                fn($page) => $page->className . '\0' . $page->methodName,
                $pages
            )
        );

        $newKeys = [];
        foreach ($tbody as $index => $row) {
            $key = $row['className'] . '\0' . $row['methodName'];
            $newKeys[$key] = $index;
        }

        $isChanged = false;

        // --- Modül Temizleme (Fiziksel klasörü silinen modüller) ---
        $existingModules = $commonModel->lists('modules');
        $moduleFolders = array_map(function($path) {
            return strtolower(basename($path));
        }, glob(ROOTPATH . 'modules/*', GLOB_ONLYDIR));

        foreach ($existingModules as $exModule) {
            if (!in_array(strtolower($exModule->name), $moduleFolders)) {
                $commonModel->remove('auth_permissions_pages', ['module_id' => $exModule->id]);
                $commonModel->remove('modules', ['id' => $exModule->id]);
                $isChanged = true;
            }
        }

        $uniqueKeys = array_diff_key($newKeys, $existingKeys);
        $removedPages = array_diff_key($existingKeys, $newKeys);
        
        if (!empty($removedPages)) {
            foreach ($removedPages as $key => $removedPage) {
                $remove = explode('\0', $key);
                if (!empty(str_replace('\0', '', $key))) {
                    $commonModel->remove('auth_permissions_pages', ['className' => $remove['0'], 'methodName' => $remove['1']]);
                    $isChanged = true;
                }
            }
        }

        $newModulesDiscovered = []; // Migration ve Seeder tetiklenecek modüller

        $insertBach = [];
        $menusFromConfig = [];

        if (!empty($uniqueKeys)) {
            $module_id = null;
            foreach ($uniqueKeys as $uniqueKey) {
                $modName = $tbody[$uniqueKey]['module'];
                $configClass = "Modules\\{$modName}\\Config\\{$modName}Config";
                $modConfig = class_exists($configClass) ? new $configClass() : null;

                $moduleIcon = 'fas fa-cogs';
                if ($modConfig && isset($modConfig->moduleInfo['icon'])) {
                    $moduleIcon = $modConfig->moduleInfo['icon'];
                }

                $module_id = $commonModel->selectOne('modules', ['name' => $modName], 'id');
                if (empty($module_id) || empty($module_id->id)) {
                    $newId = $commonModel->create('modules', [
                        'name' => $modName,
                        'icon' => $moduleIcon,
                        'isActive' => true
                    ]);
                    $module_id = (object) ['id' => $newId];
                    // YENİ ÖZELLİK: Sisteme ilk defa giren modülleri tespit edip kaydediyoruz.
                    if (!in_array($modName, $newModulesDiscovered)) {
                        $newModulesDiscovered[] = $modName;
                    }
                } else {
                    if ($modConfig && isset($modConfig->moduleInfo['icon'])) {
                        $commonModel->edit('modules', ['icon' => $moduleIcon], ['id' => $module_id->id]);
                    }
                }

                $pageName = $tbody[$uniqueKey]['pagename'];
                $symbol = '';
                $inNavigation = 0;
                $hasChild = 0;
                $pageSort = null;
                $parent_pk = null;

                if ($modConfig && isset($modConfig->menus) && isset($modConfig->menus[$pageName])) {
                    $m = $modConfig->menus[$pageName];
                    $symbol       = $m['icon'] ?? '';
                    $inNavigation = $m['inNavigation'] === true ? 1 : 0;
                    $hasChild     = $m['hasChild'] === true ? 1 : 0;
                    $pageSort     = $m['pageSort'] ?? null;
                    $parent_pk    = $m['parent_pk'] ?? null;
                }

                $insertBach[] = [
                    'pagename'          => $pageName,
                    'className'         => $tbody[$uniqueKey]['className'],
                    'methodName'        => $tbody[$uniqueKey]['methodName'],
                    'seflink'           => $tbody[$uniqueKey]['seflink'],
                    'typeOfPermissions' => $tbody[$uniqueKey]['typeOfPermissions'],
                    'module_id'         => $module_id->id,
                    'isBackoffice'      => 1,
                    'isActive'          => 1,
                    'symbol'            => $symbol,
                    'inNavigation'      => $inNavigation,
                    'hasChild'          => $hasChild,
                    'pageSort'          => $pageSort,
                    'parent_pk'         => null
                ];

                if (!empty($parent_pk)) {
                    $menusFromConfig[$pageName] = $parent_pk;
                }
            }
            
            $commonModel->createMany('auth_permissions_pages', $insertBach);
            
            if (!empty($menusFromConfig)) {
                foreach ($menusFromConfig as $childPageName => $parentPageName) {
                    $parentObj = $commonModel->selectOne('auth_permissions_pages', ['pagename' => $parentPageName], 'id');
                    if (!empty($parentObj) && !empty($parentObj->id)) {
                        $commonModel->edit('auth_permissions_pages', ['parent_pk' => $parentObj->id], ['pagename' => $childPageName]);
                    }
                }
            }

            $isChanged = true;
        }

        // Yeni modüller bulunduysa Seed ve Migration çalıştır
        if (!empty($newModulesDiscovered)) {
            $installer = new ModuleInstaller();
            foreach ($newModulesDiscovered as $modName) {
                // Modüle ait varsa Migration ve Seeder'ı tetikle
                $installer->runModuleMigrations($modName);
                $installer->runModuleSeeder($modName);
            }
        }

        if ($isChanged) {
            cache()->delete('sidebar_menu');
        }

        return $isChanged;
    }
}
