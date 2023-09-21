<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
 * Original code from DUNE HD
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense
 * of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

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
    private $pos = 0;

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
     */
    public function add($item)
    {
        $key = self::hash($item);
        $this->put($key, $item);
    }

    /**
     * @param TKey $key
     * @param TValue $item
     */
    public function put($key, $item)
    {
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
    public function get_keys()
    {
        return array_keys($this->map);
    }

    /**
     * @return array
     */
    public function get_values()
    {
        return array_values($this->map);
    }

    /**
     * @return array
     */
    public function get_order()
    {
        return $this->seq;
    }

    /**
     * @return array
     */
    public function get_ordered_values()
    {
        $values = array();
        foreach ($this->seq as $key) {
            $values[] = $this->get($key);
        }

        return $values;
    }

    public function key_sort()
    {
        uksort($this->seq, array(__CLASS__, "sort_array_cb"));
    }

    public function value_sort()
    {
        uasort($this->map, array(__CLASS__, "sort_array_cb"));
        $this->seq = array_keys($this->map);
    }

    /**
     * @param string $a
     * @param string $b
     * @return int
     */
    protected static function sort_array_cb($a, $b)
    {
        return strnatcasecmp($a, $b);
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
