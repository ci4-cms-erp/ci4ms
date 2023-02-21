<?php

namespace Modules\Backend\Controllers;


use Modules\Backend\Models\SummaryModel;

class Summary extends BaseController
{
    public $summaryModel;

    public function __construct()
    {
        $this->summaryModel=new SummaryModel();
        d($this->summaryModel->totInvAndWith());
    }

    public function index()
    {
        $this->defData['req']=(object)$this->request->getGet();
        $this->defData['websites']=$this->commonModel->lists('websites');
        $this->defData['methods']=$this->commonModel->lists('methods');
        return view('Modules\Backend\Views\summary\summary',$this->defData);
    }

    public function summary_render()
    {
        if($this->request->isAJAX()) {
            $data = clearFilter($this->request->getPost());
            if (empty($data['search']['value'])) unset($data['search']);
            unset($data['columns'], $data['order']);
            $searchData = [];
            $like=[];
            if (!empty($data['search']['value'])) $like=['nickname'=>$data['search']['value'],
                'site_name'=>$data['search']['value'],
                'method_name'=>$data['search']['value']];
            if(!empty($data['website'])) $searchData['websites.id']=$data['website'];
            if(!empty($data['methods'])) $searchData['methods.id']=$data['methods'];
            if(!empty($data['inv']) && (bool)$data['inv']===true)
                $aaData=$this->summaryModel->inveList($searchData
                ,($data['start'] > 0) ? (($data['start'] / 10) + 1) * $data['length'] : $data['length'],$data['start'],
                $like);
            if(!empty($data['withdraw']) && (bool)$data['withdraw']===true)
                $aaData=$this->summaryModel->withdrawList($searchData
                    ,($data['start'] > 0) ? (($data['start'] / 10) + 1) * $data['length'] : $data['length'],$data['start'],
                    $like);
            $data = [
                'draw' => intval($data['draw']),
                "iTotalRecords" => count($aaData),
                "iTotalDisplayRecords" => count($aaData),
            ];
            foreach ($aaData as $item) {
                $data['aaData'][] = [
                    'id' => $item->id,
                    'user_name' => $item->nickname,
                    'website' => $item->site_name,
                    'investment_history' => !empty($item->investment_history)?$item->investment_history:null,
                    'withdraw_history'=>!empty($item->withdraw_history)?$item->withdraw_history:null,
                    'amount' => format_number($item->amount).' ₺',
                    'method' => $item->method_name];
            }
            return $this->respond($data, 200);
        }
    }

    public function websites()
    {

    }

    public function website_create()
    {

    }

    public function website_create_post()
    {

    }

    public function website_edit()
    {

    }

    public function website_edit_post()
    {

    }

    public function website_delete()
    {

    }

    public function methods()
    {

    }

    public function method_create()
    {

    }

    public function method_create_post()
    {

    }

    public function method_update()
    {

    }

    public function method_update_post()
    {

    }

    public function method_delete()
    {
        
    }

    public function upload_excel()
    {
        
    }
}
