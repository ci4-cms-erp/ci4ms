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
            $parsed = $this->commonBackendLibrary->getDatatablesPagination($this->request->getPost());
            
            $locale = empty(session()->get('customLocale')) ? \Config\Services::request()->getLocale() : session()->get('customLocale');
            $defaultLocale = setting('App.defaultLocale') ?: 'tr';
            if (setting('App.siteLanguageMode') == 'single') $locale = $defaultLocale;

            $db = db_connect();
            $builder = $db->table('pages m');
            $builder->select('m.*, l.title, l.seflink');
            $builder->join('pages_langs l', "l.pages_id = m.id AND l.lang = '{$locale}'", 'left');

            if (!empty($parsed['searchString'])) {
                $builder->like('l.title', $parsed['searchString']);
            }

            $totalRecords = $builder->countAllResults(false);
            $builder->orderBy('m.id', 'DESC');
            
            if ($parsed['length'] > 0) {
                $builder->limit($parsed['length'], $parsed['start']);
            }

            $results = $builder->get()->getResult();
            $totalDisplayRecords = $totalRecords;

            foreach ($results as $result) {
                $result->status = '<input type="checkbox" name="my-checkbox" class="bswitch" ' . ((bool)$result->isActive === true ? 'checked' : '') . ' data-id="' . $result->id . '" data-off-color="danger" data-on-color="success">';
                
                $isHome = (int)setting('App.homePage') === (int)$result->id;
                $homeIcon = $isHome ? 'fas fa-home text-success' : 'fas fa-home text-secondary';
                $homeTitle = $isHome ? lang('Pages.isHomePage') : lang('Pages.setAsHomePage');
                $homeBtn = '<button type="button" onclick="setHomePage(' . $result->id . ')" class="btn btn-outline-dark btn-sm mr-1" title="' . $homeTitle . '"><i class="' . $homeIcon . '"></i></button>';

                $result->actions = $homeBtn . '<a href="' . route_to('pageUpdate', $result->id) . '"
                                   class="btn btn-outline-info btn-sm">' . lang('Backend.update') . '</a>
                                <a href="javascript:void(0);" onclick="deleteItem(' . $result->id . ')"
                                   class="btn btn-outline-danger btn-sm">' . lang('Backend.delete') . '</a>';
            }
            $data = [
                'draw' => $parsed['draw'],
                'iTotalRecords' => $totalRecords,
                'iTotalDisplayRecords' => $totalDisplayRecords,
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
                'lang.*.title' => ['label' => lang('Backend.title'), 'rules' => 'required|regex_match[/^[^<>{}=]+$/u]'],
                'lang.*.seflink' => ['label' => lang('Backend.url'), 'rules' => 'required|regex_match[/^[a-z0-9]+(?:-[a-z0-9]+)*$/]'],
                'lang.*.content' => ['label' => lang('Backend.content'), 'rules' => 'required'],
                'isActive' => ['label' => lang('Backend.draft') . ' / ' . lang('Backend.publish'), 'rules' => 'required|in_list[0,1]']
            ]);
            if (!empty($this->request->getPost('pageimg'))) {
                $valData['pageimg'] = ['label' => lang('Backend.coverImgURL'), 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'];
                $valData['pageIMGWidth'] = ['label' => lang('Backend.coverImgWith'), 'rules' => 'required|is_natural_no_zero'];
                $valData['pageIMGHeight'] = ['label' => lang('Backend.coverImgHeight'), 'rules' => 'required|is_natural_no_zero'];
            }
            if (!empty($this->request->getPost('description'))) $valData['description'] = ['label' => lang('Backend.seoDescription'), 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'];
            if (!empty($this->request->getPost('keywords'))) $valData['keywords'] = ['label' => lang('Backend.seoKeywords'), 'rules' => 'required'];

            if ($this->validate($valData) == false) return redirect()->route('pageCreate')->withInput()->with('errors', $this->validator->getErrors());

            $data = [
                'isActive' => (bool)$this->request->getPost('isActive'),
                'inMenu' => false
            ];

            $insertID = $this->commonModel->create('pages', $data);
            if ($insertID) {
                $langsData = $this->request->getPost('lang') ?? [];
                $seoData = $this->commonBackendLibrary->buildSeoData($this->request->getPost());
                
                foreach ($langsData as $langCode => $lData) {
                    $this->commonModel->create('pages_langs', [
                        'pages_id' => $insertID,
                        'lang'     => $langCode,
                        'title'    => strip_tags(trim($lData['title'])),
                        'seflink'  => strip_tags(trim($lData['seflink'])),
                        'content'  => $lData['content'],
                        'seo'      => $seoData
                    ]);
                }
                return redirect()->route('pages', [1])->with('message', lang('Backend.created', ['']));
            }
            else return redirect()->route('pageCreate')->withInput()->with('error', lang('Backend.notCreated', ['']));
        }
        $translationService = new \Modules\LanguageManager\Libraries\TranslationService();
        $this->defData['languages'] = $translationService->getActiveLanguages();
        return view('Modules\Pages\Views\create', $this->defData);
    }

    public function update($id)
    {
        if ($this->request->is('post')) {
            $valData = ([
                'lang.*.title' => ['label' => lang('Backend.title'), 'rules' => 'required|regex_match[/^[^<>{}=]+$/u]'],
                'lang.*.seflink' => ['label' => lang('Backend.url'), 'rules' => 'required|regex_match[/^[a-z0-9]+(?:-[a-z0-9]+)*$/]'],
                'lang.*.content' => ['label' => lang('Backend.content'), 'rules' => 'required'],
                'isActive' => ['label' => lang('Backend.draft') . ' / ' . lang('Backend.publish'), 'rules' => 'required|in_list[0,1]']
            ]);
            
            if (!empty($this->request->getPost('pageimg'))) {
                $valData['pageimg'] = ['label' => lang('Backend.coverImgURL'), 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'];
                $valData['pageIMGWidth'] = ['label' => lang('Backend.coverImgWith'), 'rules' => 'required|is_natural_no_zero'];
                $valData['pageIMGHeight'] = ['label' => lang('Backend.coverImgHeight'), 'rules' => 'required|is_natural_no_zero'];
            }
            if (!empty($this->request->getPost('description'))) $valData['description'] = ['label' => lang('Backend.seoDescription'), 'rules' => 'required'];
            if (!empty($this->request->getPost('keywords'))) $valData['keywords'] = ['label' => lang('Backend.seoKeywords'), 'rules' => 'required'];

            if ($this->validate($valData) == false) return redirect()->route('pageUpdate', [$id])->withInput()->with('errors', $this->validator->getErrors());
            
            $data = [
                'isActive' => (bool)$this->request->getPost('isActive'),
            ];

            if ($this->commonModel->edit('pages', $data, ['id' => $id])) {
                $langsData = $this->request->getPost('lang') ?? [];
                $seoData = $this->commonBackendLibrary->buildSeoData($this->request->getPost());

                foreach ($langsData as $langCode => $lData) {
                    $existing = $this->commonModel->selectOne('pages_langs', ['pages_id' => $id, 'lang' => $langCode]);
                    $langUpdate = [
                        'title'   => strip_tags(trim($lData['title'])),
                        'seflink' => strip_tags(trim($lData['seflink'])),
                        'content' => $lData['content'],
                        'seo'     => $seoData
                    ];
                    if ($existing) {
                        $this->commonModel->edit('pages_langs', $langUpdate, ['id' => $existing->id]);
                    } else {
                        $langUpdate['pages_id'] = $id;
                        $langUpdate['lang'] = $langCode;
                        $this->commonModel->create('pages_langs', $langUpdate);
                    }
                }
                return redirect()->route('pages', [1])->with('message', lang('Backend.updated', ['']));
            }
            else return redirect()->route('pageUpdate', [$id])->withInput()->with('error', lang('Backend.notUpdated', ['']));
        }
        
        $this->defData['pageInfo'] = $this->commonModel->selectOne('pages', ['id' => $id]);
        $translations = $this->commonModel->lists('pages_langs', '*', ['pages_id' => $id]);
        $langsData = [];
        foreach ($translations as $t) {
            $langsData[$t->lang] = $t;
            if ($t->lang === service('request')->getLocale()) {
                // For SEO fields that are shared or just to populate from current lang
                if (!empty($t->seo)) {
                    $this->defData['pageInfo']->seo = json_decode($t->seo);
                }
            }
        }
        $this->defData['langsData'] = $langsData;
        
        $translationService = new \Modules\LanguageManager\Libraries\TranslationService();
        $this->defData['languages'] = $translationService->getActiveLanguages();
        
        return view('Modules\Pages\Views\update', $this->defData);
    }

    public function delete_post()
    {
        $valData = ([
            'id' => ['label' => '', 'rules' => 'required|is_natural_no_zero'],
        ]);
        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
        if ($this->commonModel->remove('pages', ['id' => $this->request->getPost('id')]) === true) {
            $this->commonModel->remove('pages_langs', ['pages_id' => $this->request->getPost('id')]);
            return $this->respond(['status' => 'success', 'message' => lang('Backend.deleted', [''])]);
        }
        else return $this->respond(['status' => 'error', 'message' => lang('Backend.notDeleted', [''])]);
    }

    public function setHomePage(int $id)
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        try {
            $currentHome = (int)setting('App.homePage');
            if ($currentHome === $id) setting()->forget('App.homePage');
            else setting()->set('App.homePage', $id);
            cache()->delete('settings');
            return $this->respond(['status' => true, 'message' => lang('Backend.updated', [lang('Pages.homePage')])], 200);
        } catch (\Exception $e) {
            return $this->fail($e->getMessage());
        }
    }
}
