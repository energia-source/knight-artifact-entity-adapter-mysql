<?PHP

namespace MySQL\operations\common\features;

use ReflectionClass;

use MySQL\Statement;
use MySQL\entity\Table;
use MySQL\adapters\map\Injection;
use MySQL\operations\Select;
use MySQL\operations\select\Join;
use MySQL\operations\common\Base;
use MySQL\operations\select\Alias;

trait Where
{
    protected $tables = []; // (array)
    protected $where;       // Statement

    public function setWhereStatement(Statement $statement) : self
    {
        $this->where = $statement;
        return $this;
    }

    protected function getWhereStatement() :? Statement
    {
        return $this->where;
    }

    protected function singleTableWhere(Table $table, Statement $statement, ?string $alias = null) : string
    {
        $where = [];

        $table_name = $alias ?? $table->getCollectionName();

        $table_protected = $table->getAllFieldsProtectedName();
        $table_protected = array_fill_keys($table_protected, null);

        $table_injection = $table->getInjection();
        $table_injection_columns = $table_injection->getColumnsParsed(Injection::WHERE, $table_name);
        $table_injection_columns = array_diff_key($table_injection_columns, $table_protected);

        $statement->pushFromBind($table_injection);

        $table_values = $table->getAllFieldsValues(true);
        $table_values = array_diff_key($table_values, $table_injection_columns, $table_protected);

        $table_injection_columns = array_values($table_injection_columns);
        array_push($where, ...$table_injection_columns);

        if (null !== $table_name) $table_name = chr(96) . $table_name . chr(96);
        foreach ($table_values as $key => $value) {
            $operator = false !== strpos($value, chr(37)) ? 'LIKE' : chr(61);
            $placeholder = $statement->getBound($value);
            $placeholder = reset($placeholder);
            array_push($where, $table_name . chr(46) . chr(96) . $key . chr(96) . chr(32) . $operator . chr(32) . chr(58) . $placeholder); 
        }

        $tables = $this->getTablesUsingOr();
        $tables = array_map(function (Table $table) {
            return $table->getHash();
        }, $tables);

        $table_hash = $table->getHash();

        $table_where = in_array($table_hash, $tables) ? 'OR' : 'AND';
        $table_where = count($where) > 1 ? implode(chr(32) . $table_where . chr(32), $where) : reset($where);
        if (1 < count($where)
            && in_array($table_hash, $tables)) $table_where = chr(40) . $table_where . chr(41);

        return $table_where;
    }

    public function where(Table $data, string ...$skip) : Statement
    {
        $statement = new Statement();

        $join = $data->getJoinedTables()->getTables();
        $hash = $data->getHash();

        $reflection_name = new ReflectionClass($this);
        $reflection_name = $reflection_name->getName();

        if (false === in_array($hash, $skip)) {
            $alias = null;
            if (Select::class === $reflection_name) $alias = null !== $this->getAlias() ? $this->getAlias()->getName() : null;
            if ($table_where = $this->singleTableWhere($data, $statement, $alias)) $statement->append($table_where);
        }

        if (null !== $join && false === empty($join) && Select::class === $reflection_name) {
            $clauses = [];

            foreach ($join as $table) {
                $table_hash = $table->getHash();
                if (true === in_array($table_hash, $skip)) continue;

                array_unshift($skip, $table_hash);

                $alias = null;
                $joins = $this->findJoin($table);
                if (Select::class === $reflection_name && null !== $joins) $alias = $joins->getAlias()->getName(); 
                if ($table_where = $this->singleTableWhere($table, $statement, $alias)) array_push($clauses, $table_where);

                $deep = $this->where($table, ...$skip);
                $deep_sintax = $deep->get();
                if (0 !== strlen($deep_sintax)) {
                    $statement->pushFromBind($deep);
                    array_push($clauses, $deep_sintax);
                }
            }

            if (false === empty($clauses)) {
                $clauses = array_filter($clauses);
                $clauses = array_unique($clauses, SORT_STRING);
                $clauses_operator = chr(32) . 'AND' . chr(32);
                $clauses = implode($clauses_operator, $clauses);
                $sintax = $statement->get();
                if (0 !== strlen($sintax)) $statement->append('AND');
                $statement->append($clauses);
            }
        }

        $where = $this->getWhereStatement();
        if (null === $where
            || $hash !== $this->getCore()->getTable()->getHash()) return $statement;

        if (0 === strlen($statement->get())) return $statement->concat($where);

        $where_content = $where->get();
        $where_content = chr(40) . $where_content . chr(41);
        $where_content = 'AND' . chr(32) . $where_content;
        $where->set($where_content);

        return $statement->concat($where);
    }

    public function pushTablesUsingOr(Table ...$tables) : int
    {
        return array_push($this->tables, ...$tables);
    }

    protected function getTablesUsingOr() : array
    {
        return $this->tables;
    }
}