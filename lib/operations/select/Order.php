<?PHP

namespace MySQL\operations\select;

use MySQL\operations\Select;
use MySQL\operations\select\order\Direction;
use MySQL\operations\select\order\Field;
use MySQL\operations\select\order\base\Column;

class Order
{
    protected $collections = []; // (array) Column extended

    public function __clone()
    {
        $variables = get_object_vars($this);
        $variables = array_keys($variables);
        $variables_glue = [];
        foreach ($variables as $name) array_push($variables_glue, array(&$this->$name));
        array_walk_recursive($variables_glue, function (&$item, $name) {
            if (false === is_object($item)) return;
            $clone = clone $item;
        });
    }

    public function pushDirections(Direction ...$directions) : self
    {
        if (!!$direction) array_push($this->collections, ...$direction);
        return $this;
    }

    public function pushFields(Field ...$field) : self
    {
        if (!!$field) array_push($this->collections, ...$field);
        return $this;
    }

    public function getColumns(?Select $select) : array
    {
        $collections = $this->getCollections();
        $collections_condition = array_map(function (Column $column) use ($select) {
            return $column->elaborate($select);
        }, $collections);
        return $collections_condition;
    }

    public function getCollections() : array
    {
        return $this->collections;
    }
}