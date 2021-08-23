<?PHP

namespace MySQL;

use PDO;
use PDOStatement;

use MySQL\Factory;

final class Connection
{
    protected $pdo;          // PDO
    protected $prepare = []; // (array) PDOStatement
    protected $hash;         // (string)

    public function __construct(string $database, string $username, string $password, string $host, int $port)
    {
        $pdo = new PDO('mysql:dbname=' . $database . ';host=' . $host . ';port=' . $port, $username, $password, [
            PDO::ATTR_TIMEOUT => 4,
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);

        $this->setConnection($pdo);

        $hash_arguments = func_get_args();
        $hash = Factory::dialHash(...$hash_arguments);
        $this->setHash($hash);
    }

    public function __destruct()
    {
        $this->pdo = null;
    }

    public function getConnection() : PDO
    {
        return $this->pdo;
    }

    public function getHash() : string
    {
        return $this->hash;
    }

    public function getPrepare(string $statement) :? PDOStatement
    {
        $hash = md5($statement);
        if (array_key_exists($hash, $this->prepare)) return $this->prepare[$hash];

        $prepare = $this->getConnection()->prepare($statement);
        if (false === $prepare) return null;

        $this->prepare[$hash] = $prepare;
        return $prepare;
    }

    protected function setConnection(PDO $pdo) : void
    {
        $this->pdo = $pdo;
    }

    protected function setHash(string $hash) : void
    {
        $this->hash = $hash;
    }
}