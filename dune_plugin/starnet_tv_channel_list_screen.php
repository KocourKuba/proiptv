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

class Starnet_Tv_Channel_List_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'tv_channel_list';

    const ACTION_NEW_SEARCH = 'new_search';
    const ACTION_CREATE_SEARCH = 'create_search';
    const ACTION_RUN_SEARCH = 'run_search';
    const ACTION_JUMP_TO_CHANNEL = 'jump_to_channel';

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

        $action_play = User_Input_Handler_Registry::create_action($this, ACTION_PLAY_ITEM);
        $action_settings = User_Input_Handler_Registry::create_action($this, ACTION_SETTINGS);
        $show_popup = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);
        $show_info = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_INFO);

        $actions = array(
            GUI_EVENT_KEY_ENTER      => $action_play,
            GUI_EVENT_KEY_PLAY       => $action_play,
            GUI_EVENT_KEY_POPUP_MENU => $show_popup,
            GUI_EVENT_KEY_SETUP      => $action_settings,
            GUI_EVENT_KEY_INFO       => $show_info,
        );
        $actions[GUI_EVENT_KEY_RETURN]     = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

        if ((string)$media_url->group_id === ALL_CHANNEL_GROUP_ID) {
            $search_action = User_Input_Handler_Registry::create_action($this, self::ACTION_CREATE_SEARCH, TR::t('search'));
            $actions[GUI_EVENT_KEY_C_YELLOW] = $search_action;
            $actions[GUI_EVENT_KEY_SEARCH] = $search_action;
            $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, ACTION_ADD_FAV, TR::t('add_to_favorite'));
        } else if (!is_null($group = $this->plugin->tv->get_group($media_url->group_id)) && $group->get_items_order()->size() !== 0) {
            $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('up'));
            $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('down'));
            $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, ACTION_ADD_FAV, TR::t('add_to_favorite'));
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

        $media_url = MediaURL::decode($user_input->selected_media_url);
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);

        $channel_id = $media_url->channel_id;
        $sel_ndx = $user_input->sel_ndx;

        switch ($user_input->control_id) {
            case ACTION_PLAY_ITEM:
                try {
                    $post_action = $this->plugin->player_exec($media_url);
                } catch (Exception $ex) {
                    hd_debug_print("Movie can't played, exception info: " . $ex->getMessage());
                    return Action_Factory::show_title_dialog(TR::t('err_channel_cant_start'),
                        null,
                        TR::t('warn_msg2__1', $ex->getMessage()));
                }

                return $this->plugin->update_epfs_data($plugin_cookies, null, $post_action);

            case ACTION_ADD_FAV:
                $opt_type = $this->plugin->get_favorites()->in_order($channel_id) ? PLUGIN_FAVORITES_OP_REMOVE : PLUGIN_FAVORITES_OP_ADD;
                $this->plugin->change_tv_favorites($opt_type, $channel_id);
                break;

            case ACTION_SETTINGS:
                return Action_Factory::open_folder(Starnet_Setup_Screen::get_media_url_str(), TR::t('entry_setup'));

            case self::ACTION_CREATE_SEARCH:
                $defs = array();
                $channel = $this->plugin->tv->get_channel($channel_id);
                if (is_null($channel)) {
                    return null;
                }

                Control_Factory::add_text_field($defs, $this, null, self::ACTION_NEW_SEARCH, '',
                    $channel->get_title(), false, false, true, true, 1300, false, true);
                Control_Factory::add_vgap($defs, 500);
                return Action_Factory::show_dialog(TR::t('tv_screen_search_channel'), $defs, true, 1300);

            case self::ACTION_NEW_SEARCH:
                return Action_Factory::close_dialog_and_run(User_Input_Handler_Registry::create_action($this, self::ACTION_RUN_SEARCH));

            case self::ACTION_RUN_SEARCH:
                $find_text = $user_input->{self::ACTION_NEW_SEARCH};
                hd_debug_print("Search in group: $parent_media_url->group_id", true);
                $parent_group = $this->plugin->tv->get_group($parent_media_url->group_id);
                if (is_null($parent_group)) {
                    hd_debug_print("unknown parent group", true);
                    break;
                }

                return $this->do_search($parent_group, $find_text);

            case self::ACTION_JUMP_TO_CHANNEL:
                return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->number);

            case ACTION_ITEM_UP:
                $group = $this->plugin->tv->get_group($media_url->group_id);
                if (is_null($group) || !$group->get_items_order()->arrange_item($channel_id, Ordered_Array::UP))
                    return null;

                $sel_ndx--;
                if ($sel_ndx < 0) {
                    $sel_ndx = 0;
                }
                $this->plugin->invalidate_epfs();

                break;

            case ACTION_ITEM_DOWN:
                $group = $this->plugin->tv->get_group($media_url->group_id);
                if (is_null($group) || !$group->get_items_order()->arrange_item($channel_id, Ordered_Array::DOWN))
                    return null;

                $groups_cnt = $group->get_items_order()->size();
                $sel_ndx++;
                if ($sel_ndx >= $groups_cnt) {
                    $sel_ndx = $groups_cnt - 1;
                }
                $this->plugin->invalidate_epfs();

                break;

            case ACTION_ITEM_DELETE:
                $this->plugin->tv->disable_channel($channel_id, $media_url->group_id);
                $this->plugin->invalidate_epfs();

                break;

            case ACTION_ITEMS_SORT:
                $group = $this->plugin->tv->get_group($media_url->group_id);
                if (!is_null($group)) {
                    // group items order contain only ID of the channels
                    $names = new Hashed_Array();
                    /** @var Channel $channel */
                    foreach ($group->get_items_order() as $item){
                        $channel = $this->plugin->tv->get_channel($item);
                        if (is_null($channel)) continue;

                        $names->set($channel->get_id(), $channel->get_title());
                    }
                    $names->value_sort();
                    hd_debug_print($names);
                    $group->set_items_order(new Ordered_Array($names->get_keys()));
                    $this->plugin->invalidate_epfs();
                }
                break;

            case ACTION_RESET_ITEMS_SORT:
                $group = $this->plugin->tv->get_group($media_url->group_id);
                if (is_null($group)) break;

                $group->get_items_order()->clear();
                $this->plugin->save();
                $this->plugin->invalidate_epfs();
                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case GUI_EVENT_KEY_POPUP_MENU:
                $menu_items = array();

                $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEM_DELETE, TR::t('tv_screen_hide_channel'), "remove.png");

                $group = $this->plugin->tv->get_group($media_url->group_id);
                if (!is_null($group) && !$group->is_special_group(ALL_CHANNEL_GROUP_ID)) {
                    $menu_items[] = $this->plugin->create_menu_item($this, self::ACTION_CREATE_SEARCH, TR::t('search'), "search.png");
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_SORT, TR::t('sort_items'), "sort.png");
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_RESET_ITEMS_SORT, TR::t('reset_sort_default'), "brush.png");
                }

                if (is_android() && !is_apk()) {
                    $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
                    $is_external = $this->plugin->is_channel_for_ext_player($channel_id);
                    $menu_items[] = $this->plugin->create_menu_item($this,
                        ACTION_EXTERNAL_PLAYER,
                        TR::t('tv_screen_external_player'),
                        ($is_external ? "play.png" : null)
                    );

                    $menu_items[] = $this->plugin->create_menu_item($this,
                        ACTION_INTERNAL_PLAYER,
                        TR::t('tv_screen_internal_player'),
                        ($is_external ? null : "play.png")
                    );

                    $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
                }

                if ($this->plugin->get_setting(PARAM_PER_CHANNELS_ZOOM, SetupControlSwitchDefs::switch_on) === SetupControlSwitchDefs::switch_on) {
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ZOOM_POPUP_MENU, TR::t('video_aspect_ration'), "aspect.png");
                }

                $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);

                $menu_items[] = $this->plugin->create_menu_item($this, GUI_EVENT_KEY_INFO, TR::t('channel_info_dlg'), "info.png");

                return Action_Factory::show_popup_menu($menu_items);

            case ACTION_ZOOM_POPUP_MENU:
                $menu_items = array();
                $zoom_data = $this->plugin->get_channel_zoom($media_url->channel_id);
                foreach (DuneVideoZoomPresets::$zoom_ops as $idx => $zoom_item) {
                    $menu_items[] = $this->plugin->create_menu_item($this,
                        ACTION_ZOOM_APPLY,
                        TR::t($zoom_item),
                        (strcmp($idx, $zoom_data) !== 0 ? null : "check.png"),
                        array(ACTION_ZOOM_SELECT => (string)$idx));
                }

                return Action_Factory::show_popup_menu($menu_items);
            case ACTION_ZOOM_APPLY:
                if (isset($user_input->{ACTION_ZOOM_SELECT})) {
                    $zoom_select = $user_input->{ACTION_ZOOM_SELECT};
                    $this->plugin->set_channel_zoom($channel_id, ($zoom_select !== DuneVideoZoomPresets::not_set) ? $zoom_select : null);
                }
                break;

            case ACTION_EXTERNAL_PLAYER:
            case ACTION_INTERNAL_PLAYER:
                $this->plugin->set_channel_for_ext_player($channel_id, $user_input->control_id === ACTION_EXTERNAL_PLAYER);
                break;

            case GUI_EVENT_KEY_INFO:
                $channel = $this->plugin->tv->get_channel($channel_id);
                if (is_null($channel)) {
                    return null;
                }

                $info  = "ID: {$channel->get_id()}\n";
                $info .= "Name: {$channel->get_title()}\n";
                $info .= "Archive: " . var_export($channel->get_archive(), true) . " day's\n";
                $info .= "Protected: " . var_export($channel->is_protected(), true) . "\n";
                $info .= "EPG IDs: " . implode(', ', $channel->get_epg_ids()) . "\n";
                $info .= "Timeshift hours: {$channel->get_timeshift_hours()}\n";
                $groups = array();
                foreach ($channel->get_groups() as $group) {
                    $groups[] = $group->get_id();
                }
                $info .= "Categories: " . implode(', ', $groups) . "\n\n";

                $lines = wrap_string_to_lines($channel->get_icon_url(), 70);
                $info .= "Icon URL: " . implode("\n", $lines) . "\n";
                $info .= (count($lines) > 1 ? "\n" : "");

                $lines = wrap_string_to_lines($channel->get_url(), 70);
                $info .= "Live URL: " . implode("\n", $lines) . "\n";
                $info .= (count($lines) > 1 ? "\n" : "");

                $lines = wrap_string_to_lines($channel->get_archive_url(), 70);
                $info .= "Archive URL: " . implode("\n", $lines) . "\n";
                $info .= (count($lines) > 1 ? "\n" : "");

                $params = array();
                foreach ($channel->get_ext_params() as $key => $param) {
                    $params = "$key: " . str_replace("\/", DIRECTORY_SEPARATOR, json_encode($param)) . "\n";
                }
                $info .= "Params: $params\n";

                Control_Factory::add_multiline_label($defs, null, $info, 12);
                Control_Factory::add_vgap($defs, 20);

                $text = sprintf("<gap width=%s/><icon>%s</icon><gap width=10/><icon>%s</icon><text color=%s size=small>  %s</text>",
                    1160,
                    get_image_path('page_plus_btn.png'),
                    get_image_path('page_minus_btn.png'),
                    DEF_LABEL_TEXT_COLOR_SILVER,
                    TR::load_string('scroll_page')
                );
                Control_Factory::add_smart_label($defs, null, $text);
                Control_Factory::add_vgap($defs, -80);

                Control_Factory::add_close_dialog_button($defs, TR::t('ok'), 250, true);
                Control_Factory::add_vgap($defs, 10);

                return Action_Factory::show_dialog(TR::t('channel_info_dlg'), $defs, true, 1700);

            case ACTION_RELOAD:
                hd_debug_print("reload");
                $this->plugin->tv->reload_channels($plugin_cookies);
                return Starnet_Epfs_Handler::invalidate_folders(null,
                    Action_Factory::close_and_run(Action_Factory::open_folder($parent_media_url->get_media_url_str())));
                //return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $sel_ndx);

            case GUI_EVENT_KEY_RETURN:
                return $this->plugin->update_epfs_data($plugin_cookies, null, Action_Factory::close_and_run(), true);
        }

        return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $sel_ndx);
    }

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $items = array();

        try {
            if ($this->plugin->tv->load_channels($plugin_cookies) === 0) {
                throw new Exception("Channels not loaded!");
            }

            $this_group = $this->plugin->tv->get_group($media_url->group_id);
            if (is_null($this_group)) {
                throw new Exception("Group $media_url->group_id not found");
            }

            /** @var Channel $channel */
            if ($this_group->is_special_group(ALL_CHANNEL_GROUP_ID)) {
                foreach($this->plugin->tv->get_channels() as $channel) {
                    if ($channel->is_disabled()) continue;

                    foreach ($channel->get_groups() as $group) {
                        if ($group->is_disabled()) continue;

                        $items[] = $this->get_folder_item($this_group, $channel);
                        break;
                    }
                }
            } else {
                foreach ($this_group->get_items_order() as $item) {
                    $channel = $this->plugin->tv->get_channel($item);
                    if (is_null($channel) || $channel->is_disabled()) continue;

                    //hd_debug_print("Folder item: $item", true);
                    $items[] = $this->get_folder_item($this_group, $channel);
                }
            }
        } catch (Exception $e) {
            hd_debug_print("Failed collect folder items! " . $e->getMessage());
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

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param Group $group
     * @param Channel $channel
     * @return array
     */
    private function get_folder_item($group, $channel)
    {
        $zoom_data = $this->plugin->get_channel_zoom($channel->get_id());
        if ($zoom_data === DuneVideoZoomPresets::not_set) {
            $detailed_info = TR::t('tv_screen_channel_info__2', $channel->get_title(), $channel->get_archive());
        } else {
            $detailed_info = TR::t('tv_screen_channel_info__3',
                $channel->get_title(), $channel->get_archive(),
                TR::load_string(DuneVideoZoomPresets::$zoom_ops[$zoom_data]));
        }

        return array
        (
            PluginRegularFolderItem::media_url => MediaURL::encode(array('channel_id' => $channel->get_id(), 'group_id' => $group->get_id())),
            PluginRegularFolderItem::caption => $channel->get_title(),
            PluginRegularFolderItem::view_item_params => array(
                ViewItemParams::icon_path => $channel->get_icon_url(),
                ViewItemParams::item_detailed_icon_path => $channel->get_icon_url(),
                ViewItemParams::item_detailed_info => $detailed_info,
            ),
            PluginRegularFolderItem::starred => $this->plugin->get_favorites()->in_order($channel->get_id()),
        );
    }

    /**
     * @param Group $parent_group
     * @param $find_text
     * @return array
     */
    protected function do_search(Group $parent_group, $find_text)
    {
        hd_debug_print($parent_group, true);

        /** @var Channel $channel */
        $channels = array();
        if ($parent_group->is_special_group(ALL_CHANNEL_GROUP_ID)) {
            foreach($this->plugin->tv->get_channels() as $channel) {
                if ($channel->is_disabled()) continue;

                foreach ($channel->get_groups() as $group) {
                    if (!$group->is_disabled()) {
                        $channels[] = $channel;
                        break;
                    }
                }
            }
        } else {
            foreach ($parent_group->get_items_order() as $item) {
                $channel = $this->plugin->tv->get_channel($item);
                if (!is_null($channel) && !$channel->is_disabled()) {
                    $channels[] = $channel;
                }
            }
        }

        $defs = array();
        $q_result = false;
        $idx = 0;
        foreach ($channels as $channel) {
            $ch_title = $channel->get_title();
            hd_debug_print("Search in: $ch_title", true);
            $s = mb_stripos($ch_title, $find_text, 0, "UTF-8");
            if ($s !== false) {
                $q_result = true;
                hd_debug_print("found channel: $ch_title, idx: $idx", true);
                $add_params['number'] = $idx;
                Control_Factory::add_close_dialog_and_apply_button_title($defs, $this, $add_params,
                    self::ACTION_JUMP_TO_CHANNEL, '', $ch_title, 900);
            }
            ++$idx;
        }

        if ($q_result === false) {
            Control_Factory::add_multiline_label($defs, '', TR::t('tv_screen_not_found'), 6);
            Control_Factory::add_vgap($defs, 20);
            Control_Factory::add_close_dialog_and_apply_button_title($defs, $this, null,
                self::ACTION_CREATE_SEARCH, '', TR::t('new_search'), 300);
        }

        return Action_Factory::show_dialog(TR::t('search'), $defs, true);
    }
}
