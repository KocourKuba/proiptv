<?php
require_once 'group.php';

class Default_Group implements Group
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
     * @var Hashed_Array
     */
    protected $_channels;

    /**
     * @var boolean
     */
    protected $_adult;

    protected $is_favorite = false;
    protected $is_all_group = false;
    protected $is_history = false;
    protected $is_vod_group = false;

    /**
     * @param string $id
     * @param string $title
     * @param string $icon_url
     */
    public function __construct($id, $title, $icon_url, $_adult = false)
    {
        if (is_null($icon_url)) {
            $icon_url = 'gui_skin://small_icons/iptv.aai';
        }

        $this->_id = $id;
        $this->_title = $title;
        $this->_icon_url = $icon_url;
        $this->_adult = $_adult;

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
        return $this->is_favorite;
    }

    /**
     * @return bool
     */
    public function is_history_group()
    {
        return $this->is_history;
    }

    /**
     * @return bool
     */
    public function is_vod_group()
    {
        return $this->is_vod_group;
    }

    /**
     * @return bool
     */
    public function is_all_channels_group()
    {
        return $this->is_all_group;
    }

    /**
     * @return bool
     */
    public function is_adult_group()
    {
        return $this->_adult;
    }

    /**
     * @return Hashed_Array
     */
    public function get_group_channels()
    {
        return $this->_channels;
    }

    /**
     * @param Channel $channel
     */
    public function add_channel(Channel $channel)
    {
        $this->_channels->put($channel);
    }
}
