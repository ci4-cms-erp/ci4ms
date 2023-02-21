<?php namespace Modules\Backend\Models;

use CodeIgniter\Model;

class UserscrudModel extends Model
{
    /**
     * @var string
     */
    protected $table = 'users';

    /**
     * @param int $limit
     * @param array $select
     * @param array $credentials
     * @return mixed
     */
    public function loggedUser(int $limit, string $select = '*', array $credentials = [])
    {
        $builder = $this->db->table($this->table);
        $builder->select($select)
            ->join('auth_groups', 'auth_groups.id=users.group_id', 'left');
        if (!empty($credentials)) $builder->where($credentials);
        if ($limit > 0) $builder->limit($limit);
        return $builder->get()->getResult();
    }

    /**
     * @param int $limit
     * @param array $select
     * @param array $credentials
     * @param null $skip
     * @return mixed
     */
    public function userList(int $limit, string $select = '*', array $credentials = [], int $skip = 0)
    {
        $builder=$this->db->table($this->table);
        $builder->select($select)->join('auth_groups','users.group_id=auth_groups.id','left')
        ->join('black_list_users','users.id=black_list_users.blacked_id','left')
        ->where($credentials);
        if($limit>0) $builder->limit($limit,$skip);
        return $builder->get()->getResult();
    }
}
