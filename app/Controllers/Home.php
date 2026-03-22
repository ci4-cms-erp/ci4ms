<?php

namespace App\Controllers;

use App\Libraries\CommonLibrary;
use App\Models\Ci4ms;
use CodeIgniter\I18n\Time;
use Gregwar\Captcha\CaptchaBuilder;
use Modules\Backend\Models\AjaxModel;
use ci4seopro\Libraries\Seo\Search\SchemaPreset;

class Home extends BaseController
{
    private $commonLibrary;
    private $ci4msModel;

    public function __construct()
    {
        $this->commonLibrary = new CommonLibrary();
        $this->ci4msModel = new Ci4ms();
    }

    public function index(string $seflink = '')
    {
        $locale      = $this->request->getLocale();
        $homePageId  = setting('App.homePage');
        $defaultLang = cache('default_frontend_language') ?? setting('App.defaultLocale') ?? 'tr';

        if (!empty($homePageId) && empty($seflink)) {
            // Önce istenen locale'de dene
            $pages = $this->commonModel->lists(
                'pages',
                'pages.*, pages_langs.title, pages_langs.content, pages_langs.seo, pages_langs.seflink',
                ['pages.id' => $homePageId, 'pages_langs.lang' => $locale],
                'pages.id DESC', 1, 0, [], [],
                [['table' => 'pages_langs', 'cond' => 'pages_langs.pages_id = pages.id', 'type' => 'inner']],
                ['isReset' => true]
            );
            // Çeviri yoksa varsayılan dile düş
            if (empty($pages) && $locale !== $defaultLang) {
                $pages = $this->commonModel->lists(
                    'pages',
                    'pages.*, pages_langs.title, pages_langs.content, pages_langs.seo, pages_langs.seflink',
                    ['pages.id' => $homePageId, 'pages_langs.lang' => $defaultLang],
                    'pages.id DESC', 1, 0, [], [],
                    [['table' => 'pages_langs', 'cond' => 'pages_langs.pages_id = pages.id', 'type' => 'inner']],
                    ['isReset' => true]
                );
            }
        } else {
            // Önce istenen locale'de slug'a göre bul
            $pages = $this->commonModel->lists(
                'pages',
                'pages.*, pages_langs.title, pages_langs.content, pages_langs.seo, pages_langs.seflink',
                ['pages_langs.seflink' => $seflink, 'pages_langs.lang' => $locale],
                'pages.id DESC', 1, 0, [], [],
                [['table' => 'pages_langs', 'cond' => 'pages_langs.pages_id = pages.id', 'type' => 'inner']],
                ['isReset' => true]
            );
            // Çeviri yoksa varsayılan dile düş
            if (empty($pages) && $locale !== $defaultLang) {
                $pages = $this->commonModel->lists(
                    'pages',
                    'pages.*, pages_langs.title, pages_langs.content, pages_langs.seo, pages_langs.seflink',
                    ['pages_langs.seflink' => $seflink, 'pages_langs.lang' => $defaultLang],
                    'pages.id DESC', 1, 0, [], [],
                    [['table' => 'pages_langs', 'cond' => 'pages_langs.pages_id = pages.id', 'type' => 'inner']],
                    ['isReset' => true]
                );
            }
        }

        if (empty($pages)) return show_404();
        $this->defData['pageInfo'] = $pages;
        $this->defData['breadcrumbs'] = [];
        $this->defData['pageInfo']->content = $this->commonLibrary->parseInTextFunctions($this->defData['pageInfo']->content);
        $this->defData['pageInfo']->seo = json_decode($this->defData['pageInfo']->seo);
        $this->defData['pageInfo']->seo = (object)$this->defData['pageInfo']->seo;
        if (!empty($this->defData['pageInfo']->seo->keywords)) {
            $keywords = array_column($this->defData['pageInfo']->seo->keywords, 'value');
            $this->seo()->keywords($keywords);
        }
        $this->seo()->set('title', esc($this->defData['pageInfo']->title))
            ->set('excerpt', esc($this->defData['pageInfo']->seo->description ?? ''))
            ->set('logo', site_url(ltrim($this->defData['settings']->logo, '/')))
            ->addSchema(
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'Organization',
                    'url' => site_url(),
                    'logo' => $this->defData['settings']->logo,
                    'name' => esc($this->defData['settings']->siteName),
                    'ContactPoint' =>
                    [
                        '@type' => 'ContactPoint',
                        'telephone' => $this->defData['settings']->contact->phone,
                        'contactType' => 'customer support'
                    ],
                    'sameAs' => array_map(fn($sN) => $sN['link'], (array)$this->defData['settings']->socialNetwork)
                ]
            );
        if (!empty($this->defData['pageInfo']->seo->coverImage))
            $this->seo()->set('image', $this->defData['pageInfo']->seo->coverImage ? site_url($this->defData['pageInfo']->seo->coverImage) : '');
        if ($pages->id != $homePageId) {
            $this->seo()->addSchema(SchemaPreset::breadcrumbs($this->commonLibrary->get_breadcrumbs((int)$this->defData['pageInfo']->id, 'page')));
            $this->defData['breadcrumbs'] = $this->commonLibrary->get_breadcrumbs((int)$this->defData['pageInfo']->id, 'page');
        }

