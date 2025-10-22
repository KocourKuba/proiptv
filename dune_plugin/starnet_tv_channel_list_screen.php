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

    const ACTION_CUSTOM_DELETE = 'custom_delete';
    const ACTION_CUSTOM_STRING_DLG_APPLY = 'apply_custom_string_dlg';

    /**
     * Get MediaURL string representation (json encoded)
     * *
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

        $action_play = User_Input_Handler_Registry::create_action($this, ACTION_PLAY_ITEM);

        $actions[GUI_EVENT_KEY_ENTER] = $action_play;
        $actions[GUI_EVENT_KEY_PLAY] = $action_play;
        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_TOP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_TOP_MENU);
        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);
        $actions[GUI_EVENT_KEY_SETUP] = User_Input_Handler_Registry::create_action($this, ACTION_PLUGIN_SETTINGS);
        $actions[GUI_EVENT_KEY_INFO] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_INFO);
        $actions[GUI_EVENT_KEY_CLEAR] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE);
        $actions[GUI_EVENT_KEY_SUBTITLE] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_SUBTITLE);
        $actions[GUI_EVENT_TIMER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_TIMER);

        $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, ACTION_ADD_FAV, TR::t('add_to_favorite'));
        $actions[GUI_EVENT_KEY_DUNE] = User_Input_Handler_Registry::create_action($this, ACTION_ADD_FAV, TR::t('add_to_favorite'));

        if ((string)$media_url->group_id !== TV_ALL_CHANNELS_GROUP_ID && $this->plugin->get_order_count($media_url->group_id)) {
            $actions[GUI_EVENT_KEY_SELECT] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_TOGGLE_MOVE);
            if (isset($plugin_cookies->toggle_move) && $plugin_cookies->toggle_move) {
                $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_TOP, TR::t('top'));
                $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_BOTTOM, TR::t('bottom'));
            } else {
                $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('up'));
                $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('down'));
            }
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
        $selected_media_url = MediaURL::decode($user_input->selected_media_url);
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $parent_group = $parent_media_url->group_id;
        $channel_id = $selected_media_url->channel_id;
        $sel_ndx = $user_input->sel_ndx;

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

            case GUI_EVENT_TIMER:
                clearstatcache();
                return $this->plugin->get_import_xmltv_logs_actions(
                    $this->plugin->get_selected_xmltv_ids(),
                    $this->get_action_map($parent_media_url, $plugin_cookies),
                    $plugin_cookies);

            case GUI_EVENT_KEY_INFO:
                return $this->plugin->do_show_channel_info($channel_id, true);

            case GUI_EVENT_KEY_SUBTITLE:
                $attrs['initial_sel_ndx'] = 2;
                return $this->plugin->do_show_channel_epg($this, $this->plugin->get_epg_info($channel_id, -1), $attrs);

            case PARAM_EPG_SHIFT_HOURS:
            case PARAM_EPG_SHIFT_MINS:
                hd_debug_print("Applying epg shift hours: " . $user_input->{PARAM_EPG_SHIFT_HOURS}, true);
                hd_debug_print("Applying epg shift mins: " . $user_input->{PARAM_EPG_SHIFT_MINS}, true);
                $this->plugin->set_channel_epg_shift($channel_id, $user_input->{PARAM_EPG_SHIFT_HOURS}, $user_input->{PARAM_EPG_SHIFT_MINS});
                if (isset($new_value)) {
                    $attrs['initial_sel_ndx'] = $user_input->control_id === PARAM_EPG_SHIFT_HOURS ? 0 : 1;
                    return Action_Factory::close_dialog_and_run(
                        Action_Factory::invalidate_folders(array($user_input->parent_media_url),
                            $this->plugin->do_show_channel_epg($this, $this->plugin->get_epg_info($channel_id, -1), $attrs)
                        )
                    );
                }
                break;

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

            case ACTION_ADD_FAV:
                $this->force_parent_reload = true;
                $in_order = $this->plugin->is_channel_in_order($fav_id, $channel_id);
                $opt_type = $in_order ? PLUGIN_FAVORITES_OP_REMOVE : PLUGIN_FAVORITES_OP_ADD;
                $this->plugin->change_tv_favorites($opt_type, $channel_id, $plugin_cookies);
                break;

            case ACTION_PLUGIN_SETTINGS:
                return $this->plugin->show_protect_settings_dialog($this,
                    Action_Factory::open_folder(Starnet_Setup_Screen::make_controls_media_url_str(static::ID), TR::t('entry_setup'))
                );

            case ACTION_SHOW_SEARCH_DLG:
                return $this->plugin->new_search($this, $plugin_cookies);

            case ACTION_NEW_SEARCH:
                return Action_Factory::close_dialog_and_run($this->plugin->do_search($this, $user_input->{ACTION_NEW_SEARCH}, $plugin_cookies));

            case ACTION_JUMP_TO_CHANNEL:
                return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->number);

            case ACTION_JUMP_TO_CHANNEL_IN_GROUP:
                $ch_id = $channel_id;
                if (isset($user_input->{COLUMN_CHANNEL_ID})) {
                    $ch_id = $user_input->{COLUMN_CHANNEL_ID};
                }
                return Action_Factory::close_and_run($this->plugin->iptv->jump_to_channel($ch_id));

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
                $this->plugin->arrange_channels_order_rows($parent_group, $channel_id, Ordered_Array::UP);
                break;

            case ACTION_ITEM_DOWN:
                $cnt = $this->plugin->get_order_count($parent_group) - 1;
                $sel_ndx++;
                if ($sel_ndx > $cnt) {
                    return null;
                }
                $this->force_parent_reload = true;
                $this->plugin->arrange_channels_order_rows($parent_group, $channel_id, Ordered_Array::DOWN);
                break;

            case ACTION_ITEM_TOP:
                if ($sel_ndx === 0) {
                    return null;
                }
                $this->force_parent_reload = true;
                $sel_ndx = 0;
                $this->plugin->arrange_channels_order_rows($parent_group, $channel_id, Ordered_Array::TOP);
                break;

            case ACTION_ITEM_BOTTOM:
                $max_sel = $this->plugin->get_order_count($fav_id) - 1;
                if ($sel_ndx === $max_sel) {
                    return null;
                }
                $this->force_parent_reload = true;
                $sel_ndx = $max_sel;
                $this->plugin->arrange_channels_order_rows($parent_group, $channel_id, Ordered_Array::BOTTOM);
                break;

            case ACTION_ITEM_DELETE:
                $this->force_parent_reload = true;
                $this->plugin->set_channel_visible($channel_id, false);
                break;

            case ACTION_ITEMS_EDIT:
                return $this->plugin->do_edit_list_screen(static::ID, $user_input->action_edit, $parent_media_url);

            case GUI_EVENT_KEY_POPUP_MENU:
                $menu_items[] = $this->plugin->create_menu_item($this,
                    ACTION_SHOW_SEARCH_DLG,
                    TR::t('search'),
                    "search.png");

                if ($parent_group === TV_ALL_CHANNELS_GROUP_ID) {
                    $menu_items[] = $this->plugin->create_menu_item($this,
                        ACTION_JUMP_TO_CHANNEL_IN_GROUP,
                        TR::t('jump_to_channel'),
                        "goto.png");
                    $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
                    $menu_items = array_merge($menu_items, $this->plugin->edit_hidden_menu($this, $parent_group, false));
                } else {
                    $special_group = $this->plugin->get_group($parent_group, PARAM_GROUP_SPECIAL);
                    if (empty($special_group)) {
                        $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
                        $menu_items = array_merge($menu_items, $this->plugin->edit_hidden_menu($this, $parent_group, false));
                        $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEM_TOGGLE_MOVE, TR::t('tv_screen_toggle_move'), "move.png");
                        $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
                        $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_SORT, TR::t('sort_channels'));
                        $menu_items[] = $this->plugin->create_menu_item($this, ACTION_RESET_ITEMS_SORT, TR::t('reset_channels_sort'));
                    }
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
                $menu_items[] = $this->plugin->create_menu_item($this,
                    ACTION_EDIT_CHANNEL_DLG,
                    TR::t('tv_screen_edit_channel'),
                    "check.png");

                return Action_Factory::show_popup_menu($menu_items);

            case ACTION_ITEM_DELETE_CHANNELS:
                $items = array(
                    TR::t('tv_screen_hide_plus') => "[\s(]\+\d",
                    TR::t('tv_screen_hide_orig') => "[Oo]rig|[Uu]ncomp",
                    TR::t('tv_screen_hide_50') => "\s50|\sFHD",
                    TR::t('tv_screen_hide_uhd') => "UHD|\s4[KkКк]|\s8[KkКк]",
                    TR::t('tv_screen_hide_sd') => "hide_sd",
                    TR::t('tv_screen_hide_string') => "custom_string"
                );
                foreach ($items as $key => $val) {
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEM_DELETE_BY_STRING, $key, null, array('hide' => $val));
                }
                return Action_Factory::show_popup_menu($menu_items);

            case ACTION_ITEM_DELETE_BY_STRING:
                if ($user_input->hide === 'hide_sd') {
                    $this->force_parent_reload = $this->plugin->hide_sd_channels($parent_group) !== 0;
                } else if ($user_input->hide !== 'custom_string') {
                    $this->force_parent_reload = $this->plugin->hide_channels_by_mask($user_input->hide, $parent_group) !== 0;
                } else {
                    $defs = array();
                    Control_Factory::add_text_field($defs, $this, null, self::ACTION_CUSTOM_DELETE, '',
                        $this->plugin->get_parameter(PARAM_CUSTOM_DELETE_STRING),
                        false, false, false, false, 800);

                    Control_Factory::add_vgap($defs, 100);
                    Control_Factory::add_close_dialog_and_apply_button($defs, $this, self::ACTION_CUSTOM_STRING_DLG_APPLY, TR::t('ok'), 300);
                    Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
                    Control_Factory::add_vgap($defs, 10);

                    return Action_Factory::show_dialog(TR::t('tv_screen_hide_string'), $defs, true, 1300);
                }
                break;

            case self::ACTION_CUSTOM_STRING_DLG_APPLY:
                $custom_string = $user_input->{self::ACTION_CUSTOM_DELETE};
                if (!empty($custom_string)) {
                    $this->plugin->set_parameter(PARAM_CUSTOM_DELETE_STRING, $custom_string);
                    $this->force_parent_reload = $this->plugin->hide_channels_by_mask($custom_string, $parent_group, false) !== 0;
                }
                break;

            case ACTION_ITEMS_SORT:
                $this->force_parent_reload = true;
                $this->plugin->sort_channels_order($parent_group);
                break;

            case ACTION_RESET_ITEMS_SORT:
                $this->force_parent_reload = true;
                $this->plugin->sort_channels_order($parent_group, true);
                break;

            case ACTION_EDIT_CHANNEL_DLG:
                return $this->plugin->do_edit_channel_parameters($this, $channel_id);

            case ACTION_EDIT_CHANNEL_APPLY:
                $this->plugin->do_edit_channel_apply($user_input, $channel_id);
                break;

            case ACTION_SHORTCUT:
                if (!isset($user_input->{COLUMN_PLAYLIST_ID}) || $this->plugin->get_active_playlist_id() === $user_input->{COLUMN_PLAYLIST_ID}) {
                    return null;
                }

                $this->plugin->set_active_playlist_id($user_input->{COLUMN_PLAYLIST_ID});
                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case ACTION_RELOAD:
                hd_debug_print("Action reload", true);
                $this->plugin->reset_channels();
                $this->plugin->load_channels($plugin_cookies);
                return Action_Factory::invalidate_all_folders(
                    $plugin_cookies,
                    array(Starnet_Tv_Groups_Screen::ID),
                    Action_Factory::close_and_run(
                        Action_Factory::close_and_run(
                            Action_Factory::open_folder(Starnet_Tv_Groups_Screen::ID, $this->plugin->get_plugin_title())
                        )
                    )
                );

            case ACTION_INVALIDATE:
                $this->force_parent_reload = true;
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
        hd_debug_print($media_url, true);

        $items = array();

        try {
            if (!$this->plugin->is_channels_loaded() && !$this->plugin->load_channels($plugin_cookies)) {
                throw new Exception("Channels not loaded!");
            }

            if ($media_url->group_id === TV_ALL_CHANNELS_GROUP_ID) {
                $groups_order = $this->plugin->get_groups_by_order();
            } else {
                $groups_order[] = $this->plugin->get_group($media_url->group_id, PARAM_GROUP_ORDINARY);
            }

            $fav_id = $this->plugin->get_fav_id();
            $fav_ids = $this->plugin->get_channels_order($fav_id);
            $show_adult = $this->plugin->get_bool_setting(PARAM_SHOW_ADULT);

            foreach ($groups_order as $group_row) {
                if ($group_row[COLUMN_ADULT] && !$show_adult) continue;

                $group_id = $group_row[COLUMN_GROUP_ID];
                $channels_rows = $this->plugin->get_channels_by_order($group_id);
                foreach ($channels_rows as $channel_row) {
                    if (!$show_adult && $channel_row[COLUMN_ADULT] !== 0) continue;

                    $channel_id = $channel_row[COLUMN_CHANNEL_ID];
                    $icon_url = $this->plugin->get_channel_picon($channel_row, true);
                    $title = $channel_row[COLUMN_TITLE];
                    $archive = $channel_row[COLUMN_ARCHIVE];
                    $zoom = safe_get_value($channel_row, COLUMN_ZOOM, DuneVideoZoomPresets::not_set);
                    $epg_shift = format_duration_minutes((int)safe_get_value($channel_row, COLUMN_EPG_SHIFT, 0));

                    $epg_str = HD::ArrayToStr(array_values(Default_Dune_Plugin::make_epg_ids($channel_row)));
                    if ($zoom === DuneVideoZoomPresets::not_set || $zoom === null) {
                        $detailed_info = TR::t('tv_screen_channel_info__5', $title, $archive, $channel_id, $epg_str, $epg_shift);
                    } else {
                        $detailed_info = TR::t('tv_screen_channel_info__6', $title, $archive, $channel_id, $epg_str, $zoom, $epg_shift);
                    }

                    $items[] = array(
                        PluginRegularFolderItem::media_url => MediaURL::encode(array('channel_id' => $channel_id, 'group_id' => $group_id)),
                        PluginRegularFolderItem::caption => $title,
                        PluginRegularFolderItem::starred => in_array($channel_id, $fav_ids),
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
