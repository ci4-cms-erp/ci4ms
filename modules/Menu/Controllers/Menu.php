<?php

namespace Modules\Menu\Controllers;

class Menu extends \Modules\Backend\Controllers\BaseController
{
    /**
     * Varsayılan dil kodunu döndürür.
     */
    private function getDefaultLang(): string
    {
        $defaultLang = cache('default_frontend_language');
        if ($defaultLang === null) {
            $def = $this->commonModel->selectOne('languages', [
                'is_default'  => 1,
                'is_active'   => 1,
                'is_frontend' => 1,
            ], 'code');
            $defaultLang = $def->code ?? 'tr';
            cache()->save('default_frontend_language', $defaultLang, 3600);
        }
        return $defaultLang;
    }

    /**
     * where parametresinden ana tablo adını ve urlType değerini belirler.
     * pages_langs → pages, blog_langs → blog
     */
    private function resolveTableInfo(string $where): array
    {
        $map = [
            'pages_langs' => ['mainTable' => 'pages', 'urlType' => 'pages', 'fk' => 'pages_id', 'prefix' => ''],
            'blog_langs'  => ['mainTable' => 'blog',  'urlType' => 'blog',  'fk' => 'blog_id',  'prefix' => 'blog/'],
            'pages'       => ['mainTable' => 'pages', 'urlType' => 'pages', 'fk' => 'pages_id', 'prefix' => ''],
            'blog'        => ['mainTable' => 'blog',  'urlType' => 'blog',  'fk' => 'blog_id',  'prefix' => 'blog/'],
        ];
        return $map[$where] ?? $map['pages'];
    }

    /**
     * Tüm aktif diller için menü cache'lerini temizler.
     */
    private function clearMenuCache(): void
    {
        cache()->delete('menus');
        $langs = cache('frontend_languages');
        if ($langs === null) {
            $rows = $this->commonModel->lists('languages', 'code', [
                'is_active'   => 1,
                'is_frontend' => 1,
            ]);
            $langs = array_column($rows, 'code');
        }
        foreach ($langs as $lang) {
            cache()->delete('menus_' . $lang);
        }
    }

    public function index()
    {
        $defaultLang = $this->getDefaultLang();

        $this->defData = array_merge($this->defData, [
            'pages' => $this->commonModel->lists(
                'pages',
                'pages.id, pages.inMenu, pages.isActive, pages_langs.title, pages_langs.seflink',
                ['pages.inMenu' => false, 'pages.isActive' => true, 'pages_langs.lang' => $defaultLang],
                'pages.id ASC', 0, 0, [], [],
                [['table' => 'pages_langs', 'cond' => 'pages_langs.pages_id = pages.id', 'type' => 'inner']]
            ),
            'blogs' => $this->commonModel->lists(
                'blog',
                'blog.id, blog.inMenu, blog.isActive, blog_langs.title, blog_langs.seflink',
                ['blog.inMenu' => false, 'blog.isActive' => true, 'blog_langs.lang' => $defaultLang],
                'blog.id ASC', 0, 0, [], [],
                [['table' => 'blog_langs', 'cond' => 'blog_langs.blog_id = blog.id', 'type' => 'inner']]
            ),
            'nestable2' => $this->commonModel->lists('menu', '*', [], 'queue ASC')
        ]);
        return view('Modules\Menu\Views\menu', $this->defData);
    }

    public function create()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();

        if (!empty($this->request->getPost('type')) && !in_array($this->request->getPost('type'), ['pages', 'blog', 'pages_langs', 'blog_langs'])) {
            $valD = [
                'type' => ['label' => 'type', 'rules' => 'required|in_list[url]'],
                'URL' => ['label' => 'URL', 'rules' => 'required|valid_url'],
                'URLname' => ['label' => 'URLname', 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
                'target' => ['label' => 'target', 'rules' => 'required|in_list[_blank,_self,_parent,_top]']
            ];
        } else {
            $valD = [
                'id' => ['label' => 'ID', 'rules' => 'required|is_natural_no_zero'],
                'where' => ['label' => 'where', 'rules' => 'required|in_list[pages,blog,pages_langs,blog_langs]'],
            ];
        }

        if ($this->validate($valD) == false) return $this->fail($this->validator->getErrors());

        $pMax = $this->commonModel->selectOne('menu', ['parent' => null], '*', 'queue DESC');
        if (empty($pMax)) $pMax = (object)['queue' => 0];

        if ($this->request->getPost('type') == 'url') {
            $data = [
                'queue' => $pMax->queue + 1,
                'urlType' => 'url',
                'pages_id' => null,
                'seflink' => $this->request->getPost('URL'),
                'parent' => null,
                'title' => esc(strip_tags(trim($this->request->getPost('URLname')))),
                'target' => $this->request->getPost('target')
            ];
        } else {
            $where = $this->request->getPost('where');
            $info = $this->resolveTableInfo($where);
            $defaultLang = $this->getDefaultLang();
            $langTable = ($info['mainTable'] === 'pages') ? 'pages_langs' : 'blog_langs';
            $fk = $info['fk'];

            $added = $this->commonModel->selectOne($langTable, [$fk => $this->request->getPost('id'), 'lang' => $defaultLang], 'title, seflink');
            $mainId = (int)$this->request->getPost('id');

            $data = [
                'pages_id' => $mainId,
                'parent' => null,
                'queue' => $pMax->queue + 1,
                'urlType' => $info['urlType'],
                'title' => $added->title ?? '',
                'seflink' => $info['prefix'] . ($added->seflink ?? '')
            ];
            $this->commonModel->edit($info['mainTable'], ['inMenu' => true], ['id' => $mainId]);
        }

        if ($this->commonModel->create('menu', $data)) {
            $this->clearMenuCache();
            return view('Modules\Menu\Views\render-nestable2', ['nestable2' => $this->commonModel->lists('menu', '*', [], 'queue ASC')], ['debug' => false]);
        }
    }

