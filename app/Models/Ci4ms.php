<?php namespace App\Models;

use CodeIgniter\Model;

class Ci4ms extends Model
{
    public function taglist(array $credentials = [], int $limit=10, int $skip = 0,$select='*')
    {
        $builder=$this->db->table('tags_pivot');
        $builder->select($select)->join('blog','blog.id=tags_pivot.piv_id','left')->join('tags','tags.id=tags_pivot.tag_id','left');
        if (!empty($credentials)) $builder->where($credentials);
        return $builder->limit($limit,$skip)->orderBy('created_at DESC')->groupBy('blog.id')->get()->getResult();
    }

    public function categoryList(array $credentials,int $limit,int $skip)
    {
        $builder=$this->db->table('blog');
        $builder->select('blog.*')
            ->join('blog_categories_pivot','blog_categories_pivot.blog_id=blog.id','left')
            ->where($credentials)->limit($limit,$skip);
        return $builder->get()->getResult();
    }
}
