<?php
require_once 'json_serializer.php';

/**
 * @template TValue
 * @template TKey
 * @implements Iterator<TKey, TValue>
 */
class Hashed_Array extends Json_Serializer implements Iterator
{
    /**
     * @var integer
     */
    private $pos = 0;

    /**
     * @var array
     */
    private $seq = array();

    /**
     * @var TValue[]
     */
    private $map = array();

    /**
     * @return integer
     */
    public function size()
    {
        return count($this->seq);
    }

    /**
     * @param integer $ndx
     * @return TValue|null
     */
    public function get_by_idx($ndx)
    {
        return $this->get($this->seq[$ndx]);
    }

    /**
     * @param TKey $id
     * @param TValue $item
     */
    public function put($id, $item)
    {
        if (!$this->has($id)) {
            $this->seq[] = $id;
            $this->map[$id] = $item;
        }
    }

    /**
     * @param mixed $key
     * @return TValue|null
     */
    public function get($key)
    {
        return $this->has($key) ? $this->map[$key] : null;
    }

    /**
     * @param TKey $id
     * @param TValue $item
     */
    public function set($id, $item)
    {
        if (!$this->has($item->get_id())) {
            $this->seq[] = $id;
        }
        $this->map[$id] = $item;
    }

    /**
     * @param TKey $id
     */
    public function erase($id)
    {
        if ($this->has($id)) {
            $keys = array_keys($this->seq, $id);
            foreach ($keys as $key) {
                unset($this->seq[$key]);
            }
            unset($this->map[$id]);
        }
    }

    /**
     * @param mixed $key
     * @return bool
     */
    public function has($key)
    {
        return isset($this->map[$key]);
    }

    /**
     * @return array
     */
    public function keys()
    {
        return array_keys($this->map);
    }

    public function usort($callback_name)
    {
        usort($this->seq, $callback_name);
    }

    public function clear()
    {
        $this->pos = 0;
        unset($this->seq, $this->map);
        $this->seq = array();
        $this->map = array();
    }

    /////////////////////////////////////////////////////////////////////
    /// Iterator implementation

    /**
     * @inheritDoc
     */
    public function current()
    {
        return $this->get($this->seq[$this->pos]);
    }

    /**
     * @inheritDoc
     */
    public function next()
    {
        ++$this->pos;
    }

    /**
     * @inheritDoc
     */
    public function key()
    {
        return $this->pos;
    }

    /**
     * @inheritDoc
     */
    public function valid()
    {
        return $this->pos < count($this->seq);
    }

    /**
     * @inheritDoc
     */
    public function rewind()
    {
        $this->pos = 0;
    }
}
