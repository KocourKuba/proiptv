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
 * @template string
 * @template TValue
 * @implements Iterator<string, TValue>
 */
class Hashed_Array extends Json_Serializer implements Iterator
{
    /** Direction to the begin */
    const UP = -1;

    /** Direction to the end */
    const DOWN = 1;

    /**
     * @var array
     */
    protected $seq = array();

    /**
     * @var TValue[]
     */
    protected $map = array();

    /**
     * @var integer
     */
    private $pos = 0;

    /**
     * @param string $a
     * @param string $b
     * @return int
     */
    protected static function sort_array_cb($a, $b)
    {
        return strnatcasecmp($a, $b);
    }

    public function __sleep()
    {
        $new_map = array();
        foreach ($this->seq as $key) {
            $new_map[$key] = $this->map[$key];
        }
        $this->map = $new_map;

        return array('pos', 'map');
    }

    public function __wakeup()
    {
        $this->seq = array_keys($this->map);
    }

    /**
     * @return integer
     */
    public function size()
    {
        return count($this->seq);
    }

    /**
     * Get value by index
     *
     * @param integer $ndx
     * @return TValue|null
     */
    public function get_by_idx($ndx)
    {
        return $this->get($this->seq[$ndx]);
    }

    /**
     * return value associated with key
     *
     * @param string $key
     * @return TValue|null
     */
    public function get($key)
    {
        return $this->has($key) ? $this->map[$key] : null;
    }

    /**
     * Check if key exist
     *
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        return isset($this->map[$key]);
    }

    /**
     * Check if key exist
     *
     * @param TValue $value
     * @return bool
     */
    public function has_value($value)
    {
        return isset($this->map[self::hash($value)]);
    }

    /**
     * Return index by key
     *
     * @param string $key
     * @return integer|false
     */
    public function get_idx($key)
    {
        return array_search($key, $this->seq);
    }

    /**
     * Add item. key for item is hash of item
     *
     * @param TValue $item
     */
    public function add($item)
    {
        $this->put(self::hash($item), $item);
    }

    /**
     * Default hashing algorithm
     */
    public static function hash($item)
    {
        return (empty($item) ? "" : hash('crc32', $item));
    }

    /**
     * Add only new item to array
     *
     * @param string $key
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
     * Add items
     *
     * @param Hashed_Array $items
     */
    public function add_items($items)
    {
        foreach ($items as $key => $item) {
            $this->put($key, $item);
        }
    }

    /**
     * Add items
     *
     * @param array $values
     */
    public function add_values($values)
    {
        foreach ($values as $item) {
            $this->add($item);
        }
    }

    /**
     * add new item or replace existing
     *
     * @param string $key
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
     * filter array by keys, return values only match with keys
     *
     * @param array $keys
     * @return Hashed_Array
     */
    public function &filter_keys($keys)
    {
        $filtered = new self();
        foreach ($keys as $key) {
            if ($this->has($key)) {
                $filtered->put($key, $this->map[$key]);
            }
        }
        return $filtered;
    }

    /**
     * filter array by keys, return values only match with keys
     *
     * @param Hashed_Array $arr
     * @return Hashed_Array
     */
    public function &filter($arr)
    {
        $filtered = new self();
        foreach ($arr as $key => $value) {
            if ($this->has($key)) {
                $filtered->put($key, $value);
            }
        }
        return $filtered;
    }

    /**
     * Erase by key
     *
     * @param string $key
     */
    public function erase($key)
    {
        if ($this->has($key)) {
            $idx = array_search($key, $this->seq);
            array_splice($this->seq, $idx, 1);
            unset($this->map[$key]);
        }
    }

    /**
     * Erase by keys from array
     *
     * @param array $keys
     */
    public function erase_keys($keys)
    {
        $this->seq = array_values(array_diff($this->seq, $keys));
        $this->map = array_diff_key($this->map, array_fill_keys($keys, null));
    }

    /**
     * Erase by values from array
     *
     * @param array $values
     */
    public function erase_values($values)
    {
        foreach ($values as $value) {
            $this->erase(self::hash($value));
        }
    }

    /**
     * Return keys
     *
     * @return string[]
     */
    public function get_keys()
    {
        return array_keys($this->map);
    }

    /**
     * Return values
     *
     * @return array
     */
    public function get_values()
    {
        return array_values($this->map);
    }

    /**
     * Return key sequences
     *
     * @return array
     */
    public function get_ordered_keys()
    {
        return $this->seq;
    }

    /**
     * Return value sequences
     *
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

    /**
     * Convert Hashed_Array to array
     *
     * @return array
     */
    public function to_array()
    {
        $values = array();
        foreach ($this->seq as $key) {
            $values[$key] = $this->get($key);
        }

        return $values;
    }

    /**
     * Create Hashed_Array from array
     *
     * @oaram array $values
     * @return Hashed_Array
     */
    public static function from_array($values)
    {
        $result = new self();
        foreach ($values as $value) {
            $result->add($value);
        }

        return $result;
    }

    /**
     * Sort by values
     */
    public function value_sort()
    {
        uasort($this->map, array(__CLASS__, "sort_array_cb"));
        $this->seq = array_keys($this->map);
    }

    /**
     * Change position of value by key
     *
     * @param string $key
     * @param int $direction
     * @return bool
     */
    public function arrange_item($key, $direction)
    {
        $k = array_search($key, $this->seq);
        //hd_debug_print("move id: $id from idx: $k to direction: $direction");

        if ($k === false || $direction === 0)
            return false;

        if ($direction < 0 && $k !== 0) {
            $t = $this->seq[$k - 1];
            $this->seq[$k - 1] = $this->seq[$k];
            $this->seq[$k] = $t;
        } else if ($direction > 0 && $k !== count($this->seq) - 1) {
            $t = $this->seq[$k + 1];
            $this->seq[$k + 1] = $this->seq[$k];
            $this->seq[$k] = $t;
        } else {
            return false;
        }

        return true;
    }

    /**
     * Clear Hashed_Array
     */
    public function clear()
    {
        $this->pos = 0;
        unset($this->seq, $this->map);
        $this->seq = array();
        $this->map = array();
    }

    /**
     * Reset position
     */
    public function reset()
    {
        $this->pos = 0;
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
        return isset($this->seq[$this->pos]) ? $this->seq[$this->pos] : null;
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
