<?PHP

namespace MySQL\operations\select;

use MySQL\entity\Table;
use MySQL\operations\Select;
use MySQL\operations\select\Alias;
use MySQL\operations\common\Option;

class Join extends Option
{
    const RIGHT = 0x830b82;
    const INNER = 0x830c84;
    const LEFT = 0x830a80;

    protected $type;                 // (int)
    protected $conditions = [];      // (array)
    protected $using = false;        // (array)
    protected $alias;                // Alias

    public function __construct(Table $table)
    {
        parent::__construct($table);
        $this->setType(static::INNER);
        $this->setAlias();
    }

    public function setType(int $type) : self
    {
        $constants = static::getConstants(static::class);
        if (false === in_array($type, $constants)) throw new CustomException('developer/database/mysql/operations/select/join/type');
        $this->type = $type;
        return $this;
    }

    public function getType() : int
    {
        return $this->type;
    }

    public function addCondition(string $condition) : self
    {
        array_push($this->conditions, $condition);
        return $this;
    }

    public function getConditions() : array
    {
        return $this->conditions;
    }

    public function getConditionsBuilded() :? string
    {
        $conditions = $this->getConditions();
        $conditions_list = $this->getUsingList();
        if (true === $conditions_list) {
            $conditions = preg_filter('/^.*$/', chr(96) . '$0' . chr(96), $conditions);
            $conditions = implode(chr(44) . chr(32), $conditions);
            $conditions = 'USING' . chr(40) . $conditions . chr(41);
            return $conditions;
        }

        $conditions = implode(chr(32) . 'AND' . chr(32), $conditions);
        $conditions = 'ON' . chr(32) . $conditions;
        return $conditions;
    }

    public function setUsingList(bool $enable = true) : self
    {
        $this->using = $enable;
        return $this;
    }

    public function getUsingList() : bool
    {
        return $this->using;
    }

    public function getAlias() : Alias
    {
        return $this->alias;
    }

    public function getFieldParsed(string $name) : string
    {
        $table_alias = $this->getAlias()->getName();
        $table_alias = chr(96) . $table_alias . chr(96);

        $table = $this->getTable();
        $table_field = $table->getField($name);
        $table_field_name = $table_field->getName();
        $table_field_name = chr(96) . $table_field_name . chr(96);
        $table_field_name = $table_alias . '.' . $table_field_name;

        return $table_field_name;
    }

    protected function setAlias() : void
    {
        $table = $this->getTable();
        $this->alias = new Alias($table);
    }   
}