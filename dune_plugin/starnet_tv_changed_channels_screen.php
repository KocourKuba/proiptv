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

class Starnet_Tv_Changed_Channels_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'tv_changed_channels';

    /**
     * Get MediaURL string representation (json encoded)
     *
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
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $actions = array();
        $action_play = User_Input_Handler_Registry::create_action($this, ACTION_PLAY_ITEM);

        $actions[GUI_EVENT_KEY_ENTER]  = $action_play;
        $actions[GUI_EVENT_KEY_PLAY]   = $action_play;

        $actions[GUI_EVENT_KEY_RETURN]   = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_TOP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_TOP_MENU);

        $actions[GUI_EVENT_KEY_B_GREEN]    = User_Input_Handler_Registry::create_action($this, ACTION_ITEMS_CLEAR, TR::t('clear'));
        $actions[GUI_EVENT_KEY_D_BLUE]     = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE, TR::t('delete'));
        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);

        $this->plugin->add_shortcuts_handlers($this, $actions);

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
        $channel_id = MediaURL::decode($user_input->selected_media_url)->channel_id;

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                if (!$this->force_parent_reload) {
                    return Action_Factory::close_and_run();
                }

                $this->force_parent_reload = false;
                hd_debug_print("Force parent reload", true);
                return Action_Factory::close_and_run(
                    User_Input_Handler_Registry::create_screen_action(Starnet_Tv_Groups_Screen::ID,ACTION_INVALIDATE));

            case ACTION_PLAY_ITEM:
                try {
                    $selected_media_url = MediaURL::decode($user_input->selected_media_url);
                    $post_action = $this->plugin->tv_player_exec($selected_media_url);
                } catch (Exception $ex) {
                    hd_debug_print("Channel can't be played, exception info");
                    print_backtrace_exception($ex);
                    return Action_Factory::show_title_dialog(TR::t('err_channel_cant_start'),
                        null,
                        TR::t('warn_msg2__1', $ex->getMessage()));
                }

                Starnet_Epfs_Handler::update_epfs_file($plugin_cookies);
                return $post_action;

            case ACTION_ITEM_DELETE:
                $this->force_parent_reload = true;
                $this->plugin->remove_changed_channel($channel_id);

                if (!$this->plugin->get_changed_channels_count(PARAM_CHANGED)) {
                    return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
                }
                break;

            case ACTION_ITEMS_CLEAR:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_clear_all_msg'),
                    $this, ACTION_CONFIRM_CLEAR_DLG_APPLY);

            case ACTION_CONFIRM_CLEAR_DLG_APPLY:
                $this->force_parent_reload = true;
                $this->plugin->clear_changed_channels();
                if ($this->plugin->get_changed_channels_count(PARAM_CHANGED)) break;

                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

            case ACTION_JUMP_TO_CHANNEL_IN_GROUP:
                return $this->plugin->iptv->jump_to_channel($channel_id);

            case GUI_EVENT_KEY_POPUP_MENU:
                if ($this->plugin->get_changed_channels_count(PARAM_NEW, $channel_id)) {
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_JUMP_TO_CHANNEL_IN_GROUP, TR::t('jump_to_channel'), "goto.png");
                    return Action_Factory::show_popup_menu($menu_items);
                }

                return null;

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
                            Action_Factory::open_folder(Starnet_Tv_Groups_Screen::ID, $this->plugin->get_plugin_title())
                        )
                    )
                );
        }

        return $this->invalidate_current_folder($parent_media_url, $plugin_cookies);
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $items = array();

        if (LogSeverity::$is_debug) {
            $new_ids = $this->plugin->get_changed_channels_ids(PARAM_NEW);
            if (!empty($new_ids)) {
                hd_debug_print("New channels: " . pretty_json_format($new_ids), true);
            }
        }

        if (LogSeverity::$is_debug) {
            $removed_ids = $this->plugin->get_changed_channels_ids(PARAM_REMOVED);
            if (!empty($removed_ids)) {
                hd_debug_print("Removed channels: " . pretty_json_format($removed_ids), true);
            }
        }

        foreach ($this->plugin->get_changed_channels(PARAM_NEW) as $channel_row) {
            $epg_ids = array($channel_row[COLUMN_EPG_ID], $channel_row[COLUMN_CHANNEL_ID], $channel_row[COLUMN_TITLE]);
            $group = $channel_row[COLUMN_GROUP_ID];
            $detailed_info = TR::t('tv_screen_ch_channel_info__5',
                $channel_row[COLUMN_TITLE],
                str_replace('|', '¦', (is_null($group) ? "" : $group)),
                $channel_row[COLUMN_ARCHIVE],
                $channel_row[COLUMN_CHANNEL_ID],
                implode(", ", $epg_ids)
            );

            $icon_url = $this->plugin->get_channel_picon($channel_row, true);

            $items[] = array(
                PluginRegularFolderItem::media_url => MediaURL::encode(
                    array('channel_id' => $channel_row[COLUMN_CHANNEL_ID], 'group_id' => TV_CHANGED_CHANNELS_GROUP_ID)
                ),
                PluginRegularFolderItem::starred => false,
                PluginRegularFolderItem::caption => $channel_row[COLUMN_TITLE],
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::item_sticker => Control_Factory::create_sticker(get_image_path('add.png'), -63, 1),
                    ViewItemParams::icon_path => $icon_url,
                    ViewItemParams::item_detailed_icon_path => $icon_url,
                    ViewItemParams::item_detailed_info => $detailed_info,
                ),
            );
        }

        foreach ($this->plugin->get_changed_channels(PARAM_REMOVED) as $item) {
            $detailed_info = TR::t('tv_screen_ch_channel_info__2', $item[COLUMN_TITLE], $item[COLUMN_CHANNEL_ID]);

            $items[] = array(
                PluginRegularFolderItem::media_url => MediaURL::encode(
                    array('channel_id' => $item[COLUMN_CHANNEL_ID], 'group_id' => TV_CHANGED_CHANNELS_GROUP_ID)
                ),
                PluginRegularFolderItem::starred => false,
                PluginRegularFolderItem::caption => $item[COLUMN_TITLE],
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::item_sticker => Control_Factory::create_sticker(get_image_path('del.png'), -63, 1),
                    ViewItemParams::icon_path => DEFAULT_CHANNEL_ICON_PATH,
                    ViewItemParams::item_detailed_icon_path => DEFAULT_CHANNEL_ICON_PATH,
                    ViewItemParams::item_detailed_info => $detailed_info,
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
            $this->plugin->get_screen_view('list_1x11_info'),
            $this->plugin->get_screen_view('list_2x11_small_info'),
            $this->plugin->get_screen_view('list_3x11_no_info'),
        );
    }
}
