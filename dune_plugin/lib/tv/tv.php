<?php

interface Tv
{
    /**
     * @return Hashed_Array
     */
    public function get_channels();

    /**
     * @return Hashed_Array
     */
    public function get_groups();

    /**
     * unload all channels
     */
    public function unload_channels();

    /**
     * @param $plugin_cookies
     */
    public function ensure_channels_loaded($plugin_cookies);

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    public function get_tv_info(MediaURL $media_url, &$plugin_cookies);

    /**
     * @param string $playback_url
     * @param $plugin_cookies
     * @return string
     */
    public function get_tv_stream_url($playback_url, &$plugin_cookies);

    /**
     * @param string $channel_id
     * @param integer $archive_ts
     * @param string $protect_code
     * @param $plugin_cookies
     * @return string
     */
    public function get_tv_playback_url($channel_id, $archive_ts, $protect_code, &$plugin_cookies);

    /**
     * @param string $channel_id
     * @param integer $day_start_ts
     * @param $plugin_cookies
     * @return array
     */
    public function get_day_epg($channel_id, $day_start_ts, &$plugin_cookies);
}
