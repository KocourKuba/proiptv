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

require_once 'lib/abstract_preloaded_regular_screen.php';

class Starnet_Vod_History_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'vod_history';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param MediaURL $media_url
     * @param object $plugin_cookies
     * @return array
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        return array(
            GUI_EVENT_KEY_ENTER => Action_Factory::open_folder(),
            GUI_EVENT_KEY_PLAY => Action_Factory::vod_play(),
            GUI_EVENT_KEY_B_GREEN => User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE, TR::t('delete')),
            GUI_EVENT_KEY_C_YELLOW => User_Input_Handler_Registry::create_action($this, ACTION_ITEMS_CLEAR, TR::t('clear_history')),
            GUI_EVENT_KEY_D_BLUE => User_Input_Handler_Registry::create_action($this, ACTION_ADD_FAV, TR::t('add_to_favorite')),
            GUI_EVENT_KEY_RETURN => User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN),
        );
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        if (!isset($user_input->selected_media_url)) {
            return null;
        }

        $movie_id = MediaURL::decode($user_input->selected_media_url)->movie_id;
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $sel_ndx = $user_input->sel_ndx;

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_RETURN:
                if (!$this->force_parent_reload) {
                    return Action_Factory::close_and_run();
                }

                $this->force_parent_reload = false;
                return Action_Factory::invalidate_folders(
                    array(
                        self::get_media_url_string(VOD_HISTORY_GROUP_ID),
                        Default_Dune_Plugin::get_group_mediaurl_str(VOD_FAV_GROUP_ID),
                        Default_Dune_Plugin::get_group_mediaurl_str(VOD_GROUP_ID)
                    ),
                    Action_Factory::close_and_run()
                );

            case ACTION_ITEM_DELETE:
                $this->force_parent_reload = true;
                $this->plugin->remove_vod_history($movie_id);
                if ($this->plugin->get_all_vod_history_count() !== 0) break;

                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

            case ACTION_ITEMS_CLEAR:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_clear_all_msg'),
                    $this, ACTION_CONFIRM_CLEAR_DLG_APPLY);

            case ACTION_CONFIRM_CLEAR_DLG_APPLY:
                $this->force_parent_reload = true;
                $this->plugin->clear_all_vod_history();
                if ($this->plugin->get_all_vod_history_count() !== 0) break;

                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

            case ACTION_ADD_FAV:
                $in_order = $this->plugin->is_channel_in_order(VOD_FAV_GROUP_ID, $movie_id);
                $opt_type = $in_order ? PLUGIN_FAVORITES_OP_REMOVE : PLUGIN_FAVORITES_OP_ADD;
                $this->plugin->change_vod_favorites($opt_type, $movie_id);
                $message = $in_order ? TR::t('deleted_from_favorite') : TR::t('added_to_favorite');
                return Action_Factory::show_title_dialog($message);
        }

        return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $sel_ndx);
    }

    /**
     * Get MediaURL string representation (json encoded)
     * *
     * @param string $group_id
     * @return false|string
     */
    public static function get_media_url_string($group_id)
    {
        return MediaURL::encode(array('screen_id' => static::ID, 'group_id' => $group_id));
    }

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $items = array();
        foreach ($this->plugin->get_all_vod_history() as $movie_info) {
            if (empty($movie_info)) continue;

            hd_debug_print("history info: " . json_encode($movie_info), true);
            $movie_id = $movie_info['movie_id'];
            $movie = $this->plugin->vod->get_loaded_movie($movie_id);
            if (is_null($movie)) continue;

            $short_movie = $this->plugin->vod->get_cached_short_movie($movie_id);

            if (is_null($short_movie)) {
                $caption = TR::t('vod_screen_no_film_info');
                $detailed_info = $caption;
                $poster_url = "missing://";
            } else {
                $history_cnt = $this->plugin->get_vod_history_count($movie_id);
                $caption = $short_movie->name;
                if ($history_cnt === 1) {
                    $view_date = format_datetime("d.m.Y H:i", $movie_info[COLUMN_TIMESTAMP]);
                    if ($movie_info[COLUMN_WATCHED] || $movie_info[COLUMN_DURATION] === -1) {
                        $detailed_info = TR::t('vod_screen_all_viewed__2', $caption, $view_date);
                    } else {
                        $detailed_info = TR::t('vod_screen_last_viewed__4',
                            $caption,
                            $view_date,
                            (int)((float)$movie_info[COLUMN_POSITION] / (float)$movie_info[COLUMN_DURATION] * 100),
                            format_duration_seconds($movie_info[COLUMN_POSITION])
                        );
                    }
                } else {
                    $all_watched = true;
                    $recent_timestamp = 0;
                    foreach ($this->plugin->get_vod_history($movie_id) as $history_item) {
                        $all_watched = $all_watched & ($history_item[COLUMN_WATCHED] === 1);
                        if ($history_item[COLUMN_TIMESTAMP] > $recent_timestamp) {
                            $recent_timestamp = $history_item[COLUMN_TIMESTAMP];
                        }
                    }

                    $view_date = format_datetime("d.m.Y H:i", $recent_timestamp);
                    if ($all_watched) {
                        $detailed_info = TR::t('vod_screen_all_viewed__2', $short_movie->name, $view_date);
                    } else {
                        $detailed_info = TR::t('vod_screen_last_viewed__2', $short_movie->name, $view_date);
                    }
                }

                $poster_url = $short_movie->poster_url;
            }

            if ($movie->has_seasons()) {
                $screen_media_url = Starnet_Vod_Seasons_List_Screen::get_media_url_string($movie->id);
            } else {
                $screen_media_url = Starnet_Vod_Series_List_Screen::get_media_url_string($movie->id);
            }

            $items[] = array(
                PluginRegularFolderItem::media_url => $screen_media_url,
                PluginRegularFolderItem::caption => $caption,
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::icon_path => $poster_url,
                    ViewItemParams::item_detailed_info => $detailed_info,
                    ViewItemParams::item_caption_color => DEF_LABEL_TEXT_COLOR_WHITE,
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
            $this->plugin->get_screen_view('list_1x12_vod_info_small'),
            $this->plugin->get_screen_view('list_1x10_vod_info_normal'),
            $this->plugin->get_screen_view('icons_5x2_movie_caption'),
            $this->plugin->get_screen_view('icons_5x2_movie_no_caption'),
        );
    }
}
