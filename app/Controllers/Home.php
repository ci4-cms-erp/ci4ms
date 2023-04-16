<?php namespace App\Controllers;

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
        if ($this->commonModel->isHave('pages', ['seflink' => $seflink, 'isActive' => true]) === 1) {
            $this->defData['pageInfo'] = $this->commonModel->selectOne('pages', ['seflink' => $seflink]);
            $this->defData['pageInfo']->content = $this->commonLibrary->parseInTextFunctions($this->defData['pageInfo']->content);
            $keywords = [];
            $this->defData['pageInfo']->seo = json_decode($this->defData['pageInfo']->seo);
            $this->defData['pageInfo']->seo = (object)$this->defData['pageInfo']->seo;
            if (!empty($this->defData['pageInfo']->seo->keywords)) {
                foreach ($this->defData['pageInfo']->seo->keywords as $keyword) {
                    $keywords[] = $keyword->value;
                }
            }
            $this->defData['seo'] = $this->commonLibrary->seo($this->defData['pageInfo']->title, $this->defData['pageInfo']->seo->description, $seflink, $metatags = ['keywords' => $keywords], !empty($this->defData['pageInfo']->seo->coverImage) ? $this->defData['pageInfo']->seo->coverImage : '');
            return view('templates/' . $this->defData['settings']->templateInfos->path . '/pages', $this->defData);
        } else return show_404();
    }

    public function maintenanceMode()
    {
        if ((bool)$this->defData['settings']->maintenanceMode === false) return redirect()->route('/');
        return view('maintenance', $this->defData);
    }

    public function blog()
    {
        $this->defData['seo'] = $this->commonLibrary->seo('Blog', 'blog listesi', 'blog', ['keywords' => ["value" => "blog listesi"]]);
        $itemsPerPage = 12;
        $paginator = new Paginator($this->commonModel->count('blog', ['isActive' => true]), $itemsPerPage, $this->request->uri->getSegment(2, 1), '/blog/(:num)');
        $paginator->setMaxPagesToShow(5);
        $this->defData['paginator'] = $paginator;
        $bpk = ($this->request->uri->getSegment(2, 1) - 1) * $itemsPerPage;
        $this->defData['dateI18n'] = new Time();
        $this->defData['blogs'] = $this->commonModel->lists('blog', '*', ['isActive' => true], 'id ASC', $itemsPerPage, $bpk);
        $modelTag = new AjaxModel();
        foreach ($this->defData['blogs'] as $key => $blog) {
            $this->defData['blogs'][$key]->tags = $modelTag->limitTags_ajax(['tags_pivot.piv_id' => $blog->id]);
            $this->defData['blogs'][$key]->author = $this->commonModel->selectOne('users', ['id' => $blog->author], 'firstname,sirname');
        }
        $this->defData['categories'] = $this->commonModel->lists('categories', '*', ['isActive' => true]);
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
            $this->defData['seo'] = $this->commonLibrary->seo($this->defData['infos']->title, $this->defData['infos']->seo->description, 'blog/' . $seflink, $metatags = ['keywords' => $keywords, 'author' => $this->defData['authorInfo']->firstname . ' ' . $this->defData['authorInfo']->sirname], $this->defData['infos']->seo->coverImage);
            $this->defData['categories'] = $this->commonModel->lists('categories');
            return view('templates/' . $this->defData['settings']->templateInfos->path . '/blog/post', $this->defData);
        } else return show_404();
    }

    public function tagList(string $seflink)
    {
        if ($this->commonModel->isHave('tags', ['seflink' => $seflink]) === 1) {
            $totalItems = $this->commonModel->count('blog', ['isActive' => true]);
            $itemsPerPage = 12;
            $currentPage = $this->request->uri->getSegment(3, 1);
            $urlPattern = '/tag/' . $seflink . '/(:num)';
            $paginator = new Paginator($totalItems, $itemsPerPage, $currentPage, $urlPattern);
            $paginator->setMaxPagesToShow(5);
            $this->defData['paginator'] = $paginator;
            $bpk = ($this->request->uri->getSegment(3, 1) - 1) * $itemsPerPage;
            $this->defData['dateI18n'] = new Time();
            $this->defData['blogs'] = $this->ci4msModel->taglist(['tags.seflink' => $seflink, 'blog.isActive' => true], $itemsPerPage, $bpk, 'blog.*');
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
        $totalItems = $this->commonModel->count('blog', ['isActive' => true]);
        $itemsPerPage = 12;
        $currentPage = $this->request->uri->getSegment(3, 1);
        $urlPattern = '/category/' . $seflink . '/(:num)';
        $paginator = new Paginator($totalItems, $itemsPerPage, $currentPage, $urlPattern);
        $paginator->setMaxPagesToShow(5);
        $this->defData['paginator'] = $paginator;
        $bpk = ($this->request->uri->getSegment(3, 1) - 1) * $itemsPerPage;
        $this->defData['dateI18n'] = new Time();
        $this->defData['blogs'] = $this->ci4msModel->categoryList(['categories_id' => $this->defData['category']->id, 'isActive' => true], $itemsPerPage, $bpk);
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
            $badwordFilterSettings = json_decode($this->commonModel->selectOne('settings',
                ['option' => 'badwords'], 'content')->content);
            $checked = $this->commonLibrary->commentBadwordFiltering($this->request->getPost('comMessage'),
                (bool)$badwordFilterSettings->status, (bool)$badwordFilterSettings->autoReject);
            if (is_bool($checked) && !$checked) return $this->fail('Lütfen kelimelerinize dikkat ediniz.');
            $data = ['blog_id' => $this->request->getPost('blog_id'), 'created_at' => date('Y-m-d H:i:s'),
                'comFullName' => $this->request->getPost('comFullName'),
                'comEmail' => $this->request->getPost('comEmail'),
                'comMessage' => $checked];
            if (!empty($this->request->getPost('commentID'))) {
                $data['parent_id'] = $this->request->getPost('commentID');
                $this->commonModel->edit('comments', ['isThereAnReply' => true],
                    ['id' => $this->request->getPost('commentID')]);
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
