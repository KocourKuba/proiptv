<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
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
class Ordered_Array extends Json_Serializer implements Iterator
{
    const UP = -1;
    const DOWN = 1;

    const TOP = PHP_INT_MIN;
    const BOTTOM = PHP_INT_MAX;

    /**
     * @var array
     */
    protected $order = array();
    /**
     * @var int
     */
    protected $saved_pos = 0;
    /**
     * @var int
     */
    private $pos = 0;

    /**
     * @param array|null $values
     */
    public function __construct($values = null)
    {
        if (is_array($values)) {
            $this->order = $values;
        }
    }

    /**
     * @return int
     */
    public function size()
    {
        return count($this->order);
    }

    /**
     * @param mixed $id
     * @return mixed
     */
    public function get($id)
    {
        return $id;
    }

    /**
     * clear order and save changes
     *
     * @return void
     */
    public function clear()
    {
        $this->order = array();
        $this->saved_pos = 0;
    }

    /**
     * @param string $id
     * @return bool
     */
    public function add_item($id)
    {
        if ($this->in_order($id)) {
            return false;
        }

        $this->order[] = $id;
        return true;
    }

    /**
     * @param string $id
     * @return bool
     */
    public function in_order($id)
    {
        return in_array_id($id, $this->order);
    }

    /**
     * @return array
     */
    public function get_order()
    {
        return $this->order;
    }

    /////////////////////////////////////////////////////////////////////
    /// Iterator implementation

    /**
     * @inheritDoc
     */
    public function current()
    {
        return $this->order[$this->pos];
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
        return $this->pos < count($this->order);
    }

    /**
     * @inheritDoc
     */
    public function rewind()
    {
        $this->pos = 0;
    }
}
