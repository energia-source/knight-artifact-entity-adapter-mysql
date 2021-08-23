<?PHP

namespace MySQL\operations\common\features;

use MySQL\entity\Table;
use MySQL\operations\common\features\parser\Query;
use MySQL\operations\common\features\parser\Matrioska;

trait Parser
{
    use Matrioska;

    public static function tables(Table $data, string $origin, string ...$skip) : array
    {
        $response = [];

        static::matrioska($data, $origin);
        $join = $data->getJoinedTables()->getTables();
        $hash = $data->getHash();

        if (true !== in_array($hash, $skip)) array_unshift($join, $data);
        if (null === $join
            || empty($join)) return $response;

        foreach ($join as $i => $table) {
            $table_hash = $table->getHash();
            if (false === in_array($table_hash, $skip)) array_push($response, new Query($table));
            array_push($skip, $table_hash);

            if ($hash !== $table_hash) {
                static::matrioska($table, $origin, $data);
                $recursive = static::tables($table, $origin, ...$skip);
                array_push($response, ...$recursive);
            }
        }

        return $response;
    }

    public static function uniqueness(Table $table) :? array
    {
        $table_injection = $table->getInjection();
        $table_injection_columns = $table_injection->getColumns();
        $table_injection_columns = array_keys($table_injection_columns);

        $table_keys = $table->getAllFieldsKeys(true);
        $table_keys = array_merge($table_keys, $table_injection_columns);

        $table_uniqueness = $table->getAllFieldsUniqueGroups();
        foreach ($table_uniqueness as $group) {
            $uniqueness_intersection = array_intersect($group, $table_keys);
            if (count($group) === count($uniqueness_intersection)) return $group;
        }

        return null;
    }

    public static function getUniquenessMatch(Table $table, Table $child, bool $filtered = true) :? array
    {
        $child_fields = $child->getAllFieldsKeys();
        $table_values = $table->getAllFieldsValues($filtered, false);
        $table_uniqueness = $table->getAllFieldsUniqueGroups();
        foreach ($table_uniqueness as $main) {
            $main_match = array_intersect_key($table_values, array_fill_keys($main, null));
            $main_match = array_filter($main_match, function (string $name) use ($child_fields) {
                return in_array($name, $child_fields);
            }, ARRAY_FILTER_USE_KEY);
            if (count($main) === count($main_match)) return $main_match;
        }
        return null;
    }
}