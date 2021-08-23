<?PHP

namespace MySQL;

use Closure;
use ReflectionClass;

use Knight\armor\CustomException;

use MySQL\Connection;
use MySQL\entity\Table;
use MySQL\operations\common\Base;

final class Initiator
{
    const OPERATIONS = 'operations';
    const ADAPTER_NAME = 'Join';

    protected $connection; // Connection
    protected $table;      // Table

    protected function __construct() {}

    public static function getNamespaceName() : string
    {
        $class = new ReflectionClass(static::class);
        return $class->getNamespaceName();
    }

    public function __clone()
    {
        $variables = get_object_vars($this);
        $variables = array_keys($variables);
        $variables_remove = [];
        array_push($variables_remove, 'connection');
        $variables = array_diff($variables, $variables_remove);
        $variables_glue = [];
        foreach ($variables as $name) array_push($variables_glue, array(&$this->$name));
        array_walk_recursive($variables_glue, function (&$item, $name) {
            if (false === is_object($item)) return;
            $clone = clone $item;
            if ($clone instanceof Table) $clone->cloneHashEntity($item);
            $item = $clone;
        });
    }

    public function __call(string $method, array $arguments) : Base
    {
        $class_name = strtolower($method);
        $class_name = ucfirst($class_name);

        $instance = __namespace__ . '\\' . static::OPERATIONS . '\\' . $class_name;
        $instance = new $instance($this, $arguments);
        if ($instance instanceof Base) return $instance;

        throw new CustomException('developer/database/call/operation/missmatch');
    }

    public static function start(?Connection $connection, Table $table, Closure $callable = null) : self
    {
        $instance = new static();
        $instance->setConnection($connection);
        $instance->table = $table;

        if (false === $instance->table->hasAdapter()) $instance->table->useAdapter(static::ADAPTER_NAME, __namespace__);

        return $instance;
    }

    public function getTable() : Table
    {
        return $this->table;
    }

    public function getConnection() :? Connection
    {
        return $this->connection;
    }

    protected function setConnection(?Connection $connection) : void
    {
        $this->connection = $connection;
    }
}