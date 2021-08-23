<?PHP

namespace MySQL\operations\select;

use MySQL\adapters\map\common\Bind;
use MySQL\operations\Select;
use MySQL\operations\select\group\Collection;

class Group extends Bind
{
    protected $having;           // (string)
    protected $collections = []; // (array) Collection

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

    public function setHaving(?string $having, string ...$data) : self
    {
        $bound = $this->resetBind()->getBound(...$data);

        $having_injection_variables_expression = chr(47) . static::BIND_VARIABLE_PREFIX . chr(40) . '\d+' . chr(41) . chr(47);
        $having_injection_variables = preg_replace_callback($having_injection_variables_expression, function ($match) use ($bound) {
            return array_key_exists($match[1], $bound) ? chr(58) . $bound[$match[1]] : $match[0];
        }, $having);

        $this->having = $having_injection_variables;
        return $this;
    }

    public function getHaving() :? string
    {
        return $this->having;
    }

    public function setCollections(Collection ...$collections) : self
    {
        $this->collections = $collections;
        return $this;
    }

    public function addCollections(Collection ...$collections) : self
    {
        array_push($this->collections, ...$collections);
        return $this;
    }

    public function getColumns(?Select $select = null, bool $name = false) : array
    {
        $collections = $this->getCollections();
        $collections_condition = array_map(function (Collection $collection) use ($select) {
            return $collection->elaborate($select);
        }, $collections);

        if (empty($collections_condition)) return $collections_condition;
        if (false === $name) $collections_condition = array_map('array_values', $collections_condition);

        $collections_condition = call_user_func_array('array_merge', $collections_condition);
        return false === $name ? $collections_condition : array_keys($collections_condition);
    }

    protected function getCollections() : array
    {
        return $this->collections;
    }
}