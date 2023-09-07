<?php
///////////////////////////////////////////////////////////////////////////

require_once 'abstract_screen.php';

abstract class Abstract_Controls_Screen extends Abstract_Screen
{
    const CONTROLS_WIDTH = 850;

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    abstract public function get_control_defs(MediaURL $media_url, &$plugin_cookies);

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array|null
     */
    public function get_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);
        hd_debug_print($media_url->get_media_url_str(), LOG_LEVEL_DEBUG);

        $defs = $this->get_control_defs($media_url, $plugin_cookies);

        $folder_view = array
        (
            PluginControlsFolderView::defs => $defs,
            PluginControlsFolderView::initial_sel_ndx => -1,
            PluginControlsFolderView::actions => $this->get_action_map($media_url, $plugin_cookies),
            PluginControlsFolderView::params => array(
                PluginFolderViewParams::paint_path_box => true,
                PluginFolderViewParams::paint_content_box_background => true,
                PluginFolderViewParams::background_url => $this->plugin->get_background_image(),
            ),
        );

        return array
        (
            PluginFolderView::multiple_views_supported => false,
            PluginFolderView::archive => null,
            PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_CONTROLS,
            PluginFolderView::data => $folder_view,
        );
    }
}
