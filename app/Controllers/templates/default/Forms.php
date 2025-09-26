<?php

namespace App\Controllers\templates\default;

use App\Libraries\CommonLibrary;

class Forms extends \App\Controllers\BaseController
{
    public function contactForm_post()
    {
        $valData = ([
            'name' => ['label' => 'Full Name', 'rules' => 'required'],
            'email' => ['label' => 'Email Address', 'rules' => 'required|valid_email'],
            'phone' => ['label' => 'Phone Number', 'rules' => 'required'],
            'message' => ['label' => 'Message', 'rules' => 'required']
        ]);
        if ($this->validate($valData) == false) return redirect()->back()->withInput()->with('errors', $this->validator->getErrors());
        $commonLibrary = new CommonLibrary();
        $settings = (object)cache('settings');
        $mailResult = $commonLibrary->phpMailer($this->request->getPost('email'), $this->request->getPost('name'), [['mail' => $settings->company->email]], $this->request->getPost('email'), $this->request->getPost('name'), 'İletişim Formu - ' . $this->request->getPost('phone'), $this->request->getPost('message'));
        if ($mailResult === true) return redirect()->back()->with('message', 'Mesajınız tarafımıza iletildi. En kısa zamanda geri dönüş sağlanacaktır');
        else return redirect()->back()->withInput()->with('error', $mailResult);
    }

    public function searchForm()
    {
        if (!$this->request->isAJAX()) return $this->failForbidden();
        $valData = ([
            'term' => ['label' => '', 'rules' => 'required'],
        ]);
        if ($this->validate($valData) == false) return $this->fail($this->validator->getErrors());
        $result = [];
        $term = $this->request->getPost('term');
        $results = $this->commonModel->lists('pages', '*', [], 'title ASC', 0, 0, ['title' => $this->request->getGet('term'), 'content' => $this->request->getGet('term')]);
        $filtered = array_values(array_filter($results, static function ($row) use ($term) {
            $html = $row->content ?? '';         // HTML veriniz
            if ($term === '') {
                return true;                       // Boş terimde hepsini tut
            }

            $commentPattern = '/<!--[\s\S]*?-->/';
            preg_match_all($commentPattern, $html, $commentMatches);

            $termInComments = false;
            foreach ($commentMatches[0] ?? [] as $comment) {
                if (stripos($comment, $term) !== false) {
                    $termInComments = true;
                    break;
                }
            }

            $withoutComments = preg_replace($commentPattern, '', $html);
            $termOutside     = stripos($withoutComments, $term) !== false;

            return $termOutside || ! $termInComments;
        }));
        if (!empty($filtered)) {
            $pages = array_map(function ($page) {
                return [
                    'value' => $page->title,
                    'url' => '/' . $page->seflink
                ];
            }, $filtered);
            $result = array_merge($result, $pages);
        }

        $results = $this->commonModel->lists('blog', '*', [], 'title ASC', 0, 0, ['title' => $this->request->getGet('term'), 'content' => $this->request->getGet('term')]);
        if (!empty($results)) {
            $blogs = array_map(function ($page) {
                return [
                    'value' => '[blog] ' . $page->title,
                    'url' => '/blog/' . $page->seflink
                ];
            }, $results);
            $result = array_merge($result, $blogs);
        }

        $results = $this->commonModel->lists('tags', '*', [], 'tag ASC', 0, 0, ['tag' => $this->request->getGet('term')]);
        if (!empty($results)) {
            $tags = array_map(function ($page) {
                return [
                    'value' => '[etiket] ' . $page->tag,
                    'url' => '/tag/' . $page->seflink
                ];
            }, $results);
            $result = array_merge($result, $tags);
        }

        $results = $this->commonModel->lists('categories', '*', [], 'title ASC', 0, 0, ['title' => $this->request->getGet('term')]);
        if (!empty($results)) {
            $tags = array_map(function ($page) {
                return [
                    'value' => '[kategori] ' . $page->title,
                    'url' => '/category/' . $page->seflink
                ];
            }, $results);
            $result = array_merge($result, $tags);
        }
        if (!empty($result))
            return $this->respond($result, 200);
        else
            return $this->failNotFound();
    }
}
