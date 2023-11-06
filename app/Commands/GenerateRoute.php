<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class GenerateRoute extends BaseCommand
{
    /**
     * The Command's Group
     *
     * @var string
     */
    protected $group = 'Ci4MS';

    /**
     * The Command's Name
     *
     * @var string
     */
    protected $name = 'create:route';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Create a default route';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'create:route';

    /**
     * The Command's Arguments
     *
     * @var array
     */
    protected $arguments = [];

    /**
     * The Command's Options
     *
     * @var array
     */
    protected $options = [];

    /**
     * Actually execute a command.
     *
     * @param array $params
     */
    public function run(array $params)
    {
        $file = APPPATH . 'Commands/Views/routes.tpl.php';
        $content = file_get_contents($file);

        // İstenen değişiklik - "<@" ifadesini "<" ile değiştir
        $content = str_replace('<@', '<?', $content);

        if (!write_file(APPPATH . 'Config/Routes.php', $content)) {
            CLI::error('Could not create Routes.php file.','red');
            return;
        }

        CLI::write('Routes.php file created successfully.','green');
    }
}
