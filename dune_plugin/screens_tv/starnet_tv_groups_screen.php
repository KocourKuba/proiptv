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

require_once 'lib/dune_plugin_constants.php';
require_once 'lib/abstract_preloaded_regular_screen.php';
require_once 'lib/user_input_handler_registry.php';

class Starnet_Tv_Groups_Screen extends Abstract_Preloaded_Regular_Screen
{
    const ID = 'tv_groups';

    const ACTION_RESET_ICON_DEFAULT = 'reset_icon_default';
    const ACTION_ICON_SELECTED = 'icon_selected';

    ///////////////////////////////////////////////////////////////////////

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

        $actions[GUI_EVENT_KEY_ENTER] = User_Input_Handler_Registry::create_action($this, ACTION_OPEN_FOLDER);
        $actions[GUI_EVENT_KEY_PLAY] = User_Input_Handler_Registry::create_action($this, ACTION_PLAY_FOLDER);

        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_TOP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_TOP_MENU);
        $actions[GUI_EVENT_TIMER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_TIMER);

        $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_EDIT_PLAYLIST_SETTINGS, TR::t('setup_playlist'));
        $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_PLUGIN_SETTINGS, TR::t('entry_setup'));

        $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, ACTION_PLUGIN_INFO, TR::t('plugin_info'));
        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);

        $actions[GUI_EVENT_KEY_SETUP] = User_Input_Handler_Registry::create_action($this, ACTION_EDIT_PLAYLIST_SETTINGS);
        $actions[GUI_EVENT_KEY_CLEAR] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE);
        $actions[GUI_EVENT_KEY_INFO] = User_Input_Handler_Registry::create_action($this, ACTION_INFO_DLG);
        $actions[GUI_EVENT_KEY_SELECT] = User_Input_Handler_Registry::create_action($this, ACTION_ITEMS_EDIT,
            null,
            array(CONTROL_ACTION_EDIT => Starnet_Edit_Playlists_Screen::SCREEN_EDIT_PLAYLIST, PARAM_PLAYLIST_ID => $this->plugin->get_active_playlist_id())
        );

        if (!is_limited_apk()) {
            // this key used to fire event from background xmltv indexing script
            $actions[EVENT_INDEXING_DONE] = User_Input_Handler_Registry::create_action($this, EVENT_INDEXING_DONE);
        }

        if (!$this->plugin->is_plugin_inited()) {
            $this->plugin->init_plugin();
        }
        $this->plugin->add_shortcuts_handlers($this, $actions);

        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        if (!isset($user_input->parent_media_url)) {
            hd_debug_print('user input parent media url not set', true);
            return null;
        }

        $fav_id = $this->plugin->get_fav_id();
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $selected_media_url = MediaURL::decode(safe_get_value($user_input, 'selected_media_url'));
        $sel_ndx = safe_get_value($user_input, 'sel_ndx', 0);

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                if (safe_get_value($plugin_cookies, PARAM_COOKIE_PLAYLIST_FIRST, SwitchOnOff::off) === SwitchOnOff::off
                    && $this->plugin->get_bool_parameter(PARAM_ASK_EXIT)) {
                    return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, ACTION_CONFIRM_EXIT_DLG_APPLY);
                }

                $this->force_parent_reload = false;
                hd_debug_print('Force parent reload', true);
                return User_Input_Handler_Registry::create_action($this, ACTION_CONFIRM_EXIT_DLG_APPLY);

            case GUI_EVENT_TIMER:
                $error_msg = Dune_Last_Error::get_last_error(LAST_ERROR_PLAYLIST);
                if (!empty($error_msg)) {
                    hd_debug_print("Playlist loading error: $error_msg");
                    return Action_Factory::show_title_dialog(TR::t('err_load_playlist'), $error_msg);
                }

                if (!is_limited_apk()) {
                    return null;
                }
                $actions[] = $this->plugin->get_import_xmltv_logs_actions($plugin_cookies);
                $actions[] = Action_Factory::change_behaviour($this->do_get_action_map(), 1000);
                return Action_Factory::composite($actions);

            case EVENT_INDEXING_DONE:
                return $this->plugin->get_import_xmltv_logs_actions($plugin_cookies);

            case ACTION_OPEN_FOLDER:
            case ACTION_PLAY_FOLDER:
                $has_error = Dune_Last_Error::get_last_error(LAST_ERROR_PLAYLIST);
                if (empty($has_error)) {
                    if ($selected_media_url->{PARAM_GROUP_ID} !== VOD_GROUP_ID) {
                        return Action_Factory::open_folder();
                    }

                    if ($this->plugin->vod->fetchVodCategories()) {
                        return Action_Factory::open_folder();
                    }

                    $has_error = Dune_Last_Error::get_last_error(LAST_ERROR_VOD_LIST);
                }

                return Action_Factory::show_title_dialog(TR::t('err_load_any'), $has_error);

            case ACTION_ITEM_DELETE:
                $this->force_parent_reload = true;
                $group_id = safe_get_value($selected_media_url, COLUMN_GROUP_ID);
                if ($group_id !== TV_CHANGED_CHANNELS_GROUP_ID && $group_id !== TV_HISTORY_GROUP_ID) {
                    return null;
                }

                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_clear_all_msg'),
                    $this, ACTION_CONFIRM_CLEAR_DLG_APPLY);

            case ACTION_ITEMS_EDIT:
                return $this->plugin->do_edit_list_screen(static::ID, $user_input->{CONTROL_ACTION_EDIT}, $selected_media_url);

            case ACTION_NEW_SEARCH:
                return Action_Factory::close_dialog_and_run($this->plugin->do_search($this, $user_input->{ACTION_NEW_SEARCH}, $plugin_cookies));

            case ACTION_JUMP_TO_CHANNEL_IN_GROUP:
                if (isset($user_input->{COLUMN_CHANNEL_ID})) {
                    return $this->plugin->jump_to_channel($user_input->{COLUMN_CHANNEL_ID});
                }
                break;

            case ACTION_PLUGIN_SETTINGS:
                return $this->plugin->show_protect_settings_dialog($this,
                    Action_Factory::open_folder(Starnet_Setup_Plugin_Screen::make_controls_media_url_str(static::ID), TR::t('entry_setup'))
                );

            case ACTION_EDIT_PLAYLIST_SETTINGS:
                return $this->plugin->show_protect_settings_dialog($this,
                    Action_Factory::open_folder(Starnet_Setup_Playlist_Screen::make_controls_media_url_str(static::ID), TR::t('setup_playlist'))
                );

            case ACTION_PASSWORD_APPLY:
                return $this->plugin->apply_protect_settings_dialog($user_input);

            case ACTION_CONFIRM_EXIT_DLG_APPLY:
                $this->force_parent_reload = false;
                hd_debug_print('Force parent reload', true);
                return Action_Factory::invalidate_epfs_folders($plugin_cookies, Action_Factory::close_and_run());

            case ACTION_PLUGIN_INFO:
                return $this->plugin->get_plugin_info_dlg($this);

            case GUI_EVENT_KEY_POPUP_MENU:
                return $this->create_popup_menu(safe_get_value($selected_media_url, COLUMN_GROUP_ID), $plugin_cookies);

            case ACTION_EPG_CACHE_ENGINE:
                $this->plugin->epg_engine_menu_items($this, $menu_items);
                return Action_Factory::show_popup_menu($menu_items);

            case ENGINE_XMLTV:
            case ENGINE_JSON:
                if ($this->plugin->get_setting(PARAM_EPG_CACHE_ENGINE, ENGINE_XMLTV) !== $user_input->control_id) {
                    hd_debug_print("Selected engine: $user_input->control_id", true);
                    $this->plugin->set_setting(PARAM_EPG_CACHE_ENGINE, $user_input->control_id);
                    $this->plugin->init_epg_manager();
                    $active_sources = $this->plugin->get_selected_xmltv_ids($this->plugin->get_active_playlist_id());
                    $post_action = User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);
                    if ($user_input->control_id === ENGINE_XMLTV) {
                        if (empty($active_sources)) {
                            $post_action = Action_Factory::show_title_dialog(TR::t('error'), TR::t('err_no_xmltv_sources'), $post_action);
                        } else {
                            $this->plugin->reset_channels_loaded();
                        }
                    }
                    return $post_action;
                }
                break;

            case self::ACTION_ICON_SELECTED:
                $data = MediaURL::decode($user_input->{Starnet_Folder_Screen::PARAM_SELECTED_DATA});
                $group = $this->plugin->get_group($selected_media_url->{PARAM_GROUP_ID}, PARAM_ALL);
                if (is_null($group)) break;

                $cached_image_name = $this->plugin->get_active_playlist_id() . '_' . $data->{PARAM_CAPTION};
                $cached_image_path = get_cached_image_path($cached_image_name);
                hd_print('copy from: ' . $data->{PARAM_FILEPATH} . " to: $cached_image_path");
                if (!copy($data->{PARAM_FILEPATH}, $cached_image_path)) {
                    return Action_Factory::show_title_dialog(TR::t('error'), TR::t('err_copy'));
                }

                hd_debug_print("Assign icon: $cached_image_name to group: $selected_media_url->{PARAM_GROUP_ID}");
                $this->plugin->set_group_icon($selected_media_url->{PARAM_GROUP_ID}, $cached_image_name);
                return Action_Factory::refresh_entry_points($this->invalidate_current_folder($parent_media_url, $plugin_cookies, $sel_ndx));

            case ACTION_CONFIRM_CLEAR_DLG_APPLY:
                $group_id = safe_get_value($selected_media_url, COLUMN_GROUP_ID);
                if ($group_id === TV_HISTORY_GROUP_ID) {
                    $this->plugin->clear_tv_history();
                    return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);
                }

                if ($group_id === $fav_id) {
                    $this->plugin->change_tv_favorites(ACTION_ITEMS_CLEAR, null, $plugin_cookies);
                    return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);
                }

                if ($group_id === TV_CHANGED_CHANNELS_GROUP_ID) {
                    $this->plugin->clear_changed_channels();
                    return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);
                }
                break;

            case self::ACTION_RESET_ICON_DEFAULT:
                hd_debug_print("Reset icon for group: " . $selected_media_url->{PARAM_GROUP_ID} . " to default");
                switch ($selected_media_url->{PARAM_GROUP_ID}) {
                    case TV_ALL_CHANNELS_GROUP_ID:
                        $icon = TV_ALL_CHANNELS_GROUP_ICON;
                        break;

                    case $fav_id:
                        $icon = TV_FAV_GROUP_ICON;
                        break;

                    case TV_HISTORY_GROUP_ID:
                        $icon = TV_HISTORY_GROUP_ICON;
                        break;

                    case TV_CHANGED_CHANNELS_GROUP_ID:
                        $icon = TV_CHANGED_CHANNELS_GROUP_ICON;
                        break;

                    case VOD_GROUP_ID:
                        $icon = VOD_GROUP_ICON;
                        break;

                    default:
                        $icon = '';
                }

                $this->plugin->set_group_icon($selected_media_url->{PARAM_GROUP_ID}, $icon);
                break;

            case ACTION_INFO_DLG:
                $provider = $this->plugin->get_active_provider();
                if (is_null($provider) || !$provider->hasApiCommand(API_COMMAND_ACCOUNT_INFO)) {
                    return null;
                }

                return $this->plugin->do_show_subscription($this);

            case ACTION_ADD_MONEY_DLG:
                return $this->plugin->do_show_add_money();

            case ACTION_SHORTCUT:
                if (!isset($user_input->{COLUMN_PLAYLIST_ID}) || $this->plugin->get_active_playlist_id() === $user_input->{COLUMN_PLAYLIST_ID}) {
                    return null;
                }

                $this->plugin->vod = null;
                $this->plugin->set_active_playlist_id($user_input->{COLUMN_PLAYLIST_ID});
                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case ACTION_REFRESH_SCREEN:
                $actions[] = Action_Factory::refresh_entry_points();
                $actions[] = Action_Factory::close_and_run();
                $actions[] = Action_Factory::open_folder(static::ID, $this->plugin->get_plugin_title());
                $actions[] = Action_Factory::change_behaviour($this->do_get_action_map());
                return Action_Factory::composite($actions);

            case ACTION_RELOAD:
                hd_debug_print('Action reload', true);

                $this->force_parent_reload = true;
                if (!$this->plugin->load_channels($plugin_cookies, isset($user_input->{PARAM_CLEAR_PLAYLIST}))) {
                    $actions[] = Action_Factory::show_title_dialog(TR::t('err_load_playlist'), Dune_Last_Error::get_last_error(LAST_ERROR_PLAYLIST));
                }
                $actions[] = Action_Factory::invalidate_all_folders($plugin_cookies);
                $actions[] = User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);
                return Action_Factory::composite($actions);

            case ACTION_INVALIDATE:
                $this->force_parent_reload = true;
                break;

            case ACTION_EMPTY:
            default:
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

        if (!$this->plugin->is_channels_loaded()) {
            hd_debug_print('Channels not loaded!');
            return array();
        }

        $show_count = $this->plugin->get_bool_setting(PARAM_SHOW_CLASSIC_CHANNEL_COUNT, SwitchOnOff::off);
        $show_adult = $this->plugin->get_bool_setting(PARAM_SHOW_ADULT);
        $is_vod_playlist = $this->plugin->is_vod_playlist();
        $ordinary_items = array();
        if (!$is_vod_playlist) {
            $all_groups = $this->plugin->get_groups_channels($show_adult);
            foreach ($all_groups as $group_row) {
                $caption = str_replace('|', '¦', $group_row[COLUMN_TITLE]);
                if ($show_count) {
                    $enabled = safe_get_value($group_row, 'enabled', 0);
                    if ($enabled === 0) continue;

                    $disabled = safe_get_value($group_row, 'disabled', 0);
                    $detailed_info = TR::t('tv_screen_group_info__3', $caption, $enabled, $disabled);
                } else {
                    $detailed_info = TR::t('tv_screen_group_info__1', $caption);
                }

                $ordinary_items[] = $this->add_item($group_row, $caption, DEF_LABEL_TEXT_COLOR_WHITE, $detailed_info);
            }
        }

        hd_debug_print('Total ordinary items: ' . count($ordinary_items), true);
        $no_channels = empty($ordinary_items);

        $special_items = array();
        foreach ($this->plugin->get_groups(PARAM_GROUP_SPECIAL, PARAM_ALL) as $group_row) {
            $group_id = $group_row[COLUMN_GROUP_ID];
            if (($this->plugin->is_vod_playlist() && $group_id !== VOD_GROUP_ID) || ($group_id !== VOD_GROUP_ID && $no_channels)) continue;

            switch ($group_id) {
                case TV_ALL_CHANNELS_GROUP_ID:
                    if (!$this->plugin->get_bool_setting(PARAM_SHOW_ALL)) break;

                    $caption = TR::t('plugin_all_channels');
                    if ($show_count) {
                        $row = $this->plugin->get_all_channels_count($show_adult);
                        $enabled = safe_get_value($row, 'enabled', 0);
                        $disabled = safe_get_value($row, 'disabled', 0);
                        $disabled_groups = $this->plugin->get_groups_count(PARAM_GROUP_ORDINARY, PARAM_DISABLED);
                        $detailed_info = TR::t('tv_screen_group_info__4', $caption, $enabled, $disabled, $disabled_groups);
                    } else {
                        $detailed_info = TR::t('tv_screen_group_info__1', $caption);
                    }
                    $special_items[] = $this->add_item($group_row, $caption, DEF_LABEL_TEXT_COLOR_SKYBLUE, $detailed_info);
                    break;

                case TV_FAV_GROUP_ID:
                    if (!$this->plugin->get_bool_setting(PARAM_SHOW_FAVORITES)) break;
                    $fav_id = $this->plugin->get_fav_id();

                    $channels_cnt = $this->plugin->get_channels_by_order_cnt($fav_id, $show_adult, true);
                    if (!$channels_cnt) break;

                    $caption = $fav_id === TV_FAV_COMMON_GROUP_ID ? TR::t('plugin_common_favorites') : TR::t('plugin_favorites');
                    if ($show_count) {
                        $detailed_info = TR::t('tv_screen_group_info__2', $caption, $channels_cnt);
                    } else {
                        $detailed_info = TR::t('tv_screen_group_info__1', $caption);
                    }
                    $special_items[] = $this->add_item($group_row, $caption, DEF_LABEL_TEXT_COLOR_GOLD, $detailed_info);
                    break;

                case TV_HISTORY_GROUP_ID:
                    if (!$this->plugin->get_bool_setting(PARAM_SHOW_HISTORY)) break;

                    $channels_cnt = $this->plugin->get_tv_history_count();
                    if (!$channels_cnt) break;

                    $caption = TR::t('plugin_history');
                    if ($show_count) {
                        $detailed_info = TR::t('tv_screen_group_info__2', $caption, $channels_cnt);
                    } else {
                        $detailed_info = TR::t('tv_screen_group_info__1', $caption);
                    }
                    $special_items[] = $this->add_item($group_row, $caption, DEF_LABEL_TEXT_COLOR_TURQUOISE, $detailed_info);
                    break;

                case TV_CHANGED_CHANNELS_GROUP_ID:
                    if (!$this->plugin->get_bool_setting(PARAM_SHOW_CHANGED_CHANNELS)
                        || !$this->plugin->get_changed_channels_count(PARAM_CHANGED)) break;

                    $new = $this->plugin->get_changed_channels_count(PARAM_NEW);
                    $removed = $this->plugin->get_changed_channels_count(PARAM_REMOVED);
                    $caption = TR::t('plugin_changed');
                    $detailed_info = TR::t('tv_screen_group_changed_info__3', $caption, $new, $removed);
                    $special_items[] = $this->add_item($group_row, $caption, DEF_LABEL_TEXT_COLOR_RED, $detailed_info);
                    break;

                case VOD_GROUP_ID:
                    if ($this->plugin->is_vod_enabled() && $this->plugin->get_bool_setting(PARAM_SHOW_VOD)) {
                        $caption = TR::t(VOD_GROUP_CAPTION);
                        $special_items[] = $this->add_item($group_row, $caption, DEF_LABEL_TEXT_COLOR_LIGHTGREEN, $caption);
                    }
                    break;
            }
        }

        if ($is_vod_playlist) {
            return $special_items;
        }

        return array_merge($special_items, $ordinary_items);
    }

    /**
     * @inheritDoc
     */
    public function get_timer()
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
            $this->plugin->get_screen_view('list_1x11_info'),
            $this->plugin->get_screen_view('list_2x11_small_info'),
            $this->plugin->get_screen_view('list_3x11_no_info'),

            $this->plugin->get_screen_view('icons_4x3_caption'),
            $this->plugin->get_screen_view('icons_4x3_no_caption'),
            $this->plugin->get_screen_view('icons_3x3_caption'),
            $this->plugin->get_screen_view('icons_3x3_no_caption'),
            $this->plugin->get_screen_view('icons_5x3_caption'),
            $this->plugin->get_screen_view('icons_5x3_no_caption'),
            $this->plugin->get_screen_view('icons_5x4_caption'),
            $this->plugin->get_screen_view('icons_5x4_no_caption'),
        );
    }

    /**
     * @param string $group_id
     * @param $plugin_cookies
     * @return array
     */
    public function create_popup_menu($group_id, $plugin_cookies)
    {
        hd_debug_print(null, true);

        $menu_items = array();
        $this->plugin->refresh_playlist_menu_items($this, $menu_items);
        $menu_items[] = User_Input_Handler_Registry::create_popup_item_ext($this->plugin->new_search($this, $plugin_cookies),
            TR::t('search'), 'search.png');

        $menu_items[] = Control_Factory::menu_separator();

        $fav_id = $this->plugin->get_fav_id();
        if ($group_id !== null) {
            // menu for group
            $action_clear = Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_clear_all_msg'),
                $this, ACTION_CONFIRM_CLEAR_DLG_APPLY);

            if ($group_id === $fav_id && $this->plugin->get_order_count($fav_id)) {
                $menu_items[] = User_Input_Handler_Registry::create_popup_item_ext($action_clear, TR::t('clear_favorites'), 'brush.png');
            }
            if ($group_id === TV_HISTORY_GROUP_ID && $this->plugin->get_tv_history_count() !== 0) {
                $menu_items[] = User_Input_Handler_Registry::create_popup_item_ext($action_clear, TR::t('clear_history'), 'brush.png');
            } else if ($group_id === TV_CHANGED_CHANNELS_GROUP_ID) {
                $menu_items[] = User_Input_Handler_Registry::create_popup_item_ext($action_clear, TR::t('clear_changed'), 'brush.png');
            }

            $media_url = Starnet_Folder_Screen::make_callback_media_url_str(static::ID,
                array(
                    PARAM_EXTENSION => IMAGE_PREVIEW_PATTERN,
                    Starnet_Folder_Screen::PARAM_CHOOSE_FILE => self::ACTION_ICON_SELECTED,
                    Starnet_Folder_Screen::PARAM_RESET_ACTION => self::ACTION_RESET_ICON_DEFAULT,
                    Starnet_Folder_Screen::PARAM_ALLOW_NETWORK => !is_limited_apk(),
                    Starnet_Folder_Screen::PARAM_ALLOW_IMAGE_LIB => true,
                    Starnet_Folder_Screen::PARAM_READ_ONLY => true,
                )
            );
            $menu_items[] = User_Input_Handler_Registry::create_popup_item_ext(
                Action_Factory::open_folder($media_url, TR::t('select_file')),
                TR::t('change_group_icon'), 'image.png');

            $menu_items[] = User_Input_Handler_Registry::create_popup_item($this,
                ACTION_ITEMS_EDIT,
                TR::t('tv_screen_edit_groups'),
                "move.png",
                array(CONTROL_ACTION_EDIT => Starnet_Edit_Group_List_Screen::PARAM_EDIT_GROUPS));

            if ($group_id !== TV_ALL_CHANNELS_GROUP_ID) {
                $menu_items[] = User_Input_Handler_Registry::create_popup_item_ext(
                    $this->plugin->do_edit_list_screen(static::ID,
                        Starnet_Edit_Channel_List_Screen::PARAM_EDIT_CHANNELS, $group_id),
                    TR::t('tv_screen_edit_channels'), 'edit.png');
            }

            $menu_items[] = Control_Factory::menu_separator();

            $this->plugin->epg_select_menu_items($this, $menu_items);

            if ($this->plugin->has_active_provider()) {
                $menu_items[] = Control_Factory::menu_separator();
                if ($this->plugin->get_active_provider()->hasApiCommand(API_COMMAND_ACCOUNT_INFO)) {
                    $menu_items[] = User_Input_Handler_Registry::create_popup_item($this, ACTION_INFO_DLG, TR::t('subscription'), 'info.png');
                }
            }
        }

        return Action_Factory::show_popup_menu($menu_items);
    }

    /**
     * @param array $group_row
     * @param string $caption
     * @param string $color
     * @param string $item_detailed_info
     * @return array
     */
    private function add_item($group_row, $caption, $color, $item_detailed_info)
    {
        $icon = get_cached_image(safe_get_value($group_row, COLUMN_ICON, DEFAULT_GROUP_ICON));

        return array(
            PluginRegularFolderItem::media_url => Default_Dune_Plugin::get_group_media_url_str($group_row[COLUMN_GROUP_ID]),
            PluginRegularFolderItem::caption => $caption,
            PluginRegularFolderItem::view_item_params => array(
                ViewItemParams::item_caption_color => $color,
                ViewItemParams::icon_path => $icon,
                ViewItemParams::item_detailed_icon_path => $icon,
                ViewItemParams::item_detailed_info => $item_detailed_info,
            )
        );
    }
}
