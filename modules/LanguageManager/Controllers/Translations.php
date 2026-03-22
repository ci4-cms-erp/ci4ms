<?php

declare(strict_types=1);

namespace Modules\LanguageManager\Controllers;

use Modules\LanguageManager\Libraries\TranslationService;

class Translations extends \Modules\Backend\Controllers\BaseController
{
    protected TranslationService $translationService;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->translationService = new TranslationService();
    }

    public function index()
    {
        $group  = (string) ($this->request->getGet('group') ?? '');
        $search = (string) ($this->request->getGet('search') ?? '');
        $page   = max(1, (int) ($this->request->getGet('page') ?? 1));

        $this->defData['groups']    = $this->translationService->getGroups();
        $this->defData['languages'] = $this->translationService->getActiveLanguages();
        $this->defData['currentGroup'] = $group;
        $this->defData['search']    = $search;
        $this->defData['result']    = !empty($group) ? $this->translationService->getTranslations($group, $search, $page) : null;

        return view('Modules\LanguageManager\Views\translations', $this->defData);
    }

    public function save()
    {
        if (!$this->request->isAJAX()) {
            return $this->failForbidden();
        }

        $valData = [
            'key_id'        => 'required|is_natural_no_zero',
            'language_code' => 'required|max_length[10]',
            'value'         => 'permit_empty|regex_match[/^[^<>{}=]*$/u]'
        ];

        if ($this->validate($valData) === false) {
            return $this->respond(['status' => 'error', 'errors' => $this->validator->getErrors()], 422);
        }

        $keyId    = (int) $this->request->getPost('key_id');
        $langCode = (string) $this->request->getPost('language_code');
        $value    = (string) ($this->request->getPost('value') ?? '');

        $this->translationService->saveTranslation($keyId, $langCode, $value);

        return $this->respond([
            'status'  => 'success',
            'message' => lang('LanguageManager.translationSaved')
        ]);
    }

    public function addKey()
    {
        if (!$this->request->isAJAX()) {
            return $this->failForbidden();
        }

        $valData = [
            'group'    => 'required|max_length[100]|regex_match[/^[a-zA-Z0-9_\-]+$/]',
            'key_name' => 'required|max_length[255]|regex_match[/^[a-zA-Z0-9_\-\.]+$/]'
        ];

        if ($this->validate($valData) === false) {
            return $this->respond(['status' => 'error', 'errors' => $this->validator->getErrors()], 422);
        }

        $group   = trim((string) $this->request->getPost('group'));
        $keyName = trim((string) $this->request->getPost('key_name'));

        $id = $this->translationService->addKey($group, $keyName);
        if (!$id) {
            return $this->respond([
                'status'  => 'error',
                'message' => lang('LanguageManager.keyExists')
            ], 409);
        }

        return $this->respond([
            'status'  => 'success',
            'message' => lang('LanguageManager.keyAdded'),
            'id'      => $id
        ]);
    }

    public function deleteKey(int $id)
    {
        if (!$this->request->isAJAX()) {
            return $this->failForbidden();
        }

        $this->translationService->deleteKey($id);

        return $this->respond([
            'status'  => 'success',
            'message' => lang('LanguageManager.keyDeleted')
        ]);
    }

    public function export(string $langCode)
    {
        $data = $this->translationService->export($langCode);
        $filename = 'translations_' . $langCode . '_' . date('Ymd') . '.json';
        $content = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        return $this->response
            ->setHeader('Content-Type', 'application/json; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->setBody($content);
    }

    public function import()
    {
        $langCode = (string) $this->request->getPost('language_code');
        $file     = $this->request->getFile('import_file');

        if (!$file || !$file->isValid()) {
            return $this->respond([
                'status'  => 'error',
                'message' => lang('LanguageManager.invalidFile')
            ], 422);
        }

        $json = file_get_contents($file->getTempName());
        $data = json_decode($json, true);

        if (!is_array($data)) {
            return $this->respond([
                'status'  => 'error',
                'message' => lang('LanguageManager.invalidJsonFormat')
            ], 422);
        }

        $count = $this->translationService->import($langCode, $data);

        return $this->respond([
            'status'  => 'success',
            'message' => lang('LanguageManager.importSuccess', [$count])
        ]);
    }
}
