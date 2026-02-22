<?php

namespace Modules\Blog\Controllers;

class Tags extends \Modules\Backend\Controllers\BaseController
{
    public function index()
    {
        if ($this->request->is('post') && $this->request->isAJAX()) {
            $data = clearFilter($this->request->getPost());
            $like = trim(strip_tags($data['search']['value']));
            $l = [];
            $postData = [];
            if (!empty($like)) $l = ['title' => $like];
            $results = $this->commonModel->lists('tags', '*', $postData, 'id DESC', ($data['length'] == '-1') ? 0 : (int)$data['length'], ($data['length'] == '-1') ? 0 : (int)$data['start'], $l);
            $totalRecords = $this->commonModel->count('tags', $postData, $l);
            foreach ($results as $result) {
                $result->actions = '<a href="' . route_to('tagUpdate', $result->id) . '"
                                   class="btn btn-outline-info btn-sm">' . lang('Backend.update') . '</a>
                               <a href="javascript:void(0);" onclick="deleteItem(' . $result->id . ')"
                                   class="btn btn-outline-danger btn-sm">' . lang('Backend.delete') . '</a>';
            }
            $data = [
                'draw' => intval($data['draw']),
                'iTotalRecords' => $totalRecords,
                'iTotalDisplayRecords' => $totalRecords,
                'aaData' => $results,
            ];
            return $this->respond($data, 200);
        }
        return view('Modules\Blog\Views\tags\list', $this->defData);
    }

    public function create()
    {
        $valData = ([
            'title' => ['label' => lang('Backend.title'), 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
            'seflink' => ['label' => lang('Backend.url'), 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
        ]);
        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        if ($this->commonModel->create('tags', ['tag' => trim(strip_tags($this->request->getPost('title'))), 'seflink' => trim(strip_tags($this->request->getPost('seflink')))])) return redirect()->route('tags', [1])->with('message', lang('Backend.created', [esc($this->request->getPost('title'))]));
        else return redirect()->back()->withInput()->with('error', lang('Backend.notCreated', [esc($this->request->getPost('title'))]));
    }

    public function edit(int $id)
    {
        if ($this->request->is('post')) {
            $valData = ([
                'title' => ['label' => lang('Backend.title'), 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
                'seflink' => ['label' => lang('Backend.url'), 'rules' => 'required|regex_match[/^[^<>{}]*$/u]']
            ]);
            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            if ($this->commonModel->edit('tags', ['tag' => trim(strip_tags($this->request->getPost('title'))), 'seflink' => trim(strip_tags($this->request->getPost('seflink')))], ['id' => $id])) return redirect()->route('tags', [1])->with('message', lang('Backend.updated', [esc($this->request->getPost('title'))]));
            else return redirect()->back()->withInput()->with('error', lang('Backend.notUpdated', [esc($this->request->getPost('title'))]));
        }
        $this->defData['infos'] = $this->commonModel->selectOne('tags', ['id' => $id]);
        return view('Modules\Blog\Views\tags\update', $this->defData);
    }

    public function delete()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valData = ([
            'id' => ['label' => '', 'rules' => 'required|is_natural_no_zero'],
        ]);
        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
        $tag = $this->commonModel->selectOne('tags', ['id' => $this->request->getPost('id')]);
        if ($this->commonModel->remove('tags', ['id' => $this->request->getPost('id')]) === true) return $this->respond(['status' => 'success', 'message' => lang('Backend.deleted', [$tag->tag])]);
        else return $this->respond(['status' => 'error', 'message' => lang('Backend.notDeleted', [$tag->tag])]);
    }
}
