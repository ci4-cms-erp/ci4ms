<?php

namespace Modules\DashboardWidgets\Controllers;

use Modules\DashboardWidgets\Libraries\WidgetService;

class DashboardWidgets extends \Modules\Backend\Controllers\BaseController
{
    protected WidgetService $widgetService;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->widgetService = new WidgetService();
    }

    // ─────────── LIST ───────────

    public function index()
    {
        if ($this->request->isAJAX() && $this->request->is('post')) {
            $parsed = $this->commonBackendLibrary->getDatatablesPagination($this->request->getPost());

            $like = [];
            if (!empty($parsed['searchString'])) {
                $like = ['title' => $parsed['searchString'], 'slug' => $parsed['searchString'], 'type' => $parsed['searchString']];
            }

            $totalRecords  = $this->commonModel->count('dashboard_widgets');
            $filteredCount = $totalRecords;
            $rows = $this->commonModel->lists('dashboard_widgets', '*', [], 'id ASC', $parsed['length'], $parsed['start'], $like);
            if (!empty($like)) {
                $filteredCount = count($this->commonModel->lists('dashboard_widgets', 'id', [], 'id ASC', 0, 0, $like));
            }

            $data = [];
            foreach ($rows as $row) {
                $typeBadge = '<span class="badge badge-secondary">' . esc($row->type) . '</span>';
                $activeBadge = $row->is_active
                    ? '<span class="badge badge-success">' . lang('Backend.active') . '</span>'
                    : '<span class="badge badge-secondary">' . lang('Backend.inactive') . '</span>';
                $systemBadge = $row->is_system ? '<span class="badge badge-dark">' . lang('DashboardWidgets.system') . '</span>' : '';

                $actions = '<div class="btn-group btn-group-sm">';
                $actions .= '<a href="' . site_url('backend/dashboard-widgets/update/' . $row->id) . '" class="btn btn-outline-primary"><i class="fas fa-edit"></i></a>';
                $actions .= '<button class="btn btn-outline-warning btn-toggle-widget" data-id="' . $row->id . '"><i class="fas fa-power-off"></i></button>';
                if (!$row->is_system) {
                    $actions .= '<button class="btn btn-outline-danger btn-delete-widget" data-id="' . $row->id . '"><i class="fas fa-trash"></i></button>';
                }
                $actions .= '</div>';

                $data[] = [
                    $row->id,
                    '<i class="' . esc($row->icon) . ' text-' . esc($row->color) . ' mr-2"></i><strong>' . esc($row->title) . '</strong>',
                    '<code>' . esc($row->slug) . '</code>',
                    $typeBadge,
                    '<code>' . esc($row->default_size) . '</code>',
                    $activeBadge . ' ' . $systemBadge,
                    $actions,
                ];
            }

            return $this->respond([
                'draw'            => $parsed['draw'],
                'recordsTotal'    => $totalRecords,
                'recordsFiltered' => $filteredCount,
                'data'            => $data,
            ]);
        }

        return view('Modules\DashboardWidgets\Views\list', $this->defData);
    }

    // ─────────── CREATE ───────────

    public function create()
    {
        if ($this->request->is('post')) {
            $data = $this->getPostData();
            $errors = $this->validateWidget($data);

            if (!empty($errors)) {
                return $this->respond(['status' => 'error', 'errors' => $errors], 422);
            }

            $id = $this->commonModel->create('dashboard_widgets', $data);
            if ($id) {
                return $this->respond(['status' => 'success', 'message' => lang('Backend.createSuccess'), 'id' => $id]);
            }
            return $this->respond(['status' => 'error', 'message' => lang('Backend.saveFailed')], 500);
        }

        return view('Modules\DashboardWidgets\Views\form', $this->defData);
    }

    // ─────────── UPDATE ───────────

    public function update(int $id)
    {
        $widget = $this->commonModel->selectOne('dashboard_widgets', ['id' => $id]);
        if (!$widget) {
            return redirect()->to(site_url('backend/dashboard-widgets'))->with('error', lang('Backend.recordNotFound'));
        }

        if ($this->request->is('post')) {
            $data = $this->getPostData();
            $errors = $this->validateWidget($data, $id);
            if (!empty($errors)) {
                return $this->respond(['status' => 'error', 'errors' => $errors], 422);
            }
            $this->commonModel->edit('dashboard_widgets', $data, ['id' => $id]);
            return $this->respond(['status' => 'success', 'message' => lang('Backend.updateSuccess')]);
        }

        $this->defData['widget'] = $widget;
        return view('Modules\DashboardWidgets\Views\form', $this->defData);
    }

    // ─────────── DELETE ───────────

    public function delete(int $id)
    {
        $widget = $this->commonModel->selectOne('dashboard_widgets', ['id' => $id]);
        if (!$widget) return $this->respond(['status' => 'error', 'message' => lang('Backend.recordNotFound')], 404);
        if ($widget->is_system) return $this->respond(['status' => 'error', 'message' => lang('DashboardWidgets.deleteFailed')], 403);

        $this->commonModel->remove('dashboard_widgets', ['id' => $id]);
        return $this->respond(['status' => 'success', 'message' => lang('Backend.deleteSuccess')]);
    }

    // ─────────── TOGGLE ───────────

    public function toggle(int $id)
    {
        $widget = $this->commonModel->selectOne('dashboard_widgets', ['id' => $id]);
        if (!$widget) return $this->respond(['status' => 'error', 'message' => lang('Backend.recordNotFound')], 404);

        $this->commonModel->edit('dashboard_widgets', ['is_active' => $widget->is_active ? 0 : 1], ['id' => $id]);
        return $this->respond(['status' => 'success', 'message' => lang('Backend.toggleSuccess')]);
    }

    // ─────────── SAVE LAYOUT ───────────

    public function saveLayout()
    {
        $userId = auth()->user()->id;

        // Support both form-data and JSON body
        $layout = $this->request->getPost('layout');

        if (empty($layout)) {
            $json = $this->request->getJSON(true);
            $layout = $json['layout'] ?? null;
        }

        if (is_string($layout)) {
            $layout = json_decode($layout, true);
        }

        if (is_array($layout) && !empty($layout)) {
            $this->widgetService->saveLayout($userId, $layout);
            return $this->respond(['status' => 'success', 'message' => lang('DashboardWidgets.layoutSaved')]);
        }

        return $this->respond(['status' => 'error', 'message' => lang('DashboardWidgets.invalidLayoutData')], 422);
    }

    // ─────────── WIDGET DATA (AJAX) ───────────

    public function widgetData(string $slug)
    {
        $data = $this->widgetService->getWidgetData($slug);
        return $this->respond($data);
    }

    // ─────────── TOGGLE VISIBILITY (per user) ───────────

    public function toggleVisibility(int $widgetId)
    {
        $userId    = auth()->user()->id;
        $isVisible = $this->widgetService->toggleWidgetVisibility($userId, $widgetId);

        return $this->respond([
            'status'  => 'success',
            'visible' => $isVisible,
            'message' => lang('DashboardWidgets.visibilityToggled'),
        ]);
    }

    // ─────────── AVAILABLE WIDGETS (for modal) ───────────

    public function availableWidgets()
    {
        $userId  = auth()->user()->id;
        $widgets = $this->widgetService->getAvailableWidgets($userId);

        return $this->respond(['widgets' => $widgets]);
    }

    // ─────────── SEED ───────────

    public function seed()
    {
        $count = $this->widgetService->seedDefaults();
        return redirect()->to(site_url('backend/dashboard-widgets'))->with('message', lang('DashboardWidgets.seeded', [$count]));
    }

    // ─────────── HELPERS ───────────

    protected function getPostData(): array
    {
        return [
            'slug'            => url_title(trim($this->request->getPost('slug') ?? ''), '-', true),
            'title'           => trim($this->request->getPost('title') ?? ''),
            'description'     => trim($this->request->getPost('description') ?? ''),
            'type'            => $this->request->getPost('type') ?? 'stat',
            'icon'            => trim($this->request->getPost('icon') ?? 'fas fa-chart-bar'),
            'color'           => $this->request->getPost('color') ?? 'primary',
            'data_source'     => trim($this->request->getPost('data_source') ?? ''),
            'default_size'    => $this->request->getPost('default_size') ?? 'col-lg-3',
            'refresh_seconds' => max(0, (int) ($this->request->getPost('refresh_seconds') ?? 0)),
            'is_active'       => (int) ($this->request->getPost('is_active') ?? 1),
        ];
    }

    protected function validateWidget(array $data, ?int $excludeId = null): array
    {
        $valRules = [
            'slug'  => ['label' => 'Slug',  'rules' => 'required|regex_match[/^[a-z0-9]+(?:-[a-z0-9]+)*$/]'],
            'title' => ['label' => lang('Backend.title'), 'rules' => 'required|regex_match[/^[^<>{}=]+$/u]'],
        ];

        $valMessages = [
            'slug'  => ['required' => lang('Backend.slugRequired'),  'regex_match' => lang('Backend.slugRequired')],
            'title' => ['required' => lang('Backend.titleRequired'), 'regex_match' => lang('Backend.titleRequired')],
        ];

        $validation = \Config\Services::validation();
        $validation->setRules($valRules, $valMessages);

        if (!$validation->run($data)) {
            return $validation->getErrors();
        }

        $errors = [];
        // Unique slug check
        if (!empty($data['slug'])) {
            $existing = $this->commonModel->selectOne('dashboard_widgets', ['slug' => $data['slug']]);
            if ($existing && (!$excludeId || $existing->id != $excludeId)) {
                $errors['slug'] = lang('DashboardWidgets.slugAlreadyUsed');
            }
        }

        return $errors;
    }
}
