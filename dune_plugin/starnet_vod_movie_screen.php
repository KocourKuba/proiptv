<?php
require_once 'lib/abstract_controls_screen.php';
require_once 'lib/user_input_handler.php';
require_once 'starnet_vod_series_list_screen.php';

class Starnet_Vod_Movie_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'vod_movie';

    /**
     * @param string $movie_id
     * @param string|false $name
     * @param string|false $poster_url
     * @param string|false $info
     * @return false|string
     */
    public static function get_media_url_string($movie_id, $name = false, $poster_url = false, $info = false)
    {
        $arr = array('screen_id' => self::ID, 'movie_id' => $movie_id);
        if ($name !== false) {
            $arr['name'] = $name;
        }

        if ($poster_url !== false) {
            $arr['poster_url'] = $poster_url;
        }

        if ($info !== false) {
            $arr['info'] = $info;
        }

        //hd_debug_print("Movie ID: $movie_id, Movie name: $name, Movie Poster: $poster_url", true);

        return MediaURL::encode($arr);
    }

    /**
     * @inheritDoc
     */
    public function get_control_defs(MediaURL $media_url, &$plugin_cookies)
    {
        return array();
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param MediaURL $media_url
     * @param $plugin_cookies
     * @return array|null
     * @throws Exception
     */
    public function get_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url->get_media_url_str(), true);

        $movie = $this->plugin->vod->get_loaded_movie($media_url->movie_id);
        if (is_null($movie)) {
            $movie = new Movie($media_url->movie_id, $this->plugin);
            hd_debug_print("empty movie or no series data");
            $movie->description = TR::t('warn_msg5');
            return array(
                PluginFolderView::multiple_views_supported => false,
                PluginFolderView::archive => null,
                PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_MOVIE,
                PluginFolderView::data => array(
                    PluginMovieFolderView::movie => $movie->get_movie_array(),
                    PluginMovieFolderView::left_button_caption => TR::t('ok'),
                    PluginMovieFolderView::left_button_action => Action_Factory::close_and_run(),
                    PluginMovieFolderView::has_right_button => false,
                    PluginMovieFolderView::has_multiple_series => false,
                    PluginMovieFolderView::series_media_url => null,
                    PluginMovieFolderView::params => array(
                        PluginFolderViewParams::paint_path_box => false,
                        PluginFolderViewParams::paint_content_box_background => true,
                        PluginFolderViewParams::background_url => $this->plugin->plugin_info['app_background']
                    )
                ),
            );
        }

        hd_debug_print("movie: " . raw_json_encode($movie->get_movie_array()));

        $right_button_caption = $this->plugin->vod->get_special_group(FAVORITES_MOVIE_GROUP_ID)->in_items_order($movie->id)
            ? TR::t('delete_from_favorite') : TR::t('add_to_favorite');
        $right_button_action = User_Input_Handler_Registry::create_action($this, PARAM_FAVORITES, null, array('movie_id' => $movie->id));

        if (isset($movie->season_list)) {
            $screen_media_url = Starnet_Vod_Seasons_List_Screen::get_media_url_string($movie->id);
        } else {
            $screen_media_url = Starnet_Vod_Series_List_Screen::get_media_url_string($movie->id);
        }

        $movie_folder_view = array(
            PluginMovieFolderView::movie => $movie->get_movie_array(),
            PluginMovieFolderView::has_right_button => true,
            PluginMovieFolderView::right_button_caption => $right_button_caption,
            PluginMovieFolderView::right_button_action => $right_button_action,
            PluginMovieFolderView::has_multiple_series => true,
            PluginMovieFolderView::series_media_url => $screen_media_url,
            PluginMovieFolderView::params => array(
                PluginFolderViewParams::paint_path_box => false,
                PluginFolderViewParams::paint_content_box_background => true,
                PluginFolderViewParams::background_url => $this->plugin->plugin_info['app_background']
            )
        );

        return array(
            PluginFolderView::multiple_views_supported => false,
            PluginFolderView::archive => null,
            PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_MOVIE,
            PluginFolderView::data => $movie_folder_view,
        );
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        if ($user_input->control_id === PARAM_FAVORITES) {
            $movie_id = $user_input->movie_id;

            $fav_group = $this->plugin->tv->get_special_group(FAVORITES_GROUP_ID);
            $opt_type = $fav_group->in_items_order($movie_id) ? PLUGIN_FAVORITES_OP_REMOVE : PLUGIN_FAVORITES_OP_ADD;
            $this->plugin->vod->change_vod_favorites($opt_type, $movie_id);
            $this->plugin->save_orders(true);
            return Action_Factory::show_title_dialog(
                $opt_type === PLUGIN_FAVORITES_OP_REMOVE ? TR::t('deleted_from_favorite') : TR::t('added_to_favorite'),
                Action_Factory::invalidate_folders(
                    array(
                        self::get_media_url_string(FAVORITES_MOVIE_GROUP_ID),
                        Starnet_Vod_History_Screen::get_media_url_string(HISTORY_MOVIES_GROUP_ID),
                        Starnet_Vod_Category_List_Screen::get_media_url_string(VOD_GROUP_ID)
                    ),
                    Action_Factory::close_and_run()
                )
            );
        }

        return null;
    }
}
