<?PHP

namespace MySQL\operations;

use MySQL\Statement;
use MySQL\entity\Table;
use MySQL\operations\common\Handling;

class Delete extends Handling
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
            $statement->append('DELETE');
            $statement->append('FROM');
            $statement_table = $table->getCollectionName();
            $statement->append(chr(96) . $statement_table . chr(96));

            if ($statement_where = $this->where($table)) {
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