<?PHP

namespace MySQL\operations\select\order\base;

use MySQL\entity\Table;
use MySQL\operations\common\Option;
use MySQL\operations\Select;

interface Elaborate
{
    public function elaborate(?Select $select) : string;
}

abstract class Column extends Option implements Elaborate
{
    protected $name; // (string)

    public function __construct(Table $table, string $name)
    {
        parent::__construct($table);
        $this->setName($name);
    }

    public function getName() : string
    {
        return $this->name;
    }

    public function getNameElaborate(?Select $select) : string
    {
        $field = $this->getName();
        $field_elaborate = chr(96) . $field. chr(96);
        if (null === $select) return $field;

        $table = $this->getTable();
        $table_columns = $select->getAllColumns($table);
        if (array_key_exists($field, $table_columns)) $field_elaborate = $table_columns[$field];

        $group = $select->getGroup()->getColumns(null, true);
        if (false === in_array($field, $group)) $field_elaborate = 'ANY_VALUE' . chr(40) . $field_elaborate . chr(41);
        return $field_elaborate;
    }

    protected function setName(string $name) : void
    {
        $table = $this->getTable()->getAllFieldsKeys();
        if (false === in_array($name, $table)) throw new CustomException('developer/database/mysql/operations/select/order/column');

        $this->name = $name;
    }
}