<?php

namespace Modules\Menu\Controllers;

class Menu extends \Modules\Backend\Controllers\BaseController
{
    public function index()
    {
        $this->defData = array_merge($this->defData, [
            'pages' => $this->commonModel->lists('pages', '*', ['inMenu' => false, 'isActive' => true]),
            'blogs' => $this->commonModel->lists('blog', '*', ['inMenu' => false, 'isActive' => true]),
            'nestable2' => $this->commonModel->lists('menu', '*', [], 'queue ASC')
        ]);
        return view('Modules\Menu\Views\menu', $this->defData);
    }

    public function create()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valD = [
            'type' => ['label' => 'type', 'rules' => 'required|in_list[url,pages,blog]'],
            'where' => ['label' => 'where', 'rules' => 'required|in_list[pages,blog]'],
        ];
        if (!empty($this->request->getPost('URL')))
            $valD['URL'] = ['label' => 'URL', 'rules' => 'required|valid_url_strict'];
        if (!empty($this->request->getPost('URLname')))
            $valD['URLname'] = ['label' => 'URLname', 'rules' => 'required|max_length[100]'];
        if (!empty($this->request->getPost('target')))
            $valD['target'] = ['label' => 'target', 'rules' => 'required|in_list[_blank,_self,_parent,_top]'];
        $valData = ($valD);

        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
        $pMax = $this->commonModel->selectOne('menu', ['parent' => null], '*', 'queue DESC');
        if ($this->request->getPost('type') == 'url') {
            $data = [
                'queue' => $pMax->queue + 1,
                'urlType' => $this->request->getPost('type'),
                'pages_id' => null,
                'seflink' => $this->request->getPost('URL'),
                'parent' => null,
                'title' => esc(strip_tags(trim($this->request->getPost('URLname')))),
                'target' => $this->request->getPost('target')
            ];
        } else {
            $added = $this->commonModel->selectOne($this->request->getPost('where'), ['id' => $this->request->getPost('id')]);
            $type = 'pages';
            if (empty($pMax)) $pMax = (object)['queue' => 0];
            if ($this->request->getPost('where') == 'pages') $seflink = $added->seflink;
            if ($this->request->getPost('where') == 'blog') {
                $seflink = 'blog/' . $added->seflink;
                $type = 'blogs';
            }

            $data = [
                'pages_id' => $added->id,
                'parent' => null,
                'queue' => $pMax->queue + 1,
                'urlType' => $type,
                'title' => $added->title,
                'seflink' => $seflink
            ];
            $this->commonModel->edit($this->request->getPost('where'), ['inMenu' => true], ['id' => $added->id]);
        }
        if ($this->commonModel->create('menu', $data))
            return view('Modules\Menu\Views\render-nestable2', ['nestable2' => $this->commonModel->lists('menu', '*', [], 'queue ASC')]);
    }

    public function addMultipleMenu()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valData = ([
            'type' => ['label' => 'type', 'rules' => 'required|in_list[url,pages,blog]'],
            'where' => ['label' => 'where', 'rules' => 'required|in_list[pages,blog]'],
        ]);

        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
        foreach ($this->request->getPost('pageChecked') as $item) {
            $pMax = $this->commonModel->selectOne('menu', ['parent' => null], '*', 'id DESC');
            if (empty($pMax)) $pMax = (object)['queue' => 0];

            $d = $this->commonModel->selectOne($this->request->getPost('where'), ['id' => $item]);

            if ($this->request->getPost('type') == 'pages') $seflink = $d->seflink;
            if ($this->request->getPost('type') == 'blogs') $seflink = 'blog/' . $d->seflink;

            $data = [
                'pages_id' => $item,
                'parent' => null,
                'queue' => $pMax->queue + 1,
                'urlType' => $this->request->getPost('type'),
                'title' => $d->title,
                'seflink' => $seflink
            ];

            $this->commonModel->edit($this->request->getPost('where'), ['inMenu' => true], ['id' => $item]);
            $this->commonModel->create('menu', $data);
        }
        return view('Modules\Menu\Views\render-nestable2', ['nestable2' => $this->commonModel->lists('menu', '*', [], 'queue ASC')]);
    }

    public function delete_ajax()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valData = ([
            'type' => ['label' => 'type', 'rules' => 'required|in_list[url,pages,blog]'],
            'where' => ['label' => 'where', 'rules' => 'required|in_list[pages,blog]'],
            'id' => ['label' => 'id', 'rules' => 'required|is_natural_no_zero'],
        ]);

        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
        $type = 'pages';
        if ($this->request->getPost('type') == 'blogs') $type = 'blog';
        $getData = $this->commonModel->selectone('menu', ['id' => $this->request->getPost('id'), 'urlType' => $this->request->getPost('type')]);
        if ($this->commonModel->isHave('menu', ['parent' => $getData->id]) === 1) {
            $reQ = $this->commonModel->lists('menu', '*', ['parent' => $getData->id]);
            $bigQ = $this->commonModel->selectOne('menu', ['parent' => null, 'id!=' => $getData->id], '*', 'queue DESC');
            foreach ($reQ as $item) {
                $this->commonModel->edit('menu', ['queue' => $bigQ->queue + 1], ['id' => $item->id]);
            }
        }
        $this->commonModel->remove('menu', ['id' => $getData->id]);
        if (!empty($getData->parent) && $this->commonModel->isHave('menu', ['parent' => (int)$getData->parent]) === 0) $this->commonModel->edit('menu', ['hasChildren' => false], ['id' => $getData->parent]);
        $this->commonModel->edit($type, ['inMenu' => 0], ['id' => $getData->pages_id]);
        return view('Modules\Menu\Views\render-nestable2', ['nestable2' => $this->commonModel->lists('menu', '*', [], 'queue ASC')]);
    }

    private function queue($menu, $parent = null)
    {
        $i = 1;
        foreach ($menu as $d) {
            $data = ['queue' => $i, 'parent' => $parent];
            if (array_key_exists("children", $d) === true) {
                $this->commonModel->edit('menu', ['hasChildren' => true], ['id' => $d['id']]);
                $this->queue($d['children'], $d['id']);
            } else $data['hasChildren'] = false;

            $this->commonModel->edit('menu', $data, ['id' => $d['id']]);
            $i++;
        }
        cache()->delete('menus');
    }

    public function queue_ajax()
    {
        if (!$this->request->isAJAX()) $this->failForbidden();
        $valData = ([
            'queue' => ['label' => 'queue', 'rules' => 'required'],
        ]);

        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
        $this->queue($this->request->getPost('queue'));
        return view('Modules\Menu\Views\render-nestable2', ['nestable2' => $this->commonModel->lists('menu', '*', [], 'queue ASC')]);
    }

    public function listURLs()
    {
        $this->defData = array_merge($this->defData, [
            'pages' => $this->commonModel->lists('pages', '*', ['inMenu' => false, 'isActive' => true]),
            'blogs' => $this->commonModel->lists('blog', '*', ['inMenu' => false, 'isActive' => true]),
            'nestable2' => $this->commonModel->lists('menu', '*', [], 'queue ASC')
        ]);
        return view('Modules\Menu\Views\list', $this->defData);
    }
}
