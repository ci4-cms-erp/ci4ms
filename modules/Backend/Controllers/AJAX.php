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
     * @return \CodeIgniter\HTTP\RedirectResponse|\CodeIgniter\HTTP\ResponseInterface
     */
    public function limitTags_ajax()
    {
        if ($this->request->isAJAX()) {
            if (!empty($this->request->getPost('type'))) {
                if ($this->commonModel->isHave('tags', []) === 1) {
                    $data = ['tags_pivot.tagType' => $this->request->getPost('type')];
                    if (!empty($this->request->getPost('piv_id')))
                        $data['tags_pivot.piv_id'] = $this->request->getPost('piv_id');
                    $result = $this->model->limitTags_ajax($data);
                    if (empty($result)) {
                        $result = null;
                        foreach ($this->commonModel->lists('tags', '*', [], 'id DESC', 10) as $item) {
                            $result[] = ['id' => (string)$item->_id, 'value' => $item->tag];
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
        } else return $this->failForbidden();
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse|\CodeIgniter\HTTP\ResponseInterface|void
     */
    public function autoLookSeflinks()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valData = ([
            'makeSeflink' => ['label' => 'makeSeflink', 'rules' => 'required'],
            'where' => ['label' => 'where', 'rules' => 'required']
        ]);

        if ($this->validate($valData) == false) return redirect('403');
        $locale = !empty($this->request->getPost('locale')) ? ['locale' => $this->request->getPost('locale')] : ['locale' => 'tr'];
        if ($this->request->getPost('update') == 1) {
            $oldSeflink = $this->commonModel->selectOne($this->request->getPost('where'), ['id' => $this->request->getPost('id')]);
            if (seflink($this->request->getPost('makeSeflink'), $locale) == $oldSeflink->seflink)
                return $this->respond(['seflink' => $oldSeflink->seflink], 200);
        }
        $existingSeflinks = $this->commonModel->lists($this->request->getPost('where'), 'seflink');
        $desiredSeflink = seflink($this->request->getPost('makeSeflink'), $locale);
        if (in_array($desiredSeflink, array_column($existingSeflinks, 'seflink')) === true) {
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
     * @return \CodeIgniter\HTTP\RedirectResponse|void
     */
    public function isActive()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
            $valData = ([
                'id' => ['label' => 'id', 'rules' => 'required'],
                'isActive' => ['label' => 'isActive', 'rules' => 'required'],
                'where' => ['label' => 'where', 'rules' => 'required']
            ]);

            if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());

            if ($this->commonModel->edit($this->request->getPost('where'), ['isActive' => (bool)$this->request->getPost('isActive')], ['id' => $this->request->getPost('id')]))
                return $this->respond(['result' => true], 200);
            else
            return $this->failForbidden();
    }

    public function maintenance()
    {
        if ($this->request->isAJAX()) {
            $valData = ([
                'isActive' => ['label' => 'isActive', 'rules' => 'required']
            ]);
            if ($this->validate($valData) == false) return redirect('403');
            if ($this->commonModel->edit('settings', ['content' => (bool)$this->request->getPost('isActive')], ['option' => 'maintenanceMode'])){
                cache()->delete('settings');
                return $this->respond(['result' => (bool)$this->request->getPost('isActive')],200);
            }
            else{
                cache()->delete('settings');
                return $this->fail(['pr' => false]);
            }
        } else return $this->failForbidden();
    }
}
