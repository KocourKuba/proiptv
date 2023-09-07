<?php
///////////////////////////////////////////////////////////////////////////

require_once 'abstract_screen.php';

abstract class Abstract_Regular_Screen extends Abstract_Screen
{
    /**
     * @return array[]
     */
    abstract public function get_folder_views();

    ///////////////////////////////////////////////////////////////////////
    // Screen interface

    /**
     * @inheritDoc
     */
    public function get_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);
        hd_debug_print($media_url->get_media_url_str(), LOG_LEVEL_DEBUG);

        $folder_views = $this->get_folder_views();
        $folder_view = $folder_views[$this->get_folder_view_index($plugin_cookies)];
        $folder_view[PluginRegularFolderView::actions] = $this->get_action_map($media_url, $plugin_cookies);
        $folder_view[PluginRegularFolderView::initial_range] = $this->get_folder_range($media_url, 0, $plugin_cookies);

        return array
        (
            PluginFolderView::multiple_views_supported => (count($folder_views) > 1 ? 1 : 0),
            PluginFolderView::archive => null,
            PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_REGULAR,
            PluginFolderView::data => $folder_view
        );
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array|null
     * @throws Exception
     */
    public function get_next_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
        $idx = $this->get_folder_view_index($plugin_cookies);
        $folder_views = $this->get_folder_views();
        if (++$idx >= count($folder_views)) {
            $idx = 0;
        }

        $folder_views_index = "screen." . static::ID . ".view_idx";
        $plugin_cookies->{$folder_views_index} = $idx;

        return $this->get_folder_view($media_url, $plugin_cookies);
    }

    ///////////////////////////////////////////////////////////////////////

    private function get_folder_view_index(&$plugin_cookies)
    {
        $folder_views_index = "screen." . static::ID . ".view_idx";
        $idx = isset($plugin_cookies->{$folder_views_index}) ? $plugin_cookies->{$folder_views_index} : 0;

        $folder_views = $this->get_folder_views();
        $cnt = count($folder_views);
        if ($idx < 0) {
            $idx = 0;
        } else if ($idx >= $cnt) {
            $idx = $cnt - 1;
        }

        return $idx;
    }
}
