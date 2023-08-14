<?php
///////////////////////////////////////////////////////////////////////////

require_once 'abstract_regular_screen.php';

abstract class Abstract_Preloaded_Regular_Screen extends Abstract_Regular_Screen
{
    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    abstract public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies);

    /**
     * @param MediaURL $media_url
     * @param int $from_ndx
     * @param $plugin_cookies
     * @return array
     */
    public function get_folder_range(MediaURL $media_url, $from_ndx, &$plugin_cookies)
    {
        return HD::create_regular_folder_range($this->get_all_folder_items($media_url, $plugin_cookies), $from_ndx);
    }
}
