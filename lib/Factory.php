<?PHP

namespace MySQL;

use Knight\Configuration;

use MySQL\Connection;

final class Factory
{
    use Configuration;

    const CONFIGURATION_FILENAME = 'MySQL';
    const CONFIGURATION_DATABASE = 0x3d090;
    const CONFIGURATION_DATABASE_USERNAME = 0x33450;
    const CONFIGURATION_DATABASE_PASSWORD = 0x33451;
    const CONFIGURATION_HOST = 0x30d40;
    const CONFIGURATION_PORT = 0x30d41;

    protected static $databases = []; // (array) Connection

    protected function __construct() {}

    public static function connect(string $database = null, string $username = null, string $password = null, string $host = null, string $port = null) : Connection
    {
        $connection_database = $database ?? static::getConfiguration(static::CONFIGURATION_DATABASE, true, static::CONFIGURATION_FILENAME, 'DEFAULT');
        $connection_username = $username ?? static::getConfiguration(static::CONFIGURATION_DATABASE_USERNAME, true, static::CONFIGURATION_FILENAME, 'DEFAULT');
        $connection_password = $password ?? static::getConfiguration(static::CONFIGURATION_DATABASE_PASSWORD, true, static::CONFIGURATION_FILENAME, 'DEFAULT');
        $connection_host = $host ?? static::getConfiguration(static::CONFIGURATION_HOST, true, static::CONFIGURATION_FILENAME, 'DEFAULT');
        $connection_port = $port ?? static::getConfiguration(static::CONFIGURATION_PORT, true, static::CONFIGURATION_FILENAME, 'DEFAULT');

        return static::getConnection($connection_database, $connection_username, $connection_password, $connection_host, $connection_port);
    }

    public static function disconnect(string $hash) : self
    {
        foreach (static::$databases as $key => $database)
            if ($hash === $database->getHash())
                unset(static::$databases[$key]);

        static::$databases = array_values(static::$databases);

        return $this;
    }

    public static function dialHash(string ...$arguments) : string
    {
        $hash = serialize($arguments);
        $hash = md5($hash);
        return $hash;
    }

    protected static function getConnection(string $database, string $username, string $password, string $host, int $port) : Connection
    {
        $hash_arguments = func_get_args();
        $hash = Factory::dialHash(...$hash_arguments);
        $connection = static::searchConnectionFromHash($hash);
        if (null !== $connection) return $connection;

        $connection = new Connection(...$hash_arguments);
        array_push(static::$databases, $connection);

        return $connection;
    }

    protected static function searchConnectionFromHash(string $hash) :? Connection
    {
        foreach (static::$databases as $database)
            if ($hash === $database->getHash())
                return $database;
        return null;
    }
}