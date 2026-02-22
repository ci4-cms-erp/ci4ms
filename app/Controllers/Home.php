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

    public function index(string $seflink = '/')
    {
        $page = $this->commonModel->selectOne('pages', ['seflink' => $seflink]);
        if (!empty($page)) {
            $this->defData['pageInfo'] = $page;
            $this->defData['pageInfo']->content = $this->commonLibrary->parseInTextFunctions($this->defData['pageInfo']->content);
            $this->defData['pageInfo']->seo = json_decode($this->defData['pageInfo']->seo);
            $this->defData['pageInfo']->seo = (object)$this->defData['pageInfo']->seo;
            if (!empty($this->defData['pageInfo']->seo->keywords)) {
                $keywords = array_column($this->defData['pageInfo']->seo->keywords, 'value');
                $this->seo()->keywords($keywords);
            }
            $this->seo()->set('title', esc($this->defData['pageInfo']->title))
                ->set('excerpt', esc($this->defData['pageInfo']->seo->description))
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
            if ($seflink != '/') {
                $this->seo()->addSchema(SchemaPreset::breadcrumbs($this->commonLibrary->get_breadcrumbs((int)$this->defData['pageInfo']->id, 'page')));
                $this->defData['breadcrumbs'] = $this->commonLibrary->get_breadcrumbs((int)$this->defData['pageInfo']->id, 'page');
            }
            return view('templates/' . $this->defData['settings']->templateInfos->path . '/pages', $this->defData);
        } else return show_404();
    }

    public function maintenanceMode()
    {
        if ((bool)$this->defData['settings']->maintenanceMode->scalar === false) return redirect()->route('home');
        return view('maintenance', $this->defData);
    }

    public function blog(int $page = 1)
    {
        $perPage = 12;
        $page = $this->request->getUri()->getSegment(2, 1);
        $offset = ($page - 1) * $perPage;
        $this->defData['blogs'] = $this->commonModel->lists('blog', '*', ['isActive' => true], 'id ASC', $perPage, $offset);
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
        $this->defData['categories'] = $this->commonModel->lists('categories', '*', ['isActive' => true]);
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
        $this->defData['breadcrumbs'] = $this->commonLibrary->get_breadcrumbs('/blog/1', 'page');
        return view('templates/' . $this->defData['settings']->templateInfos->path . '/blog/list', $this->defData);
    }

    public function blogDetail(string $seflink)
    {
        if ($this->commonModel->isHave('blog', ['seflink' => $seflink, 'isActive' => true]) === 1) {
            $this->defData['infos'] = $this->commonModel->lists(
                'blog',
                'blog.*,
                GROUP_CONCAT(' . getenv('database.default.DBPrefix') . 'categories.title SEPARATOR \',\') as categories,
                CONCAT(' . getenv('database.default.DBPrefix') . 'users.firstname,\' \',' . getenv('database.default.DBPrefix') . 'users.surname) as author,
                users.profileIMG',
                ['blog.seflink' => strip_tags(trim($seflink))],
                'id ASC',
                0,
                0,
                [],
                [],
                [
                    ['table' => 'blog_categories_pivot', 'cond' => 'blog_categories_pivot.blog_id = blog.id', 'type' => 'left'],
                    ['table' => 'categories', 'cond' => 'categories.id = blog_categories_pivot.categories_id', 'type' => 'left'],
                    ['table' => 'users', 'cond' => 'users.id = blog.author', 'type' => 'left'],
                ],
                ['isReset' => true]
            );
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
            $this->defData['categories'] = $this->commonModel->lists('categories');
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
            return view('templates/' . $this->defData['settings']->templateInfos->path . '/blog/post', $this->defData);
        } else return show_404();
    }

    public function tagList(string $seflink, int $page = 1)
    {
        if ($this->commonModel->isHave('tags', ['seflink' => $seflink]) === 1) {
            $perPage = 12;
            $offset = ($page - 1) * $perPage;
            $this->defData['blogs'] = $this->ci4msModel->taglist(['tags.seflink' => $seflink, 'blog.isActive' => true], $perPage, $offset, 'blog.*');
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
            $this->defData['categories'] = $this->commonModel->lists('categories', '*', ['isActive' => true]);
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
        $this->defData['category'] = $this->commonModel->selectOne('categories', ['seflink' => $seflink]);
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
        $this->defData['categories'] = $this->commonModel->lists('categories', '*', ['isActive' => true]);
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
        $this->seo()->addSchema(SchemaPreset::breadcrumbs($this->commonLibrary->get_breadcrumbs($this->defData['category']->id, 'category')));
        $this->defData['breadcrumbs'] = $this->commonLibrary->get_breadcrumbs((int)$this->defData['category']->id, 'category');
        return view('templates/' . $this->defData['settings']->templateInfos->path . '/blog/list', $this->defData);
    }

    public function newComment()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $vdata = [
            'comFullName' => ['label' => 'Full name', 'rules' => 'required'],
            'comEmail' => ['label' => 'E-mail', 'rules' => 'required|valid_email'],
            'comMessage' => ['label' => 'Join the discussion and leave a comment!', 'rules' => 'required'],
            'captcha' => ['label' => 'Captcha', 'rules' => 'required'],
            'blog_id' => ['label' => 'Blog', 'rules' => 'required|is_natural_no_zero']
        ];

        if(!empty($this->request->getPost('commentID'))) $vdata['commentID'] = ['label' => 'Comment', 'rules' => 'required|is_natural_no_zero'];
        $valData = ($vdata);
        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
        if ($this->request->getPost('captcha') == session()->getFlashdata('cap')) {
            $badwordFilterSettings = json_decode($this->commonModel->selectOne(
                'settings',
                ['option' => 'badwords'],
                'content'
            )->content);
            $checked = $this->commonLibrary->commentBadwordFiltering(
                $this->request->getPost('comMessage'),
                $badwordFilterSettings->list,
                (bool)$badwordFilterSettings->status,
                (bool)$badwordFilterSettings->autoReject
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
        } else return $this->fail('Please get a new captcha !');
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
