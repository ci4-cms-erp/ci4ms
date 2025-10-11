<?php

namespace Modules\Methods\Controllers;

class Methods extends \Modules\Backend\Controllers\BaseController
{
    public function index()
    {
        if ($this->request->is('post') && $this->request->isAJAX()) {
            $flag = false;
            if (!empty($this->request->getPost('module_id')) && $this->commonModel->edit('modules', ['isActive' => $this->request->getPost('status') == 'inactive' ? false : true], ['id' => $this->request->getPost('module_id')]) && $this->commonModel->edit('auth_permissions_pages', ['isActive' => $this->request->getPost('status') == 'inactive' ? false : true], ['module_id' => $this->request->getPost('module_id')]))
                $flag = true;
            if (!empty($this->request->getPost('page_id')) && $this->commonModel->edit('auth_permissions_pages', ['isActive' => $this->request->getPost('status') == 'inactive' ? false : true], ['id' => $this->request->getPost('page_id')]))
                $flag = true;
            if ($flag == true) {
                cache()->delete("{$this->defData['logged_in_user']->id}_permissions");
                return $this->respond(['success' => 'success'], 200);
            }
        }
        $methodsModel = new \Modules\Methods\Models\MethodsModel();
        $this->defData['modules'] = $methodsModel->getModules();
        return view('Modules\Methods\Views\list', $this->defData);
    }

    public function create()
    {
        if ($this->request->is('post')) {
            $valData = ([
                'pagename' => ['label' => '', 'rules' => 'required'],
                'sefLink' => ['label' => '', 'rules' => 'required'],
                'typeOfPermissions' => ['label' => '', 'rules' => 'required'],
            ]);
            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            $roles = $this->request->getPost('typeOfPermissions');
            $r = [
                'create_r' => in_array('create', $roles),
                'update_r' => in_array('update', $roles),
                'read_r' => in_array('read', $roles),
                'delete_r' => in_array('delete', $roles),
            ];
            $roles = json_encode($r, JSON_UNESCAPED_UNICODE);
            if ($this->commonModel->create('auth_permissions_pages', [
                'pagename' => $this->request->getPost('pagename'),
                'description' => $this->request->getPost('description') ?? '',
                'className' => $this->request->getPost('className') ?? '',
                'methodName' => $this->request->getPost('methodName') ?? '',
                'sefLink' => $this->request->getPost('sefLink'),
                'hasChild' => $this->request->getPost('hasChild') ?? 0,
                'pageSort' => !empty($this->request->getPost('pageSort')) ? $this->request->getPost('pageSort') : NULL,
                'parent_pk' => $this->request->getPost('parent_pk') ?? NULL,
                'symbol' => !empty($this->request->getPost('symbol')) ? $this->request->getPost('symbol') : NULL,
                'inNavigation' => $this->request->getPost('inNavigation') ?? 0,
                'isBackoffice' => $this->request->getPost('isBackoffice') ?? 0,
                'typeOfPermissions' => $this->request->getPost('typeOfPermissions')
            ])) {
                $id = $this->defData['logged_in_user']->id;
                cache()->delete("{$id}_permissions");
                return redirect()->route('list')->with('success', lang('Backend.created',[$this->request->getPost('pagename')]));
            } else
                return redirect()->back()->withInput()->with('error', lang('Backend.notCreated',[$this->request->getPost('pagename')]));
        }
        $this->defData['modules'] = $this->commonModel->lists('modules');
        $this->defData['permPages'] = $this->commonModel->lists('auth_permissions_pages');
        return view('Modules\Methods\Views\create', $this->defData);
    }

