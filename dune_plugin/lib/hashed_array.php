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
 * @template Tkey
 * @template-covariant TValue
 * @template-implements Iterator<string, TValue>
 */
class Hashed_Array extends Json_Serializer implements Iterator
{
    /** Direction to the begin */
    const UP = -1;

    /** Direction to the end */
    const DOWN = 1;

    /**
     * @var Tkey[]
     */
    protected $seq = array();

    /**
     * @var TValue[]
     */
    protected $map = array();

    /**
     * @var int
     */
    private $pos = 0;

    /**
     * Only names what to save. It used to rewrite $map into $seq order first,
     * so the order survived as map key order - which meant anything calling
     * __sleep(), json logging included, reordered the live object. Saving $seq
     * itself keeps the order without touching anything.
     *
     * @return array
     */
    public function __sleep()
    {
        return array('pos', 'seq', 'map');
    }

    /**
     * @return void
     */
    public function __wakeup()
    {
        // data saved before $seq was part of it (the legacy .settings files
        // read by the orders migration): the order is the map key order
        if (empty($this->seq) && !empty($this->map)) {
            $this->seq = array_keys($this->map);
        }
    }

    /**
     * For the JSON dump: the map, in iteration order. $seq is left out -
     * it would only repeat the keys - so the output stays as it always was.
     *
     * @return array
     */
    protected function json_members()
    {
        $ordered = array();
        foreach ($this->seq as $key) {
            if (array_key_exists($key, $this->map)) {
                $ordered[$key] = $this->map[$key];
            }
        }

        return array('map' => $ordered);
    }

    /**
     * @return int
     */
    public function size()
    {
        return count($this->seq);
    }

    /**
     * @return bool
     */
    public function is_empty()
    {
        return $this->size() === 0;
    }

    /**
     * return value associated with key
     *
     * @param Tkey $key
     * @return TValue|array|null
     */
    public function get($key)
    {
        return $this->has($key) ? $this->map[$key] : null;
    }

    /**
     * Check if key exist
     *
     * @param Tkey $key
     * @return bool
     */
    public function has($key)
    {
        // isset() alone misses a key stored with a null value
        return isset($this->map[$key]) || array_key_exists($key, $this->map);
    }

    /**
     * Return index by key
     *
     * @param Tkey $key
     * @return int|false
     */
    public function get_idx($key)
    {
        return array_search_id($key, $this->seq);
    }

    /**
     * Add item. key for item is hash of item
     *
     * @param TValue $item
     * @return void
     */
    public function add($item)
    {
        $this->put(self::hash($item), $item);
    }

    /**
     * Default hashing algorithm
     * @param TValue $item
     * @return string
     */
    public static function hash($item)
    {
        return (empty($item) ? '' : hash('crc32', $item));
    }

    /**
     * Add only new item to array
     *
     * @param Tkey $key
     * @param TValue $item
     * @return void
     */
    public function put($key, $item)
    {
        if (!$this->has($key)) {
            $this->seq[] = $key;
            $this->map[$key] = $item;
        }
    }

    /**
     * add new item or replace existing
     *
     * @param Tkey $key
     * @param TValue $item
     * @return void
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
     * @param Hashed_Array<Tkey, TValue> $arr
     * @return Hashed_Array<Tkey, TValue>
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
     * @param Tkey $key
     * @return void
     */
    public function erase($key)
    {
        if ($this->has($key)) {
            $idx = $this->get_idx($key);
            if ($idx !== false) {
                array_splice($this->seq, $idx, 1);
            }
            unset($this->map[$key]);
        }
    }

    /**
     * Erase by keys from array
     *
     * @param Tkey[] $keys
     * @return void
     */
    public function erase_keys($keys)
    {
        $this->seq = array_values(array_diff($this->seq, $keys));
        $this->map = array_diff_key($this->map, array_fill_keys($keys, null));
    }

    /**
     * Return keys
     *
     * @return Tkey[]
     */
    public function get_keys()
    {
        return array_keys($this->map);
    }

    /**
     * Return values
     *
     * @return TValue[]
     */
    public function get_values()
    {
        return array_values($this->map);
    }

    /**
     * Clear Hashed_Array
     *
     * @return void
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
     *
     * @return void
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
