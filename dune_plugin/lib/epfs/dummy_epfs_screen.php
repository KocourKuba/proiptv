<?php

require_once 'abstract_rows_screen.php';
require_once 'rows_factory.php';
require_once 'gcomps_factory.php';
require_once 'gcomp_geom.php';

class Dummy_Epfs_Screen extends Abstract_Rows_Screen implements User_Input_Handler
{
    const ID = 'dummy_epf';

    ///////////////////////////////////////////////////////////////////////

    public function __construct(Default_Dune_Plugin $plugin)
    {
        parent::__construct(self::ID, $plugin);
    }

    /**
     * @param $no_internet
     * @return false|string
     */
    public static function get_media_url_str($no_internet)
    {
        return MediaURL::encode(array('no_internet' => $no_internet));
    }

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array|null
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        return array(GUI_EVENT_KEY_ENTER => User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER));
    }

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    public function get_rows_pane(MediaURL $media_url, $plugin_cookies)
    {
        hd_print(__METHOD__);
        $defs = array();

        $caption = $media_url->no_internet ? TR::t('err_no_internet') : TR::t('loading');

        $rows[] = Rows_Factory::vgap_row(50);
        $defs[] = GComps_Factory::label_v2(GComp_Geom::place_center(), null, $caption, 1, "#AFAFA0FF", 60);
        $rows[] = Rows_Factory::gcomps_row("single_row", $defs, null, 1920, 500);

        return Rows_Factory::pane($rows);
    }

    /**
     * @param $user_input
     * @param $plugin_cookies
     * @return array|null
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        //dump_input_handler(__METHOD__, $user_input);
        return null;
    }

    /**
     * @param $no_internet
     * @param $plugin_cookies
     * @return array|null
     */
    public function get_folder_view_for_epf($no_internet, &$plugin_cookies)
    {
        hd_print(__METHOD__);
        $media_url = MediaURL::decode(self::get_media_url_str($no_internet));

        return $this->get_folder_view($media_url, $plugin_cookies);
    }

    /**
     * @param MediaURL $media_url
     * @param int $from_ndx
     * @param $plugin_cookies
     * @return array|null
     */
    public function get_folder_range(MediaURL $media_url, $from_ndx, &$plugin_cookies)
    {
        return null;
    }

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array|null
     */
    public function get_next_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
        return null;
    }
}
