<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Commands\Ci4msTrait;

class Adminmodel extends BaseCommand
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
    protected $name = 'make:amodel';

    /**
     * The Command's Description
     *
     * @var string
     */
    protected $description = 'Generates a new mongodb model file.';

    /**
     * The Command's Usage
     *
     * @var string
     */
    protected $usage = 'make:amodel <name> [options]';

    /**
     * The Command's Arguments
     *
     * @var array
     */
    protected $arguments = ['name' => 'The model class name.'];

    /**
     * The Command's Options
     *
     * @var array
     */
    protected $options = ['--table'     => 'Supply a table name. Default: "the lowercased plural of the class name".',
        '--dbgroup'   => 'Database group to use. Default: "default".',
        '--return'    => 'Return type, Options: [array, object, entity]. Default: "array".',
        '--namespace' => 'Set root namespace. Default: "APP_NAMESPACE".',
        '--suffix'    => 'Append the component title to the class name (e.g. User => UserModel).',
        '--force'     => 'Force overwrite existing file.'];

    /**
     * Actually execute a command.
     *
     * @param array $params
     */
    public function run(array $params)
    {
        $this->component = 'Model';
        $this->directory = '..\modules\Backend\Models';
        $this->template  = 'model.tpl.php';

        $this->classNameLang = 'CLI.generator.className.model';
        $this->execute($params);
    }
    /**
     * Prepare options and do the necessary replacements.
     */
    protected function prepare(string $class): string
    {
        $table   = $this->getOption('table');
        $dbGroup = $this->getOption('dbgroup');
        $return  = $this->getOption('return');

        $baseClass = class_basename($class);

        if (preg_match('/^(\S+)Model$/i', $baseClass, $match) === 1) {
            $baseClass = $match[1];
        }

        $table   = is_string($table) ? $table : plural(strtolower($baseClass));
        $dbGroup = is_string($dbGroup) ? $dbGroup : 'default';
        $return  = is_string($return) ? $return : 'array';

        return $this->parseTemplate($class, ['{table}', '{dbGroup}', '{return}','<@'], [$table, $dbGroup, $return,'<']);
    }
}
