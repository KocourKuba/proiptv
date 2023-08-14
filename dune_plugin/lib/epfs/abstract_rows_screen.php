<?php
///////////////////////////////////////////////////////////////////////////

require_once 'rows_screen.php';

abstract class Abstract_Rows_Screen implements Rows_Screen
{
    private $id;

    protected $plugin;

    protected $cur_sel_state;

    ///////////////////////////////////////////////////////////////////////

    protected function __construct($id, Default_Dune_Plugin $plugin)
    {
        $this->id = $id;
        $this->plugin = $plugin;
    }

    public function get_cur_sel_state_str()
    {
        return $this->cur_sel_state;
    }

    public function set_cur_sel_state_str($sel_state_str)
    {
        $this->cur_sel_state = $sel_state_str;
    }

    ///////////////////////////////////////////////////////////////////////

    public function get_handler_id()
    {
    	return $this->id . '_handler';
    }

    public function get_id()
    {
    	return $this->id;
    }

    public function get_category()
    {
    	return null;
    }

    public function get_timer(MediaURL $media_url, $plugin_cookies)
    {
        return null;
    }

    public function get_folder_type()
    {
    	return null;
    }

    ///////////////////////////////////////////////////////////////////////

    public function get_folder_view_v2(MediaURL $media_url, $sel_state, &$plugin_cookies)
    {
    	$this->set_cur_sel_state_str($sel_state);

        return array(
            PluginFolderView::folder_type => $this->get_folder_type(),
	        PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_ROWS,
	        PluginFolderView::multiple_views_supported => false,
	        PluginFolderView::archive => null,
	        PluginFolderView::data => array
            (
                PluginRowsFolderView::pane      => $this->get_rows_pane($media_url, $plugin_cookies),
                PluginRowsFolderView::sel_state => $this->get_cur_sel_state_str(),
                PluginRowsFolderView::actions   => $this->get_action_map($media_url, $plugin_cookies),
                PluginRowsFolderView::timer     => $this->get_timer($media_url, $plugin_cookies),
            )
        );
    }

    public function get_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
    	return $this->get_folder_view_v2($media_url, null, $plugin_cookies);
    }
}
