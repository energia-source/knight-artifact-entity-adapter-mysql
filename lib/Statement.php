<?PHP

namespace MySQL;

use PDO;
use PDOStatement;
use PDOException;

use Knight\armor\Output;
use Knight\armor\CustomException;

use Entity\Map;

use MySQL\Connection;
use MySQL\entity\Table;
use MySQL\adapters\map\common\Bind;

class Statement extends Bind
{
    protected $connection;  // Connection
    protected $sintax = ''; // (string)

    public static function converter(&$value) : void
    {
        switch (true) {
            case is_array($value) || is_object($value):
                $reference = array(&$value);
                array_walk_recursive($reference, function (&$item) {
                    if (false === ($item instanceof Map)) return;
                    $item = $item->getAllFieldsValues(true, false);
                });
                $value = Output::json($value);
                break;
            case is_bool($value):
                $value = (int)(bool)$value;
                break;

        }
    }

    public function __construct(Connection $connection = null)
    {
        $this->setConnection($connection);
    }

    public function get() : string
    {
        return trim($this->sintax);
    }

    public function append(string $string, bool $white = true) : self
    {
        $this->sintax .= $string;
        if (true === $white) $this->sintax .= chr(32);
        return $this;
    }

    public function set(string $string) : self
    {
        $this->sintax = $string;
        return $this;
    }

    public function concat(?self $statement) : self
    {
        if (null === $statement) return $this;
        $this->append($statement->get());
        $this->pushFromBind($statement);
        return $this;
    }

    public function execute() :? PDOStatement
    {
        try {
            $connection = $this->getConnection();
            if (null === $connection) throw new CustomException('developer/database/statement/connection');

            $sintax = $this->get();
            $sintax_prepare = $connection->getPrepare($sintax);
            if (null === $sintax_prepare) throw new CustomException('developer/database/statement/prepare');

            $sintax_bind = $this->getBind();
            $sintax_bind = array_filter($sintax_bind, function (string $key) use ($sintax) {
                return preg_match('/' . chr(40) . chr(58) . $key . chr(41) . '/', $sintax);
            }, ARRAY_FILTER_USE_KEY);

            array_walk($sintax_bind, function ($value, $key) use ($sintax_prepare) {
                static::converter($value);
                if (false === is_resource($value)) return $sintax_prepare->bindParam($key, $value, PDO::PARAM_STR);
                if (get_resource_type($value) !== 'stream') throw new CustomException('developer/database/statement/resource');

                return $sintax_prepare->bindParam($key, $value, PDO::PARAM_LOB);
            });

            if (!!$sintax_prepare->execute()) return $sintax_prepare;
        } catch (PDOException $exception) {
        }

        return null;
    }

    public function getConnection() :? Connection
    {
        return $this->connection;
    }

    protected function setConnection(?Connection $connection) : void
    {
        $this->connection = $connection;
    }
}