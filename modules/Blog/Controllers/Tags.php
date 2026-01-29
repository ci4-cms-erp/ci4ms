<?php

namespace Modules\Blog\Controllers;

class Tags extends \Modules\Backend\Controllers\BaseController
{
    public function index()
    {
        if ($this->request->is('post') && $this->request->isAJAX()) {
            $data = clearFilter($this->request->getPost());
            $like = $data['search']['value'];
            $l = [];
            $postData = [];
            if (!empty($like)) $l = ['title' => $like];
            $results = $this->commonModel->lists('tags', '*', $postData, 'id DESC', ($data['length'] == '-1') ? 0 : (int)$data['length'], ($data['length'] == '-1') ? 0 : (int)$data['start'], $l);
            $totalRecords = $this->commonModel->count('tags', $postData, $l);
            foreach ($results as $result) {
                $result->actions = '<a href="' . route_to('tagUpdate', $result->id) . '"
                                   class="btn btn-outline-info btn-sm">' . lang('Backend.update') . '</a>
                                <a href="' . route_to('tagDelete', $result->id) . '"
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
        $valData = (['title' => ['label' => lang('Backend.title'), 'rules' => 'required'], 'seflink' => ['label' => lang('Backend.url'), 'rules' => 'required'],]);
        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        if ($this->commonModel->create('tags', ['tag' => $this->request->getPost('title'), 'seflink' => $this->request->getPost('seflink')])) return redirect()->route('tags', [1])->with('message', lang('Backend.created', [$this->request->getPost('title')]));
        else return redirect()->back()->withInput()->with('error', lang('Backend.notCreated', [$this->request->getPost('title')]));
    }

    public function edit(int $id)
    {
        if ($this->request->is('post')) {
            $valData = (['title' => ['label' => lang('Backend.title'), 'rules' => 'required'], 'seflink' => ['label' => lang('Backend.url'), 'rules' => 'required'],]);
            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            if ($this->commonModel->edit('tags', ['tag' => $this->request->getPost('title'), 'seflink' => $this->request->getPost('seflink')], ['id' => $id])) return redirect()->route('tags', [1])->with('message', lang('Backend.updated', [$this->request->getPost('title')]));
            else return redirect()->back()->withInput()->with('error', lang('Backend.notUpdated', [$this->request->getPost('title')]));
        }
        $this->defData['infos'] = $this->commonModel->selectOne('tags', ['id' => $id]);
        return view('Modules\Blog\Views\tags\update', $this->defData);
    }

    public function delete(string $id)
    {
        if ($this->commonModel->remove('tags', ['id' => $id])) return redirect()->route('tags', [1])->with('message', lang('Backend.deleted', ['#' . $id]));
        else return redirect()->back()->withInput()->with('error', lang('Backend.notDeleted', ['#' . $id]));
    }
}
