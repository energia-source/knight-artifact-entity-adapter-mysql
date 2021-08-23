<?PHP

namespace MySQL\operations\select;

use MySQL\entity\Table;

class Alias
{
    protected $table; // Table
    protected $name;  // (string)

    protected static $increment = 0;

    public function __construct(Table $table)
    {
        $this->setTable($table);
        $this->auto();
    }

    public function setName(string $name) : string
    {
        $this->name = $name;
        return $this;
    }

    public function getName() : string
    {
        return $this->name;
    }

    protected function setTable(Table $table) : void
    {
        $this->table = $table;
    }

    protected function auto() : void
    {
        $id = static::$increment++;
        $this->name = chr(97) . (string)$id;
    }
}