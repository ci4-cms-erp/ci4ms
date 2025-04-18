<?php

namespace Modules\Blog\Controllers;

use JasonGrimes\Paginator;

class Tags extends \Modules\Backend\Controllers\BaseController
{
    public function index()
    {
        $totalItems = $this->commonModel->count('tags',[]);
        $itemsPerPage = 20;
        $currentPage = $this->request->getUri()->getSegment(4, 1);
        $urlPattern = '/backend/pages/(:num)';
        $paginator = new Paginator($totalItems, $itemsPerPage, $currentPage, $urlPattern);
        $paginator->setMaxPagesToShow(5);
        $this->defData['paginator'] = $paginator;
        $bpk = ($this->request->getUri()->getSegment(4, 1) - 1) * $itemsPerPage;
        $this->defData['tags']=$this->commonModel->lists('tags','*',[],'id ASC',$itemsPerPage,$bpk);
        return view('Modules\Blog\Views\tags\list',$this->defData);
    }

    public function create()
    {
        $valData = (['title' => ['label' => 'Etiket Başlığı', 'rules' => 'required'], 'seflink' => ['label' => 'Etiket URL', 'rules' => 'required'],]);
        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        if ($this->commonModel->create('tags', ['tag'=>$this->request->getPost('title'),'seflink'=>$this->request->getPost('seflink')])) return redirect()->route('tags', [1])->with('message', '<b>' . $this->request->getPost('title') . '</b> adlı etiket Oluşturuldu.');
        else return redirect()->back()->withInput()->with('error', 'Etiket oluşturulamadı.');
    }

    public function edit(int $id)
    {
        if($this->request->is('post')){
            $valData = (['title' => ['label' => 'Etiket Başlığı', 'rules' => 'required'], 'seflink' => ['label' => 'Etiket URL', 'rules' => 'required'],]);
            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            if ($this->commonModel->edit('tags', ['tag'=>$this->request->getPost('title'),'seflink'=>$this->request->getPost('seflink')],['id'=>$id])) return redirect()->route('tags', [1])->with('message', '<b>' . $this->request->getPost('title') . '</b> adlı etiket güncellendi.');
            else return redirect()->back()->withInput()->with('error', 'Etiket güncellenemedi.');
        }
        $this->defData['infos']=$this->commonModel->selectOne('tags',['id'=>$id]);
        return view('Modules\Blog\Views\tags\update',$this->defData);
    }

    public function delete(string $id)
    {
        if ($this->commonModel->remove('tags',['id'=>$id])) return redirect()->route('tags', [1])->with('message', 'Etiket silindi.');
        else return redirect()->back()->withInput()->with('error', 'Etiket silinemedi.');
    }
}
