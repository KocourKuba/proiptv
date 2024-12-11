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
     * @param Object $plugin_cookies
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
            GUI_EVENT_KEY_STOP => User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_STOP),
        );
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        if (!isset($user_input->selected_media_url)) {
            return null;
        }

        $movie_id = MediaURL::decode($user_input->selected_media_url)->movie_id;
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_RETURN:
                if ($this->has_changes()) {
                    $this->plugin->save_history(true);
                    $this->set_no_changes();
                    return Action_Factory::invalidate_folders(
                        array(
                            self::get_media_url_string(HISTORY_MOVIES_GROUP_ID),
                            Starnet_Vod_Favorites_Screen::get_media_url_string(FAVORITES_MOVIE_GROUP_ID),
                            Starnet_Vod_Category_List_Screen::get_media_url_string(VOD_GROUP_ID)
                        ),
                        Action_Factory::close_and_run()
                    );
                }

                return Action_Factory::close_and_run();

            case GUI_EVENT_KEY_STOP:
                $this->plugin->save_orders(true);
                $this->set_no_changes();
                return Action_Factory::invalidate_all_folders($plugin_cookies);

            case ACTION_ITEM_DELETE:
                $history = $this->plugin->get_history(HISTORY_MOVIES);
                $history->erase($movie_id);
                $this->set_changes();
                if ($history->size() === 0) {
                    return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
                }

                $sel_ndx = $user_input->sel_ndx + 1;
                if ($sel_ndx < 0)
                    $sel_ndx = 0;
                $range = $this->get_folder_range($parent_media_url, 0, $plugin_cookies);
                return Action_Factory::update_regular_folder($range, true, $sel_ndx);

            case ACTION_ITEMS_CLEAR:
                $this->plugin->get_history(HISTORY_MOVIES)->clear();
                $this->set_changes();
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

            case ACTION_ADD_FAV:
                $fav_group = $this->plugin->vod->get_special_group(FAVORITES_MOVIE_GROUP_ID);
                $opt_type = $fav_group->in_items_order($movie_id) ? PLUGIN_FAVORITES_OP_REMOVE : PLUGIN_FAVORITES_OP_ADD;
                $this->plugin->vod->change_vod_favorites($opt_type, $movie_id);
                $this->plugin->save_orders(true);
                $message = $opt_type === PLUGIN_FAVORITES_OP_REMOVE ? TR::t('deleted_from_favorite') : TR::t('added_to_favorite');
                return Action_Factory::show_title_dialog($message);
        }

        return null;
    }

    /**
     * @param string $group_id
     * @return false|string
     */
    public static function get_media_url_string($group_id)
    {
        return MediaURL::encode(array('screen_id' => static::ID, 'group_id' => $group_id));
    }

    /**
     * @param MediaURL $media_url
     * @param Object $plugin_cookies
     * @return array
     * @throws Exception
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print("MediaUrl: " . $media_url, true);

        $items = array();
        foreach ($this->plugin->get_history(HISTORY_MOVIES) as $movie_id => $movie_infos) {
            if (empty($movie_infos)) continue;

            // $id = 95803
            // $id = serials_95803
            // $movie_infos = {"95803":{"watched":true,"position":null,"duration":null,"date":null}}
            // $movie_infos = {"1:1":{"watched":true,"position":null,"duration":null,"date":null}}
            hd_debug_print("history id: $movie_id, " . json_encode($movie_infos), true);
            $this->plugin->vod->ensure_movie_loaded($movie_id);
            $short_movie = $this->plugin->vod->get_cached_short_movie($movie_id);

            if (is_null($short_movie)) {
                $detailed_info = $caption = TR::t('vod_screen_no_film_info');
                $poster_url = "missing://";
            } else {
                $caption = $short_movie->name;
                $last_viewed = 0;
                if (is_array($movie_infos)) {
                    foreach ($movie_infos as $movie_info) {
                        if (isset($movie_info->watched) && $movie_info->date > $last_viewed) {
                            $last_viewed = $movie_info->date;
                            $info = $movie_info;
                        }
                    }
                } else {
                    $info = $movie_infos;
                }

                if (isset($info) && $info->date !== 0) {
                    if ($info->watched) {
                        $detailed_info = TR::t('vod_screen_all_viewed__2', $short_movie->name, format_datetime("d.m.Y H:i", $info->date));
                    } else if ($info->duration !== -1 && count($movie_infos) < 2) {
                        $percent = (int)((float)$info->position / (float)$info->duration * 100);
                        $detailed_info = TR::t('vod_screen_last_viewed__3', $short_movie->name, format_datetime("d.m.Y H:i", $info->date), $percent);
                    } else {
                        $detailed_info = TR::t('vod_screen_last_viewed__2', $short_movie->name, format_datetime("d.m.Y H:i", $info->date));
                    }
                } else {
                    $detailed_info = $short_movie->name;
                }

                $poster_url = $short_movie->poster_url;
            }

            $items[] = array(
                PluginRegularFolderItem::media_url => Starnet_Vod_Movie_Screen::get_media_url_string($movie_id),
                PluginRegularFolderItem::caption => $caption,
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::icon_path => $poster_url,
                    ViewItemParams::item_detailed_info => $detailed_info,
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
            $this->plugin->get_screen_view('list_1x11_small_info'),
            $this->plugin->get_screen_view('list_1x11_info'),
        );
    }
}
