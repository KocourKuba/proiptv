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
            GUI_EVENT_KEY_ENTER => $action_play,
            GUI_EVENT_KEY_PLAY => $action_play,
            GUI_EVENT_KEY_SEARCH => $search_action,
            GUI_EVENT_KEY_POPUP_MENU => User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU),
            GUI_EVENT_KEY_SETUP => User_Input_Handler_Registry::create_action($this, ACTION_SETTINGS),
            GUI_EVENT_KEY_INFO => User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_INFO),
            GUI_EVENT_KEY_CLEAR => User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE),
            GUI_EVENT_KEY_SELECT => User_Input_Handler_Registry::create_action($this, ACTION_ITEM_TOGGLE_MOVE),
            GUI_EVENT_KEY_SUBTITLE => User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_SUBTITLE),
            GUI_EVENT_TIMER => User_Input_Handler_Registry::create_action($this, GUI_EVENT_TIMER),
        );

        if ((string)$media_url->group_id === ALL_CHANNELS_GROUP_ID) {
            $actions[GUI_EVENT_KEY_C_YELLOW] = $search_action;
            $actions[GUI_EVENT_KEY_SEARCH] = $search_action;
            $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, ACTION_ADD_FAV, TR::t('add_to_favorite'));
        } else if ($this->plugin->get_channels_order_count($media_url->group_id) !== 0) {
            $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, ACTION_ADD_FAV, TR::t('add_to_favorite'));
            if (isset($plugin_cookies->toggle_move) && $plugin_cookies->toggle_move) {
                $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_TOP, TR::t('top'));
                $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_BOTTOM, TR::t('bottom'));
            } else {
                $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('up'));
                $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('down'));
            }
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
            case GUI_EVENT_TIMER:
                $epg_manager = $this->plugin->get_epg_manager();
                if ($epg_manager === null) {
                    return null;
                }

                clearstatcache();

                $res = $epg_manager->import_indexing_log();
                if ($res !== false) {
                    return null;
                }

                $actions = $this->get_action_map($parent_media_url, $plugin_cookies);
                return Action_Factory::change_behaviour($actions, 1000);

            case GUI_EVENT_KEY_INFO:
                return $this->plugin->do_show_channel_info($channel_id);

            case GUI_EVENT_KEY_SUBTITLE:
                return $this->plugin->do_show_channel_epg($channel_id, $plugin_cookies);

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

                Starnet_Epfs_Handler::update_all_epfs($plugin_cookies);
                return $post_action;

            case ACTION_ADD_FAV:
                $fav_ids = $this->plugin->get_channels_order(FAV_CHANNELS_GROUP_ID);
                $opt_type = in_array($channel_id, $fav_ids) ? PLUGIN_FAVORITES_OP_REMOVE : PLUGIN_FAVORITES_OP_ADD;
                $this->plugin->change_channels_order(FAV_CHANNELS_GROUP_ID, $channel_id, $opt_type);
                break;

            case ACTION_SETTINGS:
                return $this->plugin->show_protect_settings_dialog($this, ACTION_DO_SETTINGS);

            case ACTION_DO_SETTINGS:
                return Action_Factory::open_folder(Starnet_Setup_Screen::get_media_url_str(), TR::t('entry_setup'));

            case self::ACTION_CREATE_SEARCH:
                $defs = array();
                $row = $this->plugin->get_channel_info($channel_id);
                if (empty($row)) {
                    return null;
                }

                Control_Factory::add_text_field($defs, $this, null, self::ACTION_NEW_SEARCH, '',
                    $row['title'], false, false, true, true, 1300, false, true);
                Control_Factory::add_vgap($defs, 500);
                return Action_Factory::show_dialog(TR::t('tv_screen_search_channel'), $defs, true, 1300);

            case self::ACTION_NEW_SEARCH:
                return Action_Factory::close_dialog_and_run(User_Input_Handler_Registry::create_action($this, self::ACTION_RUN_SEARCH));

            case self::ACTION_RUN_SEARCH:
                $find_text = $user_input->{self::ACTION_NEW_SEARCH};
                hd_debug_print("Search in group: $parent_media_url->group_id", true);
                return $this->do_search($parent_media_url->group_id, $find_text);

            case ACTION_JUMP_TO_CHANNEL:
                return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->number);

            case ACTION_JUMP_TO_CHANNEL_IN_GROUP:
                return $this->plugin->tv->jump_to_channel($selected_media_url->channel_id);

            case ACTION_ITEM_TOGGLE_MOVE:
                $plugin_cookies->toggle_move = !$plugin_cookies->toggle_move;
                $actions = $this->get_action_map($parent_media_url, $plugin_cookies);
                return Action_Factory::change_behaviour($actions);

            case ACTION_ITEM_UP:
                $this->plugin->arrange_channels_order_rows($selected_media_url->group_id, $channel_id, Ordered_Array::UP);
                $sel_ndx--;
                break;

            case ACTION_ITEM_DOWN:
                $this->plugin->arrange_channels_order_rows($selected_media_url->group_id, $channel_id, Ordered_Array::DOWN);
                $sel_ndx++;
                break;

            case ACTION_ITEM_TOP:
                $this->plugin->arrange_channels_order_rows($selected_media_url->group_id, $channel_id, Ordered_Array::TOP);
                $sel_ndx = 0;
                break;

            case ACTION_ITEM_BOTTOM:
                $this->plugin->arrange_channels_order_rows($selected_media_url->group_id, $channel_id, Ordered_Array::BOTTOM);
                $sel_ndx = $this->plugin->get_channels_order_count($selected_media_url->group_id) - 1;
                break;

            case ACTION_ITEM_DELETE:
                $this->plugin->set_channel_visible($channel_id, true);
                break;

            case ACTION_ITEMS_EDIT:
                return $this->plugin->do_edit_list_screen(self::ID, $user_input->action_edit, $parent_media_url);

            case GUI_EVENT_KEY_POPUP_MENU:
                if ($selected_media_url->group_id === ALL_CHANNELS_GROUP_ID) {
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

                    $special_group = $this->plugin->get_group($selected_media_url->group_id, true);
                    if (empty($special_group)) {
                        $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
                        $menu_items = array_merge($menu_items, $this->plugin->edit_hidden_menu($this, $selected_media_url->group_id, false));
                        $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEM_TOGGLE_MOVE, TR::t('tv_screen_toggle_move'), "move.png");
                        $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_SORT, TR::t('sort_channels'));
                        $menu_items[] = $this->plugin->create_menu_item($this, ACTION_RESET_ITEMS_SORT, TR::t('reset_channels_sort'));
                    }
                }

                if (!is_limited_apk()) {
                    $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
                    $is_external = $this->plugin->get_channels_for_ext_player()->in_order($channel_id);
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
                    GUI_EVENT_KEY_SUBTITLE,
                    TR::t('channel_epg_dlg'),
                    "epg.png");
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
                    $this->plugin->hide_channels_by_mask($user_input->hide, $selected_media_url->group_id);
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
                    $this->plugin->set_parameter(PARAM_CUSTOM_DELETE_STRING, $custom_string);
                    $this->plugin->hide_channels_by_mask($custom_string, $selected_media_url->group_id, false);
                }
                break;

            case ACTION_ZOOM_POPUP_MENU:
                $menu_items = array();
                $zoom_data = $this->plugin->get_channel_zoom($selected_media_url->channel_id);
                foreach (DuneVideoZoomPresets::$zoom_ops_translated as $idx => $zoom_item) {
                    $menu_items[] = $this->plugin->create_menu_item($this,
                        ACTION_ZOOM_APPLY,
                        TR::load_string($zoom_item),
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

            case ACTION_ITEMS_SORT:
                $this->plugin->sort_channels_order($parent_media_url->group_id);
                break;

            case ACTION_RESET_ITEMS_SORT:
                $this->plugin->sort_channels_order($parent_media_url->group_id, true);
                break;

            case ACTION_RELOAD:
                hd_debug_print("reload");
                $this->plugin->reload_channels($plugin_cookies);
                return Starnet_Epfs_Handler::epfs_invalidate_folders(
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
     * @param string $group_id
     * @param string $find_text
     * @return array
     */
    protected function do_search($group_id, $find_text)
    {
        hd_debug_print($group_id, true);

        $channels = $this->plugin->get_channels($group_id, 0);

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

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print("MediaUrl: " . $media_url, true);

        $items = array();

        try {
            if ($this->plugin->load_channels($plugin_cookies) === 0) {
                throw new Exception("Channels not loaded!");
            }

            if ($media_url->group_id === ALL_CHANNELS_GROUP_ID) {
                $groups_order = $this->plugin->get_groups_order();
            } else {
                $groups_order[] = $media_url->group_id;
            }

            $picons_source = $this->plugin->get_setting(PARAM_USE_PICONS, PLAYLIST_PICONS);
            $fav_ids = $this->plugin->get_channels_order(FAV_CHANNELS_GROUP_ID);

            foreach ($groups_order as $group_id) {
                foreach ($this->plugin->get_channels_by_order($group_id) as $channel_row) {

                    $epg_ids = array('epg_id' => $channel_row['epg_id'], 'id' => $channel_row['channel_id'], 'name' => $channel_row['title']);

                    if ($picons_source !== XMLTV_PICONS) {
                        // playlist icons first in priority
                        $icon_url = $channel_row['icon'];
                    }

                    // if selected xmltv or combined mode look into xmltv source
                    // in combined mode search is not performed if already got picon from playlist
                    if ($picons_source === XMLTV_PICONS || ($picons_source === COMBINED_PICONS && empty($icon_url))) {
                        $icon_url = $this->plugin->get_epg_manager()->get_picon($epg_ids);
                        if (empty($icon_url)) {
                            hd_debug_print("no picon for " . pretty_json_format($epg_ids), true);
                        }
                    }

                    if (empty($icon_url)) {
                        $icon_url = DEFAULT_CHANNEL_ICON_PATH;
                    }

                    $epg_str = HD::ArrayToStr(array_values($epg_ids));
                    $zoom_data = $this->plugin->get_channel_zoom($channel_row['channel_id']);
                    if ($zoom_data === DuneVideoZoomPresets::not_set) {
                        $detailed_info = TR::t('tv_screen_channel_info__4',
                            $channel_row['title'],
                            $channel_row['archive'],
                            $channel_row['channel_id'],
                            $epg_str
                        );
                    } else {
                        $detailed_info = TR::t('tv_screen_channel_info__5',
                            $channel_row['title'],
                            $channel_row['archive'],
                            $channel_row['channel_id'],
                            $epg_str,
                            TR::load_string(DuneVideoZoomPresets::$zoom_ops_translated[$zoom_data])
                        );
                    }

                    $items[] = array(
                        PluginRegularFolderItem::media_url => MediaURL::encode(array('channel_id' => $channel_row['channel_id'], 'group_id' => $group_id)),
                        PluginRegularFolderItem::caption => $channel_row['title'],
                        PluginRegularFolderItem::starred => in_array($channel_row['channel_id'], $fav_ids),
                        PluginRegularFolderItem::view_item_params => array(
                            ViewItemParams::icon_path => $icon_url,
                            ViewItemParams::item_detailed_icon_path => $icon_url,
                            ViewItemParams::item_detailed_info => $detailed_info,
                        ),
                    );
                }
            }
        } catch (Exception $ex) {
            hd_debug_print("Failed collect folder items!");
            print_backtrace_exception($ex);
        }

        return $items;
    }

    ///////////////////////////////////////////////////////////////////////

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

            $this->plugin->get_screen_view('icons_7x4_no_caption'),
            $this->plugin->get_screen_view('icons_7x4_caption'),

            $this->plugin->get_screen_view('list_1x11_small_info'),
            $this->plugin->get_screen_view('list_2x11_small_info'),
            $this->plugin->get_screen_view('list_3x11_no_info'),
        );
    }
}
