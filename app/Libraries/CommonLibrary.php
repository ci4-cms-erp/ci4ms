<?php

namespace App\Libraries;

use ci4commonModel\Models\CommonModel;
use Modules\Auth\Config\AuthConfig as Auth;

class CommonLibrary
{
    protected $config;
    protected $commonModel;

    public function __construct()
    {
        $this->config = new Auth();
        $this->commonModel = new CommonModel();
    }

    /**
     * @param string $string
     * @param string $start
     * @param string $end
     */
    private function findFunction($string, $start, $end)
    {
        $part = explode($start, $string);
        $d = [];
        foreach ($part as $item) {
            if (strpos($item, '/}')) $d[] = explode($end, $item);
        }
        $part = null;
        foreach ($d as $item) {
            $part[$start . $item[0] . $end] = $item[0];
        }
        return $part;
    }

    //TODO: çoklu veri işlenmesi için virgül kullanılır hale getirilecek.(,)
    /**
     * Undocumented function
     *
     * @param string $string
     * @return void
     */
    public function parseInTextFunctions(string $string)
    {
        $functions = $this->findFunction($string, '{', '/}');
        if (strpos($string, '[/')) {
            $val = $this->findFunction($string, '[/', '/]');
            $v = array_values($val)[0];
        }
        if (empty($functions)) return $string;
        foreach ($functions as $function) {
            $f = explode('|', $function);
            if (strpos($f[1], '[/')) $f[1] = strstr($f[1], '[/', true);
            if (!empty($val)) $data[$function] = call_user_func_array($f, [$v]);
            else $data[$function] = call_user_func($f);
        }
        return str_replace(array_keys($functions), $data, $string);
    }

    /**
     * @param string $comment
     * @param array $badwordsList
     * @param bool $status
     * @param bool $autoReject
     * @param bool $autoAccept
     * @return bool|string
     */
    public function commentBadwordFiltering(string $comment, array $badwordsList, bool $status = false, bool $autoReject = false, bool $autoAccept = false): bool|string
    {
        $pattern = '/\b(' . implode('|', $badwordsList) . ')\b/i';
        if ($autoReject) return false;
        if ($status && $autoAccept) {
            $comment = preg_replace($pattern, str_repeat('*', strlen('$0')), $comment);
            return $comment;
        }
        if ($status) return preg_replace($pattern, str_repeat('*', strlen('$0')), $comment);
        if ($autoAccept) return $comment;
        return false;
    }

    /**
     *
     */
    private function getHomepageBreadcrumb()
    {
        if (empty(cache('menus'))) $menus = $this->commonModel->lists('menu', '*', [], 'queue ASC');
        else $menus = (object)cache('menus');
        $homepage = array_filter((array) $menus, function ($menu) {
            return $menu->seflink == '/';
        });
        if (!empty($homepage)) return reset($homepage);
        else return [];
    }

    /**
     * @param integer $id
     * @param string $type
     */
    public function get_breadcrumbs($id, $type = 'page')
    {
        $method = 'get' . ucfirst($type) . 'Breadcrumbs';
        if (method_exists($this, $method)) {
            return $this->$method($id);
        }
        return [];
    }

    /**
     *
     * @param int $id
     * @return void
     */
    private function getPageBreadcrumbs($id)
    {
        $menus = (object)cache('menus');
        $homepage = $this->getHomepageBreadcrumb();
        if (is_integer($id))
            $current_page = array_filter((array) $menus, function ($menu) use ($id) {
                return $menu->pages_id == $id;
            });
        if (is_string($id))
            $current_page = array_filter((array) $menus, function ($menu) use ($id) {
                return $menu->seflink == $id;
            });
        $current_page = reset($current_page);
        if (!$current_page || !$homepage) return array();

        $breadcrumbs = [['title' => $homepage->title, 'url' => base_url()]];
        $tmpCurrentPage = $current_page;
        while ($tmpCurrentPage->parent) {
            $parent_pages = array_filter((array) $menus, function ($menu) use ($tmpCurrentPage) {
                return $menu->id == $tmpCurrentPage->parent && $menu->seflink != '/';
            });
            $parent_page = reset($parent_pages);

            if ($parent_page) {
                array_push($breadcrumbs, ['title' => $parent_page->title, 'url' => $parent_page->seflink]);
                $tmpCurrentPage = $parent_page;
            }
        }
        array_push($breadcrumbs, ['title' => $current_page->title, 'url' => current_url()]);

        return $breadcrumbs;
    }

    private function getBlogBreadcrumbs($id)
    {
        $homepage = $this->getHomepageBreadcrumb();
        $breadcrumbs = [['title' => $homepage->title, 'url' => site_url($homepage->seflink)]];
        $blog = $this->commonModel->selectOne('blog', ['id' => $id]);
        $category = $this->commonModel->lists('categories', 'categories.*', ['blog_categories_pivot.blog_id' => $id], 'id ASC', 0, 0, [], [], [
            [
                'table' => 'blog_categories_pivot',
                'cond' => 'categories.id = blog_categories_pivot.categories_id',
                'type' => 'left'
            ]
        ]);
        if ($blog) {
            $breadcrumbs[] = ['title' => 'Blog', 'url' => site_url('blog')];
            $breadcrumbs[] = ['title' => $category[0]->title, 'url' => site_url('category/' . $category[0]->seflink)];
            $breadcrumbs[] = ['title' => $blog->title, 'url' => current_url()];
        }
        return $breadcrumbs;
    }

    private function getCategoryBreadcrumbs($id)
    {
        $homepage = $this->getHomepageBreadcrumb();
        $breadcrumbs = [['title' => $homepage->title, 'url' => site_url($homepage->seflink)]];
        $category = $this->commonModel->selectOne('categories', ['id' => $id]);
        if ($category) {
            $breadcrumbs[] = ['title' => 'Blog', 'url' => site_url('blog')];
            $breadcrumbs[] = ['title' => $category->title, 'url' => current_url()];
        }
        return $breadcrumbs;
    }

    private function getTagBreadcrumbs($id)
    {
        $homepage = $this->getHomepageBreadcrumb();
        $breadcrumbs = [['title' => $homepage->title, 'url' => site_url($homepage->seflink)]];
        $tag = $this->commonModel->selectOne('tags', ['id' => $id]);
        if ($tag) {
            $breadcrumbs[] = ['title' => 'Blog', 'url' => site_url('blog')];
            $breadcrumbs[] = ['title' => $tag->tag, 'url' => current_url()];
        }
        return $breadcrumbs;
    }
}
