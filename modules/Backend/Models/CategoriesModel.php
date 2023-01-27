<?php namespace Modules\Backend\Models;

use ci4mongodblibrary\Libraries\Mongo;
use Config\MongoConfig;

class CategoriesModel
{
    protected $table;
    protected $m;
    protected $databaseGroup = 'default';
    protected $mongoConfig;

    public function __construct()
    {
        $this->m = new Mongo($this->databaseGroup);
        $this->mongoConfig = new MongoConfig();
        $this->table='categories';
    }

    public function list(int $limit,int $skip){
        return $this->m->aggregate($this->table,[
            [
                '$lookup' => [
                    'from' => $this->mongoConfig->dbInfo[$this->databaseGroup]->prefix . $this->table,
                    'localField' => 'parent',
                    'foreignField' => '_id',
                    'as' => 'pivot'
                ]
            ],
            ['$unwind' => ['path' => '$pivot', 'preserveNullAndEmptyArrays' => true]],
            ['$limit'=>$limit],
            ['$skip'=>$skip]
        ])->toArray();
    }
}
