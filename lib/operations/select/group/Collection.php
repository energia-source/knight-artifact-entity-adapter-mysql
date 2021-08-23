<?PHP

namespace MySQL\operations\select\group;

use Knight\armor\CustomException;

use MySQL\entity\Table;
use MySQL\operations\common\Option;
use MySQL\operations\Select;

class Collection extends Option
{
    protected $fields = []; // (array)

    public function __construct(Table $table, string ...$fields)
    {
        parent::__construct($table);
        $this->setFields(...$fields);
    }

    public function setFields(string ...$fields) : self
    {
        $table = $this->getTable()->getAllFieldsKeys();
        $fields_unique = array_intersect($fields, $table);
        $fields_unique = array_unique($fields_unique, SORT_STRING);
        if (empty($fields_unique)) throw new CustomException('developer/database/mysql/operations/select/group/collection/field');
        $this->fields = $fields_unique;
        return $this;
    }

    public function getFields() : array
    {
        return $this->fields;
    }

    public function elaborate(?Select $select) : array
    {
        $fields = $this->getFields();
        $fields = array_fill_keys($fields, null);
        if (null === $select) return $fields;

        $table = $this->getTable();
        $table_columns = $select->getAllColumns($table);
        $table_columns = array_intersect_key($table_columns, $fields);
        return $table_columns;
    }
}