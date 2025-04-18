<?php

namespace Modules\Pages\Controllers;

use JasonGrimes\Paginator;
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
        $totalItems = $this->commonModel->count('pages', []);
        $itemsPerPage = 20;
        $currentPage = $this->request->getUri()->getSegment('3', 1);
        $urlPattern = '/backend/pages/(:num)';
        $paginator = new Paginator($totalItems, $itemsPerPage, $currentPage, $urlPattern);
        $paginator->setMaxPagesToShow(5);
        $this->defData['paginator'] = $paginator;
        $bpk = ($this->request->getUri()->getSegment(3, 1) - 1) * $itemsPerPage;
        $this->defData['pages'] = $this->commonModel->lists('pages', '*', [], 'id ASC', $itemsPerPage, $bpk);
        return view('Modules\Pages\Views\list', $this->defData);
    }

    public function create()
    {
        if ($this->request->is('post')) {
            $valData = ([
                'title' => ['label' => 'Sayfa Başlığı', 'rules' => 'required'],
                'seflink' => ['label' => 'Sayfa URL', 'rules' => 'required'],
                'content' => ['label' => 'İçerik', 'rules' => 'required'],
                'isActive' => ['label' => 'Yayın veya taslak', 'rules' => 'required']
            ]);
            if (!empty($this->request->getPost('pageimg'))) {
                $valData['pageimg'] = ['label' => 'Görsel URL', 'rules' => 'required'];
                $valData['pageIMGWidth'] = ['label' => 'Görsel Genişliği', 'rules' => 'required|is_natural_no_zero'];
                $valData['pageIMGHeight'] = ['label' => 'Görsel Yüksekliği', 'rules' => 'required|is_natural_no_zero'];
            }
            if (!empty($this->request->getPost('description'))) $valData['description'] = ['label' => 'Seo Açıklaması', 'rules' => 'required'];
            if (!empty($this->request->getPost('keywords'))) $valData['keywords'] = ['label' => 'Seo Anahtar Kelimeleri', 'rules' => 'required'];

            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            if ($this->commonModel->isHave('categories', ['seflink' => $this->request->getPost('seflink')]) === 1) return redirect()->back()->withInput()->with('error', 'Sayfa seflink adresi daha önce kullanılmış. lütfen kontrol ederek bir daha oluşturmayı deneyeyiniz.');

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
            $data['seo'] = json_encode($data['seo'], JSON_UNESCAPED_UNICODE);
            if ($this->commonModel->create('pages', $data)) return redirect()->route('pages', [1])->with('message', '<b>' . $this->request->getPost('title') . '</b> adlı sayfa Oluşturuldu.');
            else return redirect()->back()->withInput()->with('error', 'Sayfa oluşturulamadı.');
        }
        return view('Modules\Pages\Views\create', $this->defData);
    }

    public function update($id)
    {
        if ($this->request->is('post')) {
            $valData = ([
                'title' => ['label' => 'Sayfa Başlığı', 'rules' => 'required'],
                'seflink' => ['label' => 'Sayfa URL', 'rules' => 'required'],
                'content' => ['label' => 'İçerik', 'rules' => 'required'],
                'isActive' => ['label' => 'Yayın veya taslak', 'rules' => 'required']
            ]);
            if (!empty($this->request->getPost('pageimg'))) {
                $valData['pageimg'] = ['label' => 'Görsel URL', 'rules' => 'required'];
                $valData['pageIMGWidth'] = ['label' => 'Görsel Genişliği', 'rules' => 'required|is_natural_no_zero'];
                $valData['pageIMGHeight'] = ['label' => 'Görsel Yüksekliği', 'rules' => 'required|is_natural_no_zero'];
            }
            if (!empty($this->request->getPost('description'))) $valData['description'] = ['label' => 'Seo Açıklaması', 'rules' => 'required'];
            if (!empty($this->request->getPost('keywords'))) $valData['keywords'] = ['label' => 'Seo Anahtar Kelimeleri', 'rules' => 'required'];

            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            $info = $this->commonModel->selectOne('pages', ['id' => $id]);
            if ($info->seflink != $this->request->getPost('seflink') && $this->commonModel->isHave('pages', ['seflink' => $this->request->getPost('seflink'), 'id!=' => $id]) === 1) return redirect()->back()->withInput()->with('error', 'Sayfa seflink adresi daha önce kullanılmış. lütfen kontrol ederek bir daha oluşturmayı deneyeyiniz.');
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
            $data['seo'] = json_encode($data['seo'], JSON_UNESCAPED_UNICODE);
            if ($this->commonModel->edit('pages', $data, ['id' => $id])) return redirect()->route('pages', [1])->with('message', '<b>' . $this->request->getPost('title') . '</b> adlı sayfa güncellendi.');
            else return redirect()->back()->withInput()->with('error', 'Sayfa oluşturulamadı.');
        }
        $this->defData['pageInfo'] = $this->commonModel->selectOne('pages', ['id' => $id]);
        if (!empty($this->defData['pageInfo']->seo)) {
            $this->defData['pageInfo']->seo = json_decode($this->defData['pageInfo']->seo);
            if (!empty($this->defData['pageInfo']->seo->keywords)) $this->defData['pageInfo']->seo->keywords = $this->defData['pageInfo']->seo->keywords;
        }
        return view('Modules\Pages\Views\update', $this->defData);
    }

    public function delete_post($id)
    {
        $pageName = $this->commonModel->selectOne('pages', ['id' => $id]);
        if ($this->commonModel->remove('pages', ['id' => $id]) === true) return redirect()->route('pages', [1])->with('message', '<b>' . $this->request->getPost('title') . '</b> adlı sayfa silindi.');
        else return redirect()->back()->withInput()->with('error', 'Sayfa Silinemedi.');
    }
}
