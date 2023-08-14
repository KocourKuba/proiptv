<?php

interface Screen
{
    /**
     * @return string
     */
    public function get_id();

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array|null
     */
    public function get_folder_view(MediaURL $media_url, &$plugin_cookies);

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies);

    /**
     * @param MediaURL $media_url
     * @param int $from_ndx
     * @param $plugin_cookies
     * @return array
     */
    public function get_folder_range(MediaURL $media_url, $from_ndx, &$plugin_cookies);

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array|null
     */
    public function get_next_folder_view(MediaURL $media_url, &$plugin_cookies);
}
