<?php namespace Modules\Backend\Models;

use CodeIgniter\Model;

class CategoriesModel extends Model
{
    protected $table='categories';

    public function list(int $limit,int $skip){
        $builder=$this->db->table($this->table);
        $builder->join($this->table,$this->table.'.parent='.$this->table.'.id','left')->limit($limit,$skip);
        return $builder->get()->getResult();
    }
}
