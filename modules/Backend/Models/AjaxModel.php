<?php namespace Modules\Backend\Models;

use CodeIgniter\Model;

class AjaxModel extends Model
{
    public function limitTags_ajax(array $credentials = [], int $limit = 0, int $skip = 0)
    {
        $builder = $this->db->table('tags');
        if (!empty($credentials)) $builder->where($credentials);
        $builder->join('tags_pivot', 'tags_pivot.tag_id=tags.id', 'left');
        if ($limit >= 0 || $skip >= 0) $builder->limit($limit, $skip);
        $builder->groupBy('tags.id');
        return $builder->get()->getResult();
    }
}
