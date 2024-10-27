<?php

namespace App\Controllers;

use App\Libraries\CommonLibrary;
use App\Models\Ci4ms;
use CodeIgniter\I18n\Time;
use Gregwar\Captcha\CaptchaBuilder;
use JasonGrimes\Paginator;
use Modules\Backend\Models\AjaxModel;
use Modules\Backend\Models\UserscrudModel;

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
            $keywords = [];
            $this->defData['pageInfo']->seo = json_decode($this->defData['pageInfo']->seo);
            $this->defData['pageInfo']->seo = (object)$this->defData['pageInfo']->seo;
            if (!empty($this->defData['pageInfo']->seo->keywords)) {
                foreach ($this->defData['pageInfo']->seo->keywords as $keyword) {
                    $keywords[] = $keyword->value;
                }
            }
            $this->defData['seo'] = $this->ci4msseoLibrary->metaTags($this->defData['pageInfo']->title, (!empty($this->defData['pageInfo']->seo->description)) ? $this->defData['pageInfo']->seo->description : '', $seflink, $metatags = ['keywords' => $keywords], !empty($this->defData['pageInfo']->seo->coverImage) ? $this->defData['pageInfo']->seo->coverImage : '');
            $this->defData['schema'] = $this->ci4msseoLibrary->ldPlusJson('Organization', [
                'url' => site_url(),
                'logo' => $this->defData['settings']->logo,
                'name' => $this->defData['settings']->siteName,
                'children' => [
                    'ContactPoint' =>
                    [
                        'ContactPoint' => [
                            'telephone' => $this->defData['settings']->company->phone,
                            'contactType' => 'customer support'
                        ]
                    ]
                ],
                'sameAs' => array_map(fn($sN) => $sN['link'], (array)$this->defData['settings']->socialNetwork)
            ]);
            if ($seflink != '/') $this->defData['breadcrumbs'] = $this->commonLibrary->get_breadcrumbs((int)$this->defData['pageInfo']->id);
            return view('templates/' . $this->defData['settings']->templateInfos->path . '/pages', $this->defData);
        } else return show_404();
    }

    public function maintenanceMode()
    {
        d($this->defData['settings']->maintenanceMode->scalar);
        if ((bool)$this->defData['settings']->maintenanceMode->scalar === false) return redirect()->route('home');
        return view('maintenance', $this->defData);
    }

    public function blog()
    {
        $this->defData['seo'] = $this->ci4msseoLibrary->metaTags('Blog', 'blog listesi', 'blog', ['keywords' => ["value" => "blog listesi"]]);
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
            $this->defData['blogs'][$key]->author = $this->commonModel->selectOne('users', ['id' => $blog->author], 'firstname,sirname');
        }
        $this->defData['categories'] = $this->commonModel->lists('categories', '*', ['isActive' => true]);
        $this->defData['schema'] = $this->ci4msseoLibrary->ldPlusJson('Organization', [
            'url' => site_url(implode('/', $this->request->getUri()->getSegments())),
            'logo' => $this->defData['settings']->logo,
            'name' => $this->defData['settings']->siteName,
            'children' => [
                'ContactPoint' =>
                [
                    'ContactPoint' => [
                        'telephone' => $this->defData['settings']->company->phone,
                        'contactType' => 'customer support'
                    ]
                ]
            ],
            'sameAs' => array_map(fn($sN) => $sN['link'], (array)$this->defData['settings']->socialNetwork)
        ]);
        $this->defData['breadcrumbs'] = $this->commonLibrary->get_breadcrumbs('/blog/1');
        return view('templates/' . $this->defData['settings']->templateInfos->path . '/blog/list', $this->defData);
    }

    public function blogDetail(string $seflink)
    {
        if ($this->commonModel->isHave('blog', ['seflink' => $seflink, 'isActive' => true]) === 1) {
            $this->defData['infos'] = $this->commonModel->selectOne('blog', ['seflink' => $seflink]);
            $userModel = new UserscrudModel();
            $this->defData['authorInfo'] = $userModel->loggedUser(0, 'users.*,auth_groups.name as groupName', ['users.id' => $this->defData['infos']->author]);
            $this->defData['authorInfo'] = $this->defData['authorInfo'][0];
            $this->defData['dateI18n'] = new Time();
            $modelTag = new AjaxModel();
            $this->defData['tags'] = $modelTag->limitTags_ajax(['piv_id' => $this->defData['infos']->id]);
            $keywords = [];
            if (!empty($this->defData['tags'])) {
                foreach ($this->defData['tags'] as $tag) {
                    $keywords[] = $tag->tag;
                }
            }
            helper('templates/' . $this->defData['settings']->templateInfos->path . '/funcs');
            $this->defData['comments'] = $this->commonModel->lists('comments', '*', ['blog_id' => $this->defData['infos']->id], 'id ASC', 5);
            $this->defData['infos']->seo = json_decode($this->defData['infos']->seo);
            $this->defData['infos']->seo = (object)$this->defData['infos']->seo;
            $this->defData['seo'] = $this->ci4msseoLibrary->metaTags($this->defData['infos']->title, $this->defData['infos']->seo->description, 'blog/' . $seflink, $metatags = ['keywords' => $keywords, 'author' => $this->defData['authorInfo']->firstname . ' ' . $this->defData['authorInfo']->sirname], $this->defData['infos']->seo->coverImage);
            $this->defData['categories'] = $this->commonModel->lists('categories');
            $this->defData['schema'] = $this->ci4msseoLibrary->ldPlusJson('BlogPosting', [
                'url' => site_url(implode('/', $this->request->getUri()->getSegments())),
                'logo' => $this->defData['settings']->logo,
                'name' => $this->defData['settings']->siteName,
                'headline' => $this->defData['infos']->title,
                'image' => $this->defData['infos']->seo->coverImage,
                'description' => $this->defData['infos']->seo->description,
                'datePublished' => $this->defData['infos']->created_at,
                //'dateModified' => $this->defData['infos']->seo->description,
                'children' => [
                    'mainEntityOfPage' => [
                        'WebPage' => []
                    ],
                    'ContactPoint' =>
                    [
                        'ContactPoint' => [
                            'telephone' => $this->defData['settings']->company->phone,
                            'contactType' => 'customer support'
                        ]
                    ]
                ],
                'sameAs' => array_map(fn($sN) => $sN['link'], (array)$this->defData['settings']->socialNetwork)
            ]);
            return view('templates/' . $this->defData['settings']->templateInfos->path . '/blog/post', $this->defData);
        } else return show_404();
    }

    public function tagList(string $seflink)
    {
        if ($this->commonModel->isHave('tags', ['seflink' => $seflink]) === 1) {
            $perPage = 12;
            $page = $this->request->getUri()->getSegment(3, 1);
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
                $this->defData['blogs'][$key]->author = $this->commonModel->selectOne('users', ['id' => $blog->author], 'firstname,sirname');
            }
            $this->defData['categories'] = $this->commonModel->lists('categories', '*', ['isActive' => true]);
            $this->defData['tagInfo'] = $this->commonModel->selectOne('tags', ['seflink' => $seflink]);
            return view('templates/' . $this->defData['settings']->templateInfos->path . '/blog/tags', $this->defData);
        } else return show_404();
    }

    public function category($seflink)
    {
        $this->defData['category'] = $this->commonModel->selectOne('categories', ['seflink' => $seflink]);
        $keywords = [];
        $this->defData['category']->seo = json_decode($this->defData['category']->seo);
        $this->defData['category']->seo = (object)$this->defData['category']->seo;
        if (!empty($this->defData['category']->seo->keywords)) {
            foreach ($this->defData['category']->seo->keywords as $keyword) {
                $keywords[] = $keyword->value;
            }
        }
        $this->defData['seo'] = $this->commonLibrary->seo($this->defData['category']->title, $this->defData['category']->seo->description, $seflink, $metatags = ['keywords' => $keywords], !empty($this->defData['category']->seo->coverImage) ? $this->defData['category']->seo->coverImage : '');
        $perPage = 12;
        $page = $this->request->getUri()->getSegment(3, 1);
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
            $this->defData['blogs'][$key]->author = $this->commonModel->selectOne('users', ['id' => $blog->author], 'firstname,sirname');
        }
        $this->defData['categories'] = $this->commonModel->lists('categories', '*', ['isActive' => true]);
        return view('templates/' . $this->defData['settings']->templateInfos->path . '/blog/list', $this->defData);
    }

    public function newComment()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();

        $valData = ([
            'comFullName' => ['label' => 'Full name', 'rules' => 'required'],
            'comEmail' => ['label' => 'E-mail', 'rules' => 'required|valid_email'],
            'comMessage' => ['label' => 'Join the discussion and leave a comment!', 'rules' => 'required'],
            'captcha' => ['Captcha' => 'Captcha', 'rules' => 'required']
        ]);
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
                'comFullName' => $this->request->getPost('comFullName'),
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
            $valData = (['comID' => ['label' => 'Comment', 'rules' => 'required']]);
            if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
            return $this->respond(['display' => view('templates/' . $this->defData['settings']->templateInfos->path . '/blog/replies', ['replies' => $this->commonModel->lists('comments', '*', ['parent_id' => $this->request->getPost('comID')])])], 200);
        } else return $this->failForbidden();
    }

    public function loadMoreComments()
    {
        if ($this->request->isAJAX()) {
            $valData = (['blogID' => ['label' => 'Blog ID', 'rules' => 'required|string'], 'skip' => ['label' => 'data-skip', 'rules' => 'required|is_natural_no_zero']]);
            if (!empty($this->request->getPost('comID'))) $valData['comID'] = ['label' => 'Comment ID', 'rules' => 'required|string'];
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
