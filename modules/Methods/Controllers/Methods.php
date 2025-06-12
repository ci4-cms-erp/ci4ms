<?php

namespace Modules\Methods\Controllers;

class Methods extends \Modules\Backend\Controllers\BaseController
{
    public function index()
    {
        if ($this->request->is('post') && $this->request->isAJAX()) {
            $data = clearFilter($this->request->getPost());
            $like = $data['search']['value'];
            $l = [];
            if (!empty($like)) $l = ['title' => $like];
            $results = $this->commonModel->lists('auth_permissions_pages', '*', [], 'id ' . (!empty($data['order'][0]['dir']) ? $data['order'][0]['dir'] : 'asc'), ($data['length'] == '-1') ? 0 : (int)$data['length'], ($data['length'] == '-1') ? 0 : (int)$data['start'], $l);
            $totalRecords = $this->commonModel->count('auth_permissions_pages');
            $totalDisplayRecords = $totalRecords;
            foreach ($results as $result) {
                $result->actions = '<a href="' . route_to('methodUpdate', $result->id) . '" class="btn btn-default btn-sm">
                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16">
                    <path d="M15.502 1.94a.5.5 0 0 1 0 .706L14.459 3.69l-2-2L13.502.646a.5.5 0 0 1 .707 0l1.293 1.293zm-1.75 2.456-2-2L4.939 9.21a.5.5 0 0 0-.121.196l-.805 2.414a.25.25 0 0 0 .316.316l2.414-.805a.5.5 0 0 0 .196-.12l6.813-6.814z"/>
                    <path fill-rule="evenodd" d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5H9a.5.5 0 0 0 0-1H2.5A1.5 1.5 0 0 0 1 2.5z"/>
                </svg>
            </a> <a href="' . route_to('methodDelete', $result->id) . '" class="btn btn-default btn-sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash2-fill" viewBox="0 0 16 16">
  <path d="M2.037 3.225A.7.7 0 0 1 2 3c0-1.105 2.686-2 6-2s6 .895 6 2a.7.7 0 0 1-.037.225l-1.684 10.104A2 2 0 0 1 10.305 15H5.694a2 2 0 0 1-1.973-1.671zm9.89-.69C10.966 2.214 9.578 2 8 2c-1.58 0-2.968.215-3.926.534-.477.16-.795.327-.975.466.18.14.498.307.975.466C5.032 3.786 6.42 4 8 4s2.967-.215 3.926-.534c.477-.16.795-.327.975-.466-.18-.14-.498-.307-.975-.466z"/>
</svg>
        </a>';
            }
            $data = [
                'draw' => intval($data['draw']),
                'iTotalRecords' => $totalRecords,
                'iTotalDisplayRecords' => $totalDisplayRecords,
                'aaData' => $results,
            ];
            return $this->respond($data, 200);
        }
        return view('Modules\Methods\Views\list', $this->defData);
    }

    public function create()
    {
        if ($this->request->is('post')) {
            $valData = ([
                'pagename' => ['label' => '', 'rules' => 'required'],
                'className' => ['label' => '', 'rules' => 'required'],
                'methodName' => ['label' => '', 'rules' => 'required'],
                'sefLink' => ['label' => '', 'rules' => 'required'],
                'typeOfPermissions' => ['label' => '', 'rules' => 'required'],
            ]);
            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            if ($this->commonModel->create('auth_permissions_pages', [
                'pagename' => $this->request->getPost('pagename'),
                'description' => $this->request->getPost('description') ?? '',
                'className' => $this->request->getPost('className'),
                'methodName' => $this->request->getPost('methodName'),
                'sefLink' => $this->request->getPost('sefLink'),
                'hasChild' => $this->request->getPost('hasChild') ?? 0,
                'pageSort' => $this->request->getPost('pageSort') ?? 0,
                'parent_pk' => $this->request->getPost('parent_pk') ?? NULL,
                'symbol' => $this->request->getPost('symbol') ?? NULL,
                'inNavigation' => $this->request->getPost('inNavigation') ?? 0,
                'isBackoffice' => $this->request->getPost('isBackoffice') ?? 0,
                'typeOfPermissions' => $this->request->getPost('typeOfPermissions')
            ]))
                return redirect()->route('methods')->with('success', 'Kayıt başarılı bir şekilde eklendi');
            else
                return redirect()->back()->withInput()->with('error', 'Kayıt eklenirken bir hata oluştu');
        }
        return view('Modules\Methods\Views\create', $this->defData);
    }

    public function update(int $pk)
    {
        if ($this->request->is('post')) {
            $valData = ([
                'pagename' => ['label' => '', 'rules' => 'required'],
                'className' => ['label' => '', 'rules' => 'required'],
                'methodName' => ['label' => '', 'rules' => 'required'],
                'sefLink' => ['label' => '', 'rules' => 'required'],
                'typeOfPermissions' => ['label' => '', 'rules' => 'required']
            ]);
            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            if ($this->commonModel->edit('auth_permissions_pages', [
                'pagename' => $this->request->getPost('pagename'),
                'description' => $this->request->getPost('description') ?? '',
                'className' => $this->request->getPost('className'),
                'methodName' => $this->request->getPost('methodName'),
                'sefLink' => $this->request->getPost('sefLink'),
                'hasChild' => (bool)$this->request->getPost('hasChild') == true ? 1 : 0,
                'pageSort' => $this->request->getPost('pageSort') ?? 0,
                'parent_pk' => $this->request->getPost('parent_pk') ?? NULL,
                'symbol' => $this->request->getPost('symbol') ?? NULL,
                'inNavigation' => (bool)$this->request->getPost('inNavigation') == true ? 1 : 0,
                'isBackoffice' => (bool)$this->request->getPost('isBackoffice') == true ? 1 : 0,
                'typeOfPermissions' => $this->request->getPost('typeOfPermissions')
            ], ['id' => $pk]))
                return redirect()->route('list')->with('success', 'Kayıt başarılı bir şekilde eklendi');
            else
                return redirect()->back()->withInput()->with('error', 'Kayıt eklenirken bir hata oluştu');
        }
        $this->defData['method'] = $this->commonModel->selectOne('auth_permissions_pages', ['id' => $pk]);
        $this->defData['methods'] = $this->commonModel->lists('auth_permissions_pages', '*', ['id!=' => $pk]);
        return view('Modules\Methods\Views\update', $this->defData);
    }
}
