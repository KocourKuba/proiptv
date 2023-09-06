<?php
require_once 'group.php';
require_once 'json_serializer.php';

class Default_Group extends Json_Serializer implements Group
{
    /**
     * @var string
     */
    protected $_id;

    /**
     * @var string
     */
    protected $_title;

    /**
     * @var string
     */
    protected $_icon_url;

    /**
     * @var boolean
     */
    protected $_adult;

    /**
     * @var boolean
     */
    protected $_disabled;

    /**
     * @var Hashed_Array
     */
    protected $_channels;

    /**
     * @var string
     */
    protected $_order_settings;

    /**
     * @var Default_Dune_Plugin
     */
    protected $plugin;

    /**
     * @param $plugin
     * @param string $id
     * @param string $title
     * @param string|null $icon_url
     * @param string|null $order_prefix
     */
    public function __construct($plugin, $id, $title, $icon_url = null, $order_prefix = PARAM_CHANNELS_ORDER)
    {
        $this->plugin = $plugin;

        if (is_null($title)) {
            $title = $id;
        }

        $this->_id = $id;
        $this->_title = $title;
        $this->_icon_url = $icon_url;
        $this->_adult = false;
        $this->_disabled = false;
        $this->_order_settings = is_null($order_prefix) ? null : ($order_prefix . $this->_id);

        $this->_channels = new Hashed_Array();
    }

    public function __sleep()
    {
        return array('_id', '_title', '_icon_url', '_adult', '_disabled');
    }

    /**
     * @return string
     */
    public function get_id()
    {
        return $this->_id;
    }

    /**
     * @return string
     */
    public function get_title()
    {
        return $this->_title;
    }

    /**
     * @return string
     */
    public function get_icon_url()
    {
        return $this->_icon_url;
    }

    /**
     * @param string $icon_url
     */
    public function set_icon_url($icon_url)
    {
        $this->_icon_url = $icon_url;
    }

    /**
     * @return bool
     */
    public function is_favorite_group()
    {
        return ($this->_id === FAVORITES_GROUP_ID);
    }

    /**
     * @return bool
     */
    public function is_history_group()
    {
        return ($this->_id === HISTORY_GROUP_ID);
    }

    /**
     * @return bool
     */
    public function is_all_channels_group()
    {
        return ($this->_id === ALL_CHANNEL_GROUP_ID);
    }

    /**
     * @return bool
     */
    public function is_adult_group()
    {
        return $this->_adult;
    }

    /**
     * @param bool $adult
     * @return void
     */
    public function set_adult($adult)
    {
        $this->_adult = $adult;
    }

    /**
     * @return bool
     */
    public function is_disabled()
    {
        return $this->_disabled;
    }

    /**
     * @param bool $disabled
     * @return void
     */
    public function set_disabled($disabled)
    {
        $this->_disabled = $disabled;
    }

    /**
     * @return Hashed_Array
     */
    public function get_group_channels()
    {
        return $this->_channels;
    }

    /**
     * @return Ordered_Array
     */
    public function get_items_order()
    {
        if (is_null($this->_order_settings)) {
            return new Ordered_Array();
        }

        return $this->plugin->get_setting($this->_order_settings, new Ordered_Array());
    }

    /**
     * @param  Ordered_Array $order
     */
    public function set_items_order($order)
    {
        $this->plugin->set_setting($this->_order_settings, $order);
    }
    /**
     * @param Channel $channel
     */
    public function add_channel(Channel $channel)
    {
        $this->_channels->put($channel);
        if (!$channel->is_disabled() && !$this->is_all_channels_group()) {
            $this->get_items_order()->add_item($channel->get_id());
        }
    }
}
