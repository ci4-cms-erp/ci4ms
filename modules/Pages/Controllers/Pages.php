<?php

namespace Modules\Pages\Controllers;

use Modules\Backend\Libraries\CommonTagsLibrary;
use Modules\Backend\Models\AjaxModel;

class Pages extends \Modules\Backend\Controllers\BaseController
{
    protected $model;
    protected $commonTagsLib;

    public function __construct()
    {
        $this->model = new AjaxModel();
        $this->commonTagsLib = new CommonTagsLibrary();
    }

    public function index()
    {
        if ($this->request->is('post') && $this->request->isAJAX()) {
            $data = clearFilter($this->request->getPost());
            $like = $data['search']['value'];
            $l = [];
            $postData = [];
            if (!empty($like)) $l = ['title' => trim(strip_tags($like))];
            $results = $this->commonModel->lists('pages', 'id,title,isActive', $postData, 'id DESC', ($data['length'] == '-1') ? 0 : (int)$data['length'], ($data['length'] == '-1') ? 0 : (int)$data['start'], $l);
            $totalRecords = $this->commonModel->count('pages', $postData, $l);
            foreach ($results as $result) {
                $result->status = '<input type="checkbox" name="my-checkbox" class="bswitch" ' . ((bool)$result->isActive === true ? 'checked' : '') . ' data-id="' . $result->id . '" data-off-color="danger" data-on-color="success">';
                $result->actions = '<a href="' . route_to('pageUpdate', $result->id) . '"
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
        return view('Modules\Pages\Views\list', $this->defData);
    }

    public function create()
    {
        if ($this->request->is('post')) {
            $valData = ([
                'title' => ['label' => lang('Backend.title'), 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
                'seflink' => ['label' => lang('Backend.url'), 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
                'content' => ['label' => lang('Backend.content'), 'rules' => 'required'],
                'isActive' => ['label' => lang('Backend.draft') . ' / ' . lang('Backend.publish'), 'rules' => 'required|in_list[0,1]']
            ]);
            if (!empty($this->request->getPost('pageimg'))) {
                $valData['pageimg'] = ['label' => lang('Backend.coverImgURL'), 'rules' => 'required'];
                $valData['pageIMGWidth'] = ['label' => lang('Backend.coverImgWith'), 'rules' => 'required|is_natural_no_zero'];
                $valData['pageIMGHeight'] = ['label' => lang('Backend.coverImgHeight'), 'rules' => 'required|is_natural_no_zero'];
            }
            if (!empty($this->request->getPost('description'))) $valData['description'] = ['label' => lang('Backend.seoDescription'), 'rules' => 'required'];
            if (!empty($this->request->getPost('keywords'))) $valData['keywords'] = ['label' => lang('Backend.seoKeywords'), 'rules' => 'required'];

            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            if ($this->commonModel->isHave('categories', ['seflink' => $this->request->getPost('seflink')]) === 1) return redirect()->back()->withInput()->with('error', lang('Backend.slugExists', [$this->request->getPost('title')]));

            $data = [
                'title' => $this->request->getPost('title'),
                'content' => $this->request->getPost('content'),
                'isActive' => (bool)$this->request->getPost('isActive'),
                'seflink' => $this->request->getPost('seflink'),
                'inMenu' => false
            ];

            if (!empty($this->request->getPost('pageimg'))) {
                $data['seo']['coverImage'] = $this->request->getPost('pageimg');
                $data['seo']['IMGWidth'] = $this->request->getPost('pageIMGWidth');
                $data['seo']['IMGHeight'] = $this->request->getPost('pageIMGHeight');
            }
            if (!empty($this->request->getPost('description'))) $data['seo']['description'] = $this->request->getPost('description');
            if (!empty($this->request->getPost('keywords'))) $data['seo']['keywords'] = json_decode($this->request->getPost('keywords'));
            if (!empty($data['seo'])) $data['seo'] = json_encode($data['seo'], JSON_UNESCAPED_UNICODE);
            if ($this->commonModel->create('pages', $data)) return redirect()->route('pages', [1])->with('message', lang('Backend.created', [$this->request->getPost('title')]));
            else return redirect()->back()->withInput()->with('error', lang('Backend.notCreated', [$this->request->getPost('title')]));
        }
        return view('Modules\Pages\Views\create', $this->defData);
    }

    public function update($id)
    {
        if ($this->request->is('post')) {
            $valData = ([
                'title' => ['label' => lang('Backend.title'), 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
                'seflink' => ['label' => lang('Backend.url'), 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
                'content' => ['label' => lang('Backend.content'), 'rules' => 'required'],
                'isActive' => ['label' => lang('Backend.draft') . ' / ' . lang('Backend.publish'), 'rules' => 'required|in_list[0,1]']
            ]);
            if (!empty($this->request->getPost('pageimg'))) {
                $valData['pageimg'] = ['label' => lang('Backend.coverImgURL'), 'rules' => 'required'];
                $valData['pageIMGWidth'] = ['label' => lang('Backend.coverImgWith'), 'rules' => 'required|is_natural_no_zero'];
                $valData['pageIMGHeight'] = ['label' => lang('Backend.coverImgHeight'), 'rules' => 'required|is_natural_no_zero'];
            }
            if (!empty($this->request->getPost('description'))) $valData['description'] = ['label' => lang('Backend.seoDescription'), 'rules' => 'required'];
            if (!empty($this->request->getPost('keywords'))) $valData['keywords'] = ['label' => lang('Backend.seoKeywords'), 'rules' => 'required'];

            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            $info = $this->commonModel->selectOne('pages', ['id' => $id]);
            if ($info->seflink != $this->request->getPost('seflink') && $this->commonModel->isHave('pages', ['seflink' => $this->request->getPost('seflink'), 'id!=' => $id]) === 1) return redirect()->back()->withInput()->with('error', lang('Backend.slugExists', [$this->request->getPost('title')]));
            $data = [
                'title' => $this->request->getPost('title'),
                'content' => $this->request->getPost('content'),
                'isActive' => (bool)$this->request->getPost('isActive'),
                'seflink' => $this->request->getPost('seflink')
            ];

            if (!empty($this->request->getPost('pageimg'))) {
                $data['seo']['coverImage'] = $this->request->getPost('pageimg');
                $data['seo']['IMGWidth'] = $this->request->getPost('pageIMGWidth');
                $data['seo']['IMGHeight'] = $this->request->getPost('pageIMGHeight');
            }

            if (!empty($this->request->getPost('description'))) $data['seo']['description'] = $this->request->getPost('description');
            if (!empty($this->request->getPost('keywords'))) $data['seo']['keywords'] = json_decode($this->request->getPost('keywords'));
            if (!empty($data['seo'])) $data['seo'] = json_encode($data['seo'], JSON_UNESCAPED_UNICODE);
            if ($this->commonModel->edit('pages', $data, ['id' => $id])) return redirect()->route('pages', [1])->with('message', lang('Backend.updated', [$this->request->getPost('title')]));
            else return redirect()->back()->withInput()->with('error', lang('Backend.notUpdated', [$this->request->getPost('title')]));
        }
        $this->defData['pageInfo'] = $this->commonModel->selectOne('pages', ['id' => $id]);
        if (!empty($this->defData['pageInfo']->seo)) {
            $this->defData['pageInfo']->seo = json_decode($this->defData['pageInfo']->seo);
            if (!empty($this->defData['pageInfo']->seo->keywords)) $this->defData['pageInfo']->seo->keywords = $this->defData['pageInfo']->seo->keywords;
        }
        return view('Modules\Pages\Views\update', $this->defData);
    }

    public function delete_post()
    {
        $valData = ([
            'id' => ['label' => '', 'rules' => 'required|is_natural_no_zero'],
        ]);
        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
        $pageName = $this->commonModel->selectOne('pages', ['id' => $this->request->getPost('id')]);
        if ($this->commonModel->remove('pages', ['id' => $this->request->getPost('id')]) === true) return $this->respond(['status' => 'success', 'message' => lang('Backend.deleted', [$pageName->title])]);
        else return $this->respond(['status' => 'error', 'message' => lang('Backend.notDeleted', [$pageName->title])]);
    }
}