    public function update(int $pk)
    {
        if ($this->request->is('post')) {
            $valData = ([
                'pagename' => ['label' => '', 'rules' => 'required'],
                'sefLink' => ['label' => '', 'rules' => 'required'],
                'typeOfPermissions' => ['label' => '', 'rules' => 'required']
            ]);
            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            $roles = $this->request->getPost('typeOfPermissions');
            $r = [
                'create_r' => in_array('create', $roles),
                'update_r' => in_array('update', $roles),
                'read_r' => in_array('read', $roles),
                'delete_r' => in_array('delete', $roles),
            ];
            $roles = json_encode($r, JSON_UNESCAPED_UNICODE);
            if ($this->commonModel->edit('auth_permissions_pages', [
                'pagename' => $this->request->getPost('pagename'),
                'description' => $this->request->getPost('description') ?? '',
                'className' => $this->request->getPost('className') ?? '',
                'methodName' => $this->request->getPost('methodName') ?? '',
                'sefLink' => $this->request->getPost('sefLink'),
                'hasChild' => (bool)$this->request->getPost('hasChild') == true ? 1 : 0,
                'pageSort' => $this->request->getPost('pageSort') ?? 0,
                'parent_pk' => $this->request->getPost('parent_pk') ?? NULL,
                'symbol' => $this->request->getPost('symbol') ?? NULL,
                'inNavigation' => (bool)$this->request->getPost('inNavigation') == true ? 1 : 0,
                'isBackoffice' => (bool)$this->request->getPost('isBackoffice') == true ? 1 : 0,
                'typeOfPermissions' => $roles
            ], ['id' => $pk])) {
                $id = $this->defData['logged_in_user']->id;
                cache()->delete("{$id}_permissions");
                return redirect()->route('list')->with('success', lang('Backend.updated',[$this->request->getPost('pagename')]));
            } else
                return redirect()->back()->withInput()->with('error', lang('Backend.notUpdated',[$this->request->getPost('pagename')]));
        }
        $this->defData['method'] = $this->commonModel->selectOne('auth_permissions_pages', ['id' => $pk]);
        $this->defData['methods'] = $this->commonModel->lists('auth_permissions_pages', '*', ['id!=' => $pk,'inNavigation'=>true],'pagename ASC');
        $this->defData['modules'] = $this->commonModel->lists('modules');
        $this->defData['permPages'] = $this->commonModel->lists('auth_permissions_pages');
        return view('Modules\Methods\Views\update', $this->defData);
    }

    public function moduleScan()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $sortByHandler = $this->request->getGet('sortBy') === 'handler';
        $host = $this->request->getGet('host');

        if ($host !== null) {
            $request = service('request');
            $_SERVER = $request->getServer();
            $_SERVER['HTTP_HOST'] = $host;
            $request->setGlobal('server', $_SERVER);
        }

        $collection = service('routes')->loadRoutes();

        if ($host !== null) unset($_SERVER['HTTP_HOST']);

        $methods = \CodeIgniter\Router\Router::HTTP_METHODS;
        $tbody = [];
        $uriGenerator = new \CodeIgniter\Commands\Utilities\Routes\SampleURIGenerator();
        $filterCollector = new \CodeIgniter\Commands\Utilities\Routes\FilterCollector();

        $definedRouteCollector = new \CodeIgniter\Router\DefinedRouteCollector($collection);
        $findFilter = ['backendAfterLoginFilter'];
        foreach ($definedRouteCollector->collect() as $route) {
            if ($route['route'] === '__hot-reload') continue;
            $filters   = $filterCollector->get($route['method'], $uriGenerator->get($route['route']));
            if (count(array_intersect($findFilter, $filters['before'])) < 1) continue;
            preg_match('/\\\\Modules\\\\([^\\\\]+)\\\\Controllers\\\\[^:]+::([^\/]+)/', $route['handler'], $m);
            $routeName = ($route['route'] === $route['name']) ? '' : $route['name'];
            $role = $collection->getRoutesOptions(ltrim($route['route'], '\\'), $route['method'])['role'];
            $roles = explode(',', $role);
            $r = [
                'create_r' => in_array('create', $roles),
                'update_r' => in_array('update', $roles),
                'read_r' => in_array('read', $roles),
                'delete_r' => in_array('delete', $roles),
            ];
            $roles = json_encode($r, JSON_UNESCAPED_UNICODE);
            $tbody[] = [
                'method' => $route['method'],
                'route' => $route['route'],
                'seflink' => !empty($routeName) ? $routeName : 'backend',
                'pagename' => $m[1] . '.' . $routeName,
                'handler' => $route['handler'],
                'className' => str_replace('\\', '-', preg_replace('/::.*$/', '', $route['handler'])),
                'module' => $m[1],
                'methodName'    =>  $m[2],
                'role' => $role,
                'typeOfPermissions' => $roles,
                'isBackoffice' => 1
            ];
        }

