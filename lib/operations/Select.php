<?PHP

namespace MySQL\operations;

use PDOStatement;

use Entity\Field;
use Entity\Validation;
use Entity\validations\Matrioska;

use MySQL\Statement;
use MySQL\Connection;
use MySQL\entity\Table;
use MySQL\adapters\map\Injection;
use MySQL\operations\select\Join;
use MySQL\operations\select\Group;
use MySQL\operations\select\Order;
use MySQL\operations\select\Limit;
use MySQL\operations\select\Alias;
use MySQL\operations\common\Base;
use MySQL\operations\common\features\Where;

class Select extends Base
{
    use Where;

    protected $join = []; // (array)
    protected $distinct;  // (bool)
    protected $from;      // Statement
    protected $injection; // Injection
    protected $group;     // Group
    protected $order;     // Order
    protected $limit;     // Limit
    protected $alias;     // Alias

    public static function getTableName(Table $table, ?Alias $alias = null) : string
    {
        $name = $table->getCollectionName();
        if (null !== $alias) $name = $alias->getName();
        $name = chr(96) . $name . chr(96);
        return $name;
    }

    public function pushJoin(Join ...$join) : self
    {
        if (!!$join) array_push($this->join, ...$join);
        return $this;
    }

    public function getJoin() : array
    {
        return $this->join;
    }

    public function findJoin(Table $table) :? Join
    {
        $joins = $this->getJoin();
        $table_hash = $table->getHash();
        foreach ($joins as $item)
            if ($table_hash === $item->getTable()->getHash())
                return $item;
        return null;
    }

    public function setDistinct(bool $distinct = true) : self
    {
        $this->distinct = $distinct;
        return $this;
    }

    public function getInjection() : Injection
    {
        return $this->injection;
    }

    public function getGroup() : Group
    {
        return $this->group;
    }

    public function getOrder() : Order
    {
        return $this->order;
    }

    public function getLimit() : Limit
    {
        return $this->limit;
    }

    public function useAlias(string $name = null) : self
    {
        $table = $this->getTable();
        $this->alias = new Alias($table);
        if (null !== $name) $this->alias->setName($name);
        return $this;
    }

    public function getAlias() :? Alias
    {
        return $this->alias;
    }

    public function setFromStatement(Statement $statement) : self
    {
        $this->from = $statement;
        return $this;
    }

    public function getTable() : Table
    {
        return $this->getCore()->getTable();
    }

    public function getConnection() :? Connection
    {
        return $this->getCore()->getConnection();
    }

    public function getFieldParsed(Table $table, string $name) : string
    {
        $master = $this->getTable();
        if ($table === $master) {
            $table_name = $this->getFrom();
            $field_name = $table_name . '.' . chr(96) . $name . chr(96);
            return $field_name;
        }

        $join = $this->findJoin($table);
        if (null !== $join) return $join->getFieldParsed($name);

        $field_name = $table->getFieldPath($name);
        return $field_name;
    }

    public function getFrom() : string
    {
        return static::getTableName($this->getTable(), $this->getAlias());
    }

    public function getStatement() : Statement
    {
        $statement_connection = $this->getConnection();
        $statement = new Statement($statement_connection);
        $statement->append('SELECT');

        if ($this->getDistinct()) $statement->append('DISTINCT');

        $table = $this->getTable();

        $statement_columns_group = $this->getGroup();
        $statement_columns = $this->getAllColumns($table, true, $statement_columns_group);

        array_walk($statement_columns, function (&$value, $key) {
            $value = $value . chr(32) . 'AS' . chr(32) . chr(96) . $key . chr(96);
        });

        $statement_injection = $this->getInjection();
        $statement_injection_columns = $statement_injection->getColumnsParsed(Injection::FIELD);
        if (!!$statement_injection_columns) {
            $statement_columns = array_merge($statement_columns, $statement_injection_columns);
            $statement->pushFromBind($statement_injection);
        }

        $statement_columns = implode(chr(44) . chr(32), $statement_columns);
        $statement->append($statement_columns);

        $statement->append('FROM');
        $statement_from = $this->getFromStatement();
        if (null !== $statement_from) {
            $statement_from_sintax = $statement_from->get();
            $statement_from_sintax = chr(40) . $statement_from_sintax . chr(41);
            $statement->append($statement_from_sintax);
            $statement->append('AS');
            $statement->pushFromBind($statement_from);
            $statement->append($statement_from);
        } else {
            $statement_from = $this->getFrom();
            $statement_table_name = static::getTableName($table);
            $statement->append($statement_table_name);
            if ($statement_table_name !== $statement_from) {
                $statement->append('AS');
                $statement->append($statement_from);
            }
        }

        if (!!$statement_join = $this->join($table)) {
            $statement_join = implode(chr(32), $statement_join);
            $statement->append($statement_join);
        }

        $table_clone = clone $table;
        $table_clone->cloneHashEntity($table);
        $table_clone_fields = $table_clone->getFields();
        foreach ($table_clone_fields as $field) $field->setProtected(false);

        if ($statement_where = $this->where($table_clone)) {
            $statement_where_sintax = $statement_where->get();
            if (0 !== strlen($statement_where_sintax)) {
                $statement->append('WHERE');
                $statement->append($statement_where->get());
                $statement->pushFromBind($statement_where);
            }
        }

        if (!!$statement_group_columns = $statement_columns_group->getColumns($this)) {
            $statement->append('GROUP BY');
            $statement_group_columns_sintax = implode(chr(44) . chr(32), $statement_group_columns);
            $statement->append($statement_group_columns_sintax);

            $statement_columns_group_having = $statement_columns_group->getHaving();
            if (null !== $statement_columns_group_having) {
                $statement->append('HAVING');
                $statement->append($statement_columns_group_having);
            }
        }

        $statement->pushFromBind($statement_columns_group);

        if (!!$statement_order_columns = $this->getOrder()->getColumns($this)) {
            $statement->append('ORDER BY');
            $statement_order_columns_sql = implode(chr(44) . chr(32), $statement_order_columns);
            $statement->append($statement_order_columns_sql);
        }

        $this->shouldLimit($statement);

        return $statement;
    }

