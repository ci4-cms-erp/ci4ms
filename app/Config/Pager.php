<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use Ratchet\App;

class Pager extends BaseConfig
{
    /**
     * --------------------------------------------------------------------------
     * Templates
     * --------------------------------------------------------------------------
     *
     * Pagination links are rendered out using views to configure their
     * appearance. This array contains aliases and the view names to
     * use when rendering the links.
     *
     * Within each view, the Pager object will be available as $pager,
     * and the desired group as $pagerGroup;
     *
     * @var array<string, string>
     */
    public array $templates = [
        'default_full'   => 'CodeIgniter\Pager\Views\default_full',
        'default_simple' => 'CodeIgniter\Pager\Views\default_simple',
        'default_head'   => 'CodeIgniter\Pager\Views\default_head',
    ];

    /**
     * --------------------------------------------------------------------------
     * Items Per Page
     * --------------------------------------------------------------------------
     *
     * The default number of results shown in a single page.
     */
    public int $perPage = 20;

    public function __construct()
    {
        $this->loadThemePaginationTemplates();
    }

    private function loadThemePaginationTemplates()
    {
        $settings = (object)cache('settings');
        $themePath = APPPATH . "Views" . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR . "{$settings->templateInfos->path}" . DIRECTORY_SEPARATOR;
        $paginationTemplates = glob($themePath . 'pagination_*.php');
        if (!empty($paginationTemplates)) {
            foreach ($paginationTemplates as $template) {
                if (file_exists($template)) {
                    $templateName = basename($template, '.php');
                    $this->templates[$settings->templateInfos->path] = "App" . DIRECTORY_SEPARATOR . "Views" . DIRECTORY_SEPARATOR . "templates" . DIRECTORY_SEPARATOR . "{$settings->templateInfos->path}" . DIRECTORY_SEPARATOR . "{$templateName}";
                }
            }
        }
    }
}
