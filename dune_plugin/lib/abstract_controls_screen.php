<?php
///////////////////////////////////////////////////////////////////////////

require_once 'screen.php';

abstract class Abstract_Controls_Screen implements Screen
{
    const ID = 'abstract_controls_screen';

    const CONTROLS_WIDTH = 850;

    protected $plugin;
    protected $need_update_epfs = false;

    ///////////////////////////////////////////////////////////////////////

    public function __construct(Default_Dune_Plugin $plugin)
    {
        $this->plugin = $plugin;
        $plugin->create_screen($this);
    }

    public static function get_id()
    {
        return static::ID;
    }

    public static function get_handler_id()
    {
        return static::get_id() . '_handler';
    }

    /**
     * @return false|string
     */
    public static function get_media_url_str()
    {
        return MediaURL::encode(array('screen_id' => static::ID));
    }

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
        $defs = $this->get_control_defs($media_url, $plugin_cookies);

        $folder_view = array
        (
            PluginControlsFolderView::defs => $defs,
            PluginControlsFolderView::initial_sel_ndx => -1,
            PluginControlsFolderView::actions => $this->get_action_map($media_url, $plugin_cookies),
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
     * @return array|null
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        return null;
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
