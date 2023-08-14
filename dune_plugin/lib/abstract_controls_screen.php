<?php
///////////////////////////////////////////////////////////////////////////

require_once 'screen.php';

abstract class Abstract_Controls_Screen implements Screen
{
    const CONTROLS_WIDTH = 850;

    private $id;
    protected $plugin;

    ///////////////////////////////////////////////////////////////////////

    protected function __construct($id, Default_Dune_Plugin $plugin)
    {
        $this->id = $id;
        $this->plugin = $plugin;
    }

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    abstract public function get_control_defs(MediaURL $media_url, &$plugin_cookies);

    public function get_id()
    {
        return $this->id;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array|null
     */
    public function get_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
        $defs = $this->get_control_defs($media_url, $plugin_cookies);

        $folder_view = array
        (
            PluginControlsFolderView::defs => $defs,
            PluginControlsFolderView::initial_sel_ndx => -1,
            PluginControlsFolderView::params => array(
                PluginFolderViewParams::paint_path_box => true,
                PluginFolderViewParams::paint_content_box_background => true,
                PluginFolderViewParams::background_url => $this->plugin->plugin_info['app_background'],
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

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        return array();
    }

    /**
     * @param MediaURL $media_url
     * @param int $from_ndx
     * @param $plugin_cookies
     * @return array
     */
    public function get_folder_range(MediaURL $media_url, $from_ndx, &$plugin_cookies)
    {
        return array();
    }

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    public function get_next_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
        return array();
    }
}
