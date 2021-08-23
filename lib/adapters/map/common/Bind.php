<?PHP

namespace MySQL\adapters\map\common;

use Knight\armor\CustomException;

class Bind
{
    const BIND_VARIABLE_PREFIX = '\\$';
    const BIND_VARIABLE_PREFIX_ASSOCIATIVE = 'i';

    protected $bind = [];            // (array)
    protected static $increment = 0; // (int)

    public function getBind() : array
    {
        return $this->bind; /// value parse
    }

    public function resetBind() : self
    {
        $this->bind = array();
        return $this;
    }

    public function getBound(...$data) : array
    {
        $bind = $this->getBind();
        $bind_bound = [];
        foreach ($data as $key => $value) {
            $bind_count = static::increment();
            $bind_bound[$key] = static::BIND_VARIABLE_PREFIX_ASSOCIATIVE . $bind_count;
            $this->bind[$bind_bound[$key]] = $value;
        }
        return $bind_bound;
    }

    public function addBind(string $key, $data) : self
    {
        if (array_key_exists($key, $this->bind) && $this->bind[$key] !== $data) throw new CustomException('developer/database/bind/alredy/value');
        $this->bind[$key] = $data;
        return $this;
    }

    public function pushFromBind(self ...$binds) : self
    {
        array_walk($binds, function (self $bind) {
            $bind_value = $bind->getBind();
            foreach ($bind_value as $key => $value) $this->addBind($key, $value);
        });
        return $this;
    }

    private static function increment() : int
    {
        return static::$increment++;
    }
}