<?php

namespace Config;

use CodeIgniter\Config\Filters as BaseFilters;
use CodeIgniter\Filters\Cors;
use CodeIgniter\Filters\CSRF;
use CodeIgniter\Filters\DebugToolbar;
use CodeIgniter\Filters\ForceHTTPS;
use CodeIgniter\Filters\Honeypot;
use CodeIgniter\Filters\InvalidChars;
use CodeIgniter\Filters\PageCache;
use CodeIgniter\Filters\PerformanceMetrics;
use CodeIgniter\Filters\SecureHeaders;

class Filters extends BaseFilters
{
    /**
     * Configures aliases for Filter classes to
     * make reading things nicer and simpler.
     *
     * @var array<string, class-string|list<class-string>>
     *
     * [filter_name => classname]
     * or [filter_name => [classname1, classname2, ...]]
     */
    public array $aliases = [
        'csrf'          => CSRF::class,
        'toolbar'       => DebugToolbar::class,
        'honeypot'      => Honeypot::class,
        'invalidchars'  => InvalidChars::class,
        'secureheaders' => SecureHeaders::class,
        'cors'          => Cors::class,
        'forcehttps'    => ForceHTTPS::class,
        'pagecache'     => PageCache::class,
        'performance'   => PerformanceMetrics::class,
    ];

    /**
     * List of special required filters.
     *
     * The filters listed here are special. They are applied before and after
     * other kinds of filters, and always applied even if a route does not exist.
     *
     * Filters set by default provide framework functionality. If removed,
     * those functions will no longer work.
     *
     * @see https://codeigniter.com/user_guide/incoming/filters.html#provided-filters
     *
     * @var array{before: list<string>, after: list<string>}
     */
    public array $required = [
        'before' => [
            'forcehttps', // Force Global Secure Requests
            'pagecache',  // Web Page Caching
        ],
        'after' => [
            'pagecache',   // Web Page Caching
            'performance', // Performance Metrics
            'toolbar',     // Debug Toolbar
        ],
    ];

    /**
     * List of filter aliases that are always
     * applied before and after every request.
     *
     * @var array<string, array<string, array<string, string>>>|array<string, list<string>>
     */
    public array $globals = [
        'before' => [
            'honeypot',
            'csrf' => ['except' => ['newComment', 'repliesComment', 'loadMoreComments', 'commentCaptcha']],
            // 'invalidchars',
        ],
        'after' => [
            'toolbar'
            // 'honeypot',
            // 'secureheaders',
        ],
    ];

    /**
     * List of filter aliases that works on a
     * particular HTTP method (GET, POST, etc.).
     *
     * Example:
     * 'POST' => ['foo', 'bar']
     *
     * If you use this, you should disable auto-routing because auto-routing
     * permits any HTTP method to access a controller. Accessing the controller
     * with a method you don't expect could bypass the filter.
     *
     * @var array<string, list<string>>
     */
    public array $methods = [];

    /**
     * List of filter aliases that should run on any
     * before or after URI patterns.
     *
     * Example:
     * 'isLoggedIn' => ['before' => ['account/*', 'profiles/*']]
     *
     * @var array<string, array<string, list<string>>>
     */
    public array $filters = [];

    public function __construct()
    {
        parent::__construct();

        if (empty(cache('settings'))) {
            $commonModel = new \ci4commonmodel\Models\CommonModel();
            $settings = $commonModel->lists('settings');
            $set = [];
            $formatRules = new \CodeIgniter\Validation\FormatRules();
            foreach ($settings as $setting) {
                if ($formatRules->valid_json($setting->content) === true)
                    $set[$setting->option] = (object)json_decode($setting->content, JSON_UNESCAPED_UNICODE);
                else
                    $set[$setting->option] = $setting->content;
            }
            cache()->save('settings', $set, 86400);
            $settings = (object)$set;
        } else $settings = (object)cache('settings');

        // Filtre klasörünü tara
        $this->loadDynamicFilters([
            APPPATH . 'Filters',
            ROOTPATH . 'modules/Backend/Filters',
        ]);

        $this->loadConfig();
    }

    /**
     * Loads filters from specific folders and adds them to the aliases variable.
     *
     * @param array $directories
     */
    private function loadDynamicFilters(array $directories): void
    {
        foreach ($directories as $directory) {
            if (is_dir($directory)) {
                foreach (glob("$directory/*.php") as $file) {
                    $className = pathinfo($file, PATHINFO_FILENAME);
                    $classNamespace = $this->buildNamespace($directory, $className);

                    if (class_exists($classNamespace))
                        $this->aliases[lcfirst($className)] = $classNamespace;
                }
            }
        }
    }

    /**
     * Automatically determines the namespace from a directory and class name.
     *
     * @param string $directory
     * @param string $className
     * @return string
     */
    private function buildNamespace(string $directory, string $className): string
    {
        $relativePath = trim(str_replace([realpath(ROOTPATH), DIRECTORY_SEPARATOR], ['', '\\'], realpath($directory)), '\\');
        return ucfirst("$relativePath\\$className");
    }

    /**
     * Merges CSRF exceptions.
     *
     * @param array $csrfExcept
     */
    private function mergeCsrfExcept(array $csrfExcept): void
    {
        $this->globals['before']['csrf']['except'] = array_merge(
            $this->globals['before']['csrf']['except'],
            $csrfExcept
        );
    }

    /**
     * Loads the theme configuration if available.
     */
    private function loadConfig(): void
    {
        $backendFilters = new \Modules\Backend\Config\BackendConfig();
        $this->mergeCsrfExcept($backendFilters->csrfExcept);
        $this->filters = array_merge($this->filters, $backendFilters->filters);
        $settings = (object) cache('settings');
        $themeConfigPath = APPPATH . 'Config/templates/' . $settings->templateInfos->path . 'ThemeConfig.php';

        if (file_exists($themeConfigPath) && is_file($themeConfigPath)) {
            $className = '\\Config\\templates\\' . $settings->templateInfos->path;
            $themeConfig = new $className();

            if (!empty($themeConfig->csrfExcept['before']['csrf']['except'])) {
                $this->mergeCsrfExcept($themeConfig->csrfExcept['before']['csrf']['except']);
            }

            if (!empty($themeConfig->filters)) {
                $this->filters = array_merge($this->filters, $themeConfig->filters);
            }
        }
    }
}
