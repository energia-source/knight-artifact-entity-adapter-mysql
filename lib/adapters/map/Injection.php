<?PHP

namespace MySQL\adapters\map;

use ReflectionClass;

use Knight\armor\CustomException;

use MySQL\Statement;
use MySQL\operations\Select;
use MySQL\operations\select\Alias;
use MySQL\adapters\map\common\Bind;

final class Injection extends Bind
{
    const BIND_NAME = '\\#name\\#';

    const FIELD = 0x258;
    const WHERE = 0x2bc;
    const CLEAN = 0x320;

    protected $columns = []; // (array)

    public static function getConstants(string $instance) : array
    {
        $constants = new ReflectionClass($instance);
        $constants = $constants->getConstants();
        return $constants;
    }

    public function addColumn(string $field_name, string $value, ?string ...$data) :  self
    {
        $bound = $this->getBound(...$data);
        $value_injection_variables_expression = chr(47) . static::BIND_VARIABLE_PREFIX . chr(40) . '\d+' . chr(41) . chr(47);
        $value_injection_variables = preg_replace_callback($value_injection_variables_expression, function ($match) use ($bound) {
            return array_key_exists($match[1], $bound) ? chr(58) . $bound[$match[1]] : $match[0];
        }, $value);

        $this->columns[$field_name] = $value_injection_variables;

        return $this;
    }

    public function addColumnSelect(string $field_name, Select $select, bool $json = false) : self
    {
        $statement_connection = $select->getConnection();
        $statement = new Statement($statement_connection);
        $statement->append('SELECT');

        if (true !== $json) {
            $statement->append(chr(42));
        } else {
            $table_column = $select->getTable();
            $table_column = $select->getAllColumns($table_column, true);
            $table_column = array_keys($table_column);

            $table_column_injection = $select->getInjection()->getColumns();
            $table_column_injection = array_keys($table_column_injection);
            $table_column = array_merge($table_column, $table_column_injection);
            $table_column = array_unique($table_column, SORT_STRING);
            $table_column = preg_filter('/^.*$/', chr(34) . '$0' . chr(34) . ',' . chr(32) . chr(96) . '$0' . chr(96), $table_column);
            $table_column = implode(chr(44) . chr(32), $table_column);
            $table_column = 'JSON_OBJECT' . chr(40) . $table_column . chr(41);

            if (1 !== $select->getLimit()->get()) $table_column = 'JSON_ARRAYAGG' . chr(40) . $table_column . chr(41);

            $statement->append($table_column);
        }

        $statement->append('FROM');

        $statement_from = $select->getStatement();
        $this->pushFromBind($statement_from);

        $statement_from = $statement_from->get();
        $statement_from = chr(40) . $statement_from . chr(41);
        $statement->append($statement_from);
        $statement->append('AS');
        $statement_from = $select->getFrom();
        $statement->append($statement_from);

        $this->columns[$field_name] = $statement->get();
        $this->columns[$field_name] = chr(40) . $this->columns[$field_name] . chr(41);

        return $this;
    }

    public function getColumns() : array
    {
        return $this->columns;
    }

    public function getColumnsParsed(int $type, ?string $prefix = null) : array
    {
        $list = [];
        $bind = $this->getBind();
        $columns = $this->getColumns();

        $curerrent_contants = static::getConstants(static::class);
        if (false === in_array($type, $curerrent_contants)) throw new CustomException('developer/database/mysql/injection/column/parse/type');

        foreach ($columns as $key => $value) {
            $name = chr(96) . $key . chr(96);
            if (null !== $prefix) $name = chr(96) . $prefix . chr(96) . '.' . $name;

            $list[$key] = $replace = preg_replace(chr(47) . static::BIND_NAME . chr(47), $name, $value);
            $list[$key] = preg_replace('/^\[(.*)\]$/', '$1', $list[$key]);
            $expression = static::WHERE !== $type ? $name . '$' : chr(40) . '^\(?' . $name . chr(41) . '|' . chr(40) . '^\[.*\]$' . chr(41);
            if (static::CLEAN === $type
                || preg_match('/' . $expression . '/', $replace)) continue;

            $list[$key] = static::FIELD !== $type ? $name . chr(32) . '=' . chr(32) . $list[$key] : $list[$key] . chr(32) . 'AS' . chr(32) . $name;
        }

        return $list;
    }
}