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
require_once 'starnet_setup_screen.php';

class Starnet_Tv_Groups_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'tv_groups';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $actions = array();

        $actions[GUI_EVENT_KEY_ENTER] = User_Input_Handler_Registry::create_action($this, ACTION_OPEN_FOLDER);
        $actions[GUI_EVENT_KEY_PLAY] = User_Input_Handler_Registry::create_action($this, ACTION_PLAY_FOLDER);

        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_TOP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_TOP_MENU);
        $actions[GUI_EVENT_KEY_SELECT] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_TOGGLE_MOVE);
        $actions[GUI_EVENT_TIMER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_TIMER);

        if (isset($plugin_cookies->toggle_move) && $plugin_cookies->toggle_move) {
            $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_TOP, TR::t('top'));
            $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_BOTTOM, TR::t('bottom'));
        } else {
            $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('up'));
            $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('down'));
        }

        $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, ACTION_PLUGIN_INFO, TR::t('plugin_info'));
        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);

        $actions[GUI_EVENT_KEY_CLEAR] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE);
        $actions[GUI_EVENT_KEY_INFO] = User_Input_Handler_Registry::create_action($this, ACTION_INFO_DLG);

        if ($this->plugin->is_plugin_inited()) {
            $this->plugin->init_plugin();
            $this->plugin->add_shortcuts_handlers($this, $actions);
        }

        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        if (!isset($user_input->parent_media_url)) {
            return null;
        }

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $sel_media_url = MediaURL::decode(safe_get_member($user_input, 'selected_media_url', ''));
        $sel_ndx = safe_get_member($user_input, 'sel_ndx', 0);

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                if ($this->plugin->get_bool_parameter(PARAM_ASK_EXIT)) {
                    return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, ACTION_CONFIRM_EXIT_DLG_APPLY);
                }

                $this->force_parent_reload = false;
                return User_Input_Handler_Registry::create_action($this, ACTION_CONFIRM_EXIT_DLG_APPLY);

            case GUI_EVENT_TIMER:
                $error_msg = trim(HD::get_last_error($this->plugin->get_pl_error_name()));
                if (!empty($error_msg)) {
                    hd_debug_print("Playlist loading error: $error_msg");
                    return Action_Factory::show_title_dialog(TR::t('err_load_playlist'), null, $error_msg);
                }

                $epg_manager = $this->plugin->get_epg_manager();
                if ($epg_manager === null) {
                    return null;
                }

                clearstatcache();

                $actions = $this->get_action_map($parent_media_url, $plugin_cookies);
                $res = $epg_manager->import_indexing_log($this->plugin->get_selected_xmltv_ids());
                if ($res === 1) {
                    hd_debug_print("Logs imported. Timer stopped");
                    return Action_Factory::invalidate_all_folders($plugin_cookies);
                }

                if ($res === 2) {
                    hd_debug_print("No imports. Timer stopped");
                    return null;
                }

                return Action_Factory::change_behaviour($actions, 1000);

            case ACTION_OPEN_FOLDER:
            case ACTION_PLAY_FOLDER:
                $has_error = HD::get_last_error($this->plugin->get_pl_error_name());
                if (empty($has_error)) {
                    if ($sel_media_url->group_id !== VOD_GROUP_ID) {
                        return Action_Factory::open_folder();
                    }

                    $category_list = array();
                    $category_index = array();
                    if ($this->plugin->vod->fetchVodCategories($category_list, $category_index)) {
                        return Action_Factory::open_folder();
                    }

                    $has_error = HD::get_last_error($this->plugin->get_vod_error_name());
                }

                return Action_Factory::show_title_dialog(TR::t('err_load_any'), null, $has_error);

            case ACTION_ITEM_TOGGLE_MOVE:
                $plugin_cookies->toggle_move = !$plugin_cookies->toggle_move;
                $actions = $this->get_action_map($parent_media_url, $plugin_cookies);
                return Action_Factory::change_behaviour($actions);

            case ACTION_ITEM_UP:
                $this->force_parent_reload = true;
                $min_sel = $this->get_visible_groups_count();
                $sel_ndx--;
                if ($sel_ndx < $min_sel) {
                    return null;
                }

                hd_debug_print("min_sel: $min_sel sel_idx: $sel_ndx");
                $this->plugin->arrange_groups_order_rows($sel_media_url->group_id, Ordered_Array::UP);
                break;

            case ACTION_ITEM_DOWN:
                $max_sel = $this->get_visible_groups_count(true) - 1;
                $sel_ndx++;
                if ($sel_ndx > $max_sel) {
                    return null;
                }

                $this->force_parent_reload = true;
                $this->plugin->arrange_groups_order_rows($sel_media_url->group_id, Ordered_Array::DOWN);
                break;

            case ACTION_ITEM_TOP:
                $min_sel = $this->get_visible_groups_count();
                if ($sel_ndx === $min_sel) {
                    return null;
                }

                $this->force_parent_reload = true;
                $sel_ndx = $min_sel;
                hd_debug_print("min_sel: $min_sel sel_idx: $sel_ndx");
                $this->plugin->arrange_groups_order_rows($sel_media_url->group_id, Ordered_Array::TOP);
                break;

            case ACTION_ITEM_BOTTOM:
                $max_sel = $this->get_visible_groups_count(true) - 1;
                if ($sel_ndx === $max_sel) {
                    return null;
                }

                $this->force_parent_reload = true;
                $sel_ndx = $max_sel;
                $this->plugin->arrange_groups_order_rows($sel_media_url->group_id, Ordered_Array::BOTTOM);
                break;

            case ACTION_ITEM_DELETE:
                // hide group
                $this->force_parent_reload = true;
                $group_id = safe_get_member($sel_media_url, COLUMN_GROUP_ID);
                if ($group_id === TV_CHANGED_CHANNELS_GROUP_ID || $group_id === TV_HISTORY_GROUP_ID) {
                    return User_Input_Handler_Registry::create_action($this, ACTION_ITEMS_CLEAR);
                }

                $this->plugin->set_groups_visible($group_id, false);
                break;

            case ACTION_ITEMS_SORT:
                $this->force_parent_reload = true;
                $this->plugin->sort_groups_order();
                break;

            case ACTION_RESET_ITEMS_SORT:
                if (!isset($user_input->{ACTION_RESET_TYPE})) {
                    return null;
                }

                switch ($user_input->{ACTION_RESET_TYPE}) {
                    case ACTION_SORT_CHANNELS:
                        $this->plugin->sort_channels_order($sel_media_url->group_id, true);
                        break;

                    case ACTION_SORT_GROUPS:
                        $this->plugin->sort_groups_order(true);
                        break;

                    case ACTION_SORT_ALL:
                        $this->plugin->sort_groups_order(true);
                        foreach ($this->plugin->get_groups_by_order() as $row) {
                            $this->plugin->sort_channels_order($row[COLUMN_GROUP_ID],true);
                        }
                        break;

                    default:
                        return null;
                }

                $this->force_parent_reload = true;
                break;

            case ACTION_ITEMS_EDIT:
                $post_action = null;
                if (isset($user_input->{ACTION_ITEMS_EDIT}) && $user_input->{ACTION_ITEMS_EDIT} === Starnet_Edit_Playlists_Screen::SCREEN_EDIT_PLAYLIST) {
                    $active_key = $this->plugin->get_active_playlist_id();
                    if ($this->plugin->is_playlist_exist($active_key)) {
                        $post_action = User_Input_Handler_Registry::create_screen_action(Starnet_Edit_Playlists_Screen::ID,
                            ACTION_INVALIDATE,
                            null,
                            array('playlist_id' => $active_key)
                        );
                    }
                }

                return $this->plugin->do_edit_list_screen(self::ID, $user_input->action_edit, $sel_media_url, $post_action);

            case ACTION_SETTINGS:
                return $this->plugin->show_protect_settings_dialog($this, ACTION_DO_SETTINGS);

            case CONTROL_CATEGORY_SCREEN:
                return $this->plugin->show_protect_settings_dialog($this,
                    ACTION_DO_SETTINGS,
                    array(ACTION_SETUP_SCREEN => CONTROL_CATEGORY_SCREEN));

            case ACTION_DO_SETTINGS:
                if (isset($user_input->{ACTION_SETUP_SCREEN}) && $user_input->{ACTION_SETUP_SCREEN} === CONTROL_CATEGORY_SCREEN) {
                    return Action_Factory::open_folder(Starnet_Setup_Category_Screen::get_media_url_str(), TR::t('setup_category_title'));
                }
                return Action_Factory::open_folder(Starnet_Setup_Screen::get_media_url_str(), TR::t('entry_setup'));

            case ACTION_PASSWORD_APPLY:
                return $this->plugin->apply_protect_settings_dialog($this, $user_input);

            case ACTION_CONFIRM_EXIT_DLG_APPLY:
                $this->force_parent_reload = false;
                return Action_Factory::invalidate_epfs_folders($plugin_cookies, Action_Factory::close_and_run());

            case ACTION_PLUGIN_INFO:
                return $this->plugin->get_plugin_info_dlg($this);

            case ACTION_DONATE_DLG: // show donate QR codes
                return $this->plugin->do_donate_dialog();

            case GUI_EVENT_KEY_POPUP_MENU:
                if (isset($user_input->{ACTION_CHANGE_EPG_SOURCE})) {
                    $menu_items = $this->plugin->epg_source_menu($this);
                } else if (isset($user_input->{ACTION_EPG_CACHE_ENGINE})) {
                    $menu_items = $this->plugin->epg_engine_menu($this);
                } else if (isset($user_input->{ACTION_SORT_POPUP})) {
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_SORT, TR::t('sort_groups'));
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_RESET_ITEMS_SORT, TR::t('reset_groups_sort'),
                        null, array(ACTION_RESET_TYPE => ACTION_SORT_GROUPS));
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_RESET_ITEMS_SORT, TR::t('reset_all_sort'),
                        null, array(ACTION_RESET_TYPE => ACTION_SORT_ALL));
                    $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
                } else {
                    $refresh_menu = $this->plugin->refresh_playlist_menu($this);
                    $group_id = safe_get_member($sel_media_url, COLUMN_GROUP_ID);
                    $menu_items = array_merge($refresh_menu, $this->plugin->common_categories_menu($this, $group_id));
                }

                return empty($menu_items) ? null : Action_Factory::show_popup_menu($menu_items);

            case ACTION_CHANGE_EPG_SOURCE:
                hd_debug_print("Start event popup menu for epg source", true);
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU, null, array(ACTION_CHANGE_EPG_SOURCE => true));

            case ACTION_EPG_SOURCE_SELECTED:
                if (!isset($user_input->{LIST_IDX}) || $this->plugin->is_use_xmltv()) break;

                foreach ($this->plugin->get_selected_xmltv_ids() as $id) {
                    $this->plugin->safe_clear_selected_epg_cache($id);
                }
                $this->plugin->set_setting(PARAM_EPG_JSON_PRESET, $user_input->{LIST_IDX});
                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case ACTION_EPG_CACHE_ENGINE:
                hd_debug_print("Start event popup menu for epg source", true);
                return User_Input_Handler_Registry::create_action(
                    $this,
                    GUI_EVENT_KEY_POPUP_MENU,
                    null,
                    array(ACTION_EPG_CACHE_ENGINE => true)
                );

            case ENGINE_XMLTV:
            case ENGINE_JSON:
                if ($this->plugin->get_setting(PARAM_EPG_CACHE_ENGINE, ENGINE_XMLTV) !== $user_input->control_id) {
                    hd_debug_print("Selected engine: $user_input->control_id", true);
                    $this->plugin->set_setting(PARAM_EPG_CACHE_ENGINE, $user_input->control_id);
                    $this->plugin->init_epg_manager();
                    return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);
                }
                break;

            case ACTION_EDIT_PROVIDER_DLG:
            case ACTION_EDIT_PROVIDER_EXT_DLG:
                return $this->plugin->show_protect_settings_dialog($this,
                    ($user_input->control_id === ACTION_EDIT_PROVIDER_DLG)
                        ? ACTION_DO_EDIT_PROVIDER
                        : ACTION_DO_EDIT_PROVIDER_EXT);

            case ACTION_DO_EDIT_PROVIDER:
                $provider = $this->plugin->get_active_provider();
                if (is_null($provider)) {
                    return null;
                }

                $defs = array();
                Control_Factory::add_vgap($defs, 20);

                if (empty($name)) {
                    $name = $provider->getName();
                }

                $defs = $provider->GetSetupUI($name, $provider->get_provider_playlist_id(), $this);
                if (empty($defs)) {
                    return null;
                }

                return Action_Factory::show_dialog("{$provider->getName()} ({$provider->getId()})", $defs, true);

            case ACTION_DO_EDIT_PROVIDER_EXT:
                $provider = $this->plugin->get_active_provider();
                if (is_null($provider)) {
                    return null;
                }

                if (!$provider->request_provider_token()) {
                    hd_debug_print("Can't get provider token");
                    return Action_Factory::show_error(false, TR::t('err_incorrect_access_data'), array(TR::t('err_cant_get_token')));
                }

                $defs = $provider->GetExtSetupUI($this);
                if (empty($defs)) {
                    return null;
                }

                return Action_Factory::show_dialog("{$provider->getName()} ({$provider->getId()})", $defs, true);

            case ACTION_EDIT_PROVIDER_DLG_APPLY:
            case ACTION_EDIT_PROVIDER_EXT_DLG_APPLY:
                $provider = $this->plugin->get_active_provider();
                if ($provider === null) {
                    return null;
                }

                $err_msg = '';
                if ($user_input->control_id === ACTION_EDIT_PROVIDER_DLG_APPLY) {
                    $res = $provider->ApplySetupUI($user_input);
                } else {
                    $res = $provider->ApplyExtSetupUI($user_input, $err_msg);
                }

                if ($res === false || $res === null) {
                    return null;
                }

                if (is_array($res)) {
                    return $res;
                }

                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case ACTION_SORT_POPUP:
                hd_debug_print("Start event popup menu for playlist", true);
                return User_Input_Handler_Registry::create_action(
                    $this,
                    GUI_EVENT_KEY_POPUP_MENU,
                    null,
                    array(ACTION_SORT_POPUP => true)
                );

            case ACTION_CHANGE_GROUP_ICON:
                $media_url = Starnet_Folder_Screen::make_media_url(static::ID,
                    array(
                        PARAM_EXTENSION => IMAGE_PREVIEW_PATTERN,
                        Starnet_Folder_Screen::PARAM_CHOOSE_FILE => ACTION_FILE_SELECTED,
                        Starnet_Folder_Screen::PARAM_RESET_ACTION => ACTION_RESET_DEFAULT,
                        Starnet_Folder_Screen::PARAM_ALLOW_NETWORK => !is_limited_apk(),
                        Starnet_Folder_Screen::PARAM_ALLOW_IMAGE_LIB => true,
                        Starnet_Folder_Screen::PARAM_READ_ONLY => true,
                    )
                );
                return Action_Factory::open_folder($media_url->get_media_url_str(), TR::t('select_file'));

            case ACTION_FILE_SELECTED:
                $data = MediaURL::decode($user_input->{Starnet_Folder_Screen::PARAM_SELECTED_DATA});
                $group = $this->plugin->get_group($sel_media_url->group_id, PARAM_ALL);
                if (is_null($group)) break;

                $cached_image_name = $this->plugin->get_active_playlist_id() . '_' . $data->{Starnet_Folder_Screen::PARAM_CAPTION};
                $cached_image_path = get_cached_image_path($cached_image_name);
                hd_print("copy from: " . $data->{PARAM_FILEPATH} . " to: $cached_image_path");
                if (!copy($data->{PARAM_FILEPATH}, $cached_image_path)) {
                    return Action_Factory::show_title_dialog(TR::t('err_copy'));
                }

                hd_debug_print("Assign icon: $cached_image_name to group: $sel_media_url->group_id");
                $this->plugin->set_group_icon($sel_media_url->group_id, $cached_image_name);
                return Action_Factory::refresh_entry_points($this->invalidate_current_folder($parent_media_url, $plugin_cookies, $sel_ndx));

            case ACTION_ITEMS_CLEAR:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_clear_all_msg'),
                    $this, ACTION_CONFIRM_CLEAR_DLG_APPLY);

            case ACTION_CONFIRM_CLEAR_DLG_APPLY:
                $group_id = safe_get_member($sel_media_url, COLUMN_GROUP_ID);
                if ($group_id === TV_HISTORY_GROUP_ID) {
                    $this->plugin->clear_tv_history();
                    return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);
                }

                if ($group_id === TV_FAV_GROUP_ID) {
                    $this->plugin->change_tv_favorites(ACTION_ITEMS_CLEAR, null, $plugin_cookies);
                    return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);
                }

                if ($group_id === TV_CHANGED_CHANNELS_GROUP_ID) {
                    $this->plugin->clear_changed_channels();
                    return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);
                }
                break;

            case ACTION_RESET_DEFAULT:
                hd_debug_print("Reset icon for group: $sel_media_url->group_id to default");
                switch ($sel_media_url->group_id) {
                    case TV_ALL_CHANNELS_GROUP_ID:
                        $icon = TV_ALL_CHANNELS_GROUP_ICON;
                        break;

                    case TV_FAV_GROUP_ID:
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

                $this->plugin->set_group_icon($sel_media_url->group_id, $icon);
                break;

            case ACTION_INFO_DLG:
                $provider = $this->plugin->get_active_provider();
                if (is_null($provider) || !$provider->hasApiCommand(API_COMMAND_ACCOUNT_INFO)) {
                    return null;
                }

                return $this->plugin->do_show_subscription($this);

            case ACTION_ADD_MONEY_DLG:
                return $this->plugin->do_show_add_money();

            case CONTROL_PLAYLIST:
                if ($user_input->action_type !== 'confirm' || $user_input->{CONTROL_PLAYLIST} !== CUSTOM_PLAYLIST_ID) {
                    return null;
                }

                $provider = $this->plugin->get_active_provider();
                if (is_null($provider)) {
                    return null;
                }

                $url = $provider->GetParameter(MACRO_CUSTOM_PLAYLIST);

                Control_Factory::add_vgap($defs, 20);
                Control_Factory::add_text_field($defs, $this, null, CONTROL_URL_PATH, TR::t('url'),
                    $url, false, false, false, true, self::DLG_CONTROLS_WIDTH);
                Control_Factory::add_vgap($defs, 50);
                Control_Factory::add_close_dialog_and_apply_button($defs, $this, ACTION_URL_DLG_APPLY, TR::t('ok'), 300);
                Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
                Control_Factory::add_vgap($defs, 10);

                return Action_Factory::show_dialog(TR::t('edit_list_add_url'), $defs, true);

            case ACTION_URL_DLG_APPLY:
                $provider = $this->plugin->get_active_provider();
                if (!is_null($provider)) {
                    hd_debug_print("set custom playlist $user_input->url_path");
                    $provider->SetParameter(MACRO_CUSTOM_PLAYLIST, $user_input->url_path);
                }
                return null;

            case ACTION_SHORTCUT:
                if (!isset($user_input->{COLUMN_PLAYLIST_ID}) || $this->plugin->get_active_playlist_id() === $user_input->{COLUMN_PLAYLIST_ID}) {
                    return null;
                }

                $this->plugin->set_active_playlist_id($user_input->{COLUMN_PLAYLIST_ID});
                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case ACTION_REFRESH_SCREEN:
                return Action_Factory::close_and_run(
                    Action_Factory::refresh_entry_points(
                        Action_Factory::open_folder(
                            self::ID,
                            $this->plugin->get_plugin_title(),
                            null,
                            null,
                            Action_Factory::change_behaviour(
                                $this->get_action_map($parent_media_url, $plugin_cookies)
                            )
                        )
                    )
                );

            case ACTION_RELOAD:
                hd_debug_print("Action reload", true);

                $this->plugin->reload_channels($plugin_cookies);

                $post_action = User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);
                return Action_Factory::invalidate_all_folders($plugin_cookies,null, $post_action);

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

        if (!$this->plugin->is_channels_loaded() && !$this->plugin->load_channels($plugin_cookies)) {
            hd_debug_print("Channels not loaded!");
            return array();
        }

        $ordinary_items = array();
        if (!$this->plugin->is_vod_playlist()) {
            $all_groups = $this->plugin->get_groups_by_order();
            $show_adult = $this->plugin->get_bool_setting(PARAM_SHOW_ADULT);
            foreach ($all_groups as $group_row) {
                if (!$show_adult && $group_row[COLUMN_ADULT] !== 0) continue;
                if ($this->plugin->get_channels_by_order_cnt($group_row[COLUMN_GROUP_ID]) === 0) continue;

                $caption = str_replace('|', '¦', $group_row[COLUMN_TITLE]);
                $detailed_info = TR::t('tv_screen_group_info__3',
                    $caption,
                    $this->plugin->get_order_count($group_row[COLUMN_GROUP_ID]),
                    $this->plugin->get_channels_count($group_row[COLUMN_GROUP_ID], PARAM_DISABLED)
                );

                $ordinary_items[] = $this->add_item($group_row, $caption, DEF_LABEL_TEXT_COLOR_WHITE, $detailed_info);
            }
        }

        $no_channels = empty($ordinary_items);

        $special_items = array();
        foreach ($this->plugin->get_groups(PARAM_GROUP_SPECIAL, PARAM_ALL) as $group_row) {
            $group_id = $group_row[COLUMN_GROUP_ID];
            if (($this->plugin->is_vod_playlist() && $group_id !== VOD_GROUP_ID) || ($group_id !== VOD_GROUP_ID && $no_channels)) continue;

            switch ($group_id) {
                case TV_ALL_CHANNELS_GROUP_ID:
                    if (!$this->plugin->get_bool_setting(PARAM_SHOW_ALL)) break;

                    $enabled = $this->plugin->get_channels_count($group_id, PARAM_ENABLED);
                    $disabled = $this->plugin->get_channels_count($group_id, PARAM_DISABLED);
                    $caption = TR::t('plugin_all_channels');
                    $detailed_info = TR::t('tv_screen_group_info__3', $caption, $enabled, $disabled);
                    $special_items[] = $this->add_item($group_row, $caption, DEF_LABEL_TEXT_COLOR_SKYBLUE, $detailed_info);
                    break;

                case TV_FAV_GROUP_ID:
                    if (!$this->plugin->get_bool_setting(PARAM_SHOW_FAVORITES)) break;

                    $channels_cnt = $this->plugin->get_order_count($group_id);
                    if (!$channels_cnt) break;

                    $caption = TR::t('plugin_favorites');
                    $detailed_info = TR::t('tv_screen_group_info__2', $caption, $channels_cnt);
                    $special_items[] = $this->add_item($group_row, $caption, DEF_LABEL_TEXT_COLOR_GOLD, $detailed_info);
                    break;

                case TV_HISTORY_GROUP_ID:
                    if (!$this->plugin->get_bool_setting(PARAM_SHOW_HISTORY)) break;

                    $channels_cnt = $this->plugin->get_tv_history_count();
                    if (!$channels_cnt) break;

                    $caption = TR::t('plugin_history');
                    $detailed_info = TR::t('tv_screen_group_info__2', $caption, $channels_cnt);
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

        if ($this->plugin->is_vod_playlist()) {
            return $special_items;
        }

        return array_merge($special_items, $ordinary_items);
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
            PluginRegularFolderItem::media_url => Default_Dune_Plugin::get_group_mediaurl_str($group_row[COLUMN_GROUP_ID]),
            PluginRegularFolderItem::caption => $caption,
            PluginRegularFolderItem::view_item_params => array(
                ViewItemParams::item_caption_color => $color,
                ViewItemParams::icon_path => $icon,
                ViewItemParams::item_detailed_icon_path => $icon,
                ViewItemParams::item_detailed_info => $item_detailed_info,
            )
        );
    }

    private function get_visible_groups_count($include_all = false)
    {
        $visible = 0;
        foreach ($this->plugin->get_groups(PARAM_GROUP_SPECIAL, PARAM_ENABLED) as $group_row) {
            $group_id = $group_row[COLUMN_GROUP_ID];
            if ($this->plugin->is_vod_playlist() && $group_id !== VOD_GROUP_ID) continue;

            switch ($group_id) {
                case TV_ALL_CHANNELS_GROUP_ID:
                    if ($this->plugin->get_bool_setting(PARAM_SHOW_ALL)) {
                        $visible++;
                    }
                    break;

                case TV_FAV_GROUP_ID:
                    if ($this->plugin->get_bool_setting(PARAM_SHOW_FAVORITES)) {
                        $channels_cnt = $this->plugin->get_order_count($group_id);
                        if ($channels_cnt) {
                            $visible++;
                        }
                    }
                    break;

                case TV_HISTORY_GROUP_ID:
                    if ($this->plugin->get_bool_setting(PARAM_SHOW_HISTORY)) {
                        $channels_cnt = $this->plugin->get_tv_history_count();
                        if ($channels_cnt) {
                            $visible++;
                        }
                    }
                    break;

                case TV_CHANGED_CHANNELS_GROUP_ID:
                    if ($this->plugin->get_bool_setting(PARAM_SHOW_CHANGED_CHANNELS)) {
                        $has_changes = $this->plugin->get_changed_channels_count(PARAM_CHANGED);
                        if ($has_changes) {
                            $visible++;
                        }
                    }
                    break;

                case VOD_GROUP_ID:
                    if ($this->plugin->is_vod_enabled() && $this->plugin->get_bool_setting(PARAM_SHOW_VOD)) {
                        $visible++;
                    }
                    break;
            }
        }

        return $visible + ($include_all ? $this->plugin->get_groups_count(PARAM_GROUP_ORDINARY, PARAM_ENABLED) : 0);
    }
}
