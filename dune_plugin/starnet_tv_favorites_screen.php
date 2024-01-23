<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
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

class Starnet_Tv_Favorites_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'tv_favorites';

    /**
     * @param string $group_id
     * @return false|string
     */
    public static function get_media_url_string($group_id)
    {
        return MediaURL::encode(array('screen_id' => static::ID, 'group_id' => $group_id, 'is_favorites' => true));
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $actions = array();
        $action_play = User_Input_Handler_Registry::create_action($this, ACTION_PLAY_ITEM);

        $actions[GUI_EVENT_KEY_ENTER]  = $action_play;
        $actions[GUI_EVENT_KEY_PLAY]   = $action_play;

        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_TOP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_TOP_MENU);
        $actions[GUI_EVENT_KEY_STOP] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_STOP);

        if ($this->plugin->tv->get_special_group(FAVORITES_GROUP_ID)->get_items_order()->size() !== 0) {
            $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('up'));
            $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('down'));
            $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE, TR::t('delete'));
            $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);
        }

        return $actions;
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

        $selected_media_url = MediaURL::decode($user_input->selected_media_url);
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $fav_group = $this->plugin->tv->get_special_group(FAVORITES_GROUP_ID);

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                if ($this->has_changes()) {
                    $this->plugin->save_orders(true);
                    $this->set_no_changes();
                    return Action_Factory::invalidate_all_folders($plugin_cookies, Action_Factory::close_and_run());
                }

                return Action_Factory::close_and_run();

            case GUI_EVENT_KEY_STOP:
                $this->plugin->save_orders(true);
                $this->set_no_changes();
                return Action_Factory::invalidate_all_folders($plugin_cookies);

            case ACTION_PLAY_ITEM:
                try {
                    $post_action = $this->plugin->tv->tv_player_exec($selected_media_url);
                } catch (Exception $ex) {
                    hd_debug_print("Channel can't played, exception info: " . $ex->getMessage());
                    return Action_Factory::show_title_dialog(TR::t('err_channel_cant_start'),
                        null,
                        TR::t('warn_msg2__1', $ex->getMessage()));
                }

                if ($this->has_changes()) {
                    $this->plugin->save_orders(true);
                    $this->set_no_changes();
                    Starnet_Epfs_Handler::update_all_epfs($plugin_cookies);
                }

                return $post_action;

            case ACTION_ITEM_UP:
                $user_input->sel_ndx--;
                if ($user_input->sel_ndx < 0) {
                    $user_input->sel_ndx = 0;
                }
                $this->set_changes();
                $this->plugin->tv->change_tv_favorites(PLUGIN_FAVORITES_OP_MOVE_UP, $selected_media_url->channel_id);
                return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->sel_ndx);

            case ACTION_ITEM_DOWN:
                $user_input->sel_ndx++;
                if ($user_input->sel_ndx >= $fav_group->get_items_order()->size()) {
                    $user_input->sel_ndx = $fav_group->get_items_order()->size() - 1;
                }
                $this->set_changes();
                $this->plugin->tv->change_tv_favorites(PLUGIN_FAVORITES_OP_MOVE_DOWN, $selected_media_url->channel_id);
                return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->sel_ndx);

            case ACTION_ITEM_DELETE:
                $this->set_changes();
                $this->plugin->tv->change_tv_favorites(PLUGIN_FAVORITES_OP_REMOVE, $selected_media_url->channel_id);
                if ($fav_group->get_items_order()->size() !== 0) {
                    return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->sel_ndx);
                    //return $action;
                }
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

            case ACTION_ITEMS_CLEAR:
                $this->set_changes();
                $this->plugin->tv->change_tv_favorites(ACTION_ITEMS_CLEAR, null);
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

            case ACTION_JUMP_TO_CHANNEL:
                return $this->plugin->tv->jump_to_channel($selected_media_url->channel_id);

            case GUI_EVENT_KEY_POPUP_MENU:
                $menu_items[] = $this->plugin->create_menu_item($this, ACTION_JUMP_TO_CHANNEL, TR::t('jump_to_channel'));
                $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_CLEAR, TR::t('clear_favorites'), "brush.png");
                return Action_Factory::show_popup_menu($menu_items);
        }

        return null;
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

        foreach ($this->plugin->tv->get_special_group(FAVORITES_GROUP_ID)->get_items_order() as $channel_id) {
            if (!preg_match('/\S/', $channel_id)) {
                continue;
            }

            $channel = $this->plugin->tv->get_channel($channel_id);
            if (is_null($channel)) {
                hd_debug_print("Unknown channel $channel_id");
                $this->set_changes();
                $this->plugin->tv->change_tv_favorites(PLUGIN_FAVORITES_OP_REMOVE, $channel_id);
                continue;
            }

            $items[] = array
            (
                PluginRegularFolderItem::media_url => MediaURL::encode(array(
                        'channel_id' => $channel->get_id(),
                        'group_id' => FAVORITES_GROUP_ID)
                ),
                PluginRegularFolderItem::caption => $channel->get_title(),
                PluginRegularFolderItem::starred => false,
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::icon_path => $channel->get_icon_url(),
                    ViewItemParams::item_detailed_icon_path => $channel->get_icon_url(),
                ),
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
            $this->plugin->get_screen_view('icons_4x3_caption'),
            $this->plugin->get_screen_view('icons_4x3_no_caption'),
            $this->plugin->get_screen_view('icons_3x3_caption'),
            $this->plugin->get_screen_view('icons_3x3_no_caption'),
            $this->plugin->get_screen_view('icons_5x3_caption'),
            $this->plugin->get_screen_view('icons_5x3_no_caption'),
            $this->plugin->get_screen_view('icons_5x4_caption'),
            $this->plugin->get_screen_view('icons_5x4_no_caption'),

            $this->plugin->get_screen_view('list_1x11_info'),
            $this->plugin->get_screen_view('list_2x11_small_info'),
            $this->plugin->get_screen_view('list_3x11_no_info'),
        );
    }
}
