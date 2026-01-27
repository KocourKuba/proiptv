<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
 * Original code from DUNE HD
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to
 * deal in the Software without restriction, including without limitation the
 * rights to use, copy, modify, merge, publish, distribute, sublicense
 * of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included
 * in all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL
 * THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
 * FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER
 * DEALINGS IN THE SOFTWARE.
 */

require_once 'lib/abstract_controls_screen.php';
require_once 'lib/user_input_handler.php';
require_once 'starnet_vod_series_list_screen.php';

class Starnet_Vod_Movie_Screen extends Abstract_Controls_Screen
{
    const ID = 'vod_movie';

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        return null;
    }

    /**
     * @inheritDoc
     */
    public function get_control_defs(MediaURL $media_url, &$plugin_cookies)
    {
        return array();
    }

    /**
     * @param MediaURL $media_url
     * @param object $plugin_cookies
     * @return array|null
     * @throws Exception
     */
    public function get_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);
        hd_debug_print("movie id: $media_url->movie_id", true);

        $movie = $this->plugin->vod->get_loaded_movie($media_url->movie_id);
        if (is_null($movie)) {
            $movie = new Movie($media_url->movie_id, $this->plugin);
            hd_debug_print("empty movie or no series data");
            $movie_info = $movie->get_movie_info();
            $movie_info[PluginMovie::description] = TR::t('warn_msg5');
            return array(
                PluginFolderView::multiple_views_supported => false,
                PluginFolderView::archive => null,
                PluginFolderView::view_kind => PLUGIN_FOLDER_VIEW_MOVIE,
                PluginFolderView::data => array(
                    PluginMovieFolderView::movie => $movie_info,
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

        $movie_info = $movie->get_movie_info();
        $fav_ids = $this->plugin->get_channels_order(VOD_FAV_GROUP_ID);
        $movie_id = $movie->get_id();
        $right_button_caption = in_array($movie_id, $fav_ids) ? TR::t('delete_from_favorite') : TR::t('add_to_favorite');
        $right_button_action = User_Input_Handler_Registry::create_action($this, PARAM_FAVORITES, null, array('movie_id' => $movie_id));

        if ($movie->has_seasons()) {
            $screen_media_url = Starnet_Vod_Seasons_List_Screen::make_vod_media_url_str($movie_id);
        } else {
            $screen_media_url = Starnet_Vod_Series_List_Screen::make_vod_media_url_str($movie_id);
        }

        $movie_folder_view = array(
            PluginMovieFolderView::movie => $movie_info,
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

    ///////////////////////////////////////////////////////////////////////

    /**
     * Get MediaURL string representation (json encoded)
     * *
     * @param string $movie_id
     * @param string|false $name
     * @param string|false $poster_url
     * @param string|false $info
     * @return false|string
     */
    public static function make_vod_media_url_str($movie_id, $name = false, $poster_url = false, $info = false)
    {
        $arr = array(PARAM_SCREEN_ID => static::ID, 'movie_id' => $movie_id);
        if ($name !== false) {
            $arr['name'] = $name;
        }

        if ($poster_url !== false) {
            $arr['poster_url'] = $poster_url;
        }

        if ($info !== false) {
            $arr['info'] = $info;
        }

        return MediaURL::encode($arr);
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        if ($user_input->control_id === PARAM_FAVORITES) {
            $movie_id = $user_input->movie_id;

            $in_order = $this->plugin->is_channel_in_order(VOD_FAV_GROUP_ID, $movie_id);
            $opt_type = $in_order ? PLUGIN_FAVORITES_OP_REMOVE : PLUGIN_FAVORITES_OP_ADD;
            $this->plugin->change_vod_favorites($opt_type, $movie_id);

            $invalidate_urls = array(
                Default_Dune_Plugin::get_group_media_url_str(VOD_FAV_GROUP_ID),
                Default_Dune_Plugin::get_group_media_url_str(VOD_HISTORY_GROUP_ID),
                Default_Dune_Plugin::get_group_media_url_str(VOD_GROUP_ID)
            );

            $actions[] = Action_Factory::show_title_dialog(TR::t('information'), $in_order ? TR::t('deleted_from_favorite') : TR::t('added_to_favorite'));
            $actions[] = Action_Factory::invalidate_folders($invalidate_urls);
            $actions[] = Action_Factory::close_and_run();
            return Action_Factory::composite($actions);
        }

        return null;
    }
}
