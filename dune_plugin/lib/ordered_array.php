<?php

class Ordered_Array
{
    const UP = -1;
    const DOWN = 1;
    /**
     * @var array
     */
    protected $order = array();
    /**
     * @var Default_Dune_Plugin
     */
    protected $plugin;
    /**
     * @var string
     */
    protected $param_name;
    /**
     * @var array
     */
    protected $accessor = 'settings';
    /**
     * @var bool
     */
    protected $save_delay = false;

    /**
     * @var int
     */
    protected $saved_pos = 0;

    public function __construct($plugin = null, $param_name = null, $method = null)
    {
        $this->set_callback($plugin, $param_name, $method);
    }

    public function __sleep()
    {
        return array('order');
    }

    /**
     * @param bool $val
     */
    public function set_save_delay($val)
    {
        $this->save_delay = $val;
    }

    /**
     * @return void
     */
    public function save()
    {
        $set = "set_$this->accessor";
        if (!$this->save_delay && is_callable(array($this->plugin, $set))) {
            $this->plugin->{$set}($this->param_name, $this->order);
            //hd_print(__METHOD__ . ": $set: $this->param_name ({$this->size()})");
        }
    }

    /**
     * @return void
     */
    public function load()
    {
        $get = "get_$this->accessor";
        if (is_callable(array($this->plugin, $get))) {
            $this->order = $this->plugin->{$get}($this->param_name, array());
            //hd_print(__METHOD__ . ": $get: $this->param_name ({$this->size()})");
        }
    }

    /**
     * @param Object $obj
     * @param string $param_name
     * @param string $method
     * @return void
     */
    public function set_callback($obj, $param_name, $method = null)
    {
        $this->plugin = $obj;
        $this->param_name = $param_name;
        if (!is_null($method)) {
            $this->accessor = $method;
        }

        $this->load();
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
        $this->saved_pos = $pos;
    }

    /**
     * @return int
     */
    public function size()
    {
        return count($this->order);
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
        $this->save();
    }

    /**
     * clear order but do not save changes!
     *
     * @return void
     */
    public function zap()
    {
        $this->order = array();
        $this->saved_pos = 0;
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
     * @return bool
     */
    public function add_item($id)
    {
        if ($this->in_order($id)) {
            return false;
        }

        $this->order[] = $id;
        $this->save();
        return true;
    }

    /**
     * @param array $ids
     * @return void
     */
    public function add_items($ids)
    {
        foreach ($ids as $id) {
            if (!$this->in_order($id)) {
                $this->order[] = $id;
            }
        }

        $this->save();
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
            hd_print(__METHOD__ . ": remove: $id");
            $removed = array_splice($this->order, $key, 1);
            if (count($removed) !== 0) {
                $this->update_saved_pos($selected_item);
                $this->save();
                return true;
            }
        }

        return false;
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
            $this->save();
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
     * @param string $id
     * @return bool
     */
    public function in_order($id)
    {
        return in_array($id, $this->order);
    }

    /**
     * @param string $id
     * @param int $direction
     * @return bool
     */
    public function arrange_item($id, $direction)
    {
        $selected_item = $this->get_selected_item();
        $k = array_search($id, $this->order);
        hd_print(__METHOD__ . ": move id: $id from idx: $k to direction: $direction");

        if ($k === false || $direction === 0)
            return false;

        if ($direction < 0 && $k !== 0) {
            $t = $this->order[$k - 1];
            $this->order[$k - 1] = $this->order[$k];
            $this->order[$k] = $t;
        } else if ($direction > 0 && $k !== count($this->order) - 1) {
            $t = $this->order[$k + 1];
            $this->order[$k + 1] = $this->order[$k];
            $this->order[$k] = $t;
        } else {
            return false;
        }

        $this->update_saved_pos($selected_item);
        $this->save();
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
        $this->save();
    }

    /**
     * @return string|null
     */
    public function get_selected_item()
    {
        return isset($this->order[$this->saved_pos]) ? $this->order[$this->saved_pos] : null;
    }

    /**
     * @param string $item
     * @return void
     */
    private function update_saved_pos($item)
    {
        if ($item === null) {
            $this->saved_pos = 0;
        } else {
            $key = array_search($item, $this->order);
            $this->saved_pos = ($key !== false) ? $key : 0;
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
}
