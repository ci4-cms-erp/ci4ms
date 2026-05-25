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
        $themeSlug = $settings->templateInfos->path ?? null;
        $themeDir = resolve_template_path(
            APPPATH . 'Views' . DIRECTORY_SEPARATOR . 'templates' . DIRECTORY_SEPARATOR,
            $themeSlug
        );
        if ($themeDir === null) {
            return;
        }
        $paginationTemplates = glob($themeDir . DIRECTORY_SEPARATOR . 'pagination_*.php');

        if (!empty($paginationTemplates)) {
            foreach ($paginationTemplates as $template) {
                $templateName = basename($template, '.php');
                $this->templates[$themeSlug] = "App\\Views\\templates\\" . $themeSlug . "\\" . $templateName;
            }
        }
    }
}
