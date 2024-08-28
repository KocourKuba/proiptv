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
        $actions[GUI_EVENT_KEY_STOP]     = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_STOP);

        $actions[GUI_EVENT_KEY_B_GREEN]    = User_Input_Handler_Registry::create_action($this, ACTION_ITEMS_CLEAR, TR::t('clear'));
        $actions[GUI_EVENT_KEY_D_BLUE]     = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE, TR::t('delete'));
        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);

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

        $channel_id = MediaURL::decode($user_input->selected_media_url)->channel_id;

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                if ($this->has_changes()) {
                    $this->plugin->save_orders(true);
                    $this->set_no_changes();
                    $post_action = null;
                    if ($user_input->control_id === GUI_EVENT_KEY_RETURN) {
                        $post_action = User_Input_Handler_Registry::create_action(
                            User_Input_Handler_Registry::get_instance()->get_registered_handler(Starnet_Tv_Groups_Screen::get_handler_id()),
                            ACTION_REFRESH_SCREEN);
                    }
                    $post_action = Action_Factory::close_and_run($post_action);
                    return Action_Factory::invalidate_all_folders($plugin_cookies, $post_action);
                }

                return Action_Factory::close_and_run();

            case GUI_EVENT_KEY_STOP:
                $this->plugin->save_orders(true);
                $this->set_no_changes();
                return Action_Factory::invalidate_all_folders($plugin_cookies);

            case ACTION_PLAY_ITEM:
                try {
                    $selected_media_url = MediaURL::decode($user_input->selected_media_url);
                    $post_action = $this->plugin->tv->tv_player_exec($selected_media_url);
                } catch (Exception $ex) {
                    hd_debug_print("Channel can't be played, exception info");
                    print_backtrace_exception($ex);
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

            case ACTION_ITEM_DELETE:
                $new_channels = $this->plugin->tv->get_changed_channels_ids('new');
                $removed_channels = $this->plugin->tv->get_changed_channels_ids('removed');
                $order = &$this->plugin->tv->get_known_channels();
                if (($key = array_search($channel_id, $new_channels)) !== false) {
                    $channel = $this->plugin->tv->get_channel($channel_id);
                    if (!is_null($channel)) {
                        $order->set($channel->get_id(), $channel->get_title());
                        $this->set_changes();
                        unset($new_channels[$key]);
                    }
                } else if (($key = array_search($channel_id, $removed_channels)) !== false) {
                    $order->erase($channel_id);
                    $this->set_changes();
                    unset($removed_channels[$key]);
                }

                if (count($new_channels) === 0 && count($removed_channels) === 0) {
                    $this->set_changes();
                    $this->plugin->tv->get_special_group(CHANGED_CHANNELS_GROUP_ID)->set_disabled(true);
                    return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
                }
                break;

            case ACTION_ITEMS_CLEAR:
                $this->set_changes();
                $all_channels = $this->plugin->tv->get_channels();
                $order = &$this->plugin->tv->get_known_channels();
                $this->plugin->tv->get_special_group(CHANGED_CHANNELS_GROUP_ID)->set_disabled(true);
                $order->clear();
                foreach ($all_channels as $channel) {
                    $order->set($channel->get_id(), $channel->get_title());
                }

                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

            case ACTION_JUMP_TO_CHANNEL_IN_GROUP:
                return $this->plugin->tv->jump_to_channel($channel_id);

            case GUI_EVENT_KEY_POPUP_MENU:
                $new_channels = $this->plugin->tv->get_changed_channels_ids('new');
                if (in_array($channel_id, $new_channels)) {
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_JUMP_TO_CHANNEL_IN_GROUP, TR::t('jump_to_channel'), "goto.png");
                    return Action_Factory::show_popup_menu($menu_items);
                }

                return null;
        }

        return Action_Factory::update_regular_folder(
            $this->get_folder_range(MediaURL::decode($user_input->parent_media_url), 0, $plugin_cookies), true);
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

        $new_channels = $this->plugin->tv->get_changed_channels_ids('new');
        $removed_channels = $this->plugin->tv->get_changed_channels_ids('removed');
        if (LogSeverity::$is_debug) {
            hd_debug_print("New channels: " . pretty_json_format($new_channels), true);
            hd_debug_print("Removed channels: " . pretty_json_format($removed_channels), true);
        }

        /** @var Default_Channel $channel */
        foreach ($this->plugin->tv->get_filtered_channels($new_channels) as $channel) {
            if (is_null($channel)) continue;

            $group = $channel->get_parent_group();
            $detailed_info = TR::t('tv_screen_ch_channel_info__5',
                $channel->get_title(),
                str_replace('|', '¦', (is_null($group) ? "" : $group->get_title())),
                $channel->get_archive(),
                $channel->get_id(),
                implode(", ", $channel->get_epg_ids())
            );

            $items[] = array(
                PluginRegularFolderItem::media_url => MediaURL::encode(
                    array(
                        'channel_id' => $channel->get_id(),
                        'group_id' => CHANGED_CHANNELS_GROUP_ID)
                ),
                PluginRegularFolderItem::caption => $channel->get_title(),
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::item_sticker => Control_Factory::create_sticker(get_image_path('add.png'), -63, 1),
                    ViewItemParams::icon_path => $channel->get_icon_url(),
                    ViewItemParams::item_detailed_icon_path => $channel->get_icon_url(),
                    ViewItemParams::item_detailed_info => $detailed_info,
                ),
            );
        }

        foreach ($removed_channels as $item) {
            $caption = $this->plugin->tv->get_known_channels()->get($item);
            $detailed_info = TR::t('tv_screen_ch_channel_info__2', $caption, $item);

            $items[] = array(
                PluginRegularFolderItem::media_url => MediaURL::encode(
                    array(
                        'channel_id' => $item,
                        'group_id' => CHANGED_CHANNELS_GROUP_ID)
                ),
                PluginRegularFolderItem::starred => false,
                PluginRegularFolderItem::caption => $caption,
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::item_sticker => Control_Factory::create_sticker(get_image_path('del.png'), -63, 1),
                    ViewItemParams::icon_path => Starnet_Tv::DEFAULT_CHANNEL_ICON_PATH,
                    ViewItemParams::item_detailed_icon_path => Starnet_Tv::DEFAULT_CHANNEL_ICON_PATH,
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
