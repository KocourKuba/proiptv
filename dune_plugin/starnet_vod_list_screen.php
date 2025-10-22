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

class Starnet_Vod_List_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'vod_list';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param MediaURL $media_url
     * @param object $plugin_cookies
     * @return array
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        $action_play = User_Input_Handler_Registry::create_action($this, ACTION_PLAY_ITEM);

        $actions[GUI_EVENT_KEY_ENTER] = $action_play;
        $actions[GUI_EVENT_KEY_PLAY] = $action_play;
        $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('up'));
        $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('down'));
        $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE, TR::t('delete'));
        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);
        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        if (!isset($user_input->selected_media_url)) {
            hd_debug_print("user input selected media url not set", true);
            return null;
        }

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $selected_media_url = MediaURL::decode($user_input->selected_media_url);
        $sel_ndx = $user_input->sel_ndx;

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_RETURN:
                if (!$this->force_parent_reload) {
                    return Action_Factory::close_and_run();
                }

                $this->force_parent_reload = false;
                hd_debug_print("Force parent reload", true);
                return Action_Factory::close_and_run(
                    User_Input_Handler_Registry::create_screen_action(
                        Starnet_Vod_Category_List_Screen::ID,
                        ACTION_INVALIDATE
                    )
                );

            case ACTION_PLAY_ITEM:
                try {
                    $this->update_series_list();
                    $vod_info = $this->plugin->vod->get_vod_info($selected_media_url);
                    $post_action = $this->plugin->vod->vod_player_exec($vod_info, isset($user_input->external));
                } catch (Exception $ex) {
                    hd_debug_print("Movie can't played");
                    print_backtrace_exception($ex);
                    return Action_Factory::show_title_dialog(TR::t('err_channel_cant_start'), TR::t('warn_msg2__1', $ex->getMessage()));
                }

                return $post_action;

            case ACTION_ITEM_UP:
                $sel_ndx--;
                if ($sel_ndx < 0) {
                    return null;
                }
                $this->force_parent_reload = true;
                $this->plugin->arrange_channels_order_rows(VOD_LIST_GROUP_ID, $selected_media_url->episode_id, Ordered_Array::UP);
                break;

            case ACTION_ITEM_DOWN:
                $cnt = $this->plugin->get_order_count(VOD_LIST_GROUP_ID) - 1;
                $sel_ndx++;
                hd_debug_print("Cnt: $cnt, sel_ndx: $sel_ndx");
                if ($sel_ndx > $cnt) {
                    return null;
                }
                $this->force_parent_reload = true;
                $this->plugin->arrange_channels_order_rows(VOD_LIST_GROUP_ID, $selected_media_url->episode_id, Ordered_Array::DOWN);
                break;

            case ACTION_ITEM_DELETE:
                $this->force_parent_reload = true;
                $this->plugin->change_channels_order(VOD_LIST_GROUP_ID, $selected_media_url->episode_id, true);
                if ($this->plugin->get_order_count(VOD_LIST_GROUP_ID)) break;

                $this->plugin->vod->toggle_special_group(VOD_LIST_GROUP_ID, true);
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

            case ACTION_ITEMS_CLEAR:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_clear_all_msg'),
                    $this, ACTION_CONFIRM_CLEAR_DLG_APPLY);

            case ACTION_CONFIRM_CLEAR_DLG_APPLY:
                $this->force_parent_reload = true;
                $this->plugin->remove_channels_order(VOD_LIST_GROUP_ID);

                $this->plugin->vod->toggle_special_group(VOD_LIST_GROUP_ID, true);
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

            case ACTION_WATCHED:
                $movie = $this->plugin->vod->get_loaded_movie($selected_media_url->episode_id);
                if (is_null($movie)) break;

                $value = $this->plugin->get_vod_history_params($selected_media_url->episode_id, $selected_media_url->episode_id, COLUMN_WATCHED);

                if ($value) {
                    $this->plugin->remove_vod_history_part($selected_media_url->episode_id, $selected_media_url->episode_id);
                } else {
                    $this->plugin->set_vod_history(
                        $selected_media_url->episode_id,
                        $selected_media_url->episode_id,
                        array(COLUMN_WATCHED => 1, COLUMN_TIMESTAMP => time())
                    );
                }

                return Action_Factory::invalidate_folders(array(
                        $user_input->parent_media_url,
                        Default_Dune_Plugin::get_group_media_url_str(VOD_GROUP_ID),
                        Default_Dune_Plugin::get_group_media_url_str(VOD_HISTORY_GROUP_ID)
                    )
                );

            case GUI_EVENT_KEY_POPUP_MENU:
                $menu_items[] = $this->plugin->create_menu_item($this, ACTION_WATCHED, TR::t('vod_screen_viewed_not_viewed'), "hide.png");
                $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
                $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_CLEAR, TR::t('clear_list'), "brush.png");
                return Action_Factory::show_popup_menu($menu_items);
        }

        return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $sel_ndx);
    }

    /**
     * @param string $movie_id
     * @return false|string
     */
    public static function make_group_media_url_str($movie_id)
    {
        return MediaURL::encode(array(PARAM_SCREEN_ID => static::ID, 'group_id' => VOD_LIST_GROUP_ID, 'movie_id' => VOD_LIST_GROUP_ID, 'episode_id' => $movie_id));
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $items = array();
        foreach ($this->plugin->get_channels_order(VOD_LIST_GROUP_ID) as $movie_id) {
            $movie = $this->plugin->vod->get_loaded_movie($movie_id);
            if (is_null($movie)) continue;

            $movie_history = $this->plugin->get_vod_history($movie_id);
            $detailed_info = '';
            $caption = $movie->movie_info[PluginMovie::name];
            $color = DEF_LABEL_TEXT_COLOR_WHITE;
            foreach ($movie_history as $movie_info) {
                $view_date = format_datetime("d.m.Y H:i", $movie_info[COLUMN_TIMESTAMP]);
                if ($movie_info[COLUMN_WATCHED] || $movie_info[COLUMN_DURATION] === -1) {
                    $detailed_info = TR::t('vod_screen_all_viewed__2', $caption, $view_date);
                    $color = DEF_LABEL_TEXT_COLOR_SKYBLUE;
                } else {
                    $detailed_info = TR::t('vod_screen_last_viewed__4',
                        $caption,
                        $view_date,
                        (int)((float)$movie_info[COLUMN_POSITION] / (float)$movie_info[COLUMN_DURATION] * 100),
                        format_duration_seconds($movie_info[COLUMN_POSITION])
                    );
                    $color = DEF_LABEL_TEXT_COLOR_TURQUOISE;
                }
                break;
            }

            $items[] = array(
                PluginRegularFolderItem::media_url => self::make_group_media_url_str($movie_id),
                PluginRegularFolderItem::caption => $caption,
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::icon_path => $movie->movie_info[PluginMovie::poster_url],
                    ViewItemParams::item_detailed_info => $detailed_info,
                    ViewItemParams::item_caption_color => $color,
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

    /**
     * @return void
     * @throws Exception
     */
    private function update_series_list()
    {
        $list_movie = $this->plugin->vod->get_loaded_movie(VOD_LIST_GROUP_ID);
        if (empty($list_movie)) {
            hd_debug_print("vod list movie not found");
            return;
        }

        $list_movie->series_list = array();
        foreach ($this->plugin->get_channels_order(VOD_LIST_GROUP_ID) as $movie_id) {
            $movie = $this->plugin->vod->get_loaded_movie($movie_id);
            if (is_null($movie)) continue;

            $series = new Movie_Series($movie_id, $movie->movie_info[PluginMovie::name], $movie->series_list[$movie_id]->playback_url);
            $series->playback_url_is_stream_url = $movie->series_list[$movie_id]->playback_url_is_stream_url;
            $list_movie->series_list[] = $series;
        }
        $this->plugin->vod->set_cached_movie($list_movie);
    }
}