        // 2) Auto routes (if enabled)
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
                    // $row: [Method, Route, Name, Handler, Before Filters, After Filters]
                    if ($row[1] === '__hot-reload') continue;
                    $filters = $filterCollector->get($row[0], $uriGenerator->get($row[1]));
                    if (count(array_intersect($findFilter, $row[4])) < 1) continue;
                    preg_match('/\\\\Modules\\\\([^\\\\]+)\\\\Controllers\\\\[^:]+::([^\/]+)/', $row[4], $m);
                    $role = $collection->getRoutesOptions(ltrim($row[1], '\\'), $row[0])['role'];
                    $roles = explode(',', $role);
                    $r = [
                        'create_r' => in_array('create', $roles),
                        'update_r' => in_array('update', $roles),
                        'read_r' => in_array('read', $roles),
                        'delete_r' => in_array('delete', $roles),
                    ];
                    $roles = json_encode($r, JSON_UNESCAPED_UNICODE);
                    $tbody[] = [
                        'method' => $row[0],
                        'route' => $row[1],
                        'pagename' => $m[1] . '.' . $row[2],
                        'seflink' => !empty($row[2]) ? $row[2] : 'backend',
                        'handler' => $row[3],
                        'className' => str_replace('\\', '-', preg_replace('/::.*$/', '', $row[3])),
                        'module' => $m[1],
                        'methodName' => $m[2],
                        'role' => $role,
                        'typeOfPermissions' => $roles,
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
                    if ($routes[1] === '__hot-reload') continue;
                    // There is no `AUTO` method, but it is intentional not to get route filters.
                    $filters   = $filterCollector->get($route[0], $uriGenerator->get($route[1]));
                    if (count(array_intersect($findFilter, $filters['before'])) < 1) continue;
                    preg_match('/\\\\Modules\\\\([^\\\\]+)\\\\Controllers\\\\[^:]+::([^\/]+)/', $routes[4], $m);
                    $role = $collection->getRoutesOptions(ltrim($routes[1], '\\'), $routes[0])['role'];
                    $roles = explode(',', $role);
                    $r = [
                        'create_r' => in_array('create', $roles),
                        'update_r' => in_array('update', $roles),
                        'read_r' => in_array('read', $roles),
                        'delete_r' => in_array('delete', $roles),
                    ];
                    $roles = json_encode($r, JSON_UNESCAPED_UNICODE);
                    $tbody[] = [
                        'method' => $routes[0],
                        'route' => $routes[1],
                        'pagename' => $m[1] . '.' . $routes[2],
                        'seflink' => !empty($routes[2]) ? $routes[2] : 'backend',
                        'handler' => $routes[3],
                        'className' => str_replace('\\', '-', preg_replace('/::.*$/', '', $routes[3])),
                        'module' => $m[1],
                        'methodName' => $m[2],
                        'role' => $role,
                        'typeOfPermissions' => $roles,
                        'isBackoffice' => 1
                    ];
                }
            }
        }
        $seen = [];
        $tbody = array_values(array_filter($tbody, function ($row) use (&$seen) {
            $key = strtolower($row['handler']);
            if (isset($seen[$key])) {
                return false;
            }
            $seen[$key] = true;
            return true;
        }));
        if ($sortByHandler) {
            usort($tbody, static function ($a, $b) {
                return strcmp($a['handler'], $b['handler']);
            });
        }
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        $this->response->setHeader('Content-Type', 'application/json; charset=utf-8');
        $pages = $this->commonModel->lists('auth_permissions_pages');

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

        $uniqueKeys = array_diff_key($newKeys, $existingKeys);
        $uniquePages = [];
        foreach ($uniqueKeys as $key => $index) {
            $uniquePages[] = $tbody[$index];
        }
        $insertBach = [];
        if (!empty($uniqueKeys)) {
            $module_id=null;
            foreach ($uniqueKeys as $uniqueKey) {
                $module_id = $this->commonModel->selectOne('modules', ['name' => $tbody[$uniqueKey]['module']],'id');
                if (empty($module_id->id)) {
                    $module_id->id = $this->commonModel->create('modules', [
                        'name' => $tbody[$uniqueKey]['module'],
                        'isActive' => true
                    ]);
                }
                $insertBach[] = [
                    'pagename' => $tbody[$uniqueKey]['pagename'],
                    'className' => $tbody[$uniqueKey]['className'],
                    'methodName' => $tbody[$uniqueKey]['methodName'],
                    'seflink' => $tbody[$uniqueKey]['seflink'],
                    'typeOfPermissions' => $tbody[$uniqueKey]['typeOfPermissions'],
                    'module_id' => $module_id->id,
                    'isBackoffice' => 1,
                    'isActive' => 1
                ];
            }
            $this->commonModel->createMany('auth_permissions_pages', $insertBach);
            return $this->respondCreated(['result' => true]);
        } else return $this->respond(['result' => false]);
    }
}
