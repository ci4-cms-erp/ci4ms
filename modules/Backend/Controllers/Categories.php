<?php

namespace Modules\Backend\Controllers;

use JasonGrimes\Paginator;
use Modules\Backend\Models\CategoriesModel;
use MongoDB\BSON\ObjectId;

class Categories extends BaseController
{
    public function index()
    {
        $totalItems = $this->commonModel->count('categories', []);
        $itemsPerPage = 20;
        $currentPage = $this->request->uri->getSegment('4', 1);
        $urlPattern = '/backend/blogs/categories/(:num)';
        $paginator = new Paginator($totalItems, $itemsPerPage, $currentPage, $urlPattern);
        $paginator->setMaxPagesToShow(5);
        $bpk = ($this->request->uri->getSegment(4, 1) - 1) * $itemsPerPage;
        $model = new CategoriesModel();
        $this->defData = array_merge($this->defData, ['paginator' => $paginator, 'categories' => $model->list($itemsPerPage, $bpk)]);
        return view('Modules\Backend\Views\categories\list', $this->defData);
    }

    public function new()
    {
        $this->defData['categories'] = $this->commonModel->getList('categories');
        return view('Modules\Backend\Views\categories\create', $this->defData);
    }

    public function create()
    {
        $valData = (['title' => ['label' => 'Kategori Adı', 'rules' => 'required'], 'seflink' => ['label' => 'Kategori URL', 'rules' => 'required'],]);
        if (!empty($this->request->getPost('pageimg'))) {
            $valData['pageimg'] = ['label' => 'Görsel URL', 'rules' => 'required|valid_url'];
            $valData['pageIMGWidth'] = ['label' => 'Görsel Genişliği', 'rules' => 'required|is_natural_no_zero'];
            $valData['pageIMGHeight'] = ['label' => 'Görsel Yüksekliği', 'rules' => 'required|is_natural_no_zero'];
        }
        if (!empty($this->request->getPost('pageimg'))) $valData['pageimg'] = ['label' => 'Görsel URL', 'rules' => 'valid_url'];
        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        if ($this->commonModel->get_where(['seflink' => $this->request->getPost('seflink')], 'categories') === 0) {
            $data = ['title' => $this->request->getPost('title'), 'seflink' => $this->request->getPost('seflink'),'isActive' => $this->request->getPost('isActive')];
            if (!empty($this->request->getPost('parent'))) $data['parent'] = new ObjectId($this->request->getPost('parent'));
            $seo = [];
            if (!empty($this->request->getPost('description'))) $seo['description'] = $this->request->getPost('description');
            if (!empty($this->request->getPost('pageimg'))) $seo['coverImage'] = $this->request->getPost('pageimg');
            if (!empty($this->request->getPost('pageIMGWidth'))) $seo['IMGWidth'] = $this->request->getPost('pageIMGWidth');
            if (!empty($this->request->getPost('pageIMGHeight'))) $seo['IMGHeight'] = $this->request->getPost('pageIMGHeight');
            if (!empty($this->request->getPost('keywords'))) $seo['keywords'] = json_decode($this->request->getPost('keywords'));
            $data['seo'] = $seo;
            if ($this->commonModel->createOne('categories', $data)) return redirect()->route('categories', [1])->with('message', '<b>' . $this->request->getPost('title') . '</b> adlı kategori Oluşturuldu.');
            else return redirect()->back()->withInput()->with('error', 'Kategori oluşturulamadı.');
        } else return redirect()->back()->withInput()->with('error', 'Kategori seflink adresi daha önce kullanılmış. lütfen kontrol ederek bir daha oluşturmayı deneyeyiniz.');
    }

    public function edit(string $id)
    {
        $this->defData = array_merge($this->defData, ['infos' => $this->commonModel->getOne('categories', ['_id' => new ObjectId($id)]), 'categories' => $this->commonModel->getList('categories')]);
        return view('Modules\Backend\Views\categories\update', $this->defData);
    }

    public function update(string $id)
    {
        $valData = (['title' => ['label' => 'Kategori Adı', 'rules' => 'required'], 'seflink' => ['label' => 'Kategori URL', 'rules' => 'required'],]);
        if (!empty($this->request->getPost('pageimg'))) {
            $valData['pageimg'] = ['label' => 'Görsel URL', 'rules' => 'required|valid_url'];
            $valData['pageIMGWidth'] = ['label' => 'Görsel Genişliği', 'rules' => 'required|is_natural_no_zero'];
            $valData['pageIMGHeight'] = ['label' => 'Görsel Yüksekliği', 'rules' => 'required|is_natural_no_zero'];
        }
        if (!empty($this->request->getPost('pageimg'))) $valData['pageimg'] = ['label' => 'Görsel URL', 'rules' => 'valid_url'];
        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        $info = $this->commonModel->getOne('categories', ['_id' => new ObjectId($id)]);
        if ($info->seflink != $this->request->getPost('seflink') && $this->commonModel->get_where(['seflink' => $this->request->getPost('seflink')], 'categories') === 1) return redirect()->back()->withInput()->with('error', 'Kategori seflink adresi daha önce kullanılmış. lütfen kontrol ederek bir daha oluşturmayı deneyeyiniz.');
        $data = ['title' => $this->request->getPost('title'), 'seflink' => $this->request->getPost('seflink'),'isActive' => $this->request->getPost('isActive')];
        if (!empty($this->request->getPost('parent'))) $data['parent'] = new ObjectId($this->request->getPost('parent'));
        $seo = [];
        if (!empty($this->request->getPost('description'))) $seo['description'] = $this->request->getPost('description');
        if (!empty($this->request->getPost('pageimg'))) $seo['coverImage'] = $this->request->getPost('pageimg');
        if (!empty($this->request->getPost('pageIMGWidth'))) $seo['IMGWidth'] = $this->request->getPost('pageIMGWidth');
        if (!empty($this->request->getPost('pageIMGHeight'))) $seo['IMGHeight'] = $this->request->getPost('pageIMGHeight');
        if (!empty($this->request->getPost('keywords'))) $seo['keywords'] = json_decode($this->request->getPost('keywords'));
        $data['seo'] = $seo;
        if ($this->commonModel->updateOne('categories', ['_id' => new ObjectId($id)], $data)) return redirect()->route('categories', [1])->with('message', '<b>' . $this->request->getPost('title') . '</b> adlı kategori güncellendi.');
        else return redirect()->back()->withInput()->with('error', 'Kategori oluşturulamadı.');
    }

    public function delete(string $id)
    {
        if ($this->commonModel->deleteOne('categories', ['_id' => new ObjectId($id)]) && $this->commonModel->updateMany('categories', ['parent' => new ObjectId($id)], ['parent' => null])) return redirect()->route('categories', [1])->with('message', 'Kategori silindi.');
        else return redirect()->route('categories', [1])->with('error', 'Kategori silinedi.');
    }
}
