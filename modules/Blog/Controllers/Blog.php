<?php

namespace Modules\Blog\Controllers;

use Modules\Backend\Libraries\CommonTagsLibrary;
use Modules\Backend\Models\AjaxModel;

class Blog extends \Modules\Backend\Controllers\BaseController
{
    /**
     * @var CommonTagsLibrary
     */
    private $commonTagsLib;
    /**
     * @var AjaxModel
     */
    private $model;

    /**
     *
     */
    public function __construct()
    {
        $this->model = new AjaxModel();
        $this->commonTagsLib = new CommonTagsLibrary();
    }

    /**
     * @return \CodeIgniter\HTTP\ResponseInterface|string
     */
    public function index()
    {
        if ($this->request->is('post') && $this->request->isAJAX()) {
            $parsed = $this->commonBackendLibrary->getDatatablesPagination($this->request->getPost());
            $defaultLocale = setting('App.defaultLocale') ?: 'tr';
            $joins = [
                ['table' => 'blog_langs', 'cond' => 'blog_langs.blog_id = blog.id AND blog_langs.lang = "' . $defaultLocale . '"', 'type' => 'left']
            ];
            $like = [];
            $postData = [];
            if (!empty($parsed['searchString'])) {
                $like = ['blog_langs.title' => $parsed['searchString']];
            }
            $results = $this->commonModel->lists('blog', 'blog.id, blog_langs.title, blog.isActive', $postData, 'blog.id DESC', $parsed['length'], $parsed['start'], $like, [], $joins);
            $totalRecords = $this->commonModel->lists('blog', 'blog.id', $postData, 'blog.id DESC', 0, 0, $like, [], $joins, ['count' => true]);
            foreach ($results as $result) {
                $result->isActive = '<input type="checkbox" name="my-checkbox" class="bswitch" ' . ((bool)$result->isActive === true ? 'checked' : '') . ' data-id="' . $result->id . '" data-off-color="danger" data-on-color="success">';
                $result->actions = '<a href="' . route_to('blogUpdate', $result->id) . '"
                                   class="btn btn-outline-info btn-sm">' . lang('Backend.update') . '</a>
                                   <a href="javascript:void(0);" onclick="deleteItem(' . $result->id . ')"
                                   class="btn btn-outline-danger btn-sm">' . lang('Backend.delete') . '</a>';
            }
            $data = [
                'draw' => $parsed['draw'],
                'iTotalRecords' => $totalRecords,
                'iTotalDisplayRecords' => $totalRecords,
                'aaData' => $results,
            ];
            return $this->respond($data, 200);
        }
        return view('Modules\Blog\Views\list', $this->defData);
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse|string
     */
    public function new()
    {
        $translationService = new \Modules\LanguageManager\Libraries\TranslationService();
        $languages = $translationService->getActiveLanguages();

        if ($this->request->is('post')) {
            $valData = ([
                'lang.*.title' => ['label' => lang('Backend.title'), 'rules' => 'required|regex_match[/^[^<>{}=]+$/u]'],
                'lang.*.seflink' => ['label' => lang('Backend.url'), 'rules' => 'required|regex_match[/^[a-z0-9]+(?:-[a-z0-9]+)*$/]'],
                'lang.*.content' => ['label' => lang('Backend.content'), 'rules' => 'required|html_purify'],
                'isActive' => ['label' => lang('Backend.publish') . ' / ' . lang('Backend.draft'), 'rules' => 'required|in_list[0,1]'],
                'categories.*' => ['label' => lang('Blog.categories'), 'rules' => 'required|is_natural_no_zero'],
                'author' => ['label' => lang('Blog.author'), 'rules' => 'required|is_natural_no_zero'],
                'created_at' => ['label' => lang('Backend.createdAt'), 'rules' => 'required|valid_date[d.m.Y H:i:s]']
            ]);
            if (!empty($this->request->getPost('pageimg'))) {
                $valData['pageimg'] = ['label' => lang('Backend.coverImage'), 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'];
                $valData['pageIMGWidth'] = ['label' => lang('Backend.coverImgWith'), 'rules' => 'required|is_natural_no_zero'];
                $valData['pageIMGHeight'] = ['label' => lang('Backend.coverImgHeight'), 'rules' => 'required|is_natural_no_zero'];
            }

            $langsPost = $this->request->getPost('lang');
            // manual seflink unique check
            foreach ($langsPost as $lanCode => $lanData) {
                if ($this->commonModel->isHave('blog_langs', ['seflink' => $lanData['seflink']]) === 1) {
                    return redirect()->route('blogCreate')->withInput()->with('error', 'Blog seflink adresi daha önce kullanılmış. lütfen kontrol ederek bir daha oluşturmayı deneyeyiniz. Seflink: ' . $lanData['seflink']);
                }
            }

            if ($this->validate($valData) == false) return redirect()->route('blogCreate')->withInput()->with('errors', $this->validator->getErrors());

            $data = ['isActive' => (bool)$this->request->getPost('isActive'), 'inMenu' => false, 'author' => $this->request->getPost('author'), 'created_at' => date('Y-m-d H:i:s', strtotime($this->request->getPost('created_at')))];

            $insertID = $this->commonModel->create('blog', $data);
            if ($insertID) {
                // insert translatable fields
                foreach ($langsPost as $lanCode => $lanData) {
                    $seoData = clone $this->request;
                    $seoData->setMethod('post'); // Mock a request to build seo data if needed?
                    // Note: buildSeoData expects $_POST directly, we need to pass the isolated array or modify backend library.
                    // simpler route: manually extract seo directly or use the whole post. Let's pass the language specific array:
                    // we can't easily use buildSeoData if it expects global post. Let's send the merged array:
                    $langDataMerged = array_merge($this->request->getPost(), $lanData);
                    $seoData = $this->commonBackendLibrary->buildSeoData($langDataMerged);

                    $this->commonModel->create('blog_langs', [
                        'blog_id' => $insertID,
                        'lang' => $lanCode,
                        'title' => trim(strip_tags($lanData['title'])),
                        'seflink' => trim(strip_tags($lanData['seflink'])),
                        'content' => $lanData['content'],
                        'seo' => !empty($seoData) ? $seoData : ''
                    ]);
                }

                if (!empty($this->request->getPost('categories'))) {
                    foreach ($this->request->getPost('categories') as $item) {
                        $this->commonModel->create('blog_categories_pivot', ['blog_id' => $insertID, 'categories_id' => $item]);
                    }
                }

                // keywords are per language? For now, the existing form has one keyword field or multiple?
                // Currently it has one. We'll add tags globally.
                $firstLang = array_key_first($langsPost);
                if (!empty($this->request->getPost('keywords'))) $this->commonTagsLib->checkTags($this->request->getPost('keywords'), 'blogs', (string)$insertID, 'tags');
                return redirect()->route('blogs', [1])->with('message', lang('Backend.created', [esc($langsPost[$firstLang]['title'])]));
            } else return redirect()->route('blogCreate')->withInput()->with('error', lang('Backend.notCreated', ['Blog']));
        }
        $locale = empty(session()->get('customLocale')) ? \Config\Services::request()->getLocale() : session()->get('customLocale');
        $defaultLocale = setting('App.defaultLocale') ?: 'tr';
        if (setting('App.siteLanguageMode') == 'single') $locale = $defaultLocale;

        $this->defData['categories'] = $this->commonModel->lists('categories', 'categories.id, categories_langs.title, categories_langs.seflink', ['categories.isActive' => true], 'categories.id ASC', 0, 0, [], [], [
             ['table' => 'categories_langs', 'cond' => "categories_langs.categories_id = categories.id AND categories_langs.lang = '{$locale}'", 'type' => 'left']
        ]);
        $this->defData['authors'] = $this->commonModel->lists('users', '*', ['active' => 1]);
        $this->defData['languages'] = $languages;
        return view('Modules\Blog\Views\create', $this->defData);
    }

    /**
     * @param string $id
     * @return \CodeIgniter\HTTP\RedirectResponse|string
     */
    public function edit(string $id)
    {
        $translationService = new \Modules\LanguageManager\Libraries\TranslationService();
        $languages = $translationService->getActiveLanguages();

        if ($this->request->is('post')) {
            $valData = ([
                'lang.*.title' => ['label' => lang('Backend.title'), 'rules' => 'required|regex_match[/^[^<>{}=]+$/u]'],
                'lang.*.seflink' => ['label' => lang('Backend.url'), 'rules' => 'required|regex_match[/^[a-z0-9]+(?:-[a-z0-9]+)*$/]'],
                'lang.*.content' => ['label' => lang('Backend.content'), 'rules' => 'required|html_purify'],
                'isActive' => ['label' => lang('Backend.publish') . ' / ' . lang('Backend.draft'), 'rules' => 'required|in_list[0,1]'],
                'categories.*' => ['label' => lang('Blog.categories'), 'rules' => 'required|is_natural_no_zero'],
                'author' => ['label' => lang('Blog.author'), 'rules' => 'required|is_natural_no_zero'],
                'created_at' => ['label' => lang('Backend.createdAt'), 'rules' => 'required|valid_date[d.m.Y H:i:s]']
            ]);
            if (!empty($this->request->getPost('pageimg'))) {
                $valData['pageimg'] = ['label' => lang('Backend.coverImage'), 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'];
                $valData['pageIMGWidth'] = ['label' => lang('Backend.coverImgWith'), 'rules' => 'required|is_natural_no_zero'];
                $valData['pageIMGHeight'] = ['label' => lang('Backend.coverImgHeight'), 'rules' => 'required|is_natural_no_zero'];
            }

            $langsPost = $this->request->getPost('lang');
            // Check unique seflink for languages, ignoring self
            foreach ($langsPost as $lanCode => $lanData) {
                // query blog_langs where seflink equals and blog_id != $id
                $check = $this->commonModel->selectOne('blog_langs', ['seflink' => $lanData['seflink'], 'blog_id !=' => $id]);
                if (!empty($check)) {
                    return redirect()->route('blogUpdate', [$id])->withInput()->with('error', 'Blog seflink adresi daha önce kullanılmış. lütfen kontrol ederek bir daha oluşturmayı deneyeyiniz. Seflink: ' . $lanData['seflink']);
                }
            }

            if ($this->validate($valData) == false) return redirect()->route('blogUpdate', [$id])->withInput()->with('errors', $this->validator->getErrors());

            $data = ['isActive' => (bool)$this->request->getPost('isActive'), 'author' => $this->request->getPost('author'), 'created_at' => date('Y-m-d H:i:s', strtotime($this->request->getPost('created_at')))];

            if ($this->commonModel->edit('blog', $data, ['id' => $id])) {
                foreach ($langsPost as $lanCode => $lanData) {
                    $langDataMerged = array_merge($this->request->getPost(), $lanData);
                    $seoData = $this->commonBackendLibrary->buildSeoData($langDataMerged);
                    $langUpdateData = [
                        'blog_id' => $id,
                        'lang' => $lanCode,
                        'title' => trim(strip_tags($lanData['title'])),
                        'seflink' => trim(strip_tags($lanData['seflink'])),
                        'content' => $lanData['content'],
                        'seo' => !empty($seoData) ? $seoData : ''
                    ];

                    if ($this->commonModel->isHave('blog_langs', ['blog_id' => $id, 'lang' => $lanCode]) === 1) {
                        $this->commonModel->edit('blog_langs', $langUpdateData, ['blog_id' => $id, 'lang' => $lanCode]);
                    } else {
                        $this->commonModel->create('blog_langs', $langUpdateData);
                    }
                }

                if (!empty($this->request->getPost('keywords')))
                    $this->commonTagsLib->checkTags($this->request->getPost('keywords'), 'blogs', $id, 'tags', true);
                if (!empty($this->request->getPost('categories'))) {
                    $this->commonModel->remove('blog_categories_pivot', ['blog_id' => $id]);
                    foreach ($this->request->getPost('categories') as $item) {
                        $this->commonModel->create('blog_categories_pivot', ['blog_id' => $id, 'categories_id' => $item]);
                    }
                }
                $firstLang = array_key_first($langsPost);
                return redirect()->route('blogs', [1])->with('message', lang('Backend.updated', [esc($langsPost[$firstLang]['title'])]));
            } else return redirect()->route('blogUpdate', [$id])->withInput()->with('error', lang('Backend.notUpdated', ['Blog']));
        }
        $this->defData['tags'] = $this->model->limitTags_ajax(['tags_pivot.tagType' => 'blogs', 'tags_pivot.piv_id' => $id]);
        $t = [];
        foreach ($this->defData['tags'] as $tag) {
            $t[] = ['id' => (string)$tag->id, 'value' => $tag->tag];
        }
        $locale = empty(session()->get('customLocale')) ? \Config\Services::request()->getLocale() : session()->get('customLocale');
        $defaultLocale = setting('App.defaultLocale') ?: 'tr';
        if (setting('App.siteLanguageMode') == 'single') $locale = $defaultLocale;

        $this->defData['categories'] = $this->commonModel->lists('categories', 'categories.id, categories_langs.title, categories_langs.seflink', ['categories.isActive' => true], 'categories.id ASC', 0, 0, [], [], [
             ['table' => 'categories_langs', 'cond' => "categories_langs.categories_id = categories.id AND categories_langs.lang = '{$locale}'", 'type' => 'left']
        ]);
        $this->defData['infos'] = $this->commonModel->selectOne('blog', ['id' => $id]);

        $langRows = $this->commonModel->lists('blog_langs', '*', ['blog_id' => $id]);
        $langsData = [];
        foreach ($langRows as $lr) {
            $langsData[$lr->lang] = $lr;
            $langsData[$lr->lang]->seo = json_decode($lr->seo);
        }
        $this->defData['langsData'] = $langsData;

        $this->defData['infos']->categories = $this->commonModel->lists('blog_categories_pivot', '*', ['blog_id' => $id]);
        $this->defData['tags'] = json_encode($t, JSON_UNESCAPED_UNICODE);
        $this->defData['authors'] = $this->commonModel->lists('users', '*', ['active' => 1]);
        $this->defData['languages'] = $languages;
        unset($t);
        return view('Modules\Blog\Views\update', $this->defData);
    }

    /**
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function delete()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valData = ([
            'id' => ['label' => '', 'rules' => 'required|is_natural_no_zero'],
        ]);
        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());

        $deleteId = $this->request->getPost('id');
        $defaultLocale = setting('App.defaultLocale') ?: 'tr';
        $blogLang = $this->commonModel->selectOne('blog_langs', ['blog_id' => $deleteId, 'lang' => $defaultLocale]);
        $title = $blogLang ? $blogLang->title : 'Blog';

        if ($this->commonModel->remove('blog', ['id' => $deleteId]) === true) return $this->respond(['status' => 'success', 'message' => lang('Backend.deleted', [$title])]);
        else return $this->respond(['status' => 'error', 'message' => lang('Backend.notDeleted', [$title])]);
    }

    /**
     * @param string $id
     * @return \CodeIgniter\HTTP\ResponseInterface|string
     */
    public function commentList(string $id)
    {
        if ($this->request->is('post') && $this->request->isAJAX()) {
            $parsed = $this->commonBackendLibrary->getDatatablesPagination($this->request->getPost());
            $searchData = ['isApproved' => $this->request->getPost('isApproved') == 'true' ? true : false];
            $like = [];
            if (!empty($parsed['searchString'])) $like = ['comFullName' => $parsed['searchString'], 'comEmail' => $parsed['searchString']];
            $results = $this->commonModel->lists(
                'comments',
                '*',
                $searchData,
                'id DESC',
                $parsed['length'],
                $parsed['start'],
                $like
            );
            $totalRecords = $this->commonModel->count('comments', $searchData);
            $totalDisplayRecords = $totalRecords;
            $c = ($parsed['start'] > 0) ? $parsed['start'] + 1 : 1;
            $aaData = [];
            foreach ($results as $result) {
                $aaData[] = [
                    'id' => $c,
                    'com_name_surname' => esc($result->comFullName),
                    'email' => esc($result->comEmail),
                    'created_at' => $result->created_at,
                    'status' => ($result->isApproved == true) ? 'Approved' : 'Not approved',
                    'process' => '<a href="' . route_to('displayComment', $result->id) . '"
                               class="btn btn-outline-info btn-sm">' . lang('Backend.update') . '</a>
                            <a href="javascript:void(0);" onclick="deleteItem(' . $result->id . ')"
                               class="btn btn-outline-danger btn-sm">' . lang('Backend.delete') . '</a>'
                ];
                $c++;
            }

            $data = [
                'draw' => $parsed['draw'],
                'iTotalRecords' => $totalRecords,
                'iTotalDisplayRecords' => $totalDisplayRecords,
                'aaData' => $aaData,
            ];
            return $this->respond($data, 200);
        }
        return view('Modules\Blog\Views\comments\commentList', $this->defData);
    }

    public function commentRemove()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valData = ([
            'id' => ['label' => '', 'rules' => 'required|is_natural_no_zero'],
        ]);
        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
        $comment = $this->commonModel->selectOne('comments', ['id' => $this->request->getPost('id')]);
        if ($this->commonModel->remove('comments', ['id' => $this->request->getPost('id')]) === true) return $this->respond(['status' => 'success', 'message' => lang('Backend.deleted', [$comment->comFullName])]);
        else return $this->respond(['status' => 'error', 'message' => lang('Backend.notDeleted', [$comment->comFullName])]);
    }

    public function displayComment(int $id)
    {
        $this->defData['commentInfo'] = $this->commonModel->selectOne('comments', ['id' => $id]);
        $this->defData['blogInfo'] = $this->commonModel->selectOne('blog', ['id' => $this->defData['commentInfo']->blog_id]);
        return view('Modules\Blog\Views\comments\displayComment', $this->defData);
    }

    public function confirmComment(int $id)
    {
        $rules = [
            'options' => 'required|is_natural_no_zero|in_list[1,2]'
        ];
        if (!$this->validate($rules)) return redirect()->route('displayComment', [$id])->withInput()->with('errors', $this->validator->getErrors());
        $isApproved = $this->request->getPost('options');
        if ($isApproved === 1) {
            if ($this->commonModel->edit('comments', ['isApproved' => $isApproved], ['id' => $id])) {
                return redirect()->route('comments')->with('message', lang('Blog.commentPublished', [$id]));
            } else {
                return redirect()->route('displayComment', [$id])->withInput()->with('error', lang('Blog.commentPublishError'));
            }
        } else {
            if ($this->commonModel->remove('comments', ['id' => $id])) {
                return redirect()->route('comments')->with('warning', lang('Backend.deleted', ['#' . $id]));
            } else {
                return redirect()->route('displayComment', [$id])->withInput()->with('error', lang('Backend.notDeleted', ['#' . $id]));
            }
        }
    }

    public function badwordList()
    {
        $this->defData['badwords'] = json_decode($this->commonModel->selectOne('settings', ['key' => 'badwords'], 'value')->value, JSON_UNESCAPED_UNICODE);
        if (empty($this->defData['badwords']))
            $this->defData['badwords'] = null;
        else {
            $this->defData['badwords'] = (object)[
                'list' => implode(',', $this->defData['badwords']['list']),
                'status' => $this->defData['badwords']['status'],
                'autoReject' => $this->defData['badwords']['autoReject'],
                'autoAccept' => $this->defData['badwords']['autoAccept']
            ];
        }
        return view('Modules\Blog\Views\badwordlist', $this->defData);
    }

    public function badwordsAdd()
    {
        try {
            setting()->set('Security.badwords', json_encode(
                [
                    'status' => ($this->request->getPost('status') == "on") ? 1 : 0,
                    'autoReject' => ($this->request->getPost('autoReject') == "on") ? 1 : 0,
                    'autoAccept' => ($this->request->getPost('autoAccept') == "on") ? 1 : 0,
                    'list' => explode(',', strip_tags(trim($this->request->getPost('badwords'))))
                ],
                JSON_UNESCAPED_UNICODE
            ));
            cache()->delete('settings');
            return redirect()->route('badwords')->with('message', lang('Backend.updated', [lang('Blog.badwords')]));
        } catch (\Exception $e) {
            return redirect()->route('badwords')->withInput()->with('error', $e->getMessage());
        }
    }
}
