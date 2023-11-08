<?php
require_once 'lib/abstract_preloaded_regular_screen.php';

class Starnet_Vod_Genres_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'vod_genres';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        return array(GUI_EVENT_KEY_ENTER => User_Input_Handler_Registry::create_action($this, 'select_genre'));
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        if ($user_input->control_id === 'select_genre') {
            if (!isset($user_input->selected_media_url)) {
                return null;
            }

            $media_url = MediaURL::decode($user_input->selected_media_url);
            $genre_id = $media_url->genre_id;
            $caption = $this->plugin->vod->get_genre_caption($genre_id);
            $media_url_str = $this->plugin->vod->get_genre_media_url_str($genre_id);

            return Action_Factory::open_folder($media_url_str, $caption);
        }

        return null;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        $this->plugin->vod->ensure_genres_loaded();

        $genre_ids = $this->plugin->vod->get_genre_ids();

        $items = array();

        foreach ($genre_ids as $genre_id) {
            $caption = $this->plugin->vod->get_genre_caption($genre_id);
            $media_url_str = $this->plugin->vod->get_genre_media_url_str($genre_id);
            $icon_url = $this->plugin->vod->get_genre_icon_url($genre_id);

            $items[] = array(
                PluginRegularFolderItem::media_url => $media_url_str,
                PluginRegularFolderItem::caption => $caption,
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::icon_path => $icon_url,
                )
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
            $this->plugin->get_screen_view('list_1x11_info'),
        );
    }
}
