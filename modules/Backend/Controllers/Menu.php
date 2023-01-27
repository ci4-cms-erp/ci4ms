<?php

namespace Modules\Backend\Controllers;

use MongoDB\BSON\ObjectId;

class Menu extends BaseController
{
    public function __construct()
    {
        helper('Modules\Backend\Helpers\ci4ms');
    }

    /**
     * Return an array of resource objects, themselves in array format
     *
     * @return mixed
     */
    public function index()
    {
        $this->defData=array_merge($this->defData,['pages' => $this->commonModel->getList('pages', ['inMenu' => false, 'isActive' => true]),
            'blogs' => $this->commonModel->getList('blog', ['inMenu' => false, 'isActive' => true]),
            'nestable2' => $this->commonModel->getList('menu',[],['sort'=>['queue'=>1]])]);
        return view('Modules\Backend\Views\menu\menu', $this->defData);
    }

    public function create()
    {
        if ($this->request->isAJAX()) {
            $pMax = $this->commonModel->getOne('menu', ['parent' => null], ['sort' => ['_id' => -1]]);
            if ($this->request->getPost('type') == 'url') {
                $data = ['queue'=>$pMax->queue+1,'urlType' => $this->request->getPost('type'),
                    'pages_id'=>new ObjectId(null), 'seflink' => $this->request->getPost('URL'),
                    'parent'=>null, 'title' => $this->request->getPost('URLname'),
                    'target' => $this->request->getPost('target')];
            } else {
                $added = $this->commonModel->getOne($this->request->getPost('where'),
                    ['_id' => new ObjectId($this->request->getPost('id'))]);
                if (empty($pMax))
                    $pMax = (object)['queue' => 0];

                if ($this->request->getPost('where') == 'pages') $seflink = $added->seflink;
                if ($this->request->getPost('where') == 'blog') $seflink = 'blog/' . $added->seflink;

                $data = ['pages_id' => new ObjectId($added->_id),
                    'parent' => null, 'queue' => $pMax->queue + 1,
                    'urlType' => $this->request->getPost('where'),
                    'title' => $added->title, 'seflink' => $seflink, 'target' => null];
                $this->commonModel->updateOne($this->request->getPost('where'),
                    ['_id' => new ObjectId($added->_id)], ['inMenu' => true]);
            }
            if ($this->commonModel->createOne('menu', $data)) {
                return view('Modules\Backend\Views\menu\render-nestable2', ['nestable2' => $this->commonModel->getList('menu',[],['sort'=>['queue'=>1]])]);
            }
        } else return redirect()->route('403');
    }

    public function addMultipleMenu()
    {
        if ($this->request->isAJAX()) {
            foreach ($this->request->getPost('pageChecked') as $item) {
                $pMax = $this->commonModel->getOne('menu', ['parent' => null], ['sort' => ['_id' => -1]]);
                if (empty($pMax))
                    $pMax = (object)['queue' => 0];

                $d = $this->commonModel->getOne($this->request->getPost('where'), ['_id' => new ObjectId($item)]);

                if ($this->request->getPost('type') == 'pages') $seflink = $d->seflink;
                if ($this->request->getPost('type') == 'blogs') $seflink = 'blog/' . $d->seflink;

                $data = ['pages_id' => new ObjectId($item), 'parent' => null,
                    'queue' => $pMax->queue + 1, 'urlType' => $this->request->getPost('type'),
                    'title' => $d->title, 'seflink' => $seflink, 'target' => null];

                $this->commonModel->updateOne($this->request->getPost('where'), ['_id' => new ObjectId($item)], ['inMenu' => true]);
                $this->commonModel->createOne('menu', $data);
            }
            return view('Modules\Backend\Views\menu\render-nestable2', ['nestable2' => $this->commonModel->getList('menu',[],['sort'=>['queue'=>1]])]);
        } else return redirect()->route('403');
    }

    public function delete_ajax()
    {
        if ($this->request->isAJAX()) {
            $getData=$this->commonModel->getOne('menu',['pages_id' => new ObjectId($this->request->getPost('id')), 'urlType' => $this->request->getPost('type')]);
            if($this->commonModel->get_where(['parent'=>new ObjectId($getData->parent)],'menu')===0)
                $this->commonModel->updateOne('menu',['pages_id' => new ObjectId($getData->parent), 'urlType' => $this->request->getPost('type')],['hasChildren' => false]);
            if ($this->commonModel->updateMany('menu', ['parent' => $this->request->getPost('id')], ['parent' => null]) && $this->commonModel->deleteOne('menu', ['pages_id' => new ObjectId($this->request->getPost('id')), 'urlType' => $this->request->getPost('type')])) {
                if ($this->request->getPost('type') == 'pages')
                    $this->commonModel->updateOne('pages', ['_id' => new ObjectId($this->request->getPost('id'))], ['inMenu' => false]);
                if ($this->request->getPost('type') == 'blog')
                    $this->commonModel->updateOne('blog', ['_id' => new ObjectId($this->request->getPost('id'))], ['inMenu' => false]);
                return view('Modules\Backend\Views\menu\render-nestable2', ['nestable2' => $this->commonModel->getList('menu',[],['sort'=>['queue'=>1]])]);
            }
        } else return redirect()->route('403');
    }

    private function queue($menu, $parent = null)
    {
        $i = 1;
        foreach ($menu as $d) {
            $data=['queue' => $i, 'parent' => $parent];
            if (array_key_exists("children", $d)===true) {
                $this->commonModel->updateOne('menu', ['pages_id' => new ObjectId($d['id'])], ['hasChildren'=>true]);
                $this->queue($d['children'], $d['id']);
            }
            else $data['hasChildren'] = false;

            $this->commonModel->updateOne('menu',['pages_id'=>new ObjectId($d['id'])], $data);
            $i++;
        }
    }

    public function queue_ajax()
    {
        if ($this->request->isAJAX()) {
            $this->queue($this->request->getPost('queue'), null);
            return view('Modules\Backend\Views\menu\render-nestable2', ['nestable2' => $this->commonModel->getList('menu',[],['sort'=>['queue'=>1]])]);
        } else return redirect()->route('403');
    }

    public function listURLs()
    {
        $this->defData=array_merge($this->defData,['pages' => $this->commonModel->getList('pages', ['inMenu' => false, 'isActive' => true]),
            'blogs' => $this->commonModel->getList('blog', ['inMenu' => false, 'isActive' => true]),
            'nestable2' => $this->commonModel->getList('menu',[],['sort'=>['queue'=>1]])]);
        return view('Modules\Backend\Views\menu\list', $this->defData);
    }
}
