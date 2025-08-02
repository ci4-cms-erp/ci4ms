<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;
use CodeIgniter\Validation\StrictRules\CreditCardRules;
use CodeIgniter\Validation\StrictRules\FileRules;
use CodeIgniter\Validation\StrictRules\FormatRules;
use CodeIgniter\Validation\StrictRules\Rules;

class Validation extends BaseConfig
{
    // --------------------------------------------------------------------
    // Setup
    // --------------------------------------------------------------------

    /**
     * Stores the classes that contain the
     * rules that are available.
     *
     * @var list<string>
     */
    public array $ruleSets = [
        Rules::class,
        FormatRules::class,
        FileRules::class,
        CreditCardRules::class
    ];

    /**
     * Specifies the views that are used to display the
     * errors.
     *
     * @var array<string, string>
     */
    public array $templates = [
        'list'   => 'CodeIgniter\Validation\Views\list',
        'single' => 'CodeIgniter\Validation\Views\single'
    ];

    // --------------------------------------------------------------------
    // Rules
    // --------------------------------------------------------------------

    private string $modulesPath = ROOTPATH . 'modules/';
    private string $themesPath = APPPATH . 'Validation/templates/';
    public function __construct()
    {
        parent::__construct();
        $modules = array_filter(scandir($this->modulesPath), function ($module) {
            return !in_array($module, ['.', '..', '.DS_Store']) && is_dir($this->modulesPath . DIRECTORY_SEPARATOR . $module);
        });
        // 1. Modules içindeki Validation klasörlerini tara
        foreach ($modules as $module) {
            $validationDir =  $this->modulesPath . $module . '/Validation';
            if (is_dir($validationDir)) {
                foreach (glob($validationDir . '*.php') as $file) {
                    $className = "\\Modules\\$module\\Validation\\" . basename($file, '.php');
                    if (!in_array($className, $this->ruleSets)) {
                        $className .= '::class';
                        $this->ruleSets[] = $className;
                    }
                }
            }
        }

        // 2. app/Validation/temalar/{tema_adi}/ klasörlerini tara
        if (is_dir($this->themesPath)) {
            $themes = array_filter(scandir($this->themesPath), function ($module) {
                return !in_array($module, ['.', '..', '.DS_Store']) && is_dir($this->themesPath . DIRECTORY_SEPARATOR . $module);
            });
            if (!empty($themes)) {
                $settings = (object) cache('settings');
                foreach ($themes as $theme) {
                    if ($theme === '.' || $theme === '..') continue;
                    $validationDir = $this->themesPath . $settings->templateInfos->path . '/';
                    if (is_dir($validationDir)) {
                        foreach (glob($validationDir . '*.php') as $file) {

                            $className =  "\\App\\Validation\\templates\\" . $settings->templateInfos->path . "\\" . basename($file, '.php');
                            if (!in_array($className, $this->ruleSets) && class_exists($className)) {
                                $this->ruleSets[] = $className;
                            }
                        }
                    }
                }
            }
        }
    }
}
