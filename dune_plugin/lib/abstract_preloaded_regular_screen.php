<?php
///////////////////////////////////////////////////////////////////////////

require_once 'abstract_regular_screen.php';

abstract class Abstract_Preloaded_Regular_Screen extends Abstract_Regular_Screen
{
    protected $need_update_epfs = false;

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

    /**
     * @param MediaURL $parent_media_url
     * @param $plugin_cookies
     * @param int $sel_ndx
     * @return array
     */
    public function update_current_folder(MediaURL $parent_media_url, $plugin_cookies, $sel_ndx = -1)
    {
        return Action_Factory::update_regular_folder(
            $this->get_folder_range($parent_media_url, 0, $plugin_cookies),
            true,
            $sel_ndx);
    }

    /**
     * @param $plugin_cookies
     * @param null $post_action
     * @return array
     */
    public function update_epfs_data($plugin_cookies, $post_action = null)
    {
        if ($this->need_update_epfs) {
            $this->need_update_epfs = false;
            Starnet_Epfs_Handler::update_all_epfs($plugin_cookies);
            $post_action = Starnet_Epfs_Handler::invalidate_folders(null, $post_action);
        }

        return $post_action;
    }
}
