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
     * @var integer
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
     * @param string $a
     * @param string $b
     * @return int
     */
    protected static function sort_array_cb($a, $b)
    {
        return strnatcasecmp($a, $b);
    }

    /**
     * @return int
     */
    public function get_saved_pos()
    {
        return $this->saved_pos;
    }

    /**
     * @param int $pos
     */
    public function set_saved_pos($pos)
    {
        $this->saved_pos = (int)$pos;
        if ($pos >= $this->size()) {
            $this->saved_pos = 0;
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
        return in_array($id, $this->order);
    }

    /**
     * @param Ordered_Array $ids
     * @return void
     */
    public function add_ordered_array($ids)
    {
        $this->add_items($ids->get_order());
    }

    /**
     * @param array $ids
     * @return void
     */
    public function add_items($ids)
    {
        $this->order = array_values(array_unique(array_merge($this->order, $ids)));
    }

    /**
     * @return array
     */
    public function get_order()
    {
        return $this->order;
    }

    /**
     * @param string $id
     * @param bool $last
     */
    public function insert_item($id, $last)
    {
        if ($this->in_order($id)) {
            $this->remove_item($id);
        }

        if ($last) {
            $this->order[] = $id;
        } else {
            array_unshift($this->order, $id);
        }
    }

    /**
     * @param string $id
     * @return bool
     */
    public function remove_item($id)
    {
        $key = array_search($id, $this->order);
        if ($key !== false) {
            $selected_item = $this->get_selected_item();
            $removed = array_splice($this->order, $key, 1);
            if (count($removed) !== 0) {
                $this->update_saved_pos($selected_item);
                return true;
            }
        }

        return false;
    }

    /**
     * @return mixed|null
     */
    public function get_selected_item()
    {
        return isset($this->order[$this->saved_pos]) ? $this->order[$this->saved_pos] : null;
    }

    /**
     * @param string $item
     * @return void
     */
    public function update_saved_pos($item)
    {
        if ($item === null) {
            $pos = 0;
        } else {
            $key = array_search($item, $this->order);
            $pos = ($key !== false) ? $key : 0;
        }

        $this->set_saved_pos($pos);
    }

    /**
     * @param array $ids
     * @return bool
     */
    public function remove_items($ids)
    {
        $selected_item = $this->get_selected_item();
        $size = $this->size();
        $this->order = array_values(array_diff($this->order, $ids));
        $this->update_saved_pos($selected_item);
        return $size !== $this->size();
    }

    /**
     * @param int $idx
     * @return bool
     */
    public function remove_item_by_idx($idx)
    {
        $selected_item = $this->get_selected_item();
        $removed = array_splice($this->order, $idx, 1);
        if (count($removed) !== 0) {
            $this->update_saved_pos($selected_item);
            return true;
        }

        return false;
    }

    /**
     * @param int $idx
     * @return string
     */
    public function get_item_by_idx($idx)
    {
        return isset($this->order[$idx]) ? $this->order[$idx] : '';
    }

    /**
     * @param int $idx
     * @param string $item
     */
    public function set_item_by_idx($idx, $item)
    {
        $this->order[$idx] = $item;
    }

    /**
     * @param string $item
     * @return int
     */
    public function get_item_pos($item)
    {
        return array_search($item, $this->order);
    }

    /**
     * @param string $id
     * @param int $direction
     * @return bool
     */
    public function arrange_item($id, $direction)
    {
        $k = array_search($id, $this->order);
        if ($k === false) {
            return false;
        }

        $selected_item = $this->get_selected_item();

        switch ($direction) {
            case self::UP:
                if ($k !== 0) {
                    $t = $this->order[$k - 1];
                    $this->order[$k - 1] = $this->order[$k];
                    $this->order[$k] = $t;
                }
                break;
            case self::DOWN:
                if ($k !== count($this->order) - 1) {
                    $t = $this->order[$k + 1];
                    $this->order[$k + 1] = $this->order[$k];
                    $this->order[$k] = $t;
                }
                break;
            case self::TOP:
                if ($k !== 0) {
                    $t = $this->order[$k];
                    $this->remove_item_by_idx($k);
                    $this->insert_item($t, false);
                }
                break;
            case self::BOTTOM:
                $last = count($this->order) - 1;
                if ($k !== $last) {
                    $t = $this->order[$k];
                    $this->remove_item_by_idx($k);
                    $this->insert_item($t, true);
                }
                break;
            default:
                return false;
        }

        $this->update_saved_pos($selected_item);
        return true;
    }

    /**
     * @return void
     */
    public function sort_order()
    {
        $selected_item = $this->get_selected_item();
        usort($this->order, array(__CLASS__, "sort_array_cb"));
        $this->update_saved_pos($selected_item);
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
