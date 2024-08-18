<?php
require_once 'channel.php';
require_once 'json_serializer.php';

class Default_Channel extends Json_Serializer implements Channel
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
    protected $_desc;

    /**
     * @var string
     */
    protected $_icon_url;

    /**
     * @var string
     */
    protected $_streaming_url;

    /**
     * @var string
     */
    protected $_archive_url;

    /**
     * @var string
     */
    protected $_catchup;

    /**
     * @var Default_Group
     */
    protected $_group;

    /**
     * @var int
     */
    protected $_number;

    /**
     * @var int
     */
    protected $_archive;

    /**
     * @var bool
     */
    protected $_protected;

    /**
     * @var bool
     */
    protected $_disabled;

    /**
     * @var array first epg
     */
    protected $_epg_ids;

    /**
     * @var int
     */
    protected $_timeshift_hours;

    /**
     * @var array|null
     */
    protected $_ext_params;

    /**
     * @var Default_Dune_Plugin
     */
    private $plugin;

    /**
     * @param Default_Dune_Plugin $plugin
     * @param string $id
     * @param string $title
     * @param string $icon_url
     * @param string $streaming_url
     * @param string $archive_url
     * @param string $catchup
     * @param Group $group
     * @param int $archive
     * @param int $number
     * @param array $epg_ids
     * @param bool $protected
     * @param int $timeshift_hours
     * @param array $ext_params
     * @param bool $disabled
     */
    public function __construct($plugin, $id, $title, $icon_url,
                                $streaming_url, $archive_url, $catchup,
                                $group, $archive, $number, $epg_ids,
                                $protected, $timeshift_hours, $ext_params, $disabled)
    {
        $this->plugin = $plugin;

        $this->_id = $id;
        $this->_title = $title;
        $this->_icon_url = $icon_url;
        $this->_streaming_url = $streaming_url;
        $this->_archive_url = $archive_url;
        $this->_catchup = $catchup;
        $this->_group = $group;
        $this->_archive = ($archive > 0) ? $archive : 0;
        $this->_number = $number;
        $this->_epg_ids = $epg_ids;
        $this->_protected = $protected;
        $this->_timeshift_hours = $timeshift_hours;
        $this->_ext_params = $ext_params;
        $this->_disabled = $disabled;
    }

    public function __sleep()
    {
        $vars = get_object_vars($this);
        unset($vars['plugin']);
        return array_keys($vars);
    }

    /**
     * @inheritDoc
     */
    public function get_id()
    {
        return $this->_id;
    }

    /**
     * @inheritDoc
     */
    public function get_title()
    {
        return $this->_title;
    }

    /**
     * @inheritDoc
     */
    public function get_desc()
    {
        return $this->_desc;
    }

    /**
     * @inheritDoc
     */
    public function set_desc($desc)
    {
        $this->_desc = $desc;
    }

    /**
     * @inheritDoc
     */
    public function get_icon_url()
    {
        return $this->_icon_url;
    }

    /**
     * @inheritDoc
     */
    public function get_number()
    {
        return $this->_number;
    }

    /**
     * @inheritDoc
     */
    public function is_protected()
    {
        return $this->_protected;
    }

    /**
     * @inheritDoc
     */
    public function is_disabled()
    {
        return $this->_disabled;
    }

    /**
     * @inheritDoc
     */
    public function set_disabled($disabled)
    {
        $this->_disabled = $disabled;
        if ($disabled) {
            $this->plugin->tv->get_disabled_channel_ids()->add_item($this->_id);
            $this->get_parent_group()->get_items_order()->remove_item($this->_id);
        } else {
            $this->plugin->tv->get_disabled_channel_ids()->remove_item($this->_id);
            $this->get_parent_group()->get_items_order()->add_item($this->_id);
        }
    }

    /**
     * @inheritDoc
     */
    public function get_parent_group()
    {
        return $this->_group;
    }

    /**
     * @inheritDoc
     */
    public function get_archive()
    {
        return $this->_archive;
    }

    /**
     * @inheritDoc
     */
    public function get_timeshift_hours()
    {
        return $this->_timeshift_hours;
    }

    /**
     * @inheritDoc
     */
    public function get_epg_ids()
    {
        return $this->_epg_ids;
    }

    /**
     * @inheritDoc
     */
    public function get_past_epg_days()
    {
        return $this->_archive;
    }

    /**
     * @inheritDoc
     */
    public function get_future_epg_days()
    {
        return 7;
    }

    /**
     * @inheritDoc
     */
    public function get_archive_past_sec()
    {
        return $this->_archive * 86400;
    }

    /**
     * @inheritDoc
     */
    public function get_archive_delay_sec()
    {
        return 60;
    }

    /**
     * @inheritDoc
     */
    public function get_url()
    {
        return $this->_streaming_url;
    }

    /**
     * @inheritDoc
     */
    public function get_archive_url()
    {
        return $this->_archive_url;
    }

    /**
     * @inheritDoc
     */
    public function get_catchup()
    {
        return $this->_catchup;
    }

    /**
     * @inheritDoc
     */
    public function get_ext_params()
    {
        return $this->_ext_params;
    }

    /**
     * set additional parameters (filled from provider m3u8)
     * @param array $ext_params
     */
    public function set_ext_params($ext_params)
    {
        $this->_ext_params = $ext_params;
    }

    /**
     * set additional parameters (filled from provider m3u8)
     * @param string $param
     * @param string $value
     */
    public function set_ext_param($param, $value)
    {
        $this->_ext_params[$param] = $value;
    }
}
