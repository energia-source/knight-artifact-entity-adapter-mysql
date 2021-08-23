<?PHP

namespace MySQL\operations\common;

use ReflectionClass;

use MySQL\entity\Table;

abstract class Option
{
    protected $table; // Table

    public static function getConstants(string $instance) : array
    {
        $constants = new ReflectionClass($instance);
        $constants = $constants->getConstants();
        return $constants;
    }
    
    public function __construct(Table $table)
    {
        $this->setTable($table);
    }

    public function getTable() : Table
    {
        return $this->table;
    }

    protected function setTable(Table $table) : void
    {
        $this->table = $table;
    }
}