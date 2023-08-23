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
     * @var string
     */
    protected $param_name;
    /**
     * @var Default_Dune_Plugin
     */
    protected $plugin;
    /**
     * @var bool
     */
    protected $save_delay = false;

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
        if (!$this->save_delay && is_callable(array($this->plugin, 'set_settings'))) {
            $this->plugin->set_settings($this->param_name, $this->order);
        }
    }

    /**
     * @return void
     */
    public function load()
    {
        if (is_callable(array($this->plugin, 'get_settings'))) {
            $this->order = $this->plugin->get_settings($this->param_name, array());
        }
    }

    /**
     * @param Object $obj
     * @param string $param_name
     * @return void
     */
    public function set_callback($obj, $param_name)
    {
        $this->plugin = $obj;
        $this->param_name = $param_name;
        $this->load();
    }

    /**
     * @return int
     */
    public function size()
    {
        return count($this->order);
    }

    /**
     * @return void
     */
    public function clear()
    {
        $this->order = array();
        $this->save();
    }

    /**
     * @return array
     */
    public function get_order()
    {
        return $this->order;
    }

    /**
     * @param array $order
     * @return void
     */
    public function set_order($order)
    {
        $this->order = $order;
        $this->save();
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
     * @param string $id
     * @return bool
     */
    public function remove_item($id)
    {
        $key = array_search($id, $this->order);
        if ($key !== false) {
            $removed = array_splice($this->order, $key, 1);
            if (count($removed) !== 0) {
                $this->save();
                return true;
            }
        }

        return false;
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
        $k = array_search($id, $this->order);
        //hd_print(__METHOD__ . ": move group_id: $group_id from idx: $k to direction: $direction");

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

        $this->save();
        return true;
    }

    /**
     * @return void
     */
    public function sort_order()
    {
        usort($this->order, array(__CLASS__, "sort_array_cb"));
        $this->save();
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
