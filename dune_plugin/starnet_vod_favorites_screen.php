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

        $actions[GUI_EVENT_KEY_ENTER] = $action_play;
        $actions[GUI_EVENT_KEY_PLAY] = $action_play;
        $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('left'));
        $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('right'));
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
        $movie_id = MediaURL::decode($user_input->selected_media_url)->movie_id;
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
                $cnt = $this->plugin->get_order_count(VOD_FAV_GROUP_ID) - 1;
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
                if ($this->plugin->get_order_count(VOD_FAV_GROUP_ID)) break;

                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

            case ACTION_ITEMS_CLEAR:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_clear_all_msg'),
                    $this, ACTION_CONFIRM_CLEAR_DLG_APPLY);

            case ACTION_CONFIRM_CLEAR_DLG_APPLY:
                $this->force_parent_reload = true;
                $this->plugin->change_vod_favorites(ACTION_ITEMS_CLEAR, null);
                if ($this->plugin->get_order_count(VOD_FAV_GROUP_ID)) break;

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
    public static function make_group_media_url_str($group_id)
    {
        return MediaURL::encode(array(PARAM_SCREEN_ID => static::ID, 'group_id' => $group_id));
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $items = array();
        foreach ($this->plugin->get_channels_order(VOD_FAV_GROUP_ID) as $movie_id) {
            $movie = $this->plugin->vod->get_loaded_movie($movie_id);
            if (is_null($movie)) continue;

            $items[] = array(
                PluginRegularFolderItem::media_url => Starnet_Vod_Movie_Screen::make_vod_media_url_str($movie_id),
                PluginRegularFolderItem::caption => $movie->movie_info[PluginMovie::name],
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::icon_path => $movie->movie_info[PluginMovie::poster_url],
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
            $this->plugin->get_screen_view('list_1x12_vod_info_small'),
            $this->plugin->get_screen_view('list_1x10_vod_info_normal'),
        );
    }
}
