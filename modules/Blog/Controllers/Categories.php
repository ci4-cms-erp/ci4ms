<?php

namespace Modules\Blog\Controllers;

use JasonGrimes\Paginator;

class Categories extends \Modules\Backend\Controllers\BaseController
{
    public function index()
    {
        $totalItems = $this->commonModel->count('categories', []);
        $itemsPerPage = 20;
        $currentPage = $this->request->getUri()->getSegment('4', 1);
        $urlPattern = '/backend/blogs/categories/(:num)';
        $paginator = new Paginator($totalItems, $itemsPerPage, $currentPage, $urlPattern);
        $paginator->setMaxPagesToShow(5);
        $bpk = ($this->request->getUri()->getSegment(4, 1) - 1) * $itemsPerPage;
        $this->defData['paginator'] = $paginator;
        $this->defData['categories'] = $this->commonModel->lists('categories', '*', [], 'id ASC', $itemsPerPage, $bpk);
        return view('Modules\Blog\Views\categories\list', $this->defData);
    }

    public function new()
    {
        if ($this->request->is('post')) {
            $valData = (['title' => ['label' => 'Kategori Adı', 'rules' => 'required'], 'seflink' => ['label' => 'Kategori URL', 'rules' => 'required'],]);
            if (!empty($this->request->getPost('pageimg'))) {
                $valData['pageimg'] = ['label' => 'Görsel URL', 'rules' => 'required'];
                $valData['pageIMGWidth'] = ['label' => 'Görsel Genişliği', 'rules' => 'required|is_natural_no_zero'];
                $valData['pageIMGHeight'] = ['label' => 'Görsel Yüksekliği', 'rules' => 'required|is_natural_no_zero'];
            }
            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            if ($this->commonModel->isHave('categories', ['seflink' => $this->request->getPost('seflink')]) === 0) {
                $data = ['title' => $this->request->getPost('title'), 'seflink' => $this->request->getPost('seflink'), 'isActive' => $this->request->getPost('isActive')];
                if (!empty($this->request->getPost('parent'))) $data['parent'] = $this->request->getPost('parent');
                $seo = [];
                if (!empty($this->request->getPost('description'))) $seo['description'] = $this->request->getPost('description');
                if (!empty($this->request->getPost('pageimg'))) $seo['coverImage'] = $this->request->getPost('pageimg');
                if (!empty($this->request->getPost('pageIMGWidth'))) $seo['IMGWidth'] = $this->request->getPost('pageIMGWidth');
                if (!empty($this->request->getPost('pageIMGHeight'))) $seo['IMGHeight'] = $this->request->getPost('pageIMGHeight');
                if (!empty($this->request->getPost('keywords'))) $seo['keywords'] = json_decode($this->request->getPost('keywords'));
                $data['seo'] = json_encode($seo, JSON_UNESCAPED_UNICODE);
                if ($this->commonModel->create('categories', $data)) return redirect()->route('categories', [1])->with('message', '<b>' . $this->request->getPost('title') . '</b> adlı kategori Oluşturuldu.');
                else return redirect()->back()->withInput()->with('error', 'Kategori oluşturulamadı.');
            } else return redirect()->back()->withInput()->with('error', 'Kategori seflink adresi daha önce kullanılmış. lütfen kontrol ederek bir daha oluşturmayı deneyeyiniz.');
        }
        $this->defData['categories'] = $this->commonModel->lists('categories');
        return view('Modules\Blog\Views\categories\create', $this->defData);
    }

    public function edit(string $id)
    {
        if ($this->request->is('post')) {
            $valData = (['title' => ['label' => 'Kategori Adı', 'rules' => 'required'], 'seflink' => ['label' => 'Kategori URL', 'rules' => 'required'],]);
        if (!empty($this->request->getPost('pageimg'))) {
            $valData['pageimg'] = ['label' => 'Görsel URL', 'rules' => 'required'];
            $valData['pageIMGWidth'] = ['label' => 'Görsel Genişliği', 'rules' => 'required|is_natural_no_zero'];
            $valData['pageIMGHeight'] = ['label' => 'Görsel Yüksekliği', 'rules' => 'required|is_natural_no_zero'];
        }
        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        $info = $this->commonModel->selectOne('categories', ['id' => $id]);
        if ($info->seflink != $this->request->getPost('seflink') && $this->commonModel->get_where(['seflink' => $this->request->getPost('seflink')], 'categories') === 1) return redirect()->back()->withInput()->with('error', 'Kategori seflink adresi daha önce kullanılmış. lütfen kontrol ederek bir daha oluşturmayı deneyeyiniz.');
        $data = ['title' => $this->request->getPost('title'), 'seflink' => $this->request->getPost('seflink'), 'isActive' => $this->request->getPost('isActive')];
        if (!empty($this->request->getPost('parent'))) $data['parent'] = $this->request->getPost('parent');
        $seo = [];
        if (!empty($this->request->getPost('description'))) $seo['description'] = $this->request->getPost('description');
        if (!empty($this->request->getPost('pageimg'))) $seo['coverImage'] = $this->request->getPost('pageimg');
        if (!empty($this->request->getPost('pageIMGWidth'))) $seo['IMGWidth'] = $this->request->getPost('pageIMGWidth');
        if (!empty($this->request->getPost('pageIMGHeight'))) $seo['IMGHeight'] = $this->request->getPost('pageIMGHeight');
        if (!empty($this->request->getPost('keywords'))) $seo['keywords'] = json_decode($this->request->getPost('keywords'));
        $data['seo'] = json_encode($seo, JSON_UNESCAPED_UNICODE);
        if ($this->commonModel->edit('categories', $data, ['id' => $id])) return redirect()->route('categories', [1])->with('message', '<b>' . $this->request->getPost('title') . '</b> adlı kategori güncellendi.');
        else return redirect()->back()->withInput()->with('error', 'Kategori oluşturulamadı.');
        }
        $this->defData = array_merge($this->defData, ['infos' => $this->commonModel->selectOne('categories', ['id' => $id]), 'categories' => $this->commonModel->lists('categories', '*', ['id!=' => $id])]);
        $this->defData['infos']->seo = json_decode($this->defData['infos']->seo);
        $this->defData['infos']->seo->keywords = json_encode($this->defData['infos']->seo->keywords, JSON_UNESCAPED_UNICODE);
        return view('Modules\Blog\Views\categories\update', $this->defData);
    }

    public function delete(string $id)
    {
        if ($this->commonModel->remove('categories', ['id' => $id])) return redirect()->route('categories', [1])->with('message', 'Kategori silindi.');
        else return redirect()->route('categories', [1])->with('error', 'Kategori silinedi.');
    }
}