    public function addMultipleMenu()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $where = $this->request->getPost('where');
        $info = $this->resolveTableInfo($where);
        $defaultLang = $this->getDefaultLang();
        $langTable = ($info['mainTable'] === 'pages') ? 'pages_langs' : 'blog_langs';
        $fk = $info['fk'];

        foreach ($this->request->getPost('pageChecked') as $item) {
            $pMax = $this->commonModel->selectOne('menu', ['parent' => null], '*', 'queue DESC');
            if (empty($pMax)) $pMax = (object)['queue' => 0];

            $d = $this->commonModel->selectOne($langTable, [$fk => $item, 'lang' => $defaultLang], 'title, seflink');

            $data = [
                'pages_id' => $item,
                'parent' => null,
                'queue' => $pMax->queue + 1,
                'urlType' => $info['urlType'],
                'title' => $d->title ?? '',
                'seflink' => $info['prefix'] . ($d->seflink ?? '')
            ];
            $this->commonModel->edit($info['mainTable'], ['inMenu' => true], ['id' => $item]);
            $this->commonModel->create('menu', $data);
        }
        $this->clearMenuCache();
        return view('Modules\Menu\Views\render-nestable2', ['nestable2' => $this->commonModel->lists('menu', '*', [], 'queue ASC')], ['debug' => false]);
    }

    public function delete_ajax()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $getData = $this->commonModel->selectOne('menu', ['id' => $this->request->getPost('id')]);
        if (empty($getData)) return $this->failNotFound();

        $info = $this->resolveTableInfo($getData->urlType);

        if ($this->commonModel->isHave('menu', ['parent' => $getData->id]) === 1) {
            $reQ = $this->commonModel->lists('menu', '*', ['parent' => $getData->id]);
            $bigQ = $this->commonModel->selectOne('menu', ['parent' => null, 'id!=' => $getData->id], '*', 'queue DESC');
            if (empty($bigQ)) $bigQ = (object)['queue' => 0];
            foreach ($reQ as $item) {
                $this->commonModel->edit('menu', ['queue' => $bigQ->queue + 1, 'parent' => null], ['id' => $item->id]);
            }
        }

        $this->commonModel->remove('menu', ['id' => $getData->id]);
        if (!empty($getData->parent) && $this->commonModel->isHave('menu', ['parent' => (int)$getData->parent]) === 0) {
            $this->commonModel->edit('menu', ['hasChildren' => false], ['id' => $getData->parent]);
        }
        if (!empty($getData->pages_id)) {
            $this->commonModel->edit($info['mainTable'], ['inMenu' => 0], ['id' => $getData->pages_id]);
        }

        $this->clearMenuCache();
        return view('Modules\Menu\Views\render-nestable2', ['nestable2' => $this->commonModel->lists('menu', '*', [], 'queue ASC')], ['debug' => false]);
    }

    private function queue($menu, $parent = null)
    {
        $i = 1;
        foreach ($menu as $d) {
            $data = ['queue' => $i, 'parent' => $parent];
            if (array_key_exists("children", $d)) {
                $this->commonModel->edit('menu', ['hasChildren' => true], ['id' => $d['id']]);
                $this->queue($d['children'], $d['id']);
            } else {
                $data['hasChildren'] = false;
            }
            $this->commonModel->edit('menu', $data, ['id' => $d['id']]);
            $i++;
        }
    }

    public function queue_ajax()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        if (!empty($this->request->getPost('queue'))) {
            $this->queue($this->request->getPost('queue'));
            $this->clearMenuCache();
        }
        return view('Modules\Menu\Views\render-nestable2', ['nestable2' => $this->commonModel->lists('menu', '*', [], 'queue ASC')], ['debug' => false]);
    }

    public function listURLs()
    {
        $defaultLang = $this->getDefaultLang();

        $this->defData = array_merge($this->defData, [
            'pages' => $this->commonModel->lists(
                'pages',
                'pages.id, pages.inMenu, pages.isActive, pages_langs.title, pages_langs.seflink',
                ['pages.inMenu' => false, 'pages.isActive' => true, 'pages_langs.lang' => $defaultLang],
                'pages.id ASC', 0, 0, [], [],
                [['table' => 'pages_langs', 'cond' => 'pages_langs.pages_id = pages.id', 'type' => 'inner']]
            ),
            'blogs' => $this->commonModel->lists(
                'blog',
                'blog.id, blog.inMenu, blog.isActive, blog_langs.title, blog_langs.seflink',
                ['blog.inMenu' => false, 'blog.isActive' => true, 'blog_langs.lang' => $defaultLang],
                'blog.id ASC', 0, 0, [], [],
                [['table' => 'blog_langs', 'cond' => 'blog_langs.blog_id = blog.id', 'type' => 'inner']]
            ),
            'nestable2' => $this->commonModel->lists('menu', '*', [], 'queue ASC')
        ]);
        return view('Modules\Menu\Views\list', $this->defData, ['debug' => false]);
    }
}
