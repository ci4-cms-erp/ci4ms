<?php

namespace Modules\Backend\Controllers;

use JasonGrimes\Paginator;
use Modules\Backend\Libraries\CommonTagsLibrary;
use Modules\Backend\Models\AjaxModel;
use CodeIgniter\API\ResponseTrait;

class Blog extends BaseController
{
    use ResponseTrait;

    private $commonTagsLib;
    private $model;

    public function __construct()
    {
        $this->model = new AjaxModel();
        $this->commonTagsLib = new CommonTagsLibrary();
    }

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

    public function new()
    {
        $this->defData['categories'] = $this->commonModel->lists('categories');
        $this->defData['authors'] = $this->commonModel->lists('users', '*', ['status' => 'active']);
        return view('Modules\Backend\Views\blog\create', $this->defData);
    }

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
            $valData['pageimg'] = ['label' => 'Görsel URL', 'rules' => 'required|valid_url'];
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
            $valData['pageimg'] = ['label' => 'Görsel URL', 'rules' => 'required|valid_url'];
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

        if(!empty($data['seo'])) $data['seo'] = json_encode($data['seo'], JSON_UNESCAPED_UNICODE);
        if ($this->commonModel->edit('blog', $data, ['id' => $id])) {
            if (!empty($this->request->getPost('keywords'))) $this->commonTagsLib->checkTags($this->request->getPost('keywords'), 'blogs', $id, 'tags',true);
            if (!empty($this->request->getPost('categories'))) {
                $this->commonModel->remove('blog_categories_pivot', ['blog_id' => $id]);
                foreach ($this->request->getPost('categories') as $item) {
                    $this->commonModel->create('blog_categories_pivot', ['blog_id' => $id, 'categories_id' => $item]);
                }
            }
            return redirect()->route('blogs', [1])->with('message', '<b>' . $this->request->getPost('title') . '</b> adlı blog oluşturuldu.');
        } else return redirect()->back()->withInput()->with('error', 'Blog oluşturulamadı.');
    }

    public function delete($id = null)
    {
            if ($this->commonModel->remove('blog', ['id' =>$id,'tagType'=>'blogs']) === true) return redirect()->route('blogs', [1])->with('message', 'blog silindi.');
            else return redirect()->back()->withInput()->with('error', 'Blog Silinemedi.');
    }

    public function commentList()
    {
        return view('Modules\Backend\Views\blog\commentList', $this->defData);
    }

    public function commentResponse()
    {
        if ($this->request->isAJAX()) {
            $data = clearFilter($this->request->getPost());
            if (empty($data['search']['value'])) unset($data['search']);
            unset($data['columns'], $data['order']);
            $searchData = ['isApproved' => true];
            if (!empty($data['search']['value'])) $searchData['comFullName'] = $data['search']['value'];
            if ($data['length'] > 0) $results = $this->commonModel->lists('comments','*', [],'id ASC', $data['length'],(int)$data['start'],$searchData);
            else $results = $this->commonModel->lists('comments', $searchData);
            $c = ((int)$data['start'] > 0) ? (int)$data['start'] + 1 : 1;
            $data = [
                'draw' => intval($data['draw']),
                "iTotalRecords" => $this->commonModel->count('comments', $searchData),
                "iTotalDisplayRecords" => $this->commonModel->count('comments', $searchData),
            ];
            foreach ($results as $result) {
                $id = (string)$result->id;
                $data['aaData'][] = ['id' => $c,
                    'com_name_surname' => $result->comFullName,
                    'email' => $result->comEmail,
                    'created_at' => $result->created_at,
                    'status' => ($result->isApproved == true) ? 'Approved' : 'Not approved',
                    'process' => '<a href="' . route_to('blogUpdate', $result->id) . '"
                                   class="btn btn-outline-info btn-sm">' . lang('Backend.update') . '</a>
                                <a href="' . route_to('blogDelete', $result->id) . '"
                                   class="btn btn-outline-danger btn-sm">' . lang('Backend.delete') . '</a>'];
                $c++;
            }
            if (!empty($data)) return $this->respond($data, 200);
            else return $this->respond(['message' => 'Not Found data'], 204);
        }
    }

    public function commentPendingApproval()
    {
        dd('commentPendingApproval');
    }

    public function confirmComment()
    {
        dd('confirmComment');
    }

    public function badwordList()
    {
        dd('badwordList');
    }

    public function badwordsAdd()
    {
        dd('badwordsAdd');
    }
}
