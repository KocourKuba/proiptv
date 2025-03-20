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
     * Get MediaURL string representation (json encoded)
     * *
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

        $actions[GUI_EVENT_KEY_ENTER] = $action_play;
        $actions[GUI_EVENT_KEY_PLAY] = $action_play;

        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_TOP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_TOP_MENU);
        $actions[GUI_EVENT_KEY_SUBTITLE] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_SUBTITLE);

        if ($this->plugin->get_channels_order_count(TV_FAV_GROUP_ID) !== 0) {
            if (isset($plugin_cookies->toggle_move) && $plugin_cookies->toggle_move) {
                $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_TOP, TR::t('top'));
                $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_BOTTOM, TR::t('bottom'));
            } else {
                $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('up'));
                $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('down'));
            }
            $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE, TR::t('delete'));

            $actions[GUI_EVENT_KEY_SELECT] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_TOGGLE_MOVE);
            $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);
        }

        $this->plugin->add_shortcuts_handlers($this, $actions);

        return $actions;
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

        $selected_media_url = MediaURL::decode($user_input->selected_media_url);
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $sel_ndx = $user_input->sel_ndx;

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
            if (!$this->force_parent_reload) {
                return Action_Factory::close_and_run();
            }

            $this->force_parent_reload = false;
            return Action_Factory::close_and_run(
                User_Input_Handler_Registry::create_screen_action(Starnet_Tv_Groups_Screen::ID,ACTION_INVALIDATE));

            case GUI_EVENT_KEY_SUBTITLE:
                $prog_info = $this->plugin->get_program_info($selected_media_url->channel_id, -1, $plugin_cookies);
                return $this->plugin->do_show_channel_epg($prog_info);

            case GUI_EVENT_KEY_POPUP_MENU:
                $menu_items[] = $this->plugin->create_menu_item($this, ACTION_JUMP_TO_CHANNEL_IN_GROUP, TR::t('jump_to_channel'), "goto.png");
                $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEM_TOGGLE_MOVE, TR::t('tv_screen_toggle_move'), "move.png");
                $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_CLEAR, TR::t('clear_favorites'), "brush.png");
                $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
                $menu_items[] = $this->plugin->create_menu_item($this, GUI_EVENT_KEY_SUBTITLE, TR::t('channel_epg_dlg'), "epg.png");
                return Action_Factory::show_popup_menu($menu_items);

            case ACTION_PLAY_ITEM:
                try {
                    $post_action = $this->plugin->tv_player_exec($selected_media_url);
                } catch (Exception $ex) {
                    hd_debug_print("Channel can't played");
                    print_backtrace_exception($ex);
                    return Action_Factory::show_title_dialog(TR::t('err_channel_cant_start'),
                        null,
                        TR::t('warn_msg2__1', $ex->getMessage()));
                }

                Starnet_Epfs_Handler::update_epfs_file($plugin_cookies);
                return $post_action;

            case ACTION_ITEM_TOGGLE_MOVE:
                $plugin_cookies->toggle_move = !$plugin_cookies->toggle_move;
                $actions = $this->get_action_map($parent_media_url, $plugin_cookies);
                return Action_Factory::change_behaviour($actions);

            case ACTION_ITEM_UP:
                $sel_ndx--;
                if ($sel_ndx < 0) {
                    return null;
                }

                $this->force_parent_reload = true;
                $this->plugin->change_tv_favorites(PLUGIN_FAVORITES_OP_MOVE_UP, $selected_media_url->channel_id);
                break;

            case ACTION_ITEM_DOWN:
                $cnt = $this->plugin->get_channels_order_count(TV_FAV_GROUP_ID) - 1;
                $sel_ndx++;
                if ($sel_ndx > $cnt) {
                    return null;
                }
                $this->force_parent_reload = true;
                $this->plugin->change_tv_favorites(PLUGIN_FAVORITES_OP_MOVE_DOWN, $selected_media_url->channel_id);
                break;

            case ACTION_ITEM_TOP:
                if ($sel_ndx === 0) {
                    return null;
                }
                $sel_ndx = 0;
                $this->force_parent_reload = true;
                $this->plugin->change_tv_favorites(ACTION_ITEM_TOP, $selected_media_url->channel_id);
                break;

            case ACTION_ITEM_BOTTOM:
                $max_sel = $this->plugin->get_channels_order_count(TV_FAV_GROUP_ID) - 1;
                if ($sel_ndx === $max_sel) {
                    return null;
                }
                $this->force_parent_reload = true;
                $sel_ndx = $max_sel;
                $this->plugin->change_tv_favorites(ACTION_ITEM_BOTTOM, $selected_media_url->channel_id);
                break;

            case ACTION_ITEM_DELETE:
                $this->force_parent_reload = true;
                $this->plugin->change_tv_favorites(PLUGIN_FAVORITES_OP_REMOVE, $selected_media_url->channel_id);
                if ($this->plugin->get_channels_order_count(TV_FAV_GROUP_ID) == 0) {
                    return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
                }
                break;

            case ACTION_ITEMS_CLEAR:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_clear_all_msg'),
                    $this, ACTION_CONFIRM_CLEAR_DLG_APPLY);

            case ACTION_CONFIRM_CLEAR_DLG_APPLY:
                $this->force_parent_reload = true;
                $this->plugin->change_tv_favorites(ACTION_ITEMS_CLEAR, null, $plugin_cookies);
                if ($this->plugin->get_channels_order_count(TV_FAV_GROUP_ID) !== 0) break;

                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

            case ACTION_JUMP_TO_CHANNEL_IN_GROUP:
                return $this->plugin->iptv->jump_to_channel($selected_media_url->channel_id);

            case ACTION_SHORTCUT:
                if (!isset($user_input->{COLUMN_PLAYLIST_ID}) || $this->plugin->get_active_playlist_id() === $user_input->{COLUMN_PLAYLIST_ID}) {
                    return null;
                }

                $this->plugin->set_active_playlist_id($user_input->{COLUMN_PLAYLIST_ID});
                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case ACTION_RELOAD:
                hd_debug_print("Action reload", true);
                $this->plugin->reload_channels($plugin_cookies);
                return Action_Factory::invalidate_all_folders(
                    $plugin_cookies,
                    array(Starnet_Tv_Groups_Screen::ID),
                    Action_Factory::close_and_run(
                        Action_Factory::close_and_run(
                            Action_Factory::open_folder(Starnet_Tv_Groups_Screen::get_media_url_str())
                        )
                    )
                );
        }

        return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $sel_ndx);
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

        foreach ($this->plugin->get_channels_by_order(TV_FAV_GROUP_ID) as $channel_row) {
            $icon_url = $this->plugin->get_channel_picon($channel_row, true);

            $items[] = array(
                PluginRegularFolderItem::media_url => MediaURL::encode(
                    array('channel_id' => $channel_row[COLUMN_CHANNEL_ID], 'group_id' => TV_FAV_GROUP_ID)
                ),
                PluginRegularFolderItem::caption => $channel_row[COLUMN_TITLE],
                PluginRegularFolderItem::starred => false,
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::icon_path => $icon_url,
                    ViewItemParams::item_detailed_icon_path => $icon_url,
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

            $this->plugin->get_screen_view('icons_7x4_no_caption'),
            $this->plugin->get_screen_view('icons_7x4_caption'),

            $this->plugin->get_screen_view('list_1x11_info'),
            $this->plugin->get_screen_view('list_2x11_small_info'),
            $this->plugin->get_screen_view('list_3x11_no_info'),
        );
    }
}
