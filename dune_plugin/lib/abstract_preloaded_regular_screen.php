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
        $items = $this->get_all_folder_items($media_url, $plugin_cookies);
        $count = count($items);
        $total = $from_ndx + $count;

        if ($from_ndx >= $total) {
            $from_ndx = $total;
            $items = array();
        } else if ($from_ndx + $count > $total) {
            array_splice($items, $total - $from_ndx);
        }

        return array
        (
            PluginRegularFolderRange::total => $total,
            PluginRegularFolderRange::more_items_available => false,
            PluginRegularFolderRange::from_ndx => (int)$from_ndx,
            PluginRegularFolderRange::count => count($items),
            PluginRegularFolderRange::items => $items
        );
    }

    /**
     * @param MediaURL $parent_media_url
     * @param $plugin_cookies
     * @param int $sel_ndx
     * @return array
     */
    public function invalidate_current_folder2(MediaURL $parent_media_url, $plugin_cookies, $sel_ndx = -1)
    {
        return Action_Factory::update_regular_folder(
            $this->get_folder_range($parent_media_url, 0, $plugin_cookies),
            true,
            $sel_ndx);
    }

    /**
     * @param MediaURL $parent_media_url
     * @param $plugin_cookies
     * @param int $sel_ndx
     * @return array
     */
    public function invalidate_current_folder(MediaURL $parent_media_url, $plugin_cookies, $sel_ndx = -1)
    {
        return Starnet_Epfs_Handler::invalidate_folders(array(static::ID),
            Action_Factory::update_regular_folder(
            $this->get_folder_range($parent_media_url, 0, $plugin_cookies),
            true,
            $sel_ndx)
        );
    }
}
