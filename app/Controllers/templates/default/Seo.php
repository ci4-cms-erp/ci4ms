<?php

namespace App\Controllers\templates\default;

use App\Controllers\BaseController;
use Melbahja\Seo\Ping;
use Melbahja\Seo\Sitemap;

class Seo extends BaseController
{
    public function index()
    {
        $sitemap = new Sitemap(base_url('sitemapsXML'), ['save_path' => ROOTPATH . 'public/sitemapsXML']);
        $sitemap->setIndexName('sitemap.xml');
        $sitemap->links(['name' => 'pages.xml', 'images' => true],
            function ($map) {
                $pages = $this->commonModel->lists('pages', '*', ['isActive' => true]);
                foreach ($pages as $page) {
                    $page->seo = json_decode($page->seo);
                    $page->seo = (object)$page->seo;
                    $map->loc($page->seflink)->freq('daily')->priority('0.8');
                    if (!empty($page->seo->coverImage)) $map->image($page->seo->coverImage, ['caption' => $page->title]);
                }
            });
        if ($this->commonModel->count('blog', ['isActive' => true]) > 0) {
            $sitemap->links('posts.xml', function ($map) {
                $blogs = $this->commonModel->lists('blog', '*', ['isActive' => true]);
                foreach ($blogs as $blog) {
                    $blog->seo = json_decode($blog->seo);
                    $blog->seo = (object)$blog->seo;
                    $map->loc("blog/{$blog->seflink}")->freq('weekly')->priority('0.7');
                }
            });
            $sitemap->links('categories.xml', function ($map) {
                $blogs = $this->commonModel->lists('categories', '*', ['isActive' => true]);
                foreach ($blogs as $blog) {
                    $blog->seo = json_decode($blog->seo);
                    $blog->seo = (object)$blog->seo;
                    $map->loc("category/{$blog->seflink}")->freq('weekly')->priority('0.7');
                }
            });
        }
        if ($sitemap->save() === true) {
            $ping = new Ping();
            $ping->send(base_url('sitemapsXML/sitemap.xml'));
            return redirect()->to('/sitemapsXML/sitemap.xml');
        } else return show_404();
    }
}
