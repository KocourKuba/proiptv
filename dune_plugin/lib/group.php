<?php

interface Group
{
    /**
     * @return string
     */
    public function get_id();

    /**
     * @return string
     */
    public function get_title();

    /**
     * @return string
     */
    public function get_icon_url();

    /**
     * @return bool
     */
    public function is_favorite_group();

    /**
     * @return bool
     */
    public function is_vod_group();

    /**
     * @return bool
     */
    public function is_all_channels_group();

    /**
     * @return bool
     */
    public function is_adult_group();

    /**
     * @return Hashed_Array
     */
    public function get_group_channels();
}
