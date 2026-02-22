<?php

namespace Modules\Users\Controllers;

use CodeIgniter\I18n\Time;
use Exception;

class PermgroupController extends \Modules\Backend\Controllers\BaseController
{
    public function groupList($num = 1)
    {
        if ($this->request->is('post') && $this->request->isAJAX()) {
            $data = clearFilter($this->request->getPost());
            $like = $data['search']['value'];
            $l = [];
            $postData = ['group!=' => 'superadmin'];
            if (!empty($like)) $l = ['title' => $like];
            $results = $this->commonModel->lists('auth_groups', '*', $postData, 'id DESC', ($data['length'] == '-1') ? 0 : (int)$data['length'], ($data['length'] == '-1') ? 0 : (int)$data['start'], $l);
            $totalRecords = $this->commonModel->count('auth_groups', $postData, $l);
            foreach ($results as $result) {
                $result->actions = '<a href="' . route_to('group_update', $result->id) . '" class="btn btn-outline-info btn-sm">' . lang('Backend.update') . '</a>';
            }
            $data = [
                'draw' => intval($data['draw']),
                'iTotalRecords' => $totalRecords,
                'iTotalDisplayRecords' => $totalRecords,
                'aaData' => $results,
            ];
            return $this->respond($data, 200);
        }
        return view('Modules\Users\Views\permGroup\list', $this->defData);
    }

    public function group_create()
    {
        if ($this->request->is('post')) {
            $valData = ([
                'groupName' => ['label' => 'Yetki Grubu Adı', 'rules' => 'required|regex_match[/^[^<>{}]*$/u]|is_unique[auth_groups.group]'],
                'description' => ['label' => 'Grup Açıklaması', 'rules' => 'required'],
                'seflink' => ['label' => 'Seflink', 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
                'perms' => ['label' => 'İzinler', 'rules' => 'required']
            ]);

            if ($this->validate($valData) == false)
                return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());

            $data = [
                'group' => esc($this->request->getPost('groupName')),
                'description' => esc($this->request->getPost('description')),
                'redirect' => esc($this->request->getPost('seflink')),
                'who_created' => user_id()
            ];

            foreach ($this->request->getPost('perms') as $key => $perm) {
                $roles = explode('|', $perm['roles']);
                $permissions[] = [
                    'page_id' => $key,
                    'create_r' => in_array('create_r', $roles),
                    'update_r' => in_array('update_r', $roles),
                    'read_r' => in_array('read_r', $roles),
                    'delete_r' => in_array('delete_r', $roles),
                    'who_perm' => user_id(),
                    'created_at' => new Time('now')
                ];
            }
            $data['permissions'] = json_encode($permissions, JSON_UNESCAPED_UNICODE);
            $result = $this->commonModel->create('auth_groups', $data);
            if (empty($result)) return redirect()->back()->withInput()->with('errors', lang('Backend.notCreated', [$this->request->getPost('groupName')]));
            else return redirect()->to(route_to('groupList'))->with('message', lang('Backend.created', [$this->request->getPost('groupName')]));
        }
        $methodsModel = new \Modules\Methods\Models\MethodsModel();
        $this->defData['modules'] = $methodsModel->getModules();
        return view('Modules\Users\Views\permGroup\create', $this->defData);
    }

    public function group_update($id)
    {
        if ($this->request->is('post')) {
            $valData = ([
                'groupName' => ['label' => 'Yetki Grubu Adı', 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
                'description' => ['label' => 'Grup Açıklaması', 'rules' => 'required'],
                'seflink' => ['label' => 'Seflink', 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
                'perms' => ['label' => 'İzinler', 'rules' => 'required']
            ]);

            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            $data = [
                'group' => esc($this->request->getPost('groupName')),
                'description' => esc($this->request->getPost('description')),
                'redirect' => esc($this->request->getPost('seflink')),
                'who_created' => user_id()
            ];

            foreach ($this->request->getPost('perms') as $key => $perm) {
                $roles = explode('|', $perm['roles']);
                $permissions[] = [
                    'page_id' => $key,
                    'create_r' => in_array('create_r', $roles),
                    'update_r' => in_array('update_r', $roles),
                    'read_r' => in_array('read_r', $roles),
                    'delete_r' => in_array('delete_r', $roles),
                    'who_perm' => user_id(),
                    'created_at' => new Time('now')
                ];
            }

            $data['permissions'] = json_encode($permissions, JSON_UNESCAPED_UNICODE);
            if ($this->commonModel->edit('auth_groups', $data, ['id' => $id])) {
                cache()->delete("shield_auth_dynamic_config");
                return redirect()->route('groupList')->with('message', lang('Backend.updated', [$this->request->getPost('groupName')]));
            } else return redirect()->back()->withInput()->with('error', lang('Backend.notUpdated', [$this->request->getPost('groupName')]));
        }
        $methodsModel = new \Modules\Methods\Models\MethodsModel();
        $this->defData['modules'] = $methodsModel->getModules();
        $this->defData['group_name'] = $this->commonModel->selectOne('auth_groups', ['id' => $id]);
        $this->defData['perms'] = json_decode($this->defData['group_name']->permissions, JSON_UNESCAPED_UNICODE);
        return view('Modules\Users\Views\permGroup\update', $this->defData);
    }

    public function user_perms(int $id)
    {
        if ($this->request->is('post')) {
            $user = auth()->getProvider()->findById($id);
            if ($user->inGroup('superadmin')) return redirect()->to('403');
            try {
                $perms = [];
                $pageMap = [];

                // Get page map
                $pages = $this->commonModel->lists('auth_permissions_pages');
                foreach ($pages as $page) {
                    $pageMap[$page->id] = strtolower($page->pagename);
                }
                if (empty($this->request->getPost('perms'))) {
                    $user->syncPermissions();
                    return redirect()->route('users')->with('message', lang('Backend.updated', [$user->username]));
                }
                foreach ($this->request->getPost('perms') as $key => $perm) {
                    if (!isset($pageMap[$key])) continue;

                    $roles = explode('|', $perm['roles']);
                    $pagename = $pageMap[$key];

                    if (in_array('create_r', $roles)) $perms[] = $pagename . '.create';
                    if (in_array('read_r', $roles))   $perms[] = $pagename . '.read';
                    if (in_array('update_r', $roles)) $perms[] = $pagename . '.update';
                    if (in_array('delete_r', $roles)) $perms[] = $pagename . '.delete';
                }
                $user->syncPermissions(...$perms);

                return redirect()->route('users')->with('message', lang('Backend.updated', [$user->username]));
            } catch (\Exception $e) {
                return redirect()->back()->withInput()->with('error', lang('Backend.notUpdated', [$e->getMessage()]));
            }
        }
        $methodsModel = new \Modules\Methods\Models\MethodsModel();
        $user = auth()->getProvider()->findById($id);
        $this->defData['modules'] = $methodsModel->getModules();
        $this->defData['userInfos'] = $user;
        $this->defData['perms'] = $user->getPermissions();
        $this->defData['groupPerms'] = json_decode($this->commonModel->selectOne('auth_groups', ['group' => $user->getGroups()[0]])->permissions, true);
        return view('Modules\Users\Views\permGroup\userPerms', $this->defData);
    }
}
