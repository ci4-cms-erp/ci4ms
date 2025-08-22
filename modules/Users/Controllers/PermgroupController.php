<?php

namespace Modules\Users\Controllers;

use CodeIgniter\I18n\Time;
use JasonGrimes\Paginator;

class PermgroupController extends \Modules\Backend\Controllers\BaseController
{
    public function groupList($num = 1)
    {
        $this->defData['groups'] = $this->commonModel->lists('auth_groups', '*', [], 'id ASC', 12, ((int)$num - 1) * 12);
        $c = count($this->commonModel->lists('auth_groups'));
        $totalItems = $c;
        $itemsPerPage = 12;
        $currentPage = 12;
        $urlPattern = '/backend/groupList/(:num)';
        $paginator = new Paginator($totalItems, $itemsPerPage, $currentPage, $urlPattern);
        $paginator->setMaxPagesToShow(5);
        $this->defData['paginator'] = $paginator;
        return view('Modules\Users\Views\permGroup\list', $this->defData);
    }

    public function group_create()
    {
        if ($this->request->is('post')) {
            $valData = ([
                'groupName' => ['label' => 'Yetki Grubu Adı', 'rules' => 'required'],
                'description' => ['label' => 'Grup Açıklaması', 'rules' => 'required'],
                'seflink' => ['label' => 'Seflink', 'rules' => 'required'],
                'perms' => ['label' => 'İzinler', 'rules' => 'required']
            ]);

            if ($this->validate($valData) == false)
                return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());

            $result = $this->commonModel->create('auth_groups', [
                'name' => $this->request->getPost('groupName'),
                'description' => $this->request->getPost('description'),
                'seflink' => $this->request->getPost('seflink'),
                'created_at' => new Time('now'),
                'who_created' => session()->get('logged_in')
            ]);

            if (!empty($result)) {
                $data = [];
                foreach ($this->request->getPost('perms') as $key => $perm) {
                    $roles = explode('|', $perm['roles']);
                    $data[] = [
                        'group_id' => $result,
                        'page_id' => $key,
                        'create_r' => in_array('create_r', $roles),
                        'update_r' => in_array('update_r', $roles),
                        'read_r' => in_array('read_r', $roles),
                        'delete_r' => in_array('delete_r', $roles),
                        'who_perm' => $this->logged_in_user->id,
                        'created_at' => new Time('now')
                    ];
                }
                $result = $this->commonModel->createMany('auth_groups_permissions', $data);
                if (empty($result)) return redirect()->back()->withInput()->with('errors', ['Grup Yetkileri eklenemedi.']);
                else return redirect()->to(route_to('groupList', 1))->with('message', $this->request->getPost('groupName') . ' adlı grup ve yetkileri başarıyla eklendi.');
            } else return redirect()->back()->withInput()->with('errors', ['Grup Eklenemedi. Veri tabanı hatası !']);
        }
        $methodsModel = new \Modules\Methods\Models\MethodsModel();
        $this->defData['modules'] = $methodsModel->getModules();
        return view('Modules\Users\Views\permGroup\create', $this->defData);
    }

    public function group_update($id)
    {
        if ($this->request->is('post')) {
            $valData = ([
                'groupName' => ['label' => 'Yetki Grubu Adı', 'rules' => 'required'],
                'description' => ['label' => 'Grup Açıklaması', 'rules' => 'required'],
                'seflink' => ['label' => 'Seflink', 'rules' => 'required'],
                'perms' => ['label' => 'İzinler', 'rules' => 'required']
            ]);

            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());

            if ($this->commonModel->edit('auth_groups', [
                'name' => $this->request->getPost('groupName'),
                'description' => $this->request->getPost('description'),
                'seflink' => $this->request->getPost('seflink'),
                'updated_at' => new Time('now')
            ], ['id' => $id])) {
                $data = [];
                foreach ($this->request->getPost('perms') as $key => $perm) {
                    $roles = explode('|', $perm['roles']);
                    $data[] = [
                        'group_id' => $id,
                        'page_id' => $key,
                        'create_r' => in_array('create_r', $roles),
                        'update_r' => in_array('update_r', $roles),
                        'read_r' => in_array('read_r', $roles),
                        'delete_r' => in_array('delete_r', $roles),
                        'who_perm' => $this->logged_in_user->id,
                        'created_at' => new Time('now')
                    ];
                }
                $this->commonModel->remove('auth_groups_permissions', ['group_id' => $id]);
                $userIds = $this->commonModel->lists('users', 'id', ['group_id' => $id]);
                foreach ($userIds as $userId) {
                    cache()->delete("{$userId->id}_permissions");
                }
                if ($this->commonModel->createMany('auth_groups_permissions', $data)) return redirect()->route('groupList', [1])->with('message', '<b>' . $this->request->getPost('groupName') . '</b> adlı grup ve yetkileri başarıyla eklendi.');
                else return redirect()->back()->withInput()->with('error', 'Grup Yetkileri eklenemedi.');
            } else
                return redirect()->back()->withInput()->with('error', 'Grup Eklenemedi. Veri tabanı hatası !');
        }
        $methodsModel = new \Modules\Methods\Models\MethodsModel();
        $this->defData['modules'] = $methodsModel->getModules();
        $this->defData['group_name'] = $this->commonModel->selectOne('auth_groups', ['id' => $id]);
        $this->defData['perms'] = $this->commonModel->lists('auth_groups_permissions', '*', ['group_id' => $id]);
        return view('Modules\Users\Views\permGroup\update', $this->defData);
    }

    public function user_perms(int $id)
    {
        $userPerms = cache()->get("{$id}_permissions");
        if ($this->request->is('post')) {
            if ($this->validate(['perms' => ['label' => 'İzinler', 'rules' => 'required']]) == false)
                return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());

            $data = [];
            foreach ($this->request->getPost('perms') as $key => $perm) {
                $roles = explode('|', $perm['roles']);
                $data[] = [
                    'user_id' => $id,
                    'page_id' => $key,
                    'create_r' => in_array('create_r', $roles),
                    'update_r' => in_array('update_r', $roles),
                    'read_r' => in_array('read_r', $roles),
                    'delete_r' => in_array('delete_r', $roles),
                    'who_perm' => $this->logged_in_user->id,
                    'created_at' => new Time('now')
                ];
            }
            if ($this->commonModel->remove('auth_users_permissions', ['user_id' => $id]) && $this->commonModel->createMany('auth_users_permissions', $data)) {
                cache()->delete("{$id}_permissions");
                return redirect()->route('users', [1])->with('message', 'Kullanıcı yetkileri başarıyla eklendi.');
            } else
                return redirect()->back()->withInput()->with('error', 'Kullanıcı Yetkileri eklenemedi.');
        }
        $methodsModel = new \Modules\Methods\Models\MethodsModel();
        $this->defData['modules'] = $methodsModel->getModules();
        $this->defData['userInfos'] = $this->commonModel->selectOne('users', ['id' => $id]);
        $this->defData['perms'] = $this->commonModel->lists('auth_users_permissions', '*', ['user_id' => $id]);
        $this->defData['groupPerms'] = $this->commonModel->lists('users', 'auth_groups_permissions.*', ['users.id' => $id], 'id ASC', 0, 0, [], [], [
            ['table' => 'auth_groups', 'cond' => 'users.group_id = auth_groups.id', 'type' => 'left'],
            ['table' => 'ci4ms_auth_groups_permissions', 'cond' => 'ci4ms_auth_groups_permissions.group_id = auth_groups.id', 'type' => 'left']
        ]);
        return view('Modules\Users\Views\permGroup\userPerms', $this->defData);
    }
}
