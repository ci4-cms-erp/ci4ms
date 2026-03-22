<?php

namespace Modules\Blog\Controllers;

class Categories extends \Modules\Backend\Controllers\BaseController
{
    public function index()
    {
        if ($this->request->is('post') && $this->request->isAJAX()) {
            $parsed = $this->commonBackendLibrary->getDatatablesPagination($this->request->getPost());
            $like = [];
            $postData = [];
            $defaultLocale = setting('App.defaultLocale') ?: 'tr';

            if (!empty($parsed['searchString'])) {
                $like = ['categories_langs.title' => $parsed['searchString']];
            }

            $joins = [
                ['table' => 'categories_langs', 'cond' => "categories_langs.categories_id = categories.id AND categories_langs.lang = '{$defaultLocale}'", 'type' => 'left']
            ];

            $results = $this->commonModel->lists('categories', 'categories.*, categories_langs.title as title, categories_langs.seflink', $postData, 'categories.id DESC', $parsed['length'], $parsed['start'], $like, [], $joins);

            $totalRecords = $this->commonModel->count('categories', $postData);
            $filteredCount = !empty($like)
                ? count($this->commonModel->lists('categories', 'categories.id', $postData, 'categories.id ASC', 0, 0, $like, [], $joins))
                : $totalRecords;

            foreach ($results as $result) {
                $result->actions = '<a href="' . route_to('categoryUpdate', $result->id) . '"
                                   class="btn btn-outline-info btn-sm">' . lang('Backend.update') . '</a>
                                   <button type="button" onclick="deleteItem(' . $result->id . ')"
                                   class="btn btn-outline-danger btn-sm">' . lang('Backend.delete') . '</button>';
            }
            $data = [
                'draw' => $parsed['draw'],
                'iTotalRecords' => $totalRecords,
                'iTotalDisplayRecords' => $filteredCount,
                'aaData' => $results,
            ];
            return $this->respond($data, 200);
        }
        return view('Modules\Blog\Views\categories\list', $this->defData);
    }

