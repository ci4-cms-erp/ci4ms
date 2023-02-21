<?php namespace Modules\Backend\Models;

use CodeIgniter\Model;

class SummaryModel extends Model
{
    public function inveList(array $where = [], int $limit = 0, int $skip = 0, array $like = [])
    {
        $builder = $this->db->table('investments');
        $builder
            ->select('investments.*,customers.nickname,websites.site_name,methods.method_name')
            ->join('customers', 'investments.customer_id=customers.id', 'left')
            ->join('websites', 'investments.website_id=websites.id', 'left')
            ->join('methods', 'investments.method_id=methods.id', 'left')
            ->where($where);
        if (!empty($like)) $builder->orLike($like);
        if ($limit >= 0 || $skip >= 0) $builder->limit($limit, $skip);
        return $builder->get()->getResult();
    }

    public function withdrawList(array $where = [], int $limit = 0, int $skip = 0, array $like = [])
    {
        $builder = $this->db->table('withdraw_money');
        $builder
            ->select('withdraw_money.*,customers.nickname,websites.site_name,methods.method_name')
            ->join('customers', 'withdraw_money.customer_id=customers.id', 'left')
            ->join('websites', 'withdraw_money.website_id=websites.id', 'left')
            ->join('methods', 'withdraw_money.method_id=methods.id', 'left')
            ->where($where);
        if (!empty($like)) $builder->orLike($like);
        if ($limit >= 0 || $skip >= 0) $builder->limit($limit, $skip);
        return $builder->get()->getResult();
    }

    public function totInvAndWith(array $where = [])
    {
        $builder = $this->db->table('investments');
        $data['itAmount'] = $builder->selectSum('amount', 'itAmount')
            ->getWhere($where)->getResult();
        $builder = $this->db->table('withdraw_money');
        $data['wtAmount'] = $builder->selectSum('amount', 'wtAmount')
            ->getWhere($where)->getResult();
        $builder = $this->db->table('investments');
        $data['iFees'] = $builder->select('methods.method_name,methods.method_fees,(methods.method_fees*investments.amount) as iFees')
            ->join('methods', 'methods.id=investments.method_id', 'left')
            ->getWhere($where)->getResult();
        $builder = $this->db->table('withdraw_money');
        $data['wFees'] = $builder->select('methods.method_name,methods.withdraw_fees,(methods.method_fees*withdraw_money.amount) as iFees')
            ->join('methods', 'methods.id=withdraw_money.method_id', 'left')
            ->getWhere($where)->getResult();
        return $data;
    }
}
