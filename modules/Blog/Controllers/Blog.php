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
     * @return string
     */
    public function index()
    {
        if ($this->request->is('post') && $this->request->isAJAX()) {
            $data = clearFilter($this->request->getPost());
            $like = trim(strip_tags($data['search']['value']));
            $l = [];
            $postData = [];
            if (!empty($like)) $l = ['title' => $like];
            $results = $this->commonModel->lists('blog', 'id,title,isActive', $postData, 'id DESC', ($data['length'] == '-1') ? 0 : (int)$data['length'], ($data['length'] == '-1') ? 0 : (int)$data['start'], $l);
            $totalRecords = $this->commonModel->count('blog', $postData, $l);
            foreach ($results as $result) {
                $result->isActive = '<input type="checkbox" name="my-checkbox" class="bswitch" ' . ((bool)$result->isActive === true ? 'checked' : '') . ' data-id="' . $result->id . '" data-off-color="danger" data-on-color="success">';
                $result->actions = '<a href="' . route_to('blogUpdate', $result->id) . '"
                                   class="btn btn-outline-info btn-sm">' . lang('Backend.update') . '</a>
                                   <a href="javascript:void(0);" onclick="deleteItem(' . $result->id . ')"
                                   class="btn btn-outline-danger btn-sm">' . lang('Backend.delete') . '</a>';
            }
            $data = [
                'draw' => intval($data['draw']),
                'iTotalRecords' => $totalRecords,
                'iTotalDisplayRecords' => $totalRecords,
                'aaData' => $results,
            ];
            return $this->respond($data, 200);
        }
        return view('Modules\Blog\Views\list', $this->defData);
    }

    /**
     * @return string
     */
    public function new()
    {
        if ($this->request->is('post')) {
            $valData = ([
                'title' => ['label' => lang('Backend.title'), 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
                'seflink' => ['label' => lang('Backend.url'), 'rules' => 'required|regex_match[/^[^<>{}]*$/u]|is_unique[blog.seflink]'],
                'content' => ['label' => lang('Backend.content'), 'rules' => 'required'],
                'isActive' => ['label' => lang('Backend.publish') . ' / ' . lang('Backend.draft'), 'rules' => 'required|in_list[0,1]'],
                'categories' => ['label' => lang('Blog.categories'), 'rules' => 'required|is_natural_no_zero'],
                'author' => ['label' => lang('Blog.author'), 'rules' => 'required|is_natural_no_zero'],
                'created_at' => ['label' => lang('Backend.createdAt'), 'rules' => 'required|valid_date[d.m.Y H:i:s]']
            ]);
            if (!empty($this->request->getPost('pageimg'))) {
                $valData['pageimg'] = ['label' => lang('Backend.coverImage'), 'rules' => 'required'];
                $valData['pageIMGWidth'] = ['label' => lang('Backend.coverImgWith'), 'rules' => 'required|is_natural_no_zero'];
                $valData['pageIMGHeight'] = ['label' => lang('Backend.coverImgHeight'), 'rules' => 'required|is_natural_no_zero'];
            }
            if (!empty($this->request->getPost('description'))) $valData['description'] = ['label' => lang('Backend.seoDescription'), 'rules' => 'required'];
            if (!empty($this->request->getPost('keywords'))) $valData['keywords'] = ['label' => lang('Backend.seoKeywords'), 'rules' => 'required'];
            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            if ($this->commonModel->isHave('blog', ['seflink' => $this->request->getPost('seflink')]) === 1) return redirect()->back()->withInput()->with('error', 'Blog seflink adresi daha önce kullanılmış. lütfen kontrol ederek bir daha oluşturmayı deneyeyiniz.');

            $data = ['title' => trim(strip_tags($this->request->getPost('title'))), 'content' => $this->request->getPost('content'), 'isActive' => (bool)$this->request->getPost('isActive'), 'seflink' => trim(strip_tags($this->request->getPost('seflink'))), 'inMenu' => false, 'author' => $this->request->getPost('author'), 'created_at' => date('Y-m-d H:i:s', strtotime($this->request->getPost('created_at')))];

            if (!empty($this->request->getPost('pageimg'))) {
                $data['seo']['coverImage'] = trim(strip_tags($this->request->getPost('pageimg')));
                $data['seo']['IMGWidth'] = trim(strip_tags($this->request->getPost('pageIMGWidth')));
                $data['seo']['IMGHeight'] = trim(strip_tags($this->request->getPost('pageIMGHeight')));
            }
            if (!empty($this->request->getPost('description'))) $data['seo']['description'] = trim(strip_tags($this->request->getPost('description')));

            $insertID = $this->commonModel->create('blog', $data);
            if ($insertID) {
                if (!empty($this->request->getPost('categories'))) {
                    foreach ($this->request->getPost('categories') as $item) {
                        $this->commonModel->create('blog_categories_pivot', ['blog_id' => $insertID, 'categories_id' => $item]);
                    }
                }
                if (!empty($this->request->getPost('keywords'))) $this->commonTagsLib->checkTags($this->request->getPost('keywords'), 'blogs', (string)$insertID, 'tags');
                return redirect()->route('blogs', [1])->with('message', '<b>' . esc($this->request->getPost('title')) . '</b> adlı blog oluşturuldu.');
            } else return redirect()->back()->withInput()->with('error', lang('Backend.created', [esc($data['title'])]));
        }
        $this->defData['categories'] = $this->commonModel->lists('categories');
        $this->defData['authors'] = $this->commonModel->lists('users', '*', ['active' => 1]);
        return view('Modules\Blog\Views\create', $this->defData);
    }

    /**
     * @param string $id
     * @return string
     */
    public function edit(string $id)
    {
        if ($this->request->is('post')) {
            $valData = ([
                'title' => ['label' => lang('Backend.title'), 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
                'seflink' => ['label' => lang('Backend.url'), 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
                'content' => ['label' => lang('Backend.content'), 'rules' => 'required'],
                'isActive' => ['label' => lang('Backend.publish') . ' / ' . lang('Backend.draft'), 'rules' => 'required|in_list[0,1]'],
                'categories' => ['label' => lang('Blog.categories'), 'rules' => 'required|is_natural_no_zero'],
                'author' => ['label' => lang('Blog.author'), 'rules' => 'required|is_natural_no_zero'],
                'created_at' => ['label' => lang('Backend.createdAt'), 'rules' => 'required|valid_date[d.m.Y H:i:s]']
            ]);
            if (!empty($this->request->getPost('pageimg'))) {
                $valData['pageimg'] = ['label' => lang('Backend.coverImage'), 'rules' => 'required'];
                $valData['pageIMGWidth'] = ['label' => lang('Backend.coverImgWith'), 'rules' => 'required|is_natural_no_zero'];
                $valData['pageIMGHeight'] = ['label' => lang('Backend.coverImgHeight'), 'rules' => 'required|is_natural_no_zero'];
            }
            if (!empty($this->request->getPost('description'))) $valData['description'] = ['label' => lang('Backend.seoDescription'), 'rules' => 'required'];
            if (!empty($this->request->getPost('keywords'))) $valData['keywords'] = ['label' => lang('Backend.seoKeywords'), 'rules' => 'required'];
            if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
            $info = $this->commonModel->selectOne('blog', ['id' => $id]);
            if ($info->seflink != $this->request->getPost('seflink') && $this->commonModel->isHave('categories', ['seflink' => $this->request->getPost('seflink')]) === 1) return redirect()->back()->withInput()->with('error', 'Blog seflink adresi daha önce kullanılmış. lütfen kontrol ederek bir daha oluşturmayı deneyeyiniz.');
            $data = ['title' => trim(strip_tags($this->request->getPost('title'))), 'content' => $this->request->getPost('content'), 'isActive' => (bool)$this->request->getPost('isActive'), 'seflink' => trim(strip_tags($this->request->getPost('seflink'))), 'author' => $this->request->getPost('author'), 'created_at' => date('Y-m-d H:i:s', strtotime($this->request->getPost('created_at')))];

            if (!empty($this->request->getPost('pageimg'))) {
                $data['seo']['coverImage'] = trim(strip_tags($this->request->getPost('pageimg')));
                $data['seo']['IMGWidth'] = trim(strip_tags($this->request->getPost('pageIMGWidth')));
                $data['seo']['IMGHeight'] = trim(strip_tags($this->request->getPost('pageIMGHeight')));
            }
            if (!empty($this->request->getPost('description'))) $data['seo']['description'] = $this->request->getPost('description');

            if (!empty($data['seo'])) $data['seo'] = json_encode($data['seo'], JSON_UNESCAPED_UNICODE);
            if ($this->commonModel->edit('blog', $data, ['id' => $id])) {
                if (!empty($this->request->getPost('keywords')))
                    $this->commonTagsLib->checkTags($this->request->getPost('keywords'), 'blogs', $id, 'tags', true);
                if (!empty($this->request->getPost('categories'))) {
                    $this->commonModel->remove('blog_categories_pivot', ['blog_id' => $id]);
                    foreach ($this->request->getPost('categories') as $item) {
                        $this->commonModel->create('blog_categories_pivot', ['blog_id' => $id, 'categories_id' => $item]);
                    }
                }
                return redirect()->route('blogs', [1])->with('message', lang('Backend.updated', [esc($data['title'])]));
            } else return redirect()->back()->withInput()->with('error', lang('Backend.notUpdated', [esc($data['title'])]));
        }
        $this->defData['tags'] = $this->model->limitTags_ajax(['tags_pivot.tagType' => 'blogs', 'tags_pivot.piv_id' => $id]);
        $t = [];
        foreach ($this->defData['tags'] as $tag) {
            $t[] = ['id' => (string)$tag->id, 'value' => $tag->tag];
        }
        $this->defData['categories'] = $this->commonModel->lists('categories');
        $this->defData['infos'] = $this->commonModel->selectOne('blog', ['id' => $id]);
        $this->defData['infos']->seo = json_decode($this->defData['infos']->seo);
        $this->defData['infos']->categories = $this->commonModel->lists('blog_categories_pivot', '*', ['blog_id' => $id]);
        $this->defData['tags'] = json_encode($t, JSON_UNESCAPED_UNICODE);
        $this->defData['authors'] = $this->commonModel->lists('users', '*', ['active' => 1]);
        unset($t);
        return view('Modules\Blog\Views\update', $this->defData);
    }

    /**
     * @param $id
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function delete()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valData = ([
            'id' => ['label' => '', 'rules' => 'required|is_natural_no_zero'],
        ]);
        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
        $blog = $this->commonModel->selectOne('blog', ['id' => $this->request->getPost('id')]);
        if ($this->commonModel->remove('blog', ['id' => $this->request->getPost('id')]) === true) return $this->respond(['status' => 'success', 'message' => lang('Backend.deleted', [$blog->title])]);
        else return $this->respond(['status' => 'error', 'message' => lang('Backend.notDeleted', [$blog->title])]);
    }

    /**
     * @return string
     */
    public function commentList()
    {
        if ($this->request->is('post') && $this->request->isAJAX()) {
            $data = clearFilter($this->request->getPost());
            $like = $data['search']['value'] ?? '';
            $searchData = ['isApproved' => $this->request->getPost('isApproved') == 'true' ? true : false];
            $l = [];
            if (!empty($like)) $l = ['comFullName' => $like, 'comEmail' => $like];
            $results = $this->commonModel->lists(
                'comments',
                '*',
                $searchData,
                'id DESC',
                (int)$data['length'],
                (int)$data['start'],
                $l
            );
            $totalRecords = $this->commonModel->count('comments', $searchData);
            $totalDisplayRecords = $totalRecords;
            $c = ($data['start'] > 0) ? $data['start'] + 1 : 1;
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
                'draw' => intval($data['draw']),
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
            'options' => 'required|is_natural_no_zero|greater_than_equal_to[1]|less_than_equal_to[2]'
        ];
        if (!$this->validate($rules)) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        $isApproved = (int)$this->request->getPost('options');
        if ($isApproved === 1) {
            if ($this->commonModel->edit('comments', ['isApproved' => $isApproved], ['id' => $id])) {
                //$message = ;
                return redirect()->route('comments')->with('message', lang('Blog.commentPublished', [$id]));
            } else {
                //$error = ;
                return redirect()->back()->withInput()->with('error', lang('Blog.commentPublishError'));
            }
        } else {
            if ($this->commonModel->remove('comments', ['id' => $id])) {
                return redirect()->route('comments')->with('warning', lang('Backend.deleted', ['#' . $id]));
            } else {
                return redirect()->back()->withInput()->with('error', lang('Backend.notDeleted', ['#' . $id]));
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
            return redirect()->route('badwords')->with('message', lang('Backend.updated', [lang('Blog.badwords')]));
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }
}
