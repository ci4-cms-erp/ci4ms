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
                if ($this->commonModel->isHave([], 'tags') === 1) {
                    $data = ['pivot.tagType' => $this->request->getPost('type')];
                    if (!empty($this->request->getPost('piv_id')))
                        $data['pivot.piv_id'] = $this->request->getPost('piv_id');
                    $result = $this->model->limitTags_ajax($data);
                    if (empty($result)) {
                        $result = null;
                        foreach ($this->commonModel->getList('tags', [], ['limit' => 10, 'sort' => ['_id' => -1]]) as $item) {
                            $result[] = ['id' => (string)$item->_id, 'value' => $item->tag];
                        }
                        return $this->response->setJSON($result);
                    }
                    $edited = [];
                    foreach ($result as $item) {
                        $edited[] = ['id' => (string)$item->_id->id, 'value' => $item->_id->value];
                    }
                    unset($result);
                    return $this->response->setJSON($edited);
                } else return $this->response->setJSON([]);
            }
            $result = null;
            foreach ($this->commonModel->getList('tags', [], ['limit' => 10, 'sort' => ['_id' => -1]]) as $item) {
                $result[] = ['id' => (string)$item->_id, 'value' => $item->tag];
            }
            return $this->response->setJSON($result);
        } else return redirect('403');
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse|\CodeIgniter\HTTP\ResponseInterface|void
     */
    public function autoLookSeflinks()
    {
        if ($this->request->isAJAX()) {
            $valData = ([
                'makeSeflink' => ['label' => 'makeSeflink', 'rules' => 'required'],
                'where' => ['label' => 'where', 'rules' => 'required']
            ]);

            if ($this->validate($valData) == false) return redirect('403');

            $max_url_increment = 10000;
            if ($this->commonModel->get_where(['seflink' => seflink($this->request->getPost('makeSeflink'))], $this->request->getPost('where')) === 0) return $this->response->setJSON(['seflink' => seflink($this->request->getPost('makeSeflink'))]);
            else {
                for ($i = 1; $i <= $max_url_increment; $i++) {
                    $new_link = seflink($this->request->getPost('makeSeflink')) . '-' . $i;
                    if ($this->commonModel->get_where(['seflink' => $new_link], $this->request->getPost('where')) === 0) return $this->response->setJSON(['seflink' => $new_link]);
                }
            }
        } else return redirect('403');
    }

    /**
     * @return \CodeIgniter\HTTP\RedirectResponse|void
     */
    public function isActive()
    {
        if ($this->request->isAJAX()) {
            $valData = ([
                'id' => ['label' => 'id', 'rules' => 'required'],
                'isActive' => ['label' => 'isActive', 'rules' => 'required'],
                'where' => ['label' => 'where', 'rules' => 'required']
            ]);

            if ($this->validate($valData) == false) return redirect('403');

            if($this->commonModel->updateOne($this->request->getPost('where'), ['_id' => new ObjectId($this->request->getPost('id'))], ['isActive' => (bool)$this->request->getPost('isActive')]))
                return $this->response->setJSON(['result'=>true]);
            else
                return $this->response->setJSON(['result'=>false]);
        } else redirect('403');
    }

    public function maintenance()
    {
        if ($this->request->isAJAX()) {
            $valData = ([
                'id' => ['label' => 'id', 'rules' => 'required'],
                'isActive' => ['label' => 'isActive', 'rules' => 'required']
            ]);

            if ($this->validate($valData) == false) return redirect('403');
            if($this->commonModel->updateOne('settings', ['_id' => new ObjectId($this->request->getPost('id'))], ['maintenanceMode' => (bool)$this->request->getPost('isActive')]))
                return $this->response->setJSON(['result'=>true]);
            else
                return $this->response->setJSON(['result'=>false]);
        } else redirect('403');
    }
}
