<?php

namespace Modules\Backend\Controllers;

class Locked extends BaseController
{
    /**
     * @throws \Exception
     */
    public function index()
    {
        if ($this->request->is('post') && $this->request->isAJAX()) {
            $data = clearFilter($this->request->getPost());
            $like = $data['search']['value'];
            $l = [];
            if (!empty($this->request->getPost('date_range'))) {
                $dates = explode(" - ", $this->request->getPost('date_range'));
                $locked_at = date('Y-m-d H:i:s', strtotime($dates[0]));
                $expiry_date = date('Y-m-d H:i:s', strtotime($dates[1]));
            }
            $postData = [
                'isLocked' => (isset($clearData['status'])) ? (bool)$clearData['status'] : null,
                'locked_at' => $locked_at??null,
                'expiry_date' => $expiry_date??null,
            ];
            if (!empty($like)) $l = ['title' => $like];
            $results = $this->commonModel->lists('locked', '*', $postData, 'id DESC', ($data['length'] == '-1') ? 0 : (int)$data['length'], ($data['length'] == '-1') ? 0 : (int)$data['start'], $l);
            $totalRecords = $this->commonModel->count('locked', $postData, $l);
            foreach ($results as $result) {
                $result->locked_at = date('Y-m-d H:i:s', strtotime($result->locked_at));
                $result->expiry_date = date('Y-m-d H:i:s', strtotime($result->expiry_date));
                $result->actions = '<input type="checkbox" name="my-checkbox" class="bswitch"' . ($result->isLocked === true) ? 'checked' : '' . ' data-id="' . $result->_id . '" data-off-color="danger" data-on-color="success">';
            }
            $data = [
                'draw' => intval($data['draw']),
                'iTotalRecords' => $totalRecords,
                'iTotalDisplayRecords' => $totalRecords,
                'aaData' => $results,
            ];
            return $this->respond($data, 200);
        }
        return view('Modules\Backend\Views\logs\locked', $this->defData);
    }
}
