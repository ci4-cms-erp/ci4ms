<?php

namespace Modules\Auth\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class Ci4MsAuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (! auth()->loggedIn()) {
            return redirect()->route('login');
        }
        /* $user = auth()->user();
        if ($user && ($user->isBanned() || !$user->active)) {
            auth('session')->logout();

            return redirect()->route('login')->with('error', lang('Auth.bannedUser'));
        } */

        $router = service('router');
        $controllerName = $router->controllerName();

        $dbClassName = str_replace('\\', '-', $controllerName);
        if (substr($dbClassName, 0, 1) !== '-') {
            $dbClassName = '-' . $dbClassName;
        }

        $commonModel = new \ci4commonmodel\Models\CommonModel();
        $cacheKey = 'backend_page_info_' . md5($dbClassName . $router->methodName());
        if (! $page = cache($cacheKey)) {
            $page = $commonModel->lists(
                'auth_permissions_pages',
                '*',
                ['className' => $dbClassName, 'methodName' => $router->methodName()],
                'id ASC',
                0,
                0,
                [],
                [],
                [],
                ['isArray' => true, 'isReset' => true]
            );

            if ($page) {
                cache()->save($cacheKey, $page, 3600);
            }
        }

        if (auth()->user()->inGroup('superadmin')) {
            if (! $page) {
                return redirect()->to('/403');
            }
            return;
        } else {
            if (! $page) {
                return redirect()->to('/403');
            }
        }

        $neededAction = 'read';
        $pagePermissions = \json_decode($page->typeOfPermissions, JSON_UNESCAPED_UNICODE);
        if (array_key_exists('read_r', $pagePermissions) && $pagePermissions['read_r'] == 1) {
            $neededAction = 'read';
        }
        if (array_key_exists('update_r', $pagePermissions) && $pagePermissions['update_r'] == 1) {
            $neededAction = 'update';
        }
        if (array_key_exists('delete_r', $pagePermissions) && $pagePermissions['delete_r'] == 1) {
            $neededAction = 'delete';
        }
        if (array_key_exists('create_r', $pagePermissions) && $pagePermissions['create_r'] == 1) {
            $neededAction = 'create';
        }

        $permissionString = strtolower($page->pagename) . '.' . $neededAction;
        if (! auth()->user()->can($permissionString)) {
            return redirect()->to('/backend/403');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null) {}
}
