<?php

namespace Modules\Backend\Controllers;

use Modules\Backend\Models\AjaxModel;

class AJAX extends BaseController
{
    protected $model;

    public function __construct()
    {
        $this->model = new AjaxModel();
    }

    /**
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function limitTags_ajax(): \CodeIgniter\HTTP\ResponseInterface
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valData = ([
            'type' => ['label' => 'type', 'rules' => 'required']
        ]);

        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
        if (!empty($this->request->getPost('type'))) {
            if ($this->commonModel->isHave('tags', []) === 1) {
                $data = ['tags_pivot.tagType' => $this->request->getPost('type')];
                if (!empty($this->request->getPost('piv_id')))
                    $data['tags_pivot.piv_id'] = $this->request->getPost('piv_id');
                $result = $this->model->limitTags_ajax($data);
                if (empty($result)) {
                    $result = null;
                    foreach ($this->commonModel->lists('tags', '*', [], 'id DESC', 10) as $item) {
                        $result[] = ['id' => (string)$item->id, 'value' => $item->tag];
                    }
                    return $this->response->setJSON($result);
                }
                $edited = [];
                foreach ($result as $item) {
                    $edited[] = ['id' => (string)$item->id, 'value' => $item->tag];
                }
                unset($result);
                return $this->respond(['result' => $edited], 200);
            } else return $this->failNotFound();
        }
        $result = null;
        foreach ($this->commonModel->lists('tags', '*', [], 'id DESC', 10) as $item) {
            $result[] = ['id' => (string)$item->id, 'value' => $item->tag];
        }
        return $this->respond(['result' => $result], 200);
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse|\CodeIgniter\HTTP\ResponseInterface|void
     */
    public function autoLookSeflinks(): \CodeIgniter\HTTP\ResponseInterface
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valData = ([
            'makeSeflink' => ['label' => 'makeSeflink', 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
            'where' => ['label' => 'where', 'rules' => 'required|in_list[pages_langs,blog_langs,categories_langs,tags]']
        ]);

        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());

        $whereTable = $this->request->getPost('where');
        $locale = $this->request->getPost('locale') ?: ($this->request->getLocale() ?: 'tr');

        // Determine the ID column based on the table
        $idField = 'id';
        if ($whereTable === 'pages_langs') $idField = 'pages_id';
        elseif ($whereTable === 'blog_langs') $idField = 'blog_id';
        elseif ($whereTable === 'categories_langs') $idField = 'categories_id';

        if ($this->request->getPost('update') == 1) {
            $whereArr = [$idField => $this->request->getPost('id')];
            // Also add the language for lang tables
            if (strpos($whereTable, '_langs') !== false || $whereTable === 'categories_langs') {
                $whereArr['lang'] = $locale;
            }

            $oldSeflink = $this->commonModel->selectOne($whereTable, $whereArr);

            if ($oldSeflink && seflink($this->request->getPost('makeSeflink'), ['locale' => $locale]) == $oldSeflink->seflink) {
                return $this->respond(['seflink' => $oldSeflink->seflink], 200);
            }
        }

        // Filter by language (if exists) when fetching current seflinks
        $listWhere = [];
        if (strpos($whereTable, '_langs') !== false || $whereTable === 'categories_langs') {
            $listWhere = ['lang' => $locale];
        }

        $existingSeflinks = $this->commonModel->lists($whereTable, 'seflink', $listWhere);
        $desiredSeflink = seflink($this->request->getPost('makeSeflink'), ['locale' => $locale]);

        if (!empty($existingSeflinks) && in_array($desiredSeflink, array_column($existingSeflinks, 'seflink')) === true) {
            $i = 1;
            while (in_array($desiredSeflink . '-' . $i, array_column($existingSeflinks, 'seflink'))) {
                $i++;
            }
            return $this->respond(['seflink' => $desiredSeflink . '-' . $i], 200);
        } else {
            return $this->respond(['seflink' => $desiredSeflink], 200);
        }
    }

    /**
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function isActive(): \CodeIgniter\HTTP\ResponseInterface
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valData = ([
            'id' => ['label' => 'id', 'rules' => 'required|is_natural_no_zero'],
            'isActive' => ['label' => 'isActive', 'rules' => 'required|in_list[0,1]'],
            'where' => ['label' => 'where', 'rules' => 'required|in_list[pages,blog]']
        ]);

        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());

        if ($this->commonModel->edit($this->request->getPost('where'), ['isActive' => (bool)$this->request->getPost('isActive')], ['id' => $this->request->getPost('id')]))
            return $this->respond(['result' => true], 200);
        else
            return $this->failForbidden();
    }

    /**
     * @return \CodeIgniter\HTTP\ResponseInterface
     */
    public function maintenance(): \CodeIgniter\HTTP\ResponseInterface
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valData = ([
            'isActive' => ['label' => 'isActive', 'rules' => 'required|in_list[0,1]']
        ]);
        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
        try {
            setting()->set('App.maintenanceMode', (bool)$this->request->getPost('isActive'));
            cache()->delete('settings');
            return $this->respond(['result' => (bool)$this->request->getPost('isActive')], 200);
        } catch (\Exception $e) {
            return $this->fail(['pr' => false]);
        }
    }
}
