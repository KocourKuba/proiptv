<?php
///////////////////////////////////////////////////////////////////////////

require_once 'screen.php';

abstract class Abstract_Regular_Screen implements Screen
{
    private $id;

    private $folder_views;
    private $folder_view_index_attr_name;

    protected $plugin;

    ///////////////////////////////////////////////////////////////////////

    protected function __construct($id, Default_Dune_Plugin $plugin, $folder_views)
    {
        $this->id = $id;
        $this->plugin = $plugin;
        $this->folder_views = $folder_views;
        $this->set_default_folder_view_index_attr_name();
    }

    ///////////////////////////////////////////////////////////////////////

    protected function set_folder_view_index_attr_name($s)
    {
        $this->folder_view_index_attr_name = $s;
    }

    protected function set_default_folder_view_index_attr_name()
    {
        $this->folder_view_index_attr_name = "screen." . $this->id . ".view_idx";
    }

    ///////////////////////////////////////////////////////////////////////

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
        //hd_print("----> count: " . count($this->folder_views));

        $idx = $this->get_folder_view_index($plugin_cookies);

        $folder_view = $this->folder_views[$idx];

        $folder_view[PluginRegularFolderView::actions] = $this->get_action_map($media_url, $plugin_cookies);

        $folder_view[PluginRegularFolderView::initial_range] = $this->get_folder_range($media_url, 0, $plugin_cookies);

        return array
        (
            PluginFolderView::multiple_views_supported => (count($this->folder_views) > 1 ? 1 : 0),
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

        ++$idx;

        if ($idx >= count($this->folder_views)) {
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

        $idx = $plugin_cookies->{$this->folder_view_index_attr_name};

        $cnt = count($this->folder_views);

        if ($idx < 0) {
            $idx = 0;
        } else if ($idx >= $cnt) {
            $idx = $cnt - 1;
        }

        return $idx;
    }
}
