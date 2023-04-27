<?php

namespace Modules\Backend\Controllers;

use CodeIgniter\I18n\Time;
use JasonGrimes\Paginator;

class PermgroupController extends BaseController
{
    public function groupList($num = 1)
    {
        $this->defData['groups'] = $this->commonModel->lists('auth_groups', '*',[],'id ASC', 12,((int)$num - 1) * 12);
        $c = count($this->commonModel->lists('auth_groups'));
        $totalItems = $c;
        $itemsPerPage = 12;
        $currentPage = 12;
        $urlPattern = '/backend/groupList/(:num)';
        $paginator = new Paginator($totalItems, $itemsPerPage, $currentPage, $urlPattern);
        $paginator->setMaxPagesToShow(5);
        $this->defData['paginator'] = $paginator;
        return view('Modules\Backend\Views\permGroup\list', $this->defData);
    }

    public function group_create()
    {
        $this->defData['pages'] = $this->commonModel->lists('auth_permissions_pages');
        return view('Modules\Backend\Views\permGroup\create', $this->defData);
    }

    public function group_create_post()
    {
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
                $c = $this->request->getPost('perms')[$key]['c'] ?? null;
                $u = $this->request->getPost('perms')[$key]['u'] ?? null;
                $r = $this->request->getPost('perms')[$key]['r'] ?? null;
                $d = $this->request->getPost('perms')[$key]['d'] ?? null;
                $data[] = [
                    'group_id'=>$result,
                    'page_id' => $key,
                    'create_r' => (bool)$c,
                    'update_r' => (bool)$u,
                    'read_r' => (bool)$r,
                    'delete_r' => (bool)$d,
                    'who_perm' => $this->logged_in_user->id,
                    'created_at' => new Time('now')
                ];
            }
            $result = $this->commonModel->createMany('auth_groups_permissions', $data);
            if (empty($result)) return redirect()->back()->withInput()->with('errors', ['Grup Yetkileri eklenemedi.']);
            else return redirect()->to(route_to('groupList',1))->with('message', $this->request->getPost('groupName') . ' adlı grup ve yetkileri başarıyla eklendi.');
        } else return redirect()->back()->withInput()->with('errors', ['Grup Eklenemedi. Veri tabanı hatası !']);
    }

    public function group_update($id)
    {
        $this->defData['pages'] = $this->commonModel->lists('auth_permissions_pages');
        $this->defData['group_name'] = $this->commonModel->selectOne('auth_groups', ['id' => $id]);
        $this->defData['perms'] = $this->commonModel->lists('auth_groups_permissions','*', ['group_id' => $id]);
        return view('Modules\Backend\Views\permGroup\update', $this->defData);
    }

    public function group_update_post($id)
    {
        $valData = ([
            'groupName' => ['label' => 'Yetki Grubu Adı', 'rules' => 'required'],
            'description' => ['label' => 'Grup Açıklaması', 'rules' => 'required'],
            'seflink' => ['label' => 'Seflink', 'rules' => 'required'],
            'perms' => ['label' => 'İzinler', 'rules' => 'required']
        ]);

        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());

        $result = $this->commonModel->edit('auth_groups', [
            'name' => $this->request->getPost('groupName'),
            'description' => $this->request->getPost('description'),
            'seflink' => $this->request->getPost('seflink'),
            'updated_at' => new Time('now')
        ], ['id' => $id]);

        if ((bool)$result == true) {
            $data = [];
            foreach ($this->request->getPost('perms') as $key => $perm) {
                $c = $this->request->getPost('perms')[$key]['c'] ?? null;
                $u = $this->request->getPost('perms')[$key]['u'] ?? null;
                $r = $this->request->getPost('perms')[$key]['r'] ?? null;
                $d = $this->request->getPost('perms')[$key]['d'] ?? null;
                $data[] = [
                    'group_id'=>$id,
                    'page_id' => $key,
                    'create_r' => (bool)$c,
                    'update_r' => (bool)$u,
                    'read_r' => (bool)$r,
                    'delete_r' => (bool)$d,
                    'who_perm' => $this->logged_in_user->id,
                    'created_at' => new Time('now')
                ];
            }
            $this->commonModel->remove('auth_groups_permissions',['group_id'=>$id]);
            $result = $this->commonModel->createMany('auth_groups_permissions', $data);
            $userIds=$this->commonModel->lists('users','id',['group_id'=>$id]);
            foreach ($userIds as $userId) {
                cache()->delete("{$userId}_permissions");
            }
            if ((bool)$result == false) return redirect()->back()->withInput()->with('error', 'Grup Yetkileri eklenemedi.');
            else return redirect()->route('groupList',[1])->with('message', '<b>' . $this->request->getPost('groupName') . '</b> adlı grup ve yetkileri başarıyla eklendi.');
        } else
            return redirect()->back()->withInput()->with('error', 'Grup Eklenemedi. Veri tabanı hatası !');
    }

    public function user_perms($id)
    {
        //TODO: gruba dahil olmayan sayfalar gösterilecek.
        $this->defData['pages'] = $this->commonModel->lists('auth_permissions_pages');
        $this->defData['group_perms'] = $this->commonModel->selectOne('users', ['id' => $id]);
        $this->defData['group_perms']->auth_users_permissions=$this->commonModel->lists('auth_users_permissions','*',['user_id'=>$id]);
        return view('Modules\Backend\Views\permGroup\userPerms', $this->defData);
    }

    public function user_perms_post($id)
    {
        if ($this->validate(['perms' => ['label' => 'İzinler', 'rules' => 'required']]) == false)
            return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());

        $data = [];
        foreach ($this->request->getPost('perms') as $key => $perm) {
            $c = $this->request->getPost('perms')[$key]['c'] ?? null;
            $u = $this->request->getPost('perms')[$key]['u'] ?? null;
            $r = $this->request->getPost('perms')[$key]['r'] ?? null;
            $d = $this->request->getPost('perms')[$key]['d'] ?? null;
            $data[] = [
                'user_id'=>$id,
                'page_id' => $key,
                'create_r' => (bool)$c,
                'update_r' => (bool)$u,
                'read_r' => (bool)$r,
                'delete_r' => (bool)$d,
                'who_perm' => $this->logged_in_user->id,
                'created_at' => new Time('now')
            ];
        }
        $result=false;
        if($this->commonModel->remove('auth_users_permissions',['user_id'=>$id])) $result = $this->commonModel->createMany('auth_users_permissions', $data);
        cache()->delete("{$id}_permissions");
        if ((bool)$result == false)
            return redirect()->back()->withInput()->with('error', 'Kullanıcı Yetkileri eklenemedi.');
        else
            return redirect()->route('officeWorker',[1])->with('message', 'Kullanıcı yetkileri başarıyla eklendi.');
    }
}
