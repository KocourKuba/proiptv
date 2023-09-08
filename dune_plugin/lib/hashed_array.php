<?php
require_once 'json_serializer.php';

/**
 * @template TKey
 * @template TValue
 * @implements Iterator<TKey, TValue>
 */
class Hashed_Array extends Json_Serializer implements Iterator
{
    /**
     * @var integer
     */
    protected $pos = 0;

    /**
     * @var array
     */
    protected $seq = array();

    /**
     * @var TValue[]
     */
    protected $map = array();

    /**
     * Default hashing algorithm
     * @return string
     */
    public static function hash($item)
    {
        return empty($item) ? '' : hash('crc32', $item);
    }

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
     * @param TKey|null $key
     */
    public function put($item, $key = null)
    {
        if (is_null($key)) {
            $key = self::hash($item);
        }

        if (!$this->has($key)) {
            $this->seq[] = $key;
            $this->map[$key] = $item;
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
     * @param TKey $key
     * @param TValue $item
     */
    public function set($key, $item)
    {
        if (!$this->has($key)) {
            $this->seq[] = $key;
        }
        $this->map[$key] = $item;
    }

    /**
     * @param TKey $key
     */
    public function erase($key)
    {
        if ($this->has($key)) {
            $all_keys = array_keys($this->seq, $key);
            foreach ($all_keys as $k) {
                unset($this->seq[$k]);
            }
            unset($this->map[$key]);
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

    /**
     * @return array
     */
    public function order()
    {
        return $this->seq;
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
        return $this->seq[$this->pos];
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
