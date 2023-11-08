<?php
require_once 'lib/abstract_preloaded_regular_screen.php';

class Starnet_Vod_Seasons_List_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'vod_seasons';

    /**
     * @param string $movie_id
     * @param string|null $season_id
     * @return false|string
     */
    public static function get_media_url_string($movie_id, $season_id = null)
    {
        $arr = array('screen_id' => self::ID, 'movie_id' => $movie_id);
        if ($season_id !== null) {
            $arr['season_id'] = $season_id;
        }

        return MediaURL::encode($arr);
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        return null;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        return array(
            GUI_EVENT_KEY_ENTER => Action_Factory::open_folder(),
            GUI_EVENT_KEY_PLAY => Action_Factory::open_folder(),
        );
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $movie = $this->plugin->vod->get_loaded_movie($media_url->movie_id);
        if (is_null($movie)) {
            return array();
        }

        $items = array();

        foreach ($movie->season_list as $season) {
            hd_debug_print("movie_id: $movie->id season_id: $season->id season_name: $season->name", true);
            $items[] = array(
                PluginRegularFolderItem::media_url => Starnet_Vod_Series_List_Screen::get_media_url_string($movie->id, $season->id),
                PluginRegularFolderItem::caption => $season->name,
                PluginRegularFolderItem::view_item_params => array(ViewItemParams::icon_path => 'gui_skin://small_icons/folder.aai'),
            );
        }

        return $items;
    }

    /**
     * @inheritDoc
     */
    public function get_folder_views()
    {
        hd_debug_print(null, true);

        return array(
            $this->plugin->get_screen_view('list_1x11_small_info'),
            $this->plugin->get_screen_view('list_1x11_info'),
        );
    }
}
