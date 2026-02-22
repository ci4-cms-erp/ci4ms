<?php

namespace Modules\Blog\Controllers;

class Categories extends \Modules\Backend\Controllers\BaseController
{
    public function index()
    {
        if ($this->request->is('post') && $this->request->isAJAX()) {
            $data = clearFilter($this->request->getPost());
            $like = trim(strip_tags($data['search']['value']));
            $l = [];
            $postData = [];
            if (!empty($like)) $l = ['title' => $like];
            $results = $this->commonModel->lists('categories', '*', $postData, 'id DESC', ($data['length'] == '-1') ? 0 : (int)$data['length'], ($data['length'] == '-1') ? 0 : (int)$data['start'], $l);
            $totalRecords = $this->commonModel->count('categories', $postData, $l);
            foreach ($results as $result) {
                $result->actions = '<a href="' . route_to('categoryUpdate', $result->id) . '"
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
        return view('Modules\Blog\Views\categories\list', $this->defData);
    }

    public function new()
    {
        if ($this->request->is('post')) {
            $valData = ([
                'title' => ['label' => lang('Backend.title'), 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
                'seflink' => ['label' => lang('Backend.url'), 'rules' => 'required|regex_match[/^[^<>{}]*$/u]|is_unique[blog.seflink]']
            ]);
            if (!empty($this->request->getPost('pageimg'))) {
                $valData['pageimg'] = ['label' => lang('Backend.coverImgURL'), 'rules' => 'required'];
                $valData['pageIMGWidth'] = ['label' => lang('Backend.coverImgWith'), 'rules' => 'required|is_natural_no_zero'];
                $valData['pageIMGHeight'] = ['label' => lang('Backend.coverImgHeight'), 'rules' => 'required|is_natural_no_zero'];
            }
            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            if ($this->commonModel->isHave('categories', ['seflink' => $this->request->getPost('seflink')]) === 0) {
                $data = ['title' => trim(strip_tags($this->request->getPost('title'))), 'seflink' => trim(strip_tags($this->request->getPost('seflink'))), 'isActive' => $this->request->getPost('isActive')];
                if (!empty($this->request->getPost('parent'))) $data['parent'] = $this->request->getPost('parent');
                $seo = [];
                if (!empty($this->request->getPost('description'))) $seo['description'] = trim(strip_tags($this->request->getPost('description')));
                if (!empty($this->request->getPost('pageimg'))) $seo['coverImage'] = trim(strip_tags($this->request->getPost('pageimg')));
                if (!empty($this->request->getPost('pageIMGWidth'))) $seo['IMGWidth'] = trim(strip_tags($this->request->getPost('pageIMGWidth')));
                if (!empty($this->request->getPost('pageIMGHeight'))) $seo['IMGHeight'] = trim(strip_tags($this->request->getPost('pageIMGHeight')));
                if (!empty($this->request->getPost('keywords'))) $seo['keywords'] = json_decode($this->request->getPost('keywords'));
                $data['seo'] = json_encode($seo, JSON_UNESCAPED_UNICODE);
                if ($this->commonModel->create('categories', $data)) return redirect()->route('categories', [1])->with('message', lang('Backend.created', [esc($data['title'])]));
                else return redirect()->back()->withInput()->with('error', lang('Backend.created', [esc($data['title'])]));
            } else return redirect()->back()->withInput()->with('error', lang('Backend.notCreated', [esc($this->request->getPost('title'))]));
        }
        $this->defData['categories'] = $this->commonModel->lists('categories');
        return view('Modules\Blog\Views\categories\create', $this->defData);
    }

    public function edit(string $id)
    {
        if ($this->request->is('post')) {
            $valData = ([
                'title' => ['label' => lang('Backend.title'), 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
                'seflink' => ['label' => lang('Backend.url'), 'rules' => 'required|regex_match[/^[^<>{}]*$/u]|is_unique[blog.seflink]'],
            ]);
            if (!empty($this->request->getPost('pageimg'))) {
                $valData['pageimg'] = ['label' => lang('Backend.coverImgURL'), 'rules' => 'required'];
                $valData['pageIMGWidth'] = ['label' => lang('Backend.coverImgWith'), 'rules' => 'required|is_natural_no_zero'];
                $valData['pageIMGHeight'] = ['label' => lang('Backend.coverImgHeight'), 'rules' => 'required|is_natural_no_zero'];
            }
            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            $info = $this->commonModel->selectOne('categories', ['id' => $id]);
            if ($info->seflink != $this->request->getPost('seflink') && $this->commonModel->get_where(['seflink' => $this->request->getPost('seflink')], 'categories') === 1) return redirect()->back()->withInput()->with('error', lang('Backend.slugExists', [esc($this->request->getPost('seflink'))]));
            $data = ['title' => trim(strip_tags($this->request->getPost('title'))), 'seflink' => trim(strip_tags($this->request->getPost('seflink'))), 'isActive' => $this->request->getPost('isActive')];
            if (!empty($this->request->getPost('parent'))) $data['parent'] = $this->request->getPost('parent');
            $seo = [];
            if (!empty($this->request->getPost('description'))) $seo['description'] = trim(strip_tags($this->request->getPost('description')));
            if (!empty($this->request->getPost('pageimg'))) $seo['coverImage'] = trim(strip_tags($this->request->getPost('pageimg')));
            if (!empty($this->request->getPost('pageIMGWidth'))) $seo['IMGWidth'] = trim(strip_tags($this->request->getPost('pageIMGWidth')));
            if (!empty($this->request->getPost('pageIMGHeight'))) $seo['IMGHeight'] = trim(strip_tags($this->request->getPost('pageIMGHeight')));
            if (!empty($this->request->getPost('keywords'))) $seo['keywords'] = json_decode($this->request->getPost('keywords'));
            $data['seo'] = json_encode($seo, JSON_UNESCAPED_UNICODE);
            if ($this->commonModel->edit('categories', $data, ['id' => $id])) return redirect()->route('categories', [1])->with('message', lang('Backend.updated', [esc($data['title'])]));
            else return redirect()->back()->withInput()->with('error', lang('Backend.notUpdated', [esc($data['title'])]));
        }
        $this->defData = array_merge($this->defData, ['infos' => $this->commonModel->selectOne('categories', ['id' => $id]), 'categories' => $this->commonModel->lists('categories', '*', ['id!=' => $id])]);
        $this->defData['infos']->seo = json_decode($this->defData['infos']->seo);
        if (!empty($this->defData['infos']->seo))
            $this->defData['infos']->seo->keywords = !empty($this->defData['infos']->seo->keywords) ? json_encode($this->defData['infos']->seo->keywords, JSON_UNESCAPED_UNICODE) : [];
        return view('Modules\Blog\Views\categories\update', $this->defData);
    }

    public function delete()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valData = ([
            'id' => ['label' => '', 'rules' => 'required|is_natural_no_zero'],
        ]);
        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
        $category = $this->commonModel->selectOne('categories', ['id' => $this->request->getPost('id')]);
        if ($this->commonModel->remove('categories', ['id' => $this->request->getPost('id')]) === true) return $this->respond(['status' => 'success', 'message' => lang('Backend.deleted', [$category->title])]);
        else return $this->respond(['status' => 'error', 'message' => lang('Backend.notDeleted', [$category->title])]);
    }
}
