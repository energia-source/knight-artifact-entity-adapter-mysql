<?PHP

namespace MySQL\operations\select;

class Limit
{
    protected $limit;  // (int)
    protected $offset; // (int)

    public function set(int $limit) : self
    {
        $this->limit = $limit;
        return $this;
    }

    public function get() :? int
    {
        return $this->limit;
    }

    public function setOffset(int $offset) : self
    {
        $offset = abs($offset);
        $status = $this->get();
        if (null === $status) $this->set($offset);

        $this->offset = $offset;
        return $this;
    }

    public function getOffset() :? int
    {
        return $this->offset;
    }
}