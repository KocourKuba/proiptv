<?php
require_once 'group.php';

class Default_Group implements Group
{
    const DEFAULT_GROUP_ICON_PATH = 'plugin_file://icons/default_group.png';

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
     * @var boolean
     */
    protected $_favorite = false;
    /**
     * @var boolean
     */
    protected $_all_group = false;
    /**
     * @var boolean
     */
    protected $_history = false;
    /**
     * @var Hashed_Array
     */
    protected $_channels;
    /**
     * @var Ordered_Array
     */
    protected $_channels_order;

    /**
     * @var Default_Dune_Plugin
     */
    protected $plugin;

    /**
     * @param string $id
     * @param string $title
     * @param string $icon_url
     */
    public function __construct($plugin, $id, $title, $icon_url = null, $adult = false, $disabled = false)
    {
        $this->plugin = $plugin;

        if (is_null($icon_url)) {
            $icon_url = self::DEFAULT_GROUP_ICON_PATH;
        }

        if (is_null($id)) {
            $id = $title;
        }

        $this->_id = $id;
        $this->_title = $title;
        $this->_icon_url = $icon_url;
        $this->_adult = $adult;
        $this->_disabled = $disabled;

        $this->_channels = new Hashed_Array();
        $this->_channels_order = new Ordered_Array($this->plugin, $this->_title . PARAM_CHANNELS_ORDER);
    }

    public function __sleep()
    {
        return array('_id', '_title', '_icon_url', '_adult', '_disabled', '_favorite', 'all_group', '_history', '_channels_order');
    }

    public function __wakeup()
    {
        $this->_channels = new Hashed_Array();
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
     * @return bool
     */
    public function is_favorite_group()
    {
        return $this->_favorite;
    }

    /**
     * @return bool
     */
    public function is_history_group()
    {
        return $this->_history;
    }

    /**
     * @return bool
     */
    public function is_all_channels_group()
    {
        return $this->_all_group;
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
        return $this->_channels_order;
    }

    /**
     * @param Channel $channel
     */
    public function add_channel(Channel $channel)
    {
        $this->_channels->put($channel);
        if (!$channel->is_disabled() && !$this->is_all_channels_group()) {
            $this->_channels_order->add_item($channel->get_id());
        }
    }
}
