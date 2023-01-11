<?php

namespace Sdon2\Laravel;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

abstract class DynamicModel extends Model
{
    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the model's ID is auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = true;

    /**
     * The data type of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'integer';

    protected $routeKeyName = 'id';

    public function getRouteKeyName()
    {
        return $this->routeKeyName;
    }

    protected $guarded = [];

    /**
     * important! - attributes need to be passed,
     * cause of new instance generation inside laravel
     *
     * @param $attributes
     * @throws Exception
     */
    public function __construct($table, $attributes = [])
    {
        parent::__construct($attributes);

        $this->table = $table;

        if (!Schema::hasTable($this->table)) {
            throw new Exception("The table you provided ({$this->table}) to the DynamicModel does not exists! Please create it first!");
        }

        $connection = Schema::getConnection();
        $table = $connection->getDoctrineSchemaManager()->listTableDetails($this->table);
        $primaryKeyName = $table->getPrimaryKey()->getColumns()[0];
        $primaryColumn = $connection->getDoctrineColumn($this->table, $primaryKeyName);

        $this->primaryKey = $primaryColumn->getName();
        $this->incrementing = $primaryColumn->getAutoincrement();
        $this->keyType = ($primaryColumn->getType()->getName() === 'string') ? 'string' : 'integer';
        $this->routeKeyName = $primaryColumn->getName();
    }
}
