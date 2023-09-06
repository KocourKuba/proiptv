<?php

interface Channel
{
    /**
     * unique id for channel. typically hash
     * @return string
     */
    public function get_id();

    /**
     * channel title
     * @return string
     */
    public function get_title();

    /**
     * channel description
     * @return string
     */
    public function get_desc();

    /**
     * channel set description
     * @param string $desc
     */
    public function set_desc($desc);

    /**
     * icon uri
     * @return string
     */
    public function get_icon_url();

    /**
     * @return Hashed_Array<Group>
     */
    public function get_groups();

    /**
     * internal number
     * @return int
     */
    public function get_number();

    /**
     * is channel support archive playback
     * @return int
     */
    public function get_archive();

    /**
     * is protected (adult)
     * @return bool
     */
    public function is_protected();

    /**
     * is disabled (hidden)
     * @return bool
     */
    public function is_disabled();

    /**
     * set disabled (hide)
     * @param bool $disabled
     * @return void
     */
    public function set_disabled($disabled);
    /**
     * primary EPG source
     * @return array
     */
    public function get_epg_ids();

    /**
     * @return int
     */
    public function get_past_epg_days();

    /**
     * @return int
     */
    public function get_future_epg_days();

    /**
     * @return int
     */
    public function get_archive_past_sec();

    /**
     * @return int
     */
    public function get_archive_delay_sec();

    /**
     * timeshift for epg to this channel
     * @return int
     */
    public function get_timeshift_hours();

    /**
     * custom streaming url
     * @return string
     */
    public function get_url();

    /**
     * streaming url for archive
     * @return string
     */
    public function get_archive_url();

    /**
     * additional parameters
     * @return array
     */
    public function get_ext_params();
}
