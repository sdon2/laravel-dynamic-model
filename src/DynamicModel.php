<?php

namespace Sdon2\Laravel;

use Exception;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection as BaseCollection;

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

    protected static $dynamicTable;

    /**
     * important! - attributes need to be passed,
     * cause of new instance generation inside laravel
     *
     * @param $attributes
     * @throws Exception
     */
    public function __construct($table, $connection = null, $attributes = [])
    {
        parent::__construct($attributes);

        $this->table = self::$dynamicTable = $table;
        $this->connection = $connection;

        $schema = Schema::connection($this->connection);

        if (!$schema->hasTable($this->table)) {
            throw new Exception("The table you provided ({$this->table}) to the DynamicModel does not exists! Please create it first!");
        }

        $connection = $schema->getConnection();
        $table = $connection->getDoctrineSchemaManager()->listTableDetails($this->table);
        $primaryKeyName = $table->getPrimaryKey()->getColumns()[0];
        $primaryColumn = $connection->getDoctrineColumn($this->table, $primaryKeyName);

        $this->primaryKey = $primaryColumn->getName();
        $this->incrementing = $primaryColumn->getAutoincrement();
        $this->keyType = ($primaryColumn->getType()->getName() === 'string') ? 'string' : 'integer';
        $this->routeKeyName = $primaryColumn->getName();
    }

    public function newInstance($attributes = [], $exists = false)
    {
        // This method just provides a convenient way for us to generate fresh model
        // instances of this current model. It is particularly useful during the
        // hydration of new objects via the Eloquent query builder instances.
        $model = new static(static::$dynamicTable, (array) $attributes);

        $model->exists = $exists;

        $model->setConnection(
            $this->getConnectionName()
        );

        $model->setTable($this->getTable());

        $model->mergeCasts($this->casts);

        return $model;
    }

    public static function on($connection = null)
    {
        // First we will just create a fresh instance of this model, and then we can set the
        // connection on the model so that it is used for the queries we execute, as well
        // as being set on every relation we retrieve without a custom connection name.
        $instance = new static(static::$dynamicTable);

        $instance->setConnection($connection);

        return $instance->newQuery();
    }

    public static function destroy($ids)
    {
        if ($ids instanceof EloquentCollection) {
            $ids = $ids->modelKeys();
        }

        if ($ids instanceof BaseCollection) {
            $ids = $ids->all();
        }

        $ids = is_array($ids) ? $ids : func_get_args();

        if (count($ids) === 0) {
            return 0;
        }

        // We will actually pull the models from the database table and call delete on
        // each of them individually so that their events get fired properly with a
        // correct set of attributes in case the developers wants to check these.
        $key = ($instance = new static(static::$dynamicTable))->getKeyName();

        $count = 0;

        foreach ($instance->whereIn($key, $ids)->get() as $model) {
            if ($model->delete()) {
                $count++;
            }
        }

        return $count;
    }

    public static function query()
    {
        return (new static(static::$dynamicTable))->newQuery();
    }

    public function replicate(array $except = null)
    {
        $defaults = [
            $this->getKeyName(),
            $this->getCreatedAtColumn(),
            $this->getUpdatedAtColumn(),
        ];

        $attributes = Arr::except(
            $this->getAttributes(),
            $except ? array_unique(array_merge($except, $defaults)) : $defaults
        );

        return tap(new static(static::$dynamicTable), function ($instance) use ($attributes) {
            $instance->setRawAttributes($attributes);

            $instance->setRelations($this->relations);

            $instance->fireModelEvent('replicating', false);
        });
    }

    public static function __callStatic($method, $parameters)
    {
        return (new static(static::$dynamicTable))->$method(...$parameters);
    }
}
