<?php

namespace App\Controllers\templates\default;


class Forms extends \App\Controllers\BaseController
{
    public function contactForm_post()
    {
        $valData = ([
            'name' => ['label' => 'Full Name', 'rules' => 'required|regex_match[/^[^<>{}]*$/u]'],
            'email' => ['label' => 'Email Address', 'rules' => 'required|valid_email'],
            'phone' => ['label' => 'Phone Number', 'rules' => 'required|regex_match[/^[\d\s\+\-\(\)]{7,20}$/]'],
            'message' => ['label' => 'Message', 'rules' => 'required']
        ]);
        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        try {
            $email = service('email');

            $email->setFrom($this->request->getPost('email'), $this->request->getPost('name'));
            $email->setTo($this->defData['settings']->contact->email);

            $email->setSubject('İletişim Formu - ' . $this->request->getPost('phone'));
            $email->setMessage($this->request->getPost('message'));

            $email->send();
            return redirect()->back()->with('message', 'Mesajınız tarafımıza iletildi. En kısa zamanda geri dönüş sağlanacaktır');
        } catch (\Exception $e) {
            return redirect()->back()->withInput()->with('error', $e->getMessage());
        }
    }

    public function searchForm()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $locale = $this->request->getLocale();
        $valData = ([
            'term' => ['label' => '', 'rules' => 'required|min_length[2]|regex_match[/^[^<>{}=]+$/u]'],
        ]);
        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
        $result = [];
        $term = strip_tags(trim($this->request->getGet('term')));

        // Helper function for content filtering
        $filterByContent = static function ($results, $term) {
            return array_values(array_filter($results, static function ($row) use ($term) {
                if (stripos($row->title, $term) !== false) return true;
                $html = $row->content ?? '';
                $commentPattern = '/<!--[\s\S]*?-->/';
                $withoutComments = preg_replace($commentPattern, '', $html);
                return stripos($withoutComments, $term) !== false;
            }));
        };

        // Pages Search
        $results = $this->commonModel->lists('pages_langs', 'pages.id, pages_langs.title, pages_langs.seflink, pages_langs.content', [
            'pages.isActive' => 1,
            'pages_langs.lang' => $locale
        ], 'pages_langs.title ASC', 10, 0, [
            'pages_langs.title' => $term,
            'pages_langs.content' => $term
        ], [], [
            ['table' => 'pages', 'cond' => 'pages.id = pages_langs.pages_id', 'type' => 'inner']
        ]);
        
        $filteredPages = $filterByContent($results, $term);

        if (!empty($filteredPages)) {
            $pages = array_map(function ($page) {
                return [
                    'value' => $page->title,
                    'url' => '/' . ($page->id == setting('App.homePage') ? '' : $page->seflink)
                ];
            }, $filteredPages);
            $result = array_merge($result, $pages);
        }

        // Blog Search
        $results = $this->commonModel->lists('blog_langs', 'blog_langs.title, blog_langs.seflink, blog_langs.content', [
            'blog.isActive' => 1,
            'blog_langs.lang' => $locale
        ], 'blog_langs.title ASC', 10, 0, [
            'blog_langs.title' => $term,
            'blog_langs.content' => $term
        ], [], [
            ['table' => 'blog', 'cond' => 'blog.id = blog_langs.blog_id', 'type' => 'inner']
        ]);
        
        $filteredBlogs = $filterByContent($results, $term);

        if (!empty($filteredBlogs)) {
            $blogs = array_map(function ($page) {
                return [
                    'value' => '[blog] ' . $page->title,
                    'url' => '/blog/' . $page->seflink
                ];
            }, $filteredBlogs);
            $result = array_merge($result, $blogs);
        }

        // Tags Search
        $results = $this->commonModel->lists('tags', '*', [], 'tag ASC', 10, 0, ['tag' => $term]);
        if (!empty($results)) {
            $filteredTags = array_values(array_filter($results, static function ($row) use ($term) {
                return stripos($row->tag, $term) !== false;
            }));
            
            $tags = array_map(function ($page) {
                return [
                    'value' => '[etiket] ' . $page->tag,
                    'url' => '/tag/' . $page->seflink
                ];
            }, $filteredTags);
            $result = array_merge($result, $tags);
        }

        // Categories Search
        $results = $this->commonModel->lists('categories_langs', 'categories_langs.title, categories_langs.seflink', [
            'categories.isActive' => 1,
            'categories_langs.lang' => $locale
        ], 'categories_langs.title ASC', 10, 0, [
            'categories_langs.title' => $term
        ], [], [
            ['table' => 'categories', 'cond' => 'categories.id = categories_langs.categories_id', 'type' => 'inner']
        ]);
        
        if (!empty($results)) {
            $filteredCats = array_values(array_filter($results, static function ($row) use ($term) {
                return stripos($row->title, $term) !== false;
            }));

            $cats = array_map(function ($page) {
                return [
                    'value' => '[kategori] ' . $page->title,
                    'url' => '/category/' . $page->seflink
                ];
            }, $filteredCats);
            $result = array_merge($result, $cats);
        }

        if (!empty($result))
            return $this->respond($result, 200);
        else
            return $this->failNotFound();
    }
}
