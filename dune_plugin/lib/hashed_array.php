<?php

/**
 * @template TValue
 * @template TKey
 * @implements Iterator<TKey, TValue>
 */
class Hashed_Array implements Iterator
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
     * @param TValue $item
     */
    public function put($item)
    {
        $id = $item->get_id();
        if (!$this->has($item->get_id())) {
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
     * @param TValue $item
     */
    public function set($item)
    {
        $id = $item->get_id();
        if (!$this->has($item->get_id())) {
            $this->seq[] = $id;
        }
        $this->map[$id] = $item;
    }

    /**
     * @param mixed $key
     * @return bool
     */
    public function has($key)
    {
        return isset($this->map[$key]);
    }

    public function usort($callback_name)
    {
        usort($this->seq, $callback_name);
    }

    public function rewind()
    {
        $this->pos = 0;
    }

    /**
     * @return TValue
     */
    public function current()
    {
        return $this->get($this->seq[$this->pos]);
    }

    /**
     * @return integer
     */
    public function key()
    {
        return $this->pos;
    }

    public function next()
    {
        ++$this->pos;
    }

    /**
     * @return bool
     */
    public function valid()
    {
        return $this->pos < count($this->seq);
    }
}
