<?PHP

namespace MySQL\operations\common\features\parser;

use Knight\armor\CustomException;

use MySQL\Statement;
use MySQL\entity\Table;
use MySQL\adapters\map\common\Bind;

class Query extends Bind
{
    protected $statement;   // Statement
    protected $table;       // Table

    public function __clone()
    {
        $variables = get_object_vars($this);
        $variables = array_keys($variables);
        $variables_remove = [];
        array_push($variables_remove, 'statement');
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

    final public function __construct(Table $table)
    {
        $this->setTable($table);
    }

    public function getTable() : Table
    {
        return $this->table;
    }

    public function setStatement(Statement $statement) : self
    {
        $this->statement = $statement;
        return $this;
    }

    public function getStatement() :? Statement
    {
        return $this->statement;
    }

    protected function setTable(Table $table) : void
    {
        $this->table = $table;
    }
}