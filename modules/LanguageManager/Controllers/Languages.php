<?php

declare(strict_types=1);

namespace Modules\LanguageManager\Controllers;

use Modules\LanguageManager\Libraries\TranslationService;

class Languages extends \Modules\Backend\Controllers\BaseController
{
    protected TranslationService $translationService;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->translationService = new TranslationService();
    }

    public function index()
    {
        if ($this->request->isAJAX() && $this->request->is('post')) {
            $parsed = $this->commonBackendLibrary->getDatatablesPagination($this->request->getPost());

            $like = [];
            if (!empty($parsed['searchString'])) {
                $like = [
                    'code'        => $parsed['searchString'],
                    'name'        => $parsed['searchString'],
                    'native_name' => $parsed['searchString']
                ];
            }

            $totalRecords  = $this->commonModel->count('languages');
            $filteredCount = !empty($like)
                ? (int) count($this->commonModel->lists('languages', 'id', [], 'id ASC', 0, 0, $like))
                : $totalRecords;

            $rows = $this->commonModel->lists(
                'languages',
                '*',
                [],
                'sort_order ASC',
                $parsed['length'],
                $parsed['start'],
                $like
            );

            $data = [];
            foreach ($rows as $row) {
                $badges = '';
                $badges .= $row->is_active
                    ? '<span class="badge badge-success mr-1">' . lang('LanguageManager.active') . '</span>'
                    : '<span class="badge badge-secondary mr-1">' . lang('LanguageManager.inactive') . '</span>';

                if ($row->is_default) {
                    $badges .= '<span class="badge badge-primary">' . lang('LanguageManager.default') . '</span>';
                }

                $actions = '<div class="btn-group btn-group-sm">';
                $actions .= '<a href="' . site_url('backend/language-manager/languages/update/' . $row->id) . '" class="btn btn-outline-primary"><i class="fas fa-edit"></i></a>';
                $actions .= '<button class="btn btn-outline-warning btn-toggle-lang" data-id="' . $row->id . '"><i class="fas fa-power-off"></i></button>';

                if (!$row->is_default) {
                    $actions .= '<button class="btn btn-outline-info btn-set-default" data-id="' . $row->id . '" title="' . lang('LanguageManager.setDefault') . '"><i class="fas fa-star"></i></button>';
                    $actions .= '<button class="btn btn-outline-danger btn-delete-lang" data-id="' . $row->id . '"><i class="fas fa-trash"></i></button>';
                }
                $actions .= '</div>';

                $data[] = [
                    $row->id,
                    esc($row->flag ?? '') . ' <strong>' . esc($row->code) . '</strong>',
                    esc($row->name),
                    esc($row->native_name ?? ''),
                    '<code>' . esc($row->direction) . '</code>',
                    $row->sort_order,
                    $badges,
                    $actions
                ];
            }

            return $this->respond([
                'draw'            => $parsed['draw'],
                'recordsTotal'    => $totalRecords,
                'recordsFiltered' => $filteredCount,
                'data'            => $data
            ]);
        }

        return view('Modules\LanguageManager\Views\languages_list', $this->defData);
    }

    public function create()
    {
        if ($this->request->is('post')) {
            $valData = [
                'code' => [
                    'label' => lang('LanguageManager.code'),
                    'rules' => 'required|is_unique[languages.code]|regex_match[/^[a-z]{2,5}$/]'
                ],
                'name' => [
                    'label' => lang('LanguageManager.name'),
                    'rules' => 'required|max_length[100]|regex_match[/^[^<>{}=]+$/u]'
                ],
                'native_name' => [
                    'label' => lang('LanguageManager.nativeName'),
                    'rules' => 'permit_empty|max_length[100]|regex_match[/^[^<>{}=]*$/u]'
                ],
                'flag' => [
                    'label' => lang('LanguageManager.flag'),
                    'rules' => 'permit_empty|max_length[10]|regex_match[/^[^<>{}=]*$/u]'
                ],
                'sort_order' => [
                    'label' => lang('LanguageManager.sortOrder'),
                    'rules' => 'required|is_natural'
                ],
            ];

            if ($this->validate($valData) === false) {
                return $this->respond([
                    'status' => 'error',
                    'errors' => $this->validator->getErrors()
                ], 422);
            }

            $data = $this->getPostData();
            $this->commonModel->create('languages', $data);
            cache()->delete('frontend_languages');

            return $this->respond([
                'status'  => 'success',
                'message' => lang('LanguageManager.createSuccess')
            ]);
        }

        return view('Modules\LanguageManager\Views\language_form', $this->defData);
    }

    public function update(int $id)
    {
        $lang = $this->commonModel->selectOne('languages', ['id' => $id]);
        if (!$lang) {
            return redirect()->to(site_url('backend/language-manager/languages'))
                ->with('error', lang('LanguageManager.recordNotFound'));
        }

        if ($this->request->is('post')) {
            $valData = [
                'code' => [
                    'label' => lang('LanguageManager.code'),
                    'rules' => "required|is_unique[languages.code,id,{$id}]|regex_match[/^[a-z]{2,5}$/]"
                ],
                'name' => [
                    'label' => lang('LanguageManager.name'),
                    'rules' => 'required|max_length[100]|regex_match[/^[^<>{}=]+$/u]'
                ],
                'native_name' => [
                    'label' => lang('LanguageManager.nativeName'),
                    'rules' => 'permit_empty|max_length[100]|regex_match[/^[^<>{}=]*$/u]'
                ],
                'flag' => [
                    'label' => lang('LanguageManager.flag'),
                    'rules' => 'permit_empty|max_length[10]|regex_match[/^[^<>{}=]*$/u]'
                ],
                'sort_order' => [
                    'label' => lang('LanguageManager.sortOrder'),
                    'rules' => 'required|is_natural'
                ],
            ];

            if ($this->validate($valData) === false) {
                return $this->respond([
                    'status' => 'error',
                    'errors' => $this->validator->getErrors()
                ], 422);
            }

            $data = $this->getPostData();
            $this->commonModel->edit('languages', $data, ['id' => $id]);
            cache()->delete('frontend_languages');

            return $this->respond([
                'status'  => 'success',
                'message' => lang('LanguageManager.updateSuccess')
            ]);
        }

        $this->defData['language'] = $lang;
        return view('Modules\LanguageManager\Views\language_form', $this->defData);
    }

    public function delete(int $id)
    {
        $lang = $this->commonModel->selectOne('languages', ['id' => $id]);
        if ($lang && $lang->is_default) {
            return $this->respond([
                'status'  => 'error',
                'message' => lang('LanguageManager.defaultLangCannotDelete')
            ], 403);
        }

        $this->commonModel->remove('languages', ['id' => $id]);
        cache()->delete('frontend_languages');

        return $this->respond([
            'status'  => 'success',
            'message' => lang('LanguageManager.deleteSuccess')
        ]);
    }

    public function toggle(int $id)
    {
        $lang = $this->commonModel->selectOne('languages', ['id' => $id]);
        if (!$lang) {
            return $this->respond(['status' => 'error'], 404);
        }

        $this->commonModel->edit('languages', ['is_active' => $lang->is_active ? 0 : 1], ['id' => $id]);
        cache()->delete('frontend_languages');

        return $this->respond([
            'status'  => 'success',
            'message' => lang('LanguageManager.toggleSuccess')
        ]);
    }

    public function setDefault(int $id)
    {
        $this->translationService->setDefault($id);
        cache()->delete('frontend_languages');

        return $this->respond([
            'status'  => 'success',
            'message' => lang('LanguageManager.setDefaultSuccess')
        ]);
    }

    protected function getPostData(): array
    {
        return [
            'code'        => strtolower(trim((string) $this->request->getPost('code'))),
            'name'        => trim((string) $this->request->getPost('name')),
            'native_name' => trim((string) $this->request->getPost('native_name')),
            'flag'        => trim((string) $this->request->getPost('flag')),
            'direction'   => $this->request->getPost('direction') ?? 'ltr',
            'sort_order'  => (int) ($this->request->getPost('sort_order') ?? 0),
            'is_active'   => (int) ($this->request->getPost('is_active') ?? 1),
            'is_frontend' => (int) ($this->request->getPost('is_frontend') ?? 1),
        ];
    }
}
