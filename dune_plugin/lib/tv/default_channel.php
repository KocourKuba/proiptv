<?php
require_once 'channel.php';

class Default_Channel implements Channel
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
     * @var Group[]
     */
    protected $_groups;

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
     * @param string $id
     * @param string $title
     * @param string $icon_url
     * @param string $streaming_url
     * @param string $archive_url
     * @param int $archive
     * @param int $number
     * @param array $epg_ids
     * @param bool $protected
     * @param int $timeshift_hours
     */
    public function __construct($id, $title, $icon_url,
                                $streaming_url, $archive_url,
                                $archive, $number, $epg_ids,
                                $protected, $timeshift_hours, $ext_params = array())
    {
        $this->_id = $id;
        $this->_title = $title;
        $this->_icon_url = $icon_url;
        $this->_streaming_url = $streaming_url;
        $this->_archive_url = $archive_url;
        $this->_groups = array();
        $this->_archive = ($archive > 0) ? $archive : 0;
        $this->_number = $number;
        $this->_epg_ids = $epg_ids;
        $this->_protected = $protected;
        $this->_timeshift_hours = $timeshift_hours;
        $this->_disabled = false;
        $this->_ext_params = $ext_params;
    }

    /**
     * get id (hash)
     * @return string
     */
    public function get_id()
    {
        return $this->_id;
    }

    /**
     * get channel title
     * @return string
     */
    public function get_title()
    {
        return $this->_title;
    }

    /**
     * get channel desc
     * @return string
     */
    public function get_desc()
    {
        return $this->_desc;
    }

    /**
     * set channel desc
     * @param string $desc
     */
    public function set_desc($desc)
    {
        $this->_desc = $desc;
    }

    /**
     * @return string
     */
    public function get_icon_url()
    {
        return $this->_icon_url;
    }

    /**
     * get groups array
     * @return array
     */
    public function get_groups()
    {
        return $this->_groups;
    }

    /**
     * get channel number
     * @return int
     */
    public function get_number()
    {
        return $this->_number;
    }

    /**
     * is channel protected (adult)
     * @return bool
     */
    public function is_protected()
    {
        return $this->_protected;
    }

    /**
     * is disabled (hided)
     * @return bool
     */
    public function is_disabled()
    {
        return $this->_disabled;
    }

    /**
     * set disabled (hided)
     */
    public function set_disabled($disabled)
    {
        $this->_disabled = $disabled;
    }

    /**
     * is channel has archive playback
     * @return bool
     */
    public function has_archive()
    {
        return $this->_archive > 0;
    }

    /**
     * get timeshift
     * @return int
     */
    public function get_timeshift_hours()
    {
        return $this->_timeshift_hours;
    }

    /**
     * get EPG id
     * @return array
     */
    public function get_epg_ids()
    {
        return $this->_epg_ids;
    }

    /**
     * how many epg reads from the past
     * @return int
     */
    public function get_past_epg_days()
    {
        return $this->_archive > 1 ? $this->_archive : 7;
    }

    /**
     * how many epg reads forward
     * @return int
     */
    public function get_future_epg_days()
    {
        return 7;
    }

    /**
     * how many second playback from the past
     * @return int
     */
    public function get_archive_past_sec()
    {
        return $this->_archive * 86400;
    }

    /**
     * @return int
     */
    public function get_archive_delay_sec()
    {
        return 60;
    }

    /**
     * get custom stream url
     * @return string
     */
    public function get_url()
    {
        return $this->_streaming_url;
    }

    /**
     * custom archive stream url template
     * @return string
     */
    public function get_archive_url()
    {
        return $this->_archive_url;
    }

    /**
     * get additional parameters (filled from provider m3u8)
     * @return array
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
     * @param $param
     * @param $value
     */
    public function set_ext_param($param, $value)
    {
        $this->_ext_params[$param] = $value;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * add group
     * @param Group $group
     */
    public function add_group(Group $group)
    {
        $this->_groups[] = $group;
    }
}
