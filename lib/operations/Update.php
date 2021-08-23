<?PHP

namespace MySQL\operations;

use MySQL\Statement;
use MySQL\entity\Table;
use MySQL\adapters\map\Injection;
use MySQL\operations\common\Handling;

class Update extends Handling
{
    public function getQueries() : array
    {
        $core = $this->getCore();
        $core_connection = $core->getConnection();

        $skip = $this->getSkip();
        $skip = array_map(function (Table $table) {
            return $table->getHash();
        }, $skip);

        $tables = $core->getTable();
        $tables = static::tables($tables, static::class, ...$skip);
        foreach ($tables as $query) {
            $table = $query->getTable();

            $statement = new Statement($core_connection);
            $statement->append('UPDATE');
            $statement_table = $table->getCollectionName();
            $statement->append(chr(96) . $statement_table . chr(96));

            $table_columns_unique = static::uniqueness($table) ?? array();
            $table_columns_unique_fill = array_fill_keys($table_columns_unique, null);

            $table_injection = $table->getInjection();
            $table_injection_columns_sintax = $table_injection->getColumnsParsed(Injection::CLEAN);
            $table_injection_columns_sintax = array_diff_key($table_injection_columns_sintax, $table_columns_unique_fill);
            array_walk($table_injection_columns_sintax, function (string &$value, string $name) {
                $value = chr(96) . $name . chr(96) . chr(32) . '=' . chr(32) . $value;
            });

            $statement->pushFromBind($table_injection);

            $table_columns = $table->getAllFieldsValues(false, false);
            $table_columns_required = $table->getAllFieldsRequiredName();
            $table_columns_required = array_fill_keys($table_columns_required, null);
            $table_columns = array_intersect_key($table_columns, $table_columns_required);
            $table_columns = array_diff_key($table_columns, $table_columns_unique_fill);
            array_walk($table_columns, function (&$value, string $key) use ($statement) {
                $value = null === $value ? 'NULL' : chr(58) . current($statement->getBound($value));
                $value = chr(96) . $key . chr(96) . chr(32) . chr(61) . chr(32) . $value;
            });

            $table_columns_sintax = $table_injection_columns_sintax + $table_columns;
            $table_columns_sintax = implode(chr(44) . chr(32), $table_columns_sintax);

            $statement->append('SET');
            $statement->append($table_columns_sintax);

            $table_clone = clone $table;
            $table_clone->cloneHashEntity($table);
            $table_clone_fields = $table_clone->getFields();
            foreach ($table_clone_fields as $field) {
                $field_name = $field->getName();
                $field->setProtected(false);
                $field_protected = false === in_array($field_name, $table_columns_unique);
                if (true === $field_protected) $field->setDefault();
                $field->setProtected($field_protected);
            }

            if ($statement_where = $this->where($table_clone)) {
                $statement_where_sintax = $statement_where->get();
                if (0 !== strlen($statement_where_sintax)) {
                    $statement->append('WHERE');
                    $statement->append($statement_where->get());
                    $statement->pushFromBind($statement_where);
                }
            }

            $query->setStatement($statement);
        }

        return $tables;
    }
}