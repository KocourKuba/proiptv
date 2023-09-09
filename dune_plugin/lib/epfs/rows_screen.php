<?php
require_once 'lib/screen.php';

interface Rows_Screen extends Screen
{
    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    public function get_rows_pane(MediaURL $media_url, $plugin_cookies);

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return mixed
     */
    public function get_timer(MediaURL $media_url, $plugin_cookies);
}
