<@?php namespace Modules\Backend\Models;

use ci4mongodblibrary\Libraries\Mongo;

class {class}
{
    protected $table;
    protected $m;
    protected $databaseGroup = 'default';

    public function __construct()
    {
        $this->m = new Mongo($this->databaseGroup);
        $this->table='{table}';
    }

    public function (){
        //...
    }
}
