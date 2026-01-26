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
require_once 'lib/user_input_handler_registry.php';

class Starnet_Tv_History_Screen extends Abstract_Preloaded_Regular_Screen
{
    const ID = 'tv_history';

    ///////////////////////////////////////////////////////////////////////

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

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        return $this->do_get_action_map();
    }

    protected function do_get_action_map()
    {
        hd_debug_print(null, true);

        $action_play = User_Input_Handler_Registry::create_action($this, ACTION_PLAY_ITEM);

        $actions[GUI_EVENT_KEY_ENTER] = $action_play;
        $actions[GUI_EVENT_KEY_PLAY] = $action_play;

        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_TOP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_TOP_MENU);
        $actions[GUI_EVENT_TIMER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_TIMER);

        if (!is_limited_apk()) {
            // this key used to fire event from background xmltv indexing script
            $actions[EVENT_INDEXING_DONE] = User_Input_Handler_Registry::create_action($this, EVENT_INDEXING_DONE);
        }

        if ($this->plugin->get_tv_history_count() !== 0) {
            $add_to_favorite = User_Input_Handler_Registry::create_action($this, ACTION_ADD_FAV, TR::t('add_to_favorite'));
            $remove = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE, TR::t('delete'));

            $actions[GUI_EVENT_KEY_CLEAR] = $remove;
            $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);

            $actions[GUI_EVENT_KEY_B_GREEN] = $remove;
            $actions[GUI_EVENT_KEY_D_BLUE] = $add_to_favorite;
            $actions[GUI_EVENT_KEY_DUNE] = $add_to_favorite; // FAV1
        }

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

        $fav_id = $this->plugin->get_fav_id();
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $selected_media_url = MediaURL::decode($user_input->selected_media_url);

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                $actions[] = Action_Factory::close_and_run();
                if ($this->force_parent_reload) {
                    $this->force_parent_reload = false;
                    hd_debug_print("Force parent reload", true);
                    $actions[] = User_Input_Handler_Registry::create_screen_action(Starnet_Tv_Groups_Screen::ID, ACTION_INVALIDATE);
                }
                return Action_Factory::composite($actions);

            case GUI_EVENT_TIMER:
                if (!is_limited_apk()) break;

                $actions[] = $this->plugin->get_import_xmltv_logs_actions($plugin_cookies);
                $actions[] = Action_Factory::change_behaviour($this->do_get_action_map(), 1000);
                return Action_Factory::composite($actions);

            case EVENT_INDEXING_DONE:
                return $this->plugin->get_import_xmltv_logs_actions($plugin_cookies);

            case ACTION_PLAY_ITEM:
                try {
                    $post_action = $this->plugin->tv_player_exec($selected_media_url);
                } catch (Exception $ex) {
                    hd_debug_print("Channel can't played");
                    print_backtrace_exception($ex);
                    return Action_Factory::show_title_dialog(TR::t('err_channel_cant_start'), TR::t('warn_msg2__1', $ex->getMessage()));
                }

                Starnet_Epfs_Handler::update_epfs_file($plugin_cookies);
                return $post_action;

            case ACTION_ITEM_DELETE:
                $this->force_parent_reload = true;
                $this->plugin->erase_tv_history($selected_media_url->channel_id);
                if ($this->plugin->get_tv_history_count() === 0) {
                    return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
                }
                break;

            case ACTION_ITEMS_CLEAR:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_clear_all_msg'),
                    $this, ACTION_CONFIRM_CLEAR_DLG_APPLY);

            case ACTION_CONFIRM_CLEAR_DLG_APPLY:
                $this->force_parent_reload = true;
                $this->plugin->clear_tv_history();
                if ($this->plugin->get_tv_history_count() !== 0) break;

                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

            case ACTION_ADD_FAV:
                $this->force_parent_reload = true;
                $is_favorite = $this->plugin->is_channel_in_order($fav_id, $selected_media_url->channel_id);
                $opt_type = $is_favorite ? PLUGIN_FAVORITES_OP_REMOVE : PLUGIN_FAVORITES_OP_ADD;
                $message = $is_favorite ? TR::t('deleted_from_favorite') : TR::t('added_to_favorite');
                $this->plugin->change_channels_order($fav_id, $selected_media_url->channel_id, $is_favorite);

                $actions[] = Action_Factory::show_title_dialog($message);
                $actions[] = $this->plugin->change_tv_favorites($opt_type, $selected_media_url->channel_id);
                return Action_Factory::composite($actions);

            case ACTION_JUMP_TO_CHANNEL_IN_GROUP:
                return $this->plugin->jump_to_channel($selected_media_url->channel_id);

            case GUI_EVENT_KEY_POPUP_MENU:
                $menu_items = array();
                $menu_items[] = $this->plugin->create_menu_item($this, ACTION_JUMP_TO_CHANNEL_IN_GROUP, TR::t('jump_to_channel'), "goto.png");
                $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_CLEAR, TR::t('clear_history'), "brush.png");

                return Action_Factory::show_popup_menu($menu_items);

            case ACTION_SHORTCUT:
                $actions[] = Action_Factory::close_and_run();
                $actions[] = User_Input_Handler_Registry::create_screen_action(Starnet_Tv_Groups_Screen::ID,
                    ACTION_SHORTCUT,
                    '',
                    array(COLUMN_PLAYLIST_ID => $user_input->{COLUMN_PLAYLIST_ID})
                );
                return Action_Factory::composite($actions);

            case ACTION_RELOAD:
                hd_debug_print("Action reload", true);
                $actions[] = Action_Factory::close_and_run();
                $actions[] = User_Input_Handler_Registry::create_screen_action(Starnet_Tv_Groups_Screen::ID, ACTION_RELOAD);
                return Action_Factory::composite($actions);
        }

        return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->sel_ndx);
    }

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $items = array();
        $now = time();
        foreach ($this->plugin->get_tv_history() as $channel_row) {
            $channel_id = $channel_row[COLUMN_CHANNEL_ID];
            $channel_ts = $channel_row[COLUMN_TIMESTAMP];
            $prog_info = $this->plugin->get_epg_info($channel_id, $channel_ts);
            $description = '';
            if (!isset($prog_info[PluginTvEpgProgram::start_tm_sec])) {
                $title = $channel_row[COLUMN_TITLE];
            } else {
                // program epg available
                $title = $prog_info[PluginTvEpgProgram::name];
                if ($channel_ts > 0) {
                    $start_tm = $prog_info[PluginTvEpgProgram::start_tm_sec];
                    $epg_len = $prog_info[PluginTvEpgProgram::end_tm_sec] - $start_tm;
                    if ($channel_ts >= $now - $channel_row[COLUMN_ARCHIVE] * 86400 - 60) {
                        $progress = max(0.01, min(1.0, round(($channel_ts - $start_tm) / $epg_len, 2))) * 100;
                        $title = "$title | " . date("j.m H:i", $channel_ts) . " [$progress%]";
                        $description = "{$channel_row[COLUMN_TITLE]}|{$prog_info[PluginTvEpgProgram::description]}";
                    }
                }
            }

            $icon_url = $this->plugin->get_channel_picon($channel_row, true);

            $items[] = array(
                PluginRegularFolderItem::media_url => MediaURL::encode(
                    array(
                        'channel_id' => $channel_id,
                        'group_id' => TV_HISTORY_GROUP_ID,
                        'archive_tm' => $channel_ts
                    )
                ),
                PluginRegularFolderItem::caption => $title,
                PluginRegularFolderItem::starred => false,
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::icon_path => $icon_url,
                    ViewItemParams::item_detailed_icon_path => $icon_url,
                    ViewItemParams::item_detailed_info => $description,
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
            $this->plugin->get_screen_view('list_1x11_small_info'),
            $this->plugin->get_screen_view('list_1x11_info'),
        );
    }
}
