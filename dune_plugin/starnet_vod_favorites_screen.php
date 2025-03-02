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

class Starnet_Vod_Favorites_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'vod_favorites';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param MediaURL $media_url
     * @param object $plugin_cookies
     * @return array
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        $action_play = User_Input_Handler_Registry::create_action($this, ACTION_PLAY_ITEM);

        return array(
            GUI_EVENT_KEY_ENTER => $action_play,
            GUI_EVENT_KEY_PLAY => $action_play,
            GUI_EVENT_KEY_B_GREEN => User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('left')),
            GUI_EVENT_KEY_C_YELLOW => User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('right')),
            GUI_EVENT_KEY_D_BLUE => User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE, TR::t('delete')),
            GUI_EVENT_KEY_POPUP_MENU => User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU),
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

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $movie_id = MediaURL::decode($user_input->selected_media_url)->movie_id;
        $sel_ndx = $user_input->sel_ndx;

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_RETURN:
                if (!$this->force_parent_reload) {
                    return Action_Factory::close_and_run();
                }

                $this->force_parent_reload = false;
                return Action_Factory::close_and_run(
                    User_Input_Handler_Registry::create_action_screen(
                        Starnet_Vod_Category_List_Screen::ID,
                        ACTION_INVALIDATE
                    )
                );

            case ACTION_PLAY_ITEM:
                return Action_Factory::open_folder();

            case ACTION_ITEM_UP:
                $sel_ndx--;
                if ($sel_ndx < 0) {
                    return null;
                }
                $this->force_parent_reload = true;
                $this->plugin->change_vod_favorites(PLUGIN_FAVORITES_OP_MOVE_UP, $movie_id);
                break;

            case ACTION_ITEM_DOWN:
                $cnt = $this->plugin->get_channels_order_count(VOD_FAV_GROUP_ID) - 1;
                $sel_ndx++;
                if ($sel_ndx > $cnt) {
                    return null;
                }
                $this->force_parent_reload = true;
                $this->plugin->change_vod_favorites(PLUGIN_FAVORITES_OP_MOVE_DOWN, $movie_id);
                break;

            case ACTION_ITEM_DELETE:
                $this->force_parent_reload = true;
                $this->plugin->change_vod_favorites(PLUGIN_FAVORITES_OP_REMOVE, $movie_id);
                if ($this->plugin->get_channels_order_count(VOD_FAV_GROUP_ID) != 0) break;

                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

            case ACTION_ITEMS_CLEAR:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_clear_all_msg'),
                    $this, ACTION_CONFIRM_CLEAR_DLG_APPLY);

            case ACTION_CONFIRM_CLEAR_DLG_APPLY:
                $this->force_parent_reload = true;
                $this->plugin->change_vod_favorites(ACTION_ITEMS_CLEAR, null);
                if ($this->plugin->get_channels_order_count(VOD_FAV_GROUP_ID) != 0) break;

                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

            case GUI_EVENT_KEY_POPUP_MENU:
                $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_CLEAR, TR::t('clear_favorites'), "brush.png");
                return Action_Factory::show_popup_menu($menu_items);
        }

        return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $sel_ndx);
    }

    /**
     * @param string $group_id
     * @return false|string
     */
    public static function get_media_url_string($group_id)
    {
        return MediaURL::encode(array('screen_id' => static::ID, 'group_id' => $group_id));
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
        foreach ($this->plugin->get_channels_by_order(VOD_FAV_GROUP_ID) as $movie_row) {
            $this->plugin->vod->ensure_movie_loaded($movie_row[COLUMN_CHANNEL_ID]);
            $short_movie = $this->plugin->vod->get_cached_short_movie($movie_row[COLUMN_CHANNEL_ID]);

            if (is_null($short_movie)) {
                $caption = TR::t('vod_screen_no_film_info');
                $poster_url = "missing://";
            } else {
                $caption = $short_movie->name;
                $poster_url = $short_movie->poster_url;
            }

            $items[] = array(
                PluginRegularFolderItem::media_url => Starnet_Vod_Movie_Screen::get_media_url_string($movie_row[COLUMN_CHANNEL_ID]),
                PluginRegularFolderItem::caption => $caption,
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::icon_path => $poster_url,
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
            $this->plugin->get_screen_view('icons_5x2_movie_no_caption'),
            $this->plugin->get_screen_view('list_1x12_vod_info_normal'),
        );
    }
}
