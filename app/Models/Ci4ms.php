<?php namespace App\Models;

use CodeIgniter\Model;

class Ci4ms extends Model
{
    public function taglist(array $credentials = [], int $limit=10, int $skip = 0,$select='*')
    {
        $locale = \Config\Services::request()->getLocale();
        $builder=$this->db->table('tags_pivot');
        $builder->select($select)
                ->join('blog','blog.id=tags_pivot.piv_id','left')
                ->join('tags','tags.id=tags_pivot.tag_id','left')
                ->join('blog_langs', "blog_langs.blog_id = blog.id AND blog_langs.lang = '{$locale}'", 'left');
        if (!empty($credentials)) $builder->where($credentials);
        return $builder->limit($limit,$skip)->orderBy('created_at DESC')->groupBy('blog.id')->get()->getResult();
    }

    public function categoryList(array $credentials,int $limit,int $skip)
    {
        $locale = \Config\Services::request()->getLocale();
        $builder=$this->db->table('blog');
        $builder->select('blog.*, blog_langs.title, blog_langs.seflink, blog_langs.content, blog_langs.seo')
            ->join('blog_categories_pivot','blog_categories_pivot.blog_id=blog.id','left')
            ->join('blog_langs', "blog_langs.blog_id = blog.id AND blog_langs.lang = '{$locale}'", 'left')
            ->where($credentials)->limit($limit,$skip);
        return $builder->get()->getResult();
    }
}