    public function run() :? PDOStatement
    {
        $statement = $this->getStatement();
        $statement_response = $statement->execute();
        return $statement_response;
    }

    public function getAllColumns(Table $data, bool $required = false, Group $group = null) : array
    {
        $join = $data->getJoinedTables()->getTables();
        $alias = $this->getAlias();
        $columns_response = static::buildColumns($data, $alias, $required, $group);

        foreach ($join as $table) {
            $alias = $this->findJoin($table);
            if (null !== $alias) $alias = $alias->getAlias();
            $columns_response += static::buildColumns($table, $alias, $required, $group);
            $columns_response += $this->getAllColumns($table, $required, $group);
        }

        return $columns_response;
    }

    protected static function buildColumns(Table $table, ?Alias $alias = null, ?bool $required = false, ?Group $group = null) : array
    {
        $group_columns = $group === null ? [] : $group->getColumns(null, true);

        $table_columns = $table->getAllFieldsKeys();
        $table_columns_files = $table->getAllFieldsFileName();
        $table_columns = array_diff($table_columns, $table_columns_files);
        if (true === $required) $table_columns = array_intersect($table_columns, $table->getAllFieldsRequiredName());

        $table_name = static::getTableName($table, $alias);
        $sql_filter = $table_name . chr(46) . chr(96) . '$0' . chr(96);
        $sql_filter_field = empty($group_columns) ? $sql_filter : 'ANY_VALUE' . chr(40) . $sql_filter . chr(41);
        $sql = preg_filter('/^.*$/', $sql_filter_field, $table_columns);
        $sql = array_combine($table_columns, $sql);

        if (true === $required) $table_columns_files = array_intersect($table_columns_files, $table->getAllFieldsRequiredName());
        $table_columns_files_sintax_base = 'TO_BASE64' . chr(40) . $sql_filter_field . chr(41);
        $table_columns_files_sintax_replace = 'REPLACE' . chr(40) . $table_columns_files_sintax_base . chr(44) . chr(32) . chr(34) . chr(10) . chr(34) . chr(44) . chr(32) . chr(34) . chr(34) . chr(41);
        $table_columns_files_sintax = preg_filter('/^.*$/', $table_columns_files_sintax_replace, $table_columns_files);
        $table_columns_files_sintax = array_combine($table_columns_files, $table_columns_files_sintax);

        $sql = array_merge($sql, $table_columns_files_sintax);

        if ($group_intersect = array_intersect($group_columns, $table_columns)) {
            $group_intersect_sintax = preg_filter('/^.*$/', $sql_filter, $group_intersect);
            $group_intersect_sintax = array_combine($group_intersect, $group_intersect_sintax);
            $sql = array_merge($sql, $group_intersect_sintax);
        }

        return $sql;
    }

    protected function initialize() : void
    {
        $this->setGroup(new Group());
        $this->setOrder(new Order());
        $this->setLimit(new Limit());
        $this->setInjection(new Injection());
    }

    protected function setInjection(Injection $injection) : void
    {
        $this->injection = $injection;
    }

    protected function setGroup(Group $group) : void
    {
        $this->group = $group;
    }

    protected function setOrder(Order $order) : void
    {
        $this->order = $order;
    }

    protected function setLimit(Limit $limit) : void
    {
        $this->limit = $limit;
    }

    protected function getDistinct() : bool
    {
        return $this->distinct === true;
    }

    protected function setJoin(Join ...$join) : void
    {
        $this->join = $join;
    }

    protected function shouldLimit(Statement $statement) : void
    {
        $limit = $this->getLimit();
        $limit_value = $limit->get();
        if (null === $limit_value) return;

        $statement->append('LIMIT');

        $offest = $limit->getOffset();
        if (null !== $offest) $statement->append($offest . chr(44));

        $statement->append($limit_value);
    }

    protected function getFromStatement() :? Statement
    {
        return $this->from;
    }

    protected function join(Table $data) : array
    {
        $response = [];
        $tables = $data->getJoinedTables()->getTables();
        if (null === $tables
            || empty($tables)) return $response;

        $join_constant = Join::getConstants(Join::class);

        foreach ($tables as $table) {

            $join = $this->findJoin($table);

            if (null === $join || empty($join->getConditions())) {
                $table_name = $table->getCollectionName();
                $sql_string = 'NATURAL' . chr(32) . 'JOIN' . chr(32) . chr(96) . $table_name . chr(96);
                array_push($response, $sql_string);
            } else {
                $sql = [];

                $join_type = $join->getType();
                $join_type_constant = array_search($join_type, $join_constant, true);
                if (false !== $join_type_constant
                    && Join::INNER !== $join_type) array_push($sql, $join_type_constant);

                $join_table = $join->getTable()->getCollectionName();
                $join_alias = $join->getAlias()->getName();
                $join_alias = 'JOIN' . chr(32) . chr(96) . $join_table . chr(96) . chr(32) . 'AS' . chr(32) . chr(96) . $join_alias . chr(96);
                array_push($sql, $join_alias);

                $join_conditions = $join->getConditionsBuilded();
                if (null !== $join_conditions) array_push($sql, $join_conditions);
                array_push($response, implode(chr(32), $sql));
            }

            if (!!$table_response = $this->join($table)) array_push($response, ...$table_response);
        }

        return array_unique($response, SORT_REGULAR);
    }
}