        $this->calculateAlternateLinks('pages', (int)$this->defData['pageInfo']->id);

        return view('templates/' . $this->defData['settings']->templateInfos->path . '/pages', $this->defData);
    }

    public function maintenanceMode()
    {
        if ((bool)$this->defData['settings']->maintenanceMode->scalar === false) return redirect()->route('home');
        return view('maintenance', $this->defData);
    }

    public function blog(int $page = 1)
    {
        $locale = $this->request->getLocale();
        $perPage = 12;
        $offset = ($page - 1) * $perPage;

        $joins = [
            ['table' => 'blog_langs', 'cond' => "blog_langs.blog_id = blog.id AND blog_langs.lang = '{$locale}'", 'type' => 'inner']
        ];

        $this->defData['blogs'] = $this->commonModel->lists('blog', 'blog.*, blog_langs.title, blog_langs.seflink, blog_langs.content, blog_langs.seo', ['blog.isActive' => true], 'blog.id DESC', $perPage, $offset, [], [], $joins);
        $totalBlogs = $this->commonModel->count('blog', ['isActive' => true]);
        $pager = \Config\Services::pager();
        $this->defData['pager'] = $pager->makeLinks($page, $perPage, $totalBlogs, $this->defData['settings']->templateInfos->path, 2);
        $this->defData['pager_info_text'] = (object)['total_products' => $totalBlogs, 'start' => ($page - 1) * $perPage + 1, 'end' => $perPage * $page];
        $this->defData['dateI18n'] = new Time();
        $modelTag = new AjaxModel();
        foreach ($this->defData['blogs'] as $key => $blog) {
            $this->defData['blogs'][$key]->tags = $modelTag->limitTags_ajax(['tags_pivot.piv_id' => $blog->id]);
            $this->defData['blogs'][$key]->author = $this->commonModel->selectOne('users', ['id' => $blog->author], 'firstname,surname');
        }
        $this->defData['categories'] = $this->commonModel->lists('categories', 'categories.id, categories_langs.title, categories_langs.seflink', ['categories.isActive' => true], 'categories.id ASC', 0, 0, [], [], [
            ['table' => 'categories_langs', 'cond' => "categories_langs.categories_id = categories.id AND categories_langs.lang = '{$locale}'", 'type' => 'inner']
        ]);
        $this->seo()->set('title', 'Blog')
            ->set('excerpt', 'Blog Posts')
            ->set('logo', site_url(ltrim($this->defData['settings']->logo, '/')))
            ->addSchema(
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'Organization',
                    'url' => site_url(implode('/', $this->request->getUri()->getSegments())),
                    'logo' => $this->defData['settings']->logo,
                    'name' => esc($this->defData['settings']->siteName),
                    'ContactPoint' =>
                    [
                        '@type' => 'ContactPoint',
                        'telephone' => $this->defData['settings']->contact->phone,
                        'contactType' => 'customer support'
                    ],
                    'sameAs' => array_map(fn($sN) => $sN['link'], (array)$this->defData['settings']->socialNetwork)
                ]
            );
        $this->seo()->addSchema(SchemaPreset::breadcrumbs($this->commonLibrary->get_breadcrumbs('/blog/1', 'page')));
        $this->defData['breadcrumbs'] = $this->commonLibrary->get_breadcrumbs('/blog/1');
        return view('templates/' . $this->defData['settings']->templateInfos->path . '/blog/list', $this->defData);
    }

    public function blogDetail(string $seflink)
    {
        $locale = $this->request->getLocale();
        if ($this->commonModel->isHave('blog_langs', ['seflink' => $seflink, 'lang' => $locale]) === 1) {
            $infosArray = $this->commonModel->lists(
                'blog',
                'blog.*, blog_langs.title, blog_langs.seflink, blog_langs.content, blog_langs.seo,
                GROUP_CONCAT(' . getenv('database.default.DBPrefix') . 'categories_langs.title SEPARATOR \',\') as categories,
                CONCAT(' . getenv('database.default.DBPrefix') . 'users.firstname,\' \',' . getenv('database.default.DBPrefix') . 'users.surname) as author,
                users.profileIMG',
                ['blog_langs.seflink' => strip_tags(trim($seflink)), 'blog_langs.lang' => $locale],
                'blog.id ASC',
                1,
                0,
                [],
                [],
                [
                    ['table' => 'blog_langs', 'cond' => "blog_langs.blog_id = blog.id", 'type' => 'inner'],
                    ['table' => 'blog_categories_pivot', 'cond' => 'blog_categories_pivot.blog_id = blog.id', 'type' => 'left'],
                    ['table' => 'categories', 'cond' => 'categories.id = blog_categories_pivot.categories_id', 'type' => 'left'],
                    ['table' => 'categories_langs', 'cond' => "categories_langs.categories_id = categories.id AND categories_langs.lang = '{$locale}'", 'type' => 'left'],
                    ['table' => 'users', 'cond' => 'users.id = blog.author', 'type' => 'left'],
                ],
                ['isReset' => true]
            );
            $this->defData['infos'] = isset($infosArray) ? $infosArray : null;
            $this->defData['dateI18n'] = new Time();
            $modelTag = new AjaxModel();
            $this->defData['tags'] = $modelTag->limitTags_ajax(['piv_id' => $this->defData['infos']->id]);
            if (!empty($this->defData['tags'])) {
                $keywords = array_column($this->defData['tags'], 'tag');
                $this->seo()->keywords($keywords);
            }
            helper('templates/' . $this->defData['settings']->templateInfos->path . '/funcs');
            $this->defData['comments'] = $this->commonModel->lists('comments', '*', ['blog_id' => $this->defData['infos']->id], 'id ASC', 5);
            $this->defData['infos']->seo = json_decode($this->defData['infos']->seo);
            $this->defData['infos']->seo = (object)$this->defData['infos']->seo;
            $this->defData['categories'] = $this->commonModel->lists('categories', 'categories.id, categories_langs.title, categories_langs.seflink', ['categories.isActive' => true], 'categories.id ASC', 0, 0, [], [], [
                ['table' => 'categories_langs', 'cond' => "categories_langs.categories_id = categories.id AND categories_langs.lang = '{$locale}'", 'type' => 'inner']
            ]);
            $this->seo()->set('title', esc($this->defData['infos']->title))
                ->set('excerpt', esc($this->defData['infos']->title))
                ->set('logo', site_url(ltrim($this->defData['settings']->logo, '/')))
                ->set('author', esc($this->defData['infos']->author))
                ->set('image', $this->defData['infos']->seo->coverImage ?? '')
                ->addSchema(SchemaPreset::article(
                    [
                        'url' => site_url(implode('/', $this->request->getUri()->getSegments())),
                        'logo' => $this->defData['settings']->logo,
                        'name' => esc($this->defData['settings']->siteName),
                        'title' => esc($this->defData['infos']->title),
                        'image' => $this->defData['infos']->seo->coverImage ?? '',
                        'description' => esc($this->defData['infos']->seo->description ?? ''),
                        'date' => $this->defData['infos']->created_at,
                        'author' => esc($this->defData['infos']->author),
                        'section' => $this->defData['infos']->categories,
                        'ContactPoint' =>
                        [
                            '@type' => 'ContactPoint',
                            'telephone' => $this->defData['settings']->contact->phone,
                            'contactType' => 'customer support'
                        ],
                        'sameAs' => array_map(fn($sN) => $sN['link'], (array)$this->defData['settings']->socialNetwork)
                    ]
                ));
            $this->seo()->addSchema(SchemaPreset::breadcrumbs($this->commonLibrary->get_breadcrumbs((int)$this->defData['infos']->id, 'blog')));
            $this->defData['breadcrumbs'] = $this->commonLibrary->get_breadcrumbs((int)$this->defData['infos']->id, 'blog');

            $this->calculateAlternateLinks('blog', (int)$this->defData['infos']->id);

            return view('templates/' . $this->defData['settings']->templateInfos->path . '/blog/post', $this->defData);
        } else return show_404();
    }

    public function tagList(string $seflink, int $page = 1)
    {
        if ($this->commonModel->isHave('tags', ['seflink' => $seflink]) === 1) {
            $locale = $this->request->getLocale();
            $perPage = 12;
            $offset = ($page - 1) * $perPage;
            $this->defData['blogs'] = $this->ci4msModel->taglist(['tags.seflink' => $seflink, 'blog.isActive' => true], $perPage, $offset, 'blog.*, blog_langs.title, blog_langs.seflink, blog_langs.content, blog_langs.seo');
            $totalBlogs = count($this->defData['blogs']);
            $pager = \Config\Services::pager();
            $this->defData['pager'] = $pager->makeLinks($page, $perPage, $totalBlogs, $this->defData['settings']->templateInfos->path, 3);
            $this->defData['pager_info_text'] = (object)['total_products' => $totalBlogs, 'start' => ($page - 1) * $perPage + 1, 'end' => $perPage * $page];
            $this->defData['dateI18n'] = new Time();
            $modelTag = new AjaxModel();
            foreach ($this->defData['blogs'] as $key => $blog) {
                $this->defData['blogs'][$key]->tags = $modelTag->limitTags_ajax(['piv_id' => $blog->id]);
                $this->defData['blogs'][$key]->author = $this->commonModel->selectOne('users', ['id' => $blog->author], 'firstname,surname');
            }
            $this->defData['categories'] = $this->commonModel->lists('categories', 'categories.id, categories_langs.title, categories_langs.seflink', ['categories.isActive' => true], 'categories.id ASC', 0, 0, [], [], [
                ['table' => 'categories_langs', 'cond' => "categories_langs.categories_id = categories.id AND categories_langs.lang = '{$locale}'", 'type' => 'inner']
            ]);
            $this->defData['tagInfo'] = $this->commonModel->selectOne('tags', ['seflink' => $seflink]);
            $this->seo()->set('title', $this->defData['tagInfo']->tag)
                ->set('excerpt', $this->defData['tagInfo']->tag)
                ->set('logo', site_url(ltrim($this->defData['settings']->logo, '/')))
                ->addSchema(
                    [
                        '@context' => 'https://schema.org',
                        '@type' => 'Organization',
                        'url' => site_url(implode('/', $this->request->getUri()->getSegments())),
                        'logo' => $this->defData['settings']->logo,
                        'name' => $this->defData['settings']->siteName,
                        'ContactPoint' =>
                        [
                            '@type' => 'ContactPoint',
                            'telephone' => $this->defData['settings']->contact->phone,
                            'contactType' => 'customer support'
                        ],
                        'sameAs' => array_map(fn($sN) => $sN['link'], (array)$this->defData['settings']->socialNetwork)
                    ]
                );
            $this->seo()->addSchema(SchemaPreset::breadcrumbs($this->commonLibrary->get_breadcrumbs($this->defData['tagInfo']->id, 'tag')));
            $this->defData['breadcrumbs'] = $this->commonLibrary->get_breadcrumbs((int)$this->defData['tagInfo']->id, 'tag');
            return view('templates/' . $this->defData['settings']->templateInfos->path . '/blog/list', $this->defData);
        } else return show_404();
    }

    public function category(string $seflink, int $page = 1)
    {
        $locale = $this->request->getLocale();
        $categoriesArray = $this->commonModel->lists('categories', 'categories.id, categories_langs.title, categories_langs.seflink, categories_langs.seo', ['categories_langs.seflink' => $seflink, 'categories_langs.lang' => $locale], 'categories.id ASC', 1, 0, [], [], [
            ['table' => 'categories_langs', 'cond' => "categories_langs.categories_id = categories.id", 'type' => 'inner']
        ]);

        if (empty($categoriesArray)) return show_404();

        $this->defData['category'] = $categoriesArray[0];
        $this->defData['category']->seo = json_decode($this->defData['category']->seo);
        $this->defData['category']->seo = (object)$this->defData['category']->seo;
        if (!empty($this->defData['category']->seo->keywords)) {
            $keywords = array_column($this->defData['category']->seo->keywords, 'value');
            $this->seo()->keywords($keywords);
        }
        $perPage = 12;
        $offset = ($page - 1) * $perPage;
        $this->defData['blogs'] = $this->ci4msModel->categoryList(['categories_id' => $this->defData['category']->id, 'isActive' => true], $perPage, $offset);
        $totalBlogs = count($this->defData['blogs']);
        $pager = \Config\Services::pager();
        $this->defData['pager'] = $pager->makeLinks($page, $perPage, $totalBlogs, $this->defData['settings']->templateInfos->path, 3);
        $this->defData['pager_info_text'] = (object)['total_products' => $totalBlogs, 'start' => ($page - 1) * $perPage + 1, 'end' => $perPage * $page];
        $this->defData['dateI18n'] = new Time();
        $modelTag = new AjaxModel();
        foreach ($this->defData['blogs'] as $key => $blog) {
            $this->defData['blogs'][$key]->tags = $modelTag->limitTags_ajax(['tags_pivot.piv_id' => $blog->id]);
            $this->defData['blogs'][$key]->author = $this->commonModel->selectOne('users', ['id' => $blog->author], 'firstname,surname');
        }
        $this->defData['categories'] = $this->commonModel->lists('categories', 'categories.id, categories_langs.title, categories_langs.seflink', ['categories.isActive' => true], 'categories.id ASC', 0, 0, [], [], [
            ['table' => 'categories_langs', 'cond' => "categories_langs.categories_id = categories.id AND categories_langs.lang = '{$locale}'", 'type' => 'inner']
        ]);
        $this->seo()->set('title', $this->defData['category']->title)
            ->set('excerpt', $this->defData['category']->seo->description ?? '')
            ->set('logo', site_url(ltrim($this->defData['settings']->logo, '/')))
            ->set('image', !empty($this->defData['category']->seo->coverImage) ? site_url($this->defData['category']->seo->coverImage) : '')
            ->addSchema(
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'Organization',
                    'url' => site_url(implode('/', $this->request->getUri()->getSegments())),
                    'logo' => $this->defData['settings']->logo,
                    'name' => $this->defData['settings']->siteName,
                    'ContactPoint' =>
                    [
                        '@type' => 'ContactPoint',
                        'telephone' => $this->defData['settings']->contact->phone,
                        'contactType' => 'customer support'
                    ],
                    'sameAs' => array_map(fn($sN) => $sN['link'], (array)$this->defData['settings']->socialNetwork)
                ]
            );
        $this->defData['breadcrumbs'] = $this->commonLibrary->get_breadcrumbs((int)$this->defData['category']->id, 'category');

        $this->calculateAlternateLinks('category', (int)$this->defData['category']->id);

        return view('templates/' . $this->defData['settings']->templateInfos->path . '/blog/list', $this->defData);
    }

    public function newComment()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $vdata = [
            'comFullName' => ['label' => 'Full name', 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
            'comEmail' => ['label' => 'E-mail', 'rules' => 'required|valid_email'],
            'comMessage' => ['label' => 'Join the discussion and leave a comment!', 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
            'captcha' => ['label' => 'Captcha', 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
            'blog_id' => ['label' => 'Blog', 'rules' => 'required|is_natural_no_zero']
        ];

        if (!empty($this->request->getPost('commentID'))) $vdata['commentID'] = ['label' => 'Comment', 'rules' => 'required|is_natural_no_zero'];
        $valData = ($vdata);
        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
        if (ENVIRONMENT !== 'development' && $this->request->getPost('captcha') != session()->getFlashdata('cap')) return $this->fail('Please get a new captcha !');
        $checked = $this->commonLibrary->commentBadwordFiltering(
            $this->request->getPost('comMessage'),
            $this->defData['settings']->badwords->list,
            (bool)$this->defData['settings']->badwords->status,
            (bool)$this->defData['settings']->badwords->autoReject
        );
        if (is_bool($checked) && !$checked) return $this->fail('Lütfen kelimelerinize dikkat ediniz.');
        $data = [
            'blog_id' => $this->request->getPost('blog_id'),
            'created_at' => date('Y-m-d H:i:s'),
            'comFullName' => strip_tags(trim($this->request->getPost('comFullName'))),
            'comEmail' => $this->request->getPost('comEmail'),
            'comMessage' => $checked
        ];
        if (!empty($this->request->getPost('commentID'))) {
            $data['parent_id'] = $this->request->getPost('commentID');
            $this->commonModel->edit(
                'comments',
                ['isThereAnReply' => true],
                ['id' => $this->request->getPost('commentID')]
            );
        }
        if ($this->commonModel->create('comments', $data)) return $this->respondCreated(['result' => true]);
    }

    public function repliesComment()
    {
        if ($this->request->isAJAX()) {
            $valData = (['comID' => ['label' => 'Comment', 'rules' => 'required|is_natural_no_zero']]);
            if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
            return $this->respond(['display' => view('templates/' . $this->defData['settings']->templateInfos->path . '/blog/replies', ['replies' => $this->commonModel->lists('comments', '*', ['parent_id' => $this->request->getPost('comID')])])], 200);
        } else return $this->failForbidden();
    }

    public function loadMoreComments()
    {
        if ($this->request->isAJAX()) {
            $valData = (['blogID' => ['label' => 'Blog ID', 'rules' => 'required|is_natural_no_zero'], 'skip' => ['label' => 'data-skip', 'rules' => 'required|is_natural_no_zero']]);
            if (!empty($this->request->getPost('comID'))) $valData['comID'] = ['label' => 'Comment ID', 'rules' => 'required|is_natural_no_zero'];
            if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
            helper('templates/' . $this->defData['settings']->templateInfos->path . '/funcs');
            $data = ['blog_id' => $this->request->getPost('blogID')];
            if (!empty($this->request->getPost('comID'))) $data['parent_id'] = $this->request->getPost('comID');
            $comments = $this->commonModel->lists('comments', '*', $data, 'id ASC', 5, (int)$this->request->getPost('skip'));
            return $this->respond(['display' => view('templates/' . $this->defData['settings']->templateInfos->path . '/blog/loadMoreComments', ['comments' => $comments, 'blogID' => $this->request->getPost('blogID')]), 'count' => count($comments)], 200);
        } else return $this->failForbidden();
    }

    public function commentCaptcha()
    {
        if ($this->request->isAJAX()) {
            $cap = new CaptchaBuilder();
            $cap->setBackgroundColor(139, 203, 183);
            $cap->setIgnoreAllEffects(false);
            $cap->setMaxFrontLines(0);
            $cap->setMaxBehindLines(0);
            $cap->setMaxAngle(1);
            $cap->setTextColor(18, 58, 73);
            $cap->setLineColor(18, 58, 73);
            $cap->build();
            session()->setFlashdata('cap', $cap->getPhrase());
            return $this->respond(['capIMG' => $cap->inline()], 200);
        } else return $this->failForbidden();
    }
}
