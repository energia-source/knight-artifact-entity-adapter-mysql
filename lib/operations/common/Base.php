<?PHP

namespace MySQL\operations\common;

use PDOStatement;

use MySQL\Initiator;
use MySQL\operations\common\features\Parser;

interface Run
{
    public function run() :? PDOStatement;
}

abstract class Base implements Run
{
    use Parser;

    protected $core; // Initiator

    final public function __clone()
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

    final public function __construct(Initiator $core, array $arguments)
    {
        $this->setCore($core);
        if (method_exists($this, 'initialize')) $this->initialize(...$arguments);
    }

    final protected function setCore(Initiator $core) : void
    {
        $this->core = $core;
    }

    final protected function getCore() : Initiator
    {
        return $this->core;
    }
}