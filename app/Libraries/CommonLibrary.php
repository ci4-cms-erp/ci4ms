<?php

namespace App\Libraries;

use ci4commonModel\CommonModel;
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
     * @return string
     */
    public function parseInTextFunctions(string $string)
    {
        // 1. Yeni FormBuilder parser: {{form=iletisim-form}}
        if (preg_match_all('/\{\{form=([a-zA-Z0-9_-]+)\}\}/', $string, $matches)) {
            foreach ($matches[1] as $index => $formSlug) {
                // Eğer modül aktif ve FormRenderer sınıfı varsa form HTML'ini al
                if (class_exists('\Modules\FormBuilder\Libraries\FormRenderer')) {
                    $formHtml = \Modules\FormBuilder\Libraries\FormRenderer::render($formSlug);
                    $string = str_replace($matches[0][$index], $formHtml, $string);
                } else {
                    $string = str_replace($matches[0][$index], '<!-- FormBulider modülü kurulu/aktif değil -->', $string);
                }
            }
        }

        // 2. Mevcut parse in text fonksiyonları
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
        if ($autoReject && preg_match($pattern, $comment)) {
            return false;
        }
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
    /**
     * @return object|array
     */
    private function getHomepageBreadcrumb()
    {
        $locale = \Config\Services::request()->getLocale();
        $homePageId = setting('App.homePage');

        if (!empty($homePageId)) {
            $pages = $this->commonModel->lists('pages', 'pages.id, pages_langs.title', ['pages.id' => $homePageId, 'pages_langs.lang' => $locale], 'pages.id DESC', 1, 0, [], [], [
                ['table' => 'pages_langs', 'cond' => 'pages_langs.pages_id = pages.id', 'type' => 'inner']
            ]);
            if (!empty($pages)) {
                return (object)['title' => $pages[0]->title, 'seflink' => '/'];
            }
        }

        // Fallback to menu check for '/'
        if (empty(cache('menus'))) $menus = $this->commonModel->lists('menu', '*', [], 'queue ASC');
        else $menus = (object)cache('menus');

        $homepage = array_filter((array) $menus, function ($menu) {
            return $menu->seflink == '/';
        });

        if (!empty($homepage)) return reset($homepage);

        return (object)['title' => lang('Backend.home'), 'seflink' => '/'];
    }

    /**
     * @param int|string $id
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
     * @param int|string $id
     * @return array
     */
    /**
     *
     * @param int|string $id
     * @return array
     */
    private function getPageBreadcrumbs($id)
    {
        $locale = \Config\Services::request()->getLocale();
        $menus = (object)cache('menus');
        $homepage = $this->getHomepageBreadcrumb();

        if (is_integer($id))
            $current_menu = array_filter((array) $menus, function ($menu) use ($id) {
                return $menu->pages_id == $id;
            });
        else if (is_string($id))
            $current_menu = array_filter((array) $menus, function ($menu) use ($id) {
                return $menu->seflink == $id;
            });

        $current_menu = !empty($current_menu) ? reset($current_menu) : null;

        // If no menu association, or it is the homepage itself, just return home
        if (!$current_menu || $homepage->seflink == $current_menu->seflink) {
            // Get current page title even without menu
            $title = lang('Backend.home');
            if (is_numeric($id)) {
                $pageData = $this->commonModel->lists('pages', 'pages_langs.title', ['pages.id' => $id, 'pages_langs.lang' => $locale], 'pages.id DESC', 1, 0, [], [], [
                    ['table' => 'pages_langs', 'cond' => 'pages_langs.pages_id = pages.id', 'type' => 'inner']
                ]);
                if (!empty($pageData)) $title = $pageData[0]->title;
            } else if (is_string($id)) {
                $title = trim($id, '/');
                $title = ucfirst(explode('/', $title)[0]);
            }

            return [['title' => $homepage->title, 'url' => site_url()], ['title' => $title, 'url' => current_url()]];
        }

        $breadcrumbs = [['title' => $homepage->title, 'url' => site_url()]];
        $tmpCurrentMenu = $current_menu;

        $path = [];
        while ($tmpCurrentMenu && $tmpCurrentMenu->parent) {
            $parent_menus = array_filter((array) $menus, function ($menu) use ($tmpCurrentMenu) {
                return $menu->id == $tmpCurrentMenu->parent && $menu->seflink != '/';
            });
            $parent_menu = reset($parent_menus);

            if ($parent_menu) {
                // Fetch localized title for parent if it's a page
                $parent_title = $parent_menu->title;
                if ($parent_menu->urlType === 'pages' && !empty($parent_menu->pages_id)) {
                    $pData = $this->commonModel->lists('pages', 'pages_langs.title', ['pages.id' => $parent_menu->pages_id, 'pages_langs.lang' => $locale], 'pages.id DESC', 1, 0, [], [], [
                        ['table' => 'pages_langs', 'cond' => 'pages_langs.pages_id = pages.id', 'type' => 'inner']
                    ]);
                    if (!empty($pData)) $parent_title = $pData[0]->title;
                }

                array_unshift($path, ['title' => $parent_title, 'url' => site_url($parent_menu->seflink)]);
                $tmpCurrentMenu = $parent_menu;
            } else {
                break;
            }
        }

        foreach ($path as $p) $breadcrumbs[] = $p;

        // Localized title for current page
        $currPageData = $this->commonModel->lists('pages', 'pages_langs.title', ['pages.id' => $id, 'pages_langs.lang' => $locale], 'pages.id DESC', 1, 0, [], [], [
            ['table' => 'pages_langs', 'cond' => 'pages_langs.pages_id = pages.id', 'type' => 'inner']
        ]);
        $currTitle = !empty($currPageData) ? $currPageData[0]->title : $current_menu->title;

        $breadcrumbs[] = ['title' => $currTitle, 'url' => current_url()];

        return $breadcrumbs;
    }

    private function getBlogBreadcrumbs($id)
    {
        $locale = \Config\Services::request()->getLocale();
        $homepage = $this->getHomepageBreadcrumb();
        $breadcrumbs = [['title' => $homepage->title, 'url' => site_url()]];

        $blogArray = $this->commonModel->lists('blog', 'blog.*, blog_langs.title, blog_langs.seflink', ['blog.id' => $id], 'blog.id ASC', 1, 0, [], [], [
            ['table' => 'blog_langs', 'cond' => "blog_langs.blog_id = blog.id AND blog_langs.lang = '{$locale}'", 'type' => 'inner']
        ]);
        $blog = !empty($blogArray) ? $blogArray[0] : null;

        $category = $this->commonModel->lists('categories', 'categories.id, categories_langs.title, categories_langs.seflink', ['blog_categories_pivot.blog_id' => $id], 'categories.id ASC', 1, 0, [], [], [
            [
                'table' => 'blog_categories_pivot',
                'cond' => 'categories.id = blog_categories_pivot.categories_id',
                'type' => 'left'
            ],
            [
                'table' => 'categories_langs',
                'cond' => "categories_langs.categories_id = categories.id AND categories_langs.lang = '{$locale}'",
                'type' => 'inner'
            ]
        ]);
        if ($blog) {
            $breadcrumbs[] = ['title' => 'Blog', 'url' => site_url('blog')];
            if (!empty($category)) {
                $breadcrumbs[] = ['title' => $category[0]->title, 'url' => site_url('category/' . $category[0]->seflink)];
            }
            $breadcrumbs[] = ['title' => $blog->title, 'url' => current_url()];
        }
        return $breadcrumbs;
    }

    private function getCategoryBreadcrumbs($id)
    {
        $locale = \Config\Services::request()->getLocale();
        $homepage = $this->getHomepageBreadcrumb();
        $breadcrumbs = [['title' => $homepage->title, 'url' => site_url()]];
        $categoryArray = $this->commonModel->lists('categories', 'categories.id, categories_langs.title', ['categories.id' => $id], 'categories.id ASC', 1, 0, [], [], [
            ['table' => 'categories_langs', 'cond' => "categories_langs.categories_id = categories.id AND categories_langs.lang = '{$locale}'", 'type' => 'inner']
        ]);
        $category = !empty($categoryArray) ? $categoryArray[0] : null;
        if ($category) {
            $breadcrumbs[] = ['title' => 'Blog', 'url' => site_url('blog')];
            $breadcrumbs[] = ['title' => $category->title, 'url' => current_url()];
        }
        return $breadcrumbs;
    }

    private function getTagBreadcrumbs($id)
    {
        $homepage = $this->getHomepageBreadcrumb();
        $breadcrumbs = [['title' => $homepage->title, 'url' => site_url()]];
        $tag = $this->commonModel->selectOne('tags', ['id' => $id]);
        if ($tag) {
            $breadcrumbs[] = ['title' => 'Blog', 'url' => site_url('blog')];
            $breadcrumbs[] = ['title' => $tag->tag, 'url' => current_url()];
        }
        return $breadcrumbs;
    }
}
