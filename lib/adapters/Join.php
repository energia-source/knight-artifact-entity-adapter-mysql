<?PHP

namespace MySQL\adapters;

use Entity\Adapter;

use MySQL\entity\Table;
use MySQL\adapters\map\Injection;
use MySQL\adapters\map\JoinedTables;

class Join extends Adapter
{
    protected $injection; // Injection
    protected $tables;    // JoinedTables

    public function __construct()
    {
        $this->setInjection(null, new Injection());
        $this->setJoinedTables(null, new JoinedTables());
    }

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

    public function setInjection(?Table $table, Injection $injection) : self
    {
        $this->injection = $injection;
        return $this;
    }

    public function setJoinedTables(?Table $table, JoinedTables $tables) : self
    {
        $this->tables = $tables;
        return $this;
    }

    public function getInjection() : Injection
    {
        return $this->injection;
    }

    public function getJoinedTables() : JoinedTables
    {
        return $this->tables;
    }

    public function join(?Table $table, Table ...$tables) : Table
    {
        $tables_last = array_pop($tables);
        if (null === $tables_last) return $this->getReference();

        $this->getJoinedTables()->pushTables($tables_last);
        if (empty($tables)) return $tables_last;

        $injection = $tables_last->getInjection();
        $container = $tables_last->getJoinedTables();
        foreach ($tables as $item) {
            $this->getJoinedTables()->pushTables($item);
            $item->setInjection($injection);
            $item->setJoinedTables($container);
        }

        return $tables_last;
    }

    public function getFieldPath(?Table $table, string $name) : string
    {
        $table_name = $table->getCollectionName();
        $table_name = chr(96) . $table_name . chr(96);
        $table_field = $table->getField($name);
        $table_field_name = $table_field->getName();
        $table_field_name = chr(96) . $table_field_name . chr(96);
        $table_field_name = $table_name . chr(46) . $table_field_name;
        return $table_field_name;
    }
}