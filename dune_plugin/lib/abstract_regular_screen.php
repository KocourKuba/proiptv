<?php
///////////////////////////////////////////////////////////////////////////

require_once 'screen.php';

abstract class Abstract_Regular_Screen implements Screen
{
    const ID = 'abstract_regular_screen';

    private $folder_view_index_attr_name;

    protected $plugin;

    ///////////////////////////////////////////////////////////////////////

    public function __construct(Default_Dune_Plugin $plugin)
    {
        $this->plugin = $plugin;
        $this->plugin->create_screen($this);
        $this->set_default_folder_view_index_attr_name();
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

    ///////////////////////////////////////////////////////////////////////

    protected function set_folder_view_index_attr_name($s)
    {
        $this->folder_view_index_attr_name = $s;
    }

    protected function set_default_folder_view_index_attr_name()
    {
        $this->folder_view_index_attr_name = "screen." . static::ID . ".view_idx";
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array|null
     */
    public function get_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
        //hd_debug_print("----> count: " . count($this->folder_views));

        $idx = $this->get_folder_view_index($plugin_cookies);
        $folder_views = $this->get_folder_views();
        $folder_view = $folder_views[$idx];
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

        $plugin_cookies->{$this->folder_view_index_attr_name} = $idx;

        return $this->get_folder_view($media_url, $plugin_cookies);
    }

    ///////////////////////////////////////////////////////////////////////

    private function get_folder_view_index(&$plugin_cookies)
    {
        if (!isset($plugin_cookies->{$this->folder_view_index_attr_name})) {
            return 0;
        }

        $folder_views = $this->get_folder_views();
        $cnt = count($folder_views);
        $idx = $plugin_cookies->{$this->folder_view_index_attr_name};
        if ($idx < 0) {
            $idx = 0;
        } else if ($idx >= $cnt) {
            $idx = $cnt - 1;
        }

        return $idx;
    }

    /**
     * @return array[]
     */
    abstract public function get_folder_views();
}
