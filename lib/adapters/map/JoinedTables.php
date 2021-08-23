<?PHP

namespace MySQL\adapters\map;

use MySQL\Initiator;
use MySQL\entity\Table;

final class JoinedTables
{
    protected $tables = []; // (array) Table

    public function __clone()
    {
        $variables = get_object_vars($this);
        $variables = array_keys($variables);
        $variables_glue = [];
        foreach ($variables as $name) array_push($variables_glue, array(&$this->$name));
        array_walk_recursive($variables_glue, function (&$item, $name) {
            if (false === is_object($item)) return;
            $clone = clone $item;
            if ($clone instanceof Table) $clone->cloneHashEntity($item);
            $item = $clone;
        });
    }

    public function pushTables(Table ...$tables) : int
    {
        foreach ($tables as $table)
            if (false === $table->hasAdapter())
                $table->useAdapter(Initiator::ADAPTER_NAME, Initiator::getNamespaceName());
        return array_push($this->tables, ...$tables);
    }

    public function getTablesByName(string ...$names) : array
    {
        $tables = $this->getTables();
        $tables = array_filter($tables, function (Table $table) use ($names) {
            return in_array($table->getReflection()->getShortName(), $names);
        });
        return $tables;
    }

    public function getTables() : array
    {
        return $this->tables;
    }
}