    public function new()
    {
        if ($this->request->is('post')) {
            $langInputs = $this->request->getPost('lang');
            if (empty($langInputs) || !is_array($langInputs)) {
                return redirect()->route('categories')->withInput()->with('error', lang('Backend.requiredContent'));
            }

            $valData = [];
            foreach ($langInputs as $l => $in) {
                $valData["lang.{$l}.title"]   = ['label' => lang('Backend.title')." ({$l})", 'rules' => 'required|regex_match[/^[^<>{}=]+$/u]'];
                $valData["lang.{$l}.seflink"] = ['label' => lang('Backend.url')." ({$l})", 'rules' => 'required|regex_match[/^[a-z0-9]+(?:-[a-z0-9]+)*$/]'];
            }

            if (!empty($this->request->getPost('pageimg'))) {
                $valData['pageimg']       = ['label' => lang('Backend.coverImgURL'), 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'];
                $valData['pageIMGWidth']  = ['label' => lang('Backend.coverImgWith'), 'rules' => 'required|is_natural_no_zero'];
                $valData['pageIMGHeight'] = ['label' => lang('Backend.coverImgHeight'), 'rules' => 'required|is_natural_no_zero'];
            }
            if ($this->validate($valData) == false) return redirect()->route('categories')->withInput()->with('errors', $this->validator->getErrors());

            $baseData = ['isActive' => $this->request->getPost('isActive')];
            if (!empty($this->request->getPost('parent'))) $baseData['parent'] = $this->request->getPost('parent');

            $categories_id = $this->commonModel->create('categories', $baseData);

            if ($categories_id) {
                $seoData = $this->commonBackendLibrary->buildSeoData($this->request->getPost());
                foreach ($langInputs as $l => $in) {
                    $insertLang = [
                        'categories_id' => $categories_id,
                        'lang'          => $l,
                        'title'         => trim(strip_tags($in['title'])),
                        'seflink'       => trim(strip_tags($in['seflink'])),
                        'seo'           => !empty($seoData) ? $seoData : null
                    ];
                    $this->commonModel->create('categories_langs', $insertLang);
                }

                $defaultLocale = setting('App.defaultLocale') ?: 'tr';
                $defaultTitle = isset($langInputs[$defaultLocale]['title']) ? $langInputs[$defaultLocale]['title'] : current($langInputs)['title'];

                return redirect()->route('categories', [1])->with('message', lang('Backend.created', [esc($defaultTitle)]));
            } else {
                return redirect()->route('categories')->withInput()->with('error', lang('Backend.notCreated', ['Kategori']));
            }
        }

        $locale = $this->request->getLocale();
        $this->defData['categories'] = $this->commonModel->lists('categories', 'categories.*, categories_langs.title as title', [], 'categories.id DESC', 0, 0, [], [], [
            ['table' => 'categories_langs', 'cond' => "categories_langs.categories_id = categories.id AND categories_langs.lang = '{$locale}'", 'type' => 'left']
        ]);

        if (setting('App.siteLanguageMode') === 'multi') {
            $translationService = new \Modules\LanguageManager\Libraries\TranslationService();
            $this->defData['languages'] = $translationService->getActiveLanguages();
        } else {
            $this->defData['languages'] = [];
        }

        return view('Modules\Blog\Views\categories\create', $this->defData);
    }

    public function edit(string $id)
    {
        if ($this->request->is('post')) {
            $langInputs = $this->request->getPost('lang');
            if (empty($langInputs) || !is_array($langInputs)) {
                return redirect()->route('categories')->withInput()->with('error', lang('Backend.requiredContent'));
            }

            $valData = [];
            foreach ($langInputs as $l => $in) {
                $valData["lang.{$l}.title"]   = ['label' => lang('Backend.title')." ({$l})", 'rules' => 'required|regex_match[/^[^<>{}=]+$/u]'];
                $valData["lang.{$l}.seflink"] = ['label' => lang('Backend.url')." ({$l})", 'rules' => 'required|regex_match[/^[a-z0-9]+(?:-[a-z0-9]+)*$/]'];
            }

            if (!empty($this->request->getPost('pageimg'))) {
                $valData['pageimg']       = ['label' => lang('Backend.coverImgURL'), 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'];
                $valData['pageIMGWidth']  = ['label' => lang('Backend.coverImgWith'), 'rules' => 'required|is_natural_no_zero'];
                $valData['pageIMGHeight'] = ['label' => lang('Backend.coverImgHeight'), 'rules' => 'required|is_natural_no_zero'];
            }
            if ($this->validate($valData) == false) return redirect()->route('categories')->withInput()->with('errors', $this->validator->getErrors());

            $baseData = ['isActive' => $this->request->getPost('isActive')];
            if (!empty($this->request->getPost('parent'))) $baseData['parent'] = $this->request->getPost('parent');

            if ($this->commonModel->edit('categories', $baseData, ['id' => $id])) {
                $seoData = $this->commonBackendLibrary->buildSeoData($this->request->getPost());
                foreach ($langInputs as $l => $in) {
                    $langData = [
                        'title'         => trim(strip_tags($in['title'])),
                        'seflink'       => trim(strip_tags($in['seflink'])),
                        'seo'           => !empty($seoData) ? $seoData : null
                    ];

                    if ($this->commonModel->isHave('categories_langs', ['categories_id' => $id, 'lang' => $l]) === 1) {
                        $this->commonModel->edit('categories_langs', $langData, ['categories_id' => $id, 'lang' => $l]);
                    } else {
                        $langData['categories_id'] = $id;
                        $langData['lang'] = $l;
                        $this->commonModel->create('categories_langs', $langData);
                    }
                }

                $defaultLocale = setting('App.defaultLocale') ?: 'tr';
                $defaultTitle = isset($langInputs[$defaultLocale]['title']) ? $langInputs[$defaultLocale]['title'] : current($langInputs)['title'];

                return redirect()->route('categories', [1])->with('message', lang('Backend.updated', [esc($defaultTitle)]));
            } else {
                return redirect()->route('categories')->withInput()->with('error', lang('Backend.notUpdated', ['Kategori']));
            }
        }

        $locale = $this->request->getLocale();
        $this->defData = array_merge($this->defData, [
            'infos' => $this->commonModel->selectOne('categories', ['id' => $id]),
            'categories' => $this->commonModel->lists('categories', 'categories.*, categories_langs.title as title', ['categories.id!=' => $id], 'categories.id DESC', 0, 0, [], [], [
                ['table' => 'categories_langs', 'cond' => "categories_langs.categories_id = categories.id AND categories_langs.lang = '{$locale}'", 'type' => 'left']
            ])
        ]);

        $langsDataRaw = $this->commonModel->lists('categories_langs', '*', ['categories_id' => $id]);
        $langsData = [];
        foreach ($langsDataRaw as $langRow) {
            $langRow->seo = json_decode($langRow->seo);
            if (!empty($langRow->seo) && !empty($langRow->seo->keywords)) {
                $langRow->seo->keywords = json_encode($langRow->seo->keywords, JSON_UNESCAPED_UNICODE);
            }
            $langsData[$langRow->lang] = $langRow;
        }
        $this->defData['langsData'] = $langsData;

        if (setting('App.siteLanguageMode') === 'multi') {
            $translationService = new \Modules\LanguageManager\Libraries\TranslationService();
            $this->defData['languages'] = $translationService->getActiveLanguages();
        } else {
            $this->defData['languages'] = [];
        }

        return view('Modules\Blog\Views\categories\update', $this->defData);
    }

    public function delete()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valData = ([
            'id' => ['label' => '', 'rules' => 'required|is_natural_no_zero'],
        ]);
        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());

        $id = $this->request->getPost('id');
        $defaultLocale = setting('App.defaultLocale') ?: 'tr';
        $categoryLang = $this->commonModel->selectOne('categories_langs', ['categories_id' => $id, 'lang' => $defaultLocale]);
        $title = $categoryLang ? $categoryLang->title : 'Kategori';

        // cascade will take care of deleting from categories_langs
        if ($this->commonModel->remove('categories', ['id' => $id]) === true) return $this->respond(['status' => 'success', 'message' => lang('Backend.deleted', [$title])]);
        else return $this->respond(['status' => 'error', 'message' => lang('Backend.notDeleted', [$title])]);
    }
}
