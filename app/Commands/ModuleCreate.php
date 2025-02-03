<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ModuleCreate extends BaseCommand
{
    protected $group       = 'Ci4MS';
    protected $name        = 'module:create';
    protected $description = 'Yeni bir modül oluşturur.';

    public function run(array $params)
    {
        $moduleName = $params[0] ?? null;

        if (!$moduleName) {
            CLI::error('Lütfen bir modül adı belirtin.');
            return;
        }

        $moduleName = ucfirst($moduleName); // İlk harfi büyük yap
        $modulePath = ROOTPATH . "modules/{$moduleName}";

        if (is_dir($modulePath)) {
            CLI::error("{$moduleName} modülü zaten mevcut.");
            return;
        }

        // Modül klasörlerini oluştur
        mkdir($modulePath, 0755, true);
        mkdir("{$modulePath}/Controllers", 0755, true);
        mkdir("{$modulePath}/Models", 0755, true);
        mkdir("{$modulePath}/Views", 0755, true);
        mkdir("{$modulePath}/Config", 0755, true);

        // Örnek dosyaları oluştur
        file_put_contents("{$modulePath}/Controllers/{$moduleName}Controller.php", $this->getControllerTemplate($moduleName));
        file_put_contents("{$modulePath}/Models/{$moduleName}Model.php", $this->getModelTemplate($moduleName));
        file_put_contents("{$modulePath}/Config/Routes.php", $this->getRoutesTemplate($moduleName));

        CLI::write("{$moduleName} modülü başarıyla oluşturuldu!", 'green');
    }

    private function getControllerTemplate($moduleName)
    {
        return <<<EOD
<?php

namespace Modules\\{$moduleName}\\Controllers;

use App\Controllers\BaseController;

class {$moduleName}Controller extends BaseController
{
    public function index()
    {
        return view('Modules/{$moduleName}/Views/index');
    }
}
EOD;
    }

    private function getModelTemplate($moduleName)
    {
        return <<<EOD
<?php

namespace Modules\\{$moduleName}\\Models;

use CodeIgniter\Model;

class {$moduleName}Model extends Model
{
    protected \$table = '{$moduleName}';
    protected \$primaryKey = 'id';
    protected \$allowedFields = ['name', 'description'];
}
EOD;
    }

    private function getRoutesTemplate($moduleName)
    {
        return <<<EOD
<?php

\$routes->group('{$moduleName}', ['namespace' => 'Modules\\{$moduleName}\\Controllers'], function (\$routes) {
    \$routes->get('/', '{$moduleName}Controller::index');
});
EOD;
    }
}
