<?php

namespace Modules\Backend\Controllers;

use JasonGrimes\Paginator;
use Modules\Backend\Libraries\CommonTagsLibrary;
use Modules\Backend\Models\AjaxModel;
use CodeIgniter\API\ResponseTrait;

class Blog extends BaseController
{
    use ResponseTrait;

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
        $totalItems = $this->commonModel->count('categories', []);
        $itemsPerPage = 20;
        $currentPage = $this->request->uri->getSegment(3, 1);
        $urlPattern = '/backend/blogs/(:num)';
        $paginator = new Paginator($totalItems, $itemsPerPage, $currentPage, $urlPattern);
        $paginator->setMaxPagesToShow(5);
        $bpk = ($this->request->uri->getSegment(3, 1) - 1) * $itemsPerPage;
        $this->defData = array_merge($this->defData, ['paginator' => $paginator, 'blogs' => $this->commonModel->lists('blog', '*', [], 'id ASC', $itemsPerPage, $bpk)]);
        return view('Modules\Backend\Views\blog\list', $this->defData);
    }

    /**
     * @return string
     */
    public function new()
    {
        $this->defData['categories'] = $this->commonModel->lists('categories');
        $this->defData['authors'] = $this->commonModel->lists('users', '*', ['status' => 'active']);
        return view('Modules\Backend\Views\blog\create', $this->defData);
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse
     * @throws \Exception
     */
    public function create()
    {
        $valData = ([
            'title' => ['label' => 'Sayfa Başlığı', 'rules' => 'required'],
            'seflink' => ['label' => 'Sayfa URL', 'rules' => 'required'],
            'content' => ['label' => 'İçerik', 'rules' => 'required'],
            'isActive' => ['label' => 'Yayın veya taslak', 'rules' => 'required'],
            'categories' => ['label' => 'Kategoriler', 'rules' => 'required'],
            'author' => ['label' => 'Yazar', 'rules' => 'required'],
            'created_at' => ['label' => 'Oluşturulma Tarihi', 'rules' => 'required|valid_date[d.m.Y H:i:s]']
        ]);
        if (!empty($this->request->getPost('pageimg'))) {
            $valData['pageimg'] = ['label' => 'Görsel URL', 'rules' => 'required'];
            $valData['pageIMGWidth'] = ['label' => 'Görsel Genişliği', 'rules' => 'required|is_natural_no_zero'];
            $valData['pageIMGHeight'] = ['label' => 'Görsel Yüksekliği', 'rules' => 'required|is_natural_no_zero'];
        }
        if (!empty($this->request->getPost('description'))) $valData['description'] = ['label' => 'Seo Açıklaması', 'rules' => 'required'];
        if (!empty($this->request->getPost('keywords'))) $valData['keywords'] = ['label' => 'Seo Anahtar Kelimeleri', 'rules' => 'required'];
        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        if ($this->commonModel->isHave('blog', ['seflink' => $this->request->getPost('seflink')]) === 1) return redirect()->back()->withInput()->with('error', 'Blog seflink adresi daha önce kullanılmış. lütfen kontrol ederek bir daha oluşturmayı deneyeyiniz.');

        $data = ['title' => $this->request->getPost('title'), 'content' => $this->request->getPost('content'), 'isActive' => (bool)$this->request->getPost('isActive'), 'seflink' => $this->request->getPost('seflink'), 'inMenu' => false, 'author' => $this->request->getPost('author'), 'created_at' => $this->request->getPost('created_at')];

        if (!empty($this->request->getPost('pageimg'))) {
            $data['seo']['coverImage'] = $this->request->getPost('pageimg');
            $data['seo']['IMGWidth'] = $this->request->getPost('pageIMGWidth');
            $data['seo']['IMGHeight'] = $this->request->getPost('pageIMGHeight');
        }
        if (!empty($this->request->getPost('description'))) $data['seo']['description'] = $this->request->getPost('description');

        $insertID = $this->commonModel->create('blog', $data);
        if ($insertID) {
            if (!empty($this->request->getPost('categories'))) {
                foreach ($this->request->getPost('categories') as $item) {
                    $this->commonModel->create('blog_categories_pivot', ['blog_id' => $insertID, 'categories_id' => $item]);
                }
            }
            if (!empty($this->request->getPost('keywords'))) $this->commonTagsLib->checkTags($this->request->getPost('keywords'), 'blogs', (string)$insertID, 'tags');
            return redirect()->route('blogs', [1])->with('message', '<b>' . $this->request->getPost('title') . '</b> adlı blog oluşturuldu.');
        } else return redirect()->back()->withInput()->with('error', 'Blog oluşturulamadı.');
    }

    /**
     * @param string $id
     * @return string
     */
    public function edit(string $id)
    {
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
        $this->defData['authors'] = $this->commonModel->lists('users', '*', ['status' => 'active']);
        unset($t);
        return view('Modules\Backend\Views\blog\update', $this->defData);
    }

    /**
     * @param string $id
     * @return \CodeIgniter\HTTP\RedirectResponse
     * @throws \Exception
     */
    public function update(string $id)
    {
        $valData = ([
            'title' => ['label' => 'Sayfa Başlığı', 'rules' => 'required'],
            'seflink' => ['label' => 'Sayfa URL', 'rules' => 'required'],
            'content' => ['label' => 'İçerik', 'rules' => 'required'],
            'isActive' => ['label' => 'Yayın veya taslak', 'rules' => 'required'],
            'categories' => ['label' => 'Kategoriler', 'rules' => 'required'],
            'author' => ['label' => 'Yazar', 'rules' => 'required'],
            'created_at' => ['label' => 'Oluşturulma Tarihi', 'rules' => 'required|valid_date[d.m.Y H:i:s]']
        ]);
        if (!empty($this->request->getPost('pageimg'))) {
            $valData['pageimg'] = ['label' => 'Görsel URL', 'rules' => 'required'];
            $valData['pageIMGWidth'] = ['label' => 'Görsel Genişliği', 'rules' => 'required|is_natural_no_zero'];
            $valData['pageIMGHeight'] = ['label' => 'Görsel Yüksekliği', 'rules' => 'required|is_natural_no_zero'];
        }
        if (!empty($this->request->getPost('description'))) $valData['description'] = ['label' => 'Seo Açıklaması', 'rules' => 'required'];
        if (!empty($this->request->getPost('keywords'))) $valData['keywords'] = ['label' => 'Seo Anahtar Kelimeleri', 'rules' => 'required'];
        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        $info = $this->commonModel->selectOne('blog', ['id' => $id]);
        if ($info->seflink != $this->request->getPost('seflink') && $this->commonModel->isHave('categories', ['seflink' => $this->request->getPost('seflink')]) === 1) return redirect()->back()->withInput()->with('error', 'Blog seflink adresi daha önce kullanılmış. lütfen kontrol ederek bir daha oluşturmayı deneyeyiniz.');
        $data = ['title' => $this->request->getPost('title'), 'content' => $this->request->getPost('content'), 'isActive' => (bool)$this->request->getPost('isActive'), 'seflink' => $this->request->getPost('seflink'), 'author' => $this->request->getPost('author'), 'created_at' => $this->request->getPost('created_at')];

        if (!empty($this->request->getPost('pageimg'))) {
            $data['seo']['coverImage'] = $this->request->getPost('pageimg');
            $data['seo']['IMGWidth'] = $this->request->getPost('pageIMGWidth');
            $data['seo']['IMGHeight'] = $this->request->getPost('pageIMGHeight');
        }
        if (!empty($this->request->getPost('description'))) $data['seo']['description'] = $this->request->getPost('description');

        if (!empty($data['seo'])) $data['seo'] = json_encode($data['seo'], JSON_UNESCAPED_UNICODE);
        if ($this->commonModel->edit('blog', $data, ['id' => $id])) {
            if (!empty($this->request->getPost('keywords'))) $this->commonTagsLib->checkTags($this->request->getPost('keywords'), 'blogs', $id, 'tags', true);
            if (!empty($this->request->getPost('categories'))) {
                $this->commonModel->remove('blog_categories_pivot', ['blog_id' => $id]);
                foreach ($this->request->getPost('categories') as $item) {
                    $this->commonModel->create('blog_categories_pivot', ['blog_id' => $id, 'categories_id' => $item]);
                }
            }
            return redirect()->route('blogs', [1])->with('message', '<b>' . $this->request->getPost('title') . '</b> adlı blog oluşturuldu.');
        } else return redirect()->back()->withInput()->with('error', 'Blog oluşturulamadı.');
    }

    /**
     * @param $id
     * @return \CodeIgniter\HTTP\RedirectResponse
     */
    public function delete($id = null)
    {
        if ($this->commonModel->remove('blog', ['id' => $id, 'tagType' => 'blogs']) === true) return redirect()->route('blogs', [1])->with('message', 'blog silindi.');
        else return redirect()->back()->withInput()->with('error', 'Blog Silinemedi.');
    }

    /**
     * @return string
     */
    public function commentList()
    {
        return view('Modules\Backend\Views\blog\commentList', $this->defData);
    }

    public function commentResponse()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $data = clearFilter($this->request->getPost());
        $like = $data['search']['value'] ?? '';
        $searchData = ['isApproved' => $this->request->getPost('isApproved') == 'true' ? true : false];
        $l = [];
        if (!empty($like)) $l = ['comFullName' => $like, 'comEmail' => $like];
        $results = $this->commonModel->lists('comments', '*', $searchData, 'id DESC',
            (int)$data['length'], (int)$data['start'], $l);
        $totalRecords = $this->commonModel->count('comments', $searchData);
        $totalDisplayRecords = $totalRecords;
        $c = ($data['start'] > 0) ? $data['start'] + 1 : 1;
        $aaData = [];
        foreach ($results as $result) {
            $aaData[] = [
                'id' => $c,
                'com_name_surname' => $result->comFullName,
                'email' => $result->comEmail,
                'created_at' => $result->created_at,
                'status' => ($result->isApproved == true) ? 'Approved' : 'Not approved',
                'process' => '<a href="' . route_to('displayComment', $result->id) . '"
                               class="btn btn-outline-info btn-sm">' . lang('Backend.update') . '</a>
                            <a href="' . route_to('commentRemove', $result->id) . '"
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

    public function commentRemove(int $id)
    {
        if ($this->commonModel->remove('comments', ['id' => $id])) return redirect()->route('comments')->with('warning', "The comment with an id of <strong>{$id}</strong> has been removed.");
        else return redirect()->back()->withInput()->with('error', 'Comment cannot be removed. Please try again or check logs.');
    }

    public function displayComment(int $id)
    {
        $this->defData['commentInfo'] = $this->commonModel->selectOne('comments', ['id' => $id]);
        $this->defData['blogInfo'] = $this->commonModel->selectOne('blog', ['id' => $this->defData['commentInfo']->blog_id]);
        return view('Modules\Backend\Views\blog\displayComment', $this->defData);
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
                $message = "The comment with an id of {$id} has been published.";
                return redirect()->route('comments')->with('message', $message);
            } else {
                $error = 'Comment cannot be published. Please try again or check logs.';
                return redirect()->back()->withInput()->with('error', $error);
            }
        } else {
            if ($this->commonModel->remove('comments', ['id' => $id])) {
                $message = "The comment with an id of {$id} has been removed.";
                return redirect()->route('comments')->with('warning', $message);
            } else {
                $error = 'Comment cannot be removed. Please try again or check logs.';
                return redirect()->back()->withInput()->with('error', $error);
            }
        }
    }

    public function badwordList()
    {
        $this->defData['badwords'] = json_decode($this->commonModel->selectOne('settings', ['option' => 'badwords'], 'content')->content, JSON_UNESCAPED_UNICODE);
        if (empty($this->defData['badwords']))
            $this->defData['badwords'] = null;
        else {
            $this->defData['badwords'] = (object)['list' => implode(',', $this->defData['badwords']['list']),
                'status' => $this->defData['badwords']['status'],
                'autoReject'=>$this->defData['badwords']['autoReject'],
                'autoAccept'=>$this->defData['badwords']['autoAccept']
            ];
        }
        return view('Modules\Backend\Views\blog\badwordlist', $this->defData);
    }

    public function badwordsAdd()
    {
        if ($this->commonModel->edit('settings',
            ['content' => json_encode(['status' => ($this->request->getPost('status') == "on") ? 1 : 0,
                'autoReject' => ($this->request->getPost('autoReject') == "on") ? 1 : 0,
                'autoAccept' => ($this->request->getPost('autoAccept') == "on") ? 1 : 0,
                'list' => explode(',', $this->request->getPost('badwords'))],
                JSON_UNESCAPED_UNICODE)],
            ['option' => 'badwords']))
            return redirect()->route('badwords')->with('message', "Bad word list updated.");
        else return redirect()->back()->withInput()->with('error',
            "Bad word list cannot updated. Please try again or check logs.");
    }
}
