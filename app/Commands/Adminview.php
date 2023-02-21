<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Commands\Ci4msTrait;

class Adminview extends BaseCommand
{
    use Ci4msTrait;

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
    protected $name = 'make:abview';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Generates a new admin-lite blank view';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'make:abview [name]';

    /**
     * The Command's Arguments
     *
     * @var array
     */
    protected $arguments = [
        'name' => 'view file name'
    ];

    /**
     * The Command's Options
     *
     * @var array
     */
    protected $options = [
        '--force'     => 'Force overwrite existing file.',
    ];

    /**
     * Actually execute a command.
     *
     * @param array $params
     */
    public function run(array $params)
    {
        $this->component = 'View';
        $this->directory = '../modules/Backend/Views';
        $this->template  = 'abview.tpl.php';
        $this->isUpperLetter=false;

        $this->execute($params);
    }

    protected function prepare(string $class): string
    {
        return $this->parseTemplate(
            $class,['<@'],['<']
        );
    }
}
