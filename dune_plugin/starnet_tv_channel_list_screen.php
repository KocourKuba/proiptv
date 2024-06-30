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
    const ACTION_CUSTOM_DELETE = 'custom_delete';
    const ACTION_CUSTOM_STRING_DLG_APPLY = 'apply_custom_string_dlg';

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

        $action_play = User_Input_Handler_Registry::create_action($this, ACTION_PLAY_ITEM);
        $search_action = User_Input_Handler_Registry::create_action($this, self::ACTION_CREATE_SEARCH, TR::t('search'));

        $actions = array(
            GUI_EVENT_KEY_ENTER      => $action_play,
            GUI_EVENT_KEY_PLAY       => $action_play,
            GUI_EVENT_KEY_SEARCH     => $search_action,
            GUI_EVENT_KEY_POPUP_MENU => User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU),
            GUI_EVENT_KEY_SETUP      => User_Input_Handler_Registry::create_action($this, ACTION_SETTINGS),
            GUI_EVENT_KEY_INFO       => User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_INFO),
            GUI_EVENT_KEY_RETURN     => User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN),
            GUI_EVENT_KEY_TOP_MENU   => User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_TOP_MENU),
            GUI_EVENT_KEY_STOP       => User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_STOP),
            GUI_EVENT_TIMER          => User_Input_Handler_Registry::create_action($this, GUI_EVENT_TIMER),
        );

        if ((string)$media_url->group_id === ALL_CHANNEL_GROUP_ID) {
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

        $selected_media_url = MediaURL::decode($user_input->selected_media_url);
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);

        $channel_id = $selected_media_url->channel_id;
        $sel_ndx = $user_input->sel_ndx;

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

            case GUI_EVENT_TIMER:
                clearstatcache();
                $epg_manager = $this->plugin->get_epg_manager();
                if ($epg_manager->is_index_locked()) {
                    $actions = $this->get_action_map($parent_media_url, $plugin_cookies);
                    return Action_Factory::change_behaviour($actions, 2000);
                }

                $epg_manager->import_indexing_log();
                return null;

            case GUI_EVENT_KEY_STOP:
                $this->plugin->save_orders(true);
                $this->set_no_changes();
                return Action_Factory::invalidate_all_folders($plugin_cookies);

            case ACTION_PLAY_ITEM:
                try {
                    $post_action = $this->plugin->tv->tv_player_exec($selected_media_url);
                } catch (Exception $ex) {
                    hd_debug_print("Channel can't played");
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

            case ACTION_ADD_FAV:
                $fav_group = $this->plugin->tv->get_special_group(FAVORITES_GROUP_ID);
                $opt_type = $fav_group->in_items_order($channel_id) ? PLUGIN_FAVORITES_OP_REMOVE : PLUGIN_FAVORITES_OP_ADD;
                $this->plugin->tv->change_tv_favorites($opt_type, $channel_id);
                $this->set_changes();
                break;

            case ACTION_SETTINGS:
                $this->save_if_changed();
                return $this->plugin->show_protect_settings_dialog($this, ACTION_DO_SETTINGS);

            case ACTION_DO_SETTINGS:
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
                $parent_group = $parent_media_url->group_id === ALL_CHANNEL_GROUP_ID
                    ? $this->plugin->tv->get_special_group($parent_media_url->group_id)
                    : $this->plugin->tv->get_group($parent_media_url->group_id);

                if (is_null($parent_group)) {
                    hd_debug_print("unknown parent group", true);
                    break;
                }

                return $this->do_search($parent_group, $find_text);

            case ACTION_JUMP_TO_CHANNEL:
                return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->number);

            case ACTION_JUMP_TO_CHANNEL_IN_GROUP:
                return $this->plugin->tv->jump_to_channel($selected_media_url->channel_id);

            case ACTION_ITEM_UP:
                $group = $this->plugin->tv->get_group($selected_media_url->group_id);
                if (is_null($group) || !$group->get_items_order()->arrange_item($channel_id, Ordered_Array::UP))
                    return null;

                $sel_ndx--;
                if ($sel_ndx < 0) {
                    $sel_ndx = 0;
                }

                $this->set_changes();
                break;

            case ACTION_ITEM_DOWN:
                $group = $this->plugin->tv->get_group($selected_media_url->group_id);
                if (is_null($group) || !$group->get_items_order()->arrange_item($channel_id, Ordered_Array::DOWN))
                    return null;

                $groups_cnt = $group->get_items_order()->size();
                $sel_ndx++;
                if ($sel_ndx >= $groups_cnt) {
                    $sel_ndx = $groups_cnt - 1;
                }

                $this->set_changes();
                break;

            case ACTION_ITEM_DELETE:
                if ($this->plugin->tv->disable_channel($channel_id, true)) {
                    $this->set_changes();
                }
                break;

            case ACTION_ITEMS_EDIT:
                $this->save_if_changed();
                return $this->plugin->do_edit_list_screen(self::ID, $user_input->action_edit, $parent_media_url);

            case GUI_EVENT_KEY_POPUP_MENU:
                if ($selected_media_url->group_id === ALL_CHANNEL_GROUP_ID) {
                    $menu_items[] = $this->plugin->create_menu_item($this,
                        ACTION_JUMP_TO_CHANNEL_IN_GROUP,
                        TR::t('jump_to_channel'),
                        "goto.png");
                    $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
                    $menu_items = array_merge($menu_items, $this->plugin->edit_hidden_menu($this, $selected_media_url->group_id, false));
                } else {
                    $menu_items[] = $this->plugin->create_menu_item($this,
                        self::ACTION_CREATE_SEARCH,
                        TR::t('search'),
                        "search.png");

                    if ($this->plugin->tv->get_special_group($selected_media_url->group_id) === null) {
                        $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
                        $menu_items = array_merge($menu_items, $this->plugin->edit_hidden_menu($this, $selected_media_url->group_id, false));
                        $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_SORT, TR::t('sort_channels'));
                        $menu_items[] = $this->plugin->create_menu_item($this, ACTION_RESET_ITEMS_SORT, TR::t('reset_channels_sort'));
                    }
                }

                if (!is_limited_apk()) {
                    $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
                    $is_external = $this->plugin->tv->get_channels_for_ext_player()->in_order($channel_id);
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
                }

                if ($this->plugin->get_bool_setting(PARAM_PER_CHANNELS_ZOOM)) {
                    $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
                    $menu_items[] = $this->plugin->create_menu_item($this,
                        ACTION_ZOOM_POPUP_MENU,
                        TR::t('video_aspect_ratio'),
                        "aspect.png");
                }

                $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
                $menu_items[] = $this->plugin->create_menu_item($this,
                    GUI_EVENT_KEY_INFO,
                    TR::t('channel_info_dlg'),
                    "info.png");

                $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
                $menu_items[] = $this->plugin->create_menu_item($this, ACTION_SETTINGS,
                    TR::t('entry_setup'), "settings.png");

                return Action_Factory::show_popup_menu($menu_items);

            case ACTION_ITEM_DELETE_CHANNELS:
                $items = array(
                    TR::t('tv_screen_hide_plus') => "[\s(]\+\d",
                    TR::t('tv_screen_hide_orig') => "[Oo]rig|[Uu]ncomp",
                    TR::t('tv_screen_hide_50') => "\s50|\sFHD",
                    TR::t('tv_screen_hide_uhd') => "UHD|\s4[KkКк]|\s8[KkКк]",
                    TR::t('tv_screen_hide_string') => "custom_string"
                );
                foreach ($items as $key => $val) {
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEM_DELETE_BY_STRING, $key, null, array('hide' => $val));
                }
                return Action_Factory::show_popup_menu($menu_items);

            case ACTION_ITEM_DELETE_BY_STRING:
                if ($user_input->hide !== 'custom_string') {
                    $this->set_changes();
                    $this->plugin->tv->disable_group_channels($user_input->hide, $selected_media_url->group_id);
                    break;
                }

                $defs = array();
                Control_Factory::add_text_field($defs, $this, null, self::ACTION_CUSTOM_DELETE, '',
                    $this->plugin->get_parameter(PARAM_CUSTOM_DELETE_STRING, ''),
                    false, false, false, false, 800);

                Control_Factory::add_vgap($defs, 100);
                Control_Factory::add_close_dialog_and_apply_button($defs, $this, null,
                    self::ACTION_CUSTOM_STRING_DLG_APPLY, TR::t('ok'), 300);
                Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
                Control_Factory::add_vgap($defs, 10);

                return Action_Factory::show_dialog(TR::t('tv_screen_hide_string'), $defs, true, 1300);

            case self::ACTION_CUSTOM_STRING_DLG_APPLY:
                $custom_string = $user_input->{self::ACTION_CUSTOM_DELETE};
                if (!empty($custom_string)) {
                    $this->set_changes();
                    $this->plugin->set_parameter(PARAM_CUSTOM_DELETE_STRING, $custom_string);
                    $this->plugin->tv->disable_group_channels($custom_string, $selected_media_url->group_id, false);
                }
                break;

            case ACTION_ZOOM_POPUP_MENU:
                $menu_items = array();
                $zoom_data = $this->plugin->tv->get_channel_zoom($selected_media_url->channel_id);
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
                    $this->plugin->tv->set_channel_zoom($channel_id, ($zoom_select !== DuneVideoZoomPresets::not_set) ? $zoom_select : null);
                }
                break;

            case ACTION_EXTERNAL_PLAYER:
            case ACTION_INTERNAL_PLAYER:
                $this->plugin->tv->set_channel_for_ext_player($channel_id, $user_input->control_id === ACTION_EXTERNAL_PLAYER);
                break;

            case GUI_EVENT_KEY_INFO:
                return $this->plugin->do_show_channel_info($channel_id);

            case ACTION_ITEMS_SORT:
                $group = $this->plugin->tv->get_group($parent_media_url->group_id);
                if (is_null($group)) {
                    return null;
                }

                $group->sort_group_items();
                $this->set_changes();
                break;

            case ACTION_RESET_ITEMS_SORT:
                $group = $this->plugin->tv->get_group($parent_media_url->group_id);
                if (is_null($group)) {
                    return null;
                }

                $group->sort_group_items(true);
                $this->set_changes();
                break;

            case ACTION_RELOAD:
                hd_debug_print("reload");
                $this->plugin->tv->reload_channels($plugin_cookies);
                return Starnet_Epfs_Handler::invalidate_folders(
                    array(Starnet_Tv_Groups_Screen::ID),
                    Action_Factory::close_and_run(
                        Action_Factory::open_folder($parent_media_url->get_media_url_str())
                    )
                );

            case ACTION_REFRESH_SCREEN:
                break;

            case ACTION_EMPTY:
                return null;
        }

        return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $sel_ndx);
    }

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $items = array();

        try {
            if ($this->plugin->tv->load_channels($plugin_cookies) === 0) {
                throw new Exception("Channels not loaded!");
            }

            if ($media_url->group_id === ALL_CHANNEL_GROUP_ID) {
                $groups_order = $this->plugin->tv->get_groups_order()->get_order();
            } else {
                $groups_order[] = $media_url->group_id;
            }

            foreach($groups_order as $id) {
                $group = $this->plugin->tv->get_group($id);
                if (is_null($group) || $group->is_disabled()) continue;

                foreach ($group->get_items_order() as $channel_id) {
                    if (($item = $this->get_folder_item($media_url->group_id, $channel_id)) !== null) {
                        $items[] = $item;
                    }
                }
            }
        } catch (Exception $ex) {
            hd_debug_print("Failed collect folder items!");
            print_backtrace_exception($ex);
        }

        return $items;
    }

    /**
     * @inheritDoc
     */
    public function get_timer(MediaURL $media_url, $plugin_cookies)
    {
        return Action_Factory::timer(1000);
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

            $this->plugin->get_screen_view('list_1x11_small_info'),
            $this->plugin->get_screen_view('list_2x11_small_info'),
            $this->plugin->get_screen_view('list_3x11_no_info'),
        );
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $group_id
     * @param string $channel_id
     * @return array|null
     */
    private function get_folder_item($group_id, $channel_id)
    {
        $channel = $this->plugin->tv->get_channel($channel_id);
        if (is_null($channel) || $channel->is_disabled()) {
            return null;
        }

        $zoom_data = $this->plugin->tv->get_channel_zoom($channel_id);
        if ($zoom_data === DuneVideoZoomPresets::not_set) {
            $detailed_info = TR::t('tv_screen_channel_info__4',
                $channel->get_title(),
                $channel->get_archive(),
                $channel->get_id(),
                implode(", ", $channel->get_epg_ids())
            );
        } else {
            $detailed_info = TR::t('tv_screen_channel_info__4',
                $channel->get_title(),
                $channel->get_archive(),
                $channel->get_id(),
                implode(", ", $channel->get_epg_ids()),
                TR::load_string(DuneVideoZoomPresets::$zoom_ops[$zoom_data])
                );
        }

        return array(
            PluginRegularFolderItem::media_url => MediaURL::encode(array('channel_id' => $channel_id, 'group_id' => $group_id)),
            PluginRegularFolderItem::caption => $channel->get_title(),
            PluginRegularFolderItem::starred => $this->plugin->tv->get_special_group(FAVORITES_GROUP_ID)->in_items_order($channel_id),
            PluginRegularFolderItem::view_item_params => array(
                ViewItemParams::icon_path => $channel->get_icon_url(),
                ViewItemParams::item_detailed_icon_path => $channel->get_icon_url(),
                ViewItemParams::item_detailed_info => $detailed_info,
            ),
        );
    }

    /**
     * @param Group $group
     * @param $find_text
     * @return array
     */
    protected function do_search(Group $group, $find_text)
    {
        hd_debug_print($group, true);

        /** @var Default_Channel $channel */
        $channels = $group->get_group_enabled_channels()->get_ordered_values();

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
                    ACTION_JUMP_TO_CHANNEL, '', $ch_title, 900);
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
