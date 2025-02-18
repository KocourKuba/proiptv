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
require_once 'starnet_setup_screen.php';

class Starnet_Tv_Groups_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'tv_groups';

    const ACTION_CONFIRM_DLG_APPLY = 'apply_dlg';
    const ACTION_DO_EPG_SETTINGS = 'do_epg_settings';

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

        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        if (!isset($user_input->parent_media_url)) {
            return null;
        }

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);

        $sel_ndx = safe_get_member($user_input, 'sel_ndx', 0);
        if (isset($user_input->selected_media_url)) {
            $sel_media_url = MediaURL::decode($user_input->selected_media_url);
        } else {
            $sel_media_url = MediaURL::make(array());
        }

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                if ($this->plugin->get_bool_parameter(PARAM_ASK_EXIT)) {
                    return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::ACTION_CONFIRM_DLG_APPLY);
                }

            $this->force_parent_reload = false;
            return Action_Factory::invalidate_all_folders($plugin_cookies, null, Action_Factory::close_and_run());

            case GUI_EVENT_TIMER:
                $epg_manager = $this->plugin->get_epg_manager();
                if ($epg_manager === null) {
                    return null;
                }

                clearstatcache();

                $actions = $this->get_action_map($parent_media_url, $plugin_cookies);
                $res = $epg_manager->import_indexing_log();
                if ($res !== false) {
                    foreach (array($this->plugin->get_pl_error_name(), "xmltv_last_error") as $last_error) {
                        $error_msg = HD::get_last_error($last_error);
                        if (!empty($error_msg)) {
                            return Action_Factory::show_title_dialog(TR::t('err_load_playlist'), null, $error_msg);
                        }
                    }
                    return null;
                }

                return Action_Factory::change_behaviour($actions, 1000);

            case ACTION_OPEN_FOLDER:
            case ACTION_PLAY_FOLDER:
                Starnet_Epfs_Handler::update_epfs_file($plugin_cookies);
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
                if (!$this->plugin->arrange_groups_order_rows($sel_media_url->group_id, Ordered_Array::UP)) {
                    return null;
                }

                $this->force_parent_reload = true;
                $min_sel = $this->plugin->get_groups_count(1, 0) - 1;
                $sel_ndx--;
                if ($sel_ndx < $min_sel) {
                    $sel_ndx = $min_sel;
                }
                break;

            case ACTION_ITEM_DOWN:
                if (!$this->plugin->arrange_groups_order_rows($sel_media_url->group_id, Ordered_Array::DOWN)) {
                    return null;
                }

                $this->force_parent_reload = true;
                $max_sel = $this->plugin->get_groups_count(-1, 0);
                $sel_ndx++;
                if ($sel_ndx >= $max_sel) {
                    $sel_ndx = $max_sel - 1;
                }
                break;

            case ACTION_ITEM_TOP:
                if (!$this->plugin->arrange_groups_order_rows($sel_media_url->group_id, Ordered_Array::TOP)) {
                    return null;
                }

                $this->force_parent_reload = true;
                $sel_ndx = $this->plugin->get_groups_count(1, 0);
                break;

            case ACTION_ITEM_BOTTOM:
                if (!$this->plugin->arrange_groups_order_rows($sel_media_url->group_id, Ordered_Array::BOTTOM)) {
                    return null;
                }

                $this->force_parent_reload = true;
                $sel_ndx = $this->plugin->get_groups_count(0, 0) - 1;
                break;

            case ACTION_ITEM_DELETE:
                // hide group
                $this->force_parent_reload = true;
                $this->plugin->set_groups_visible($sel_media_url->group_id, true);
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
                        $this->plugin->sort_channels_order($parent_media_url->group_id, true);
                        break;

                    case ACTION_SORT_GROUPS:
                        $this->plugin->sort_groups_order(true);
                        break;

                    case ACTION_SORT_ALL:
                        $this->plugin->sort_groups_order(true);
                        foreach ($this->plugin->get_groups_by_order() as $row) {
                            $this->plugin->sort_channels_order($row['group_id'],true);
                        }
                        break;

                    default:
                        return null;
                }

                $this->force_parent_reload = true;
                break;

            case ACTION_ITEMS_EDIT:
                return $this->plugin->do_edit_list_screen(self::ID, $user_input->action_edit, $sel_media_url);

            case ACTION_SETTINGS:
                return $this->plugin->show_protect_settings_dialog($this, ACTION_DO_SETTINGS);

            case ACTION_DO_SETTINGS:
                return Action_Factory::open_folder(Starnet_Setup_Screen::get_media_url_str(), TR::t('entry_setup'));

            case ACTION_PASSWORD_APPLY:
                if ($this->plugin->get_parameter(PARAM_SETTINGS_PASSWORD) !== $user_input->pass) {
                    return null;
                }
                return User_Input_Handler_Registry::create_action($this, $user_input->param_action);

            case self::ACTION_CONFIRM_DLG_APPLY:
                $this->force_parent_reload = false;
                return Action_Factory::invalidate_all_folders($plugin_cookies,null, Action_Factory::close_and_run());

            case ACTION_PLUGIN_INFO:
                return $this->plugin->get_plugin_info_dlg($this);

            case ACTION_DONATE_DLG: // show donate QR codes
                return $this->plugin->do_donate_dialog();

            case GUI_EVENT_KEY_POPUP_MENU:
                if (isset($user_input->{ACTION_CHANGE_EPG_SOURCE})) {
                    $menu_items = $this->plugin->epg_source_menu($this);
                } else if (isset($user_input->{ACTION_EPG_CACHE_ENGINE})) {
                    $menu_items = $this->plugin->epg_engine_menu($this);
                } else if (isset($user_input->{ACTION_CHANGE_PICONS_SOURCE})) {
                    $menu_items = $this->plugin->picons_source_menu($this);
                } else if (isset($user_input->{ACTION_SORT_POPUP})) {
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_SORT, TR::t('sort_groups'));
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_RESET_ITEMS_SORT, TR::t('reset_groups_sort'),
                        null, array(ACTION_RESET_TYPE => ACTION_SORT_GROUPS));
                    $menu_items[] = $this->plugin->create_menu_item($this, ACTION_RESET_ITEMS_SORT, TR::t('reset_all_sort'),
                        null, array(ACTION_RESET_TYPE => ACTION_SORT_ALL));
                    $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
                } else {
                    $group_id = safe_get_member($sel_media_url, 'group_id');
                    $menu_items = $this->plugin->common_categories_menu($this, $group_id);
                }

                return empty($menu_items) ? null : Action_Factory::show_popup_menu($menu_items);

            case ACTION_CHANGE_EPG_SOURCE:
                hd_debug_print("Start event popup menu for epg source", true);
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU, null, array(ACTION_CHANGE_EPG_SOURCE => true));

            case ACTION_EPG_SOURCE_SELECTED:
                if (!isset($user_input->{LIST_IDX}) || $this->plugin->get_setting(PARAM_EPG_CACHE_ENGINE, ENGINE_XMLTV) !== ENGINE_JSON) break;

                $epg_manager = $this->plugin->get_epg_manager();

                if ($epg_manager === null) {
                    return Action_Factory::show_title_dialog(TR::t('err_epg_manager'));
                }
                $epg_manager->clear_current_epg_cache();
                $this->plugin->set_setting(PARAM_EPG_JSON_PRESET, $user_input->{LIST_IDX});
                return User_Input_Handler_Registry::create_action(
                    $this,
                    ACTION_RELOAD,
                    null,
                    array('reload_action' => Starnet_Edit_List_Screen::SCREEN_EDIT_PLAYLIST));

            case ACTION_EPG_CACHE_ENGINE:
                hd_debug_print("Start event popup menu for epg source", true);
                return User_Input_Handler_Registry::create_action(
                    $this,
                    GUI_EVENT_KEY_POPUP_MENU,
                    null,
                    array(ACTION_EPG_CACHE_ENGINE => true)
                );

            case ACTION_CHANGE_PICONS_SOURCE:
                hd_debug_print("Start event popup menu for picons source", true);
                return User_Input_Handler_Registry::create_action(
                    $this,
                    GUI_EVENT_KEY_POPUP_MENU,
                    null,
                    array(ACTION_CHANGE_PICONS_SOURCE => true)
                );

            case ENGINE_XMLTV:
            case ENGINE_JSON:
                if ($this->plugin->get_setting(PARAM_EPG_CACHE_ENGINE, ENGINE_XMLTV) !== $user_input->control_id) {
                    hd_debug_print("Selected engine: $user_input->control_id", true);
                    $this->plugin->reset_playlist_db();
                    $this->plugin->set_setting(PARAM_EPG_CACHE_ENGINE, $user_input->control_id);
                    $this->plugin->init_epg_manager();
                    return User_Input_Handler_Registry::create_action(
                        $this,
                        ACTION_RELOAD,
                        null,
                        array('reload_action' => Starnet_Edit_List_Screen::SCREEN_EDIT_PLAYLIST)
                    );
                }
                break;

            case PLAYLIST_PICONS:
            case XMLTV_PICONS:
            case COMBINED_PICONS:
                if ($this->plugin->get_setting(PARAM_USE_PICONS, PLAYLIST_PICONS) !== $user_input->control_id) {
                    hd_debug_print("Selected icons source: $user_input->control_id", true);
                    $this->plugin->reset_playlist_db();
                    $this->plugin->set_setting(PARAM_USE_PICONS, $user_input->control_id);
                    $this->plugin->init_epg_manager();
                    $this->force_parent_reload = true;
                    return User_Input_Handler_Registry::create_action(
                        $this,
                        ACTION_RELOAD,
                        null,
                        array('reload_action' => Starnet_Edit_List_Screen::SCREEN_EDIT_PLAYLIST)
                    );
                }
                break;

            case ACTION_EDIT_PROVIDER_DLG:
            case ACTION_EDIT_PROVIDER_EXT_DLG:
                return $this->plugin->show_protect_settings_dialog($this,
                    ($user_input->control_id === ACTION_EDIT_PROVIDER_DLG)
                        ? ACTION_DO_EDIT_PROVIDER
                        : ACTION_DO_EDIT_PROVIDER_EXT);

            case ACTION_DO_EDIT_PROVIDER:
            case ACTION_DO_EDIT_PROVIDER_EXT:
                $provider = $this->plugin->get_active_provider();
                if (is_null($provider)) {
                    return null;
                }

                if ($user_input->control_id === ACTION_DO_EDIT_PROVIDER) {
                    hd_debug_print(pretty_json_format($provider));
                    return $this->plugin->do_edit_provider_dlg($this, $provider->getId(), $provider->get_provider_playlist_id());
                }

                if ($provider->request_provider_token()) {
                    return $this->plugin->do_edit_provider_ext_dlg($this);
                }

                hd_debug_print("Can't get provider token");
                return Action_Factory::show_error(false, TR::t('err_incorrect_access_data'), array(TR::t('err_cant_get_token')));

            case ACTION_EDIT_PROVIDER_DLG_APPLY:
            case ACTION_EDIT_PROVIDER_EXT_DLG_APPLY:
                if ($user_input->control_id === ACTION_EDIT_PROVIDER_DLG_APPLY) {
                    $res = $this->plugin->apply_edit_provider_dlg($user_input);
                } else {
                    $res = $this->plugin->apply_edit_provider_ext_dlg($user_input);
                }

                if ($res === false || $res === null) {
                    return null;
                }

                if (is_array($res)) {
                    return $res;
                }

                return User_Input_Handler_Registry::create_action(
                    $this,
                    ACTION_RELOAD,
                    null,
                    array('reload_action' => Starnet_Edit_List_Screen::SCREEN_EDIT_PLAYLIST));

            case ACTION_SORT_POPUP:
                hd_debug_print("Start event popup menu for playlist", true);
                return User_Input_Handler_Registry::create_action(
                    $this,
                    GUI_EVENT_KEY_POPUP_MENU,
                    null,
                    array(ACTION_SORT_POPUP => true)
                );

            case ACTION_CHANGE_GROUP_ICON:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'source_window_id' => static::ID,
                        'choose_file' => $user_input->control_id,
                        'extension' => IMAGE_PREVIEW_PATTERN,
                        'allow_network' => !is_limited_apk(),
                        'allow_image_lib' => true,
                        'allow_reset' => true,
                        'read_only' => true,
                        'windowCounter' => 1,
                    )
                );
                return Action_Factory::open_folder($media_url_str, TR::t('select_file'));

            case ACTION_FILE_SELECTED:
                $data = MediaURL::decode($user_input->selected_data);
                if ($data->choose_file === ACTION_CHANGE_GROUP_ICON) {
                    $group = $this->plugin->get_group($sel_media_url->group_id, -1);
                    if (is_null($group)) break;

                    $cached_image_name = "{$this->plugin->get_active_playlist_key()}_$data->caption";
                    $cached_image_path = get_cached_image_path($cached_image_name);
                    hd_print("copy from: $data->filepath to: $cached_image_path");
                    if (!copy($data->filepath, $cached_image_path)) {
                        return Action_Factory::show_title_dialog(TR::t('err_copy'));
                    }

                    hd_debug_print("Assign icon: $cached_image_name to group: $sel_media_url->group_id");
                    $this->plugin->set_group_icon($sel_media_url->group_id, $cached_image_name);
                }
                break;

            case ACTION_ITEMS_CLEAR:
                $group_id = safe_get_member($sel_media_url, 'group_id');
                if ($group_id === HISTORY_GROUP_ID) {
                    $this->plugin->clear_tv_history();
                    return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);
                }

                if ($group_id === FAV_CHANNELS_GROUP_ID) {
                    $this->plugin->change_tv_favorites(ACTION_ITEMS_CLEAR, null, $plugin_cookies);
                    return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);
                }

                if ($group_id === CHANGED_CHANNELS_GROUP_ID) {
                    $this->plugin->clear_changed_channels();
                    return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);
                }
                break;

            case ACTION_RESET_DEFAULT:
                $data = MediaURL::decode($user_input->selected_data);
                if ($data->choose_file === ACTION_CHANGE_GROUP_ICON) {
                    hd_debug_print("Reset icon for group: $sel_media_url->group_id to default");
                    switch ($sel_media_url->group_id) {
                        case ALL_CHANNELS_GROUP_ID:
                            $icon = ALL_CHANNELS_GROUP_ICON;
                            break;

                        case FAV_CHANNELS_GROUP_ID:
                            $icon = FAV_CHANNELS_GROUP_ICON;
                            break;

                        case HISTORY_GROUP_ID:
                            $icon = HISTORY_GROUP_ICON;
                            break;

                        case CHANGED_CHANNELS_GROUP_ID:
                            $icon = CHANGED_CHANNELS_GROUP_ICON;
                            break;

                        case VOD_GROUP_ID:
                            $icon = VOD_GROUP_ICON;
                            break;

                        default:
                            $icon = DEFAULT_GROUP_ICON;
                    }

                    $this->plugin->set_group_icon($sel_media_url->group_id, $icon);
                }
                break;

            case ACTION_RELOAD:
                hd_debug_print("Action reload", true);
                if (!$this->plugin->reload_channels($plugin_cookies)) {
                    $post_action = Action_Factory::show_title_dialog(TR::t('err_load_playlist'),
                        null,
                        HD::get_last_error($this->plugin->get_pl_error_name()));
                    $post_action = Action_Factory::close_and_run(
                        Action_Factory::open_folder(self::ID, $this->plugin->create_plugin_title(), null, null, $post_action));

                    return Action_Factory::invalidate_all_folders($plugin_cookies,null, $post_action);
                }

                return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);

            case ACTION_INFO_DLG:
                $provider = $this->plugin->get_active_provider();
                if (is_null($provider) || !$provider->hasApiCommand(API_COMMAND_ACCOUNT_INFO)) {
                    return null;
                }

                return $this->plugin->do_show_subscription($this);

            case ACTION_ADD_MONEY_DLG:
                return $this->plugin->do_show_add_money();

            case ACTION_REFRESH_SCREEN:
                return Action_Factory::close_and_run(
                    Action_Factory::open_folder(
                        self::ID,
                        $this->plugin->create_plugin_title(),
                        null,
                        null,
                        Action_Factory::change_behaviour($this->get_action_map($parent_media_url, $plugin_cookies))
                    )
                );

            case CONTROL_PLAYLIST:
                if ($user_input->action_type !== 'confirm' || $user_input->{CONTROL_PLAYLIST} !== CUSTOM_PLAYLIST_ID) {
                    return null;
                }

                $provider = $this->plugin->get_active_provider();
                if (is_null($provider)) {
                    return null;
                }

                $url = $provider->getParameter(MACRO_CUSTOM_PLAYLIST);

                Control_Factory::add_vgap($defs, 20);
                Control_Factory::add_text_field($defs, $this, null, CONTROL_URL_PATH, TR::t('url'),
                    $url, false, false, false, true, self::DLG_CONTROLS_WIDTH);
                Control_Factory::add_vgap($defs, 50);
                Control_Factory::add_close_dialog_and_apply_button($defs, $this, null,
                    ACTION_URL_DLG_APPLY, TR::t('ok'), 300);
                Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
                Control_Factory::add_vgap($defs, 10);

                return Action_Factory::show_dialog(TR::t('edit_list_add_url'), $defs, true);

            case ACTION_URL_DLG_APPLY:
                $provider = $this->plugin->get_active_provider();
                if (!is_null($provider)) {
                    hd_debug_print("set custom playlist $user_input->url_path");
                    $provider->setParameter(MACRO_CUSTOM_PLAYLIST, $user_input->url_path);
                }
                return null;

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
        hd_debug_print("MediaUrl: " . $media_url, true);

        $items = array();
        if (!$this->plugin->load_channels($plugin_cookies)) {
            hd_debug_print("Channels not loaded!");
            return $items;
        }

        foreach ($this->plugin->get_groups(1, 0) as $group_row) {
            $show = false;
            switch ($group_row['group_id']) {
                case ALL_CHANNELS_GROUP_ID:
                    $show = $this->plugin->get_bool_setting(PARAM_SHOW_ALL);
                    if (!$show) break;

                    $item_detailed_info = TR::t('tv_screen_group_info__3',
                        TR::load_string(ALL_CHANNELS_GROUP_CAPTION),
                        $this->plugin->get_channels_count(ALL_CHANNELS_GROUP_ID, 0),
                        $this->plugin->get_channels_count(ALL_CHANNELS_GROUP_ID, 1)
                    );
                    $color = DEF_LABEL_TEXT_COLOR_SKYBLUE;
                    break;

                case FAV_CHANNELS_GROUP_ID:
                    $show = $this->plugin->get_bool_setting(PARAM_SHOW_FAVORITES);
                    if (!$show) break;

                    $item_detailed_info = TR::t('tv_screen_group_info__2',
                        TR::load_string(FAV_CHANNELS_GROUP_CAPTION),
                        $this->plugin->get_channels_order_count(FAV_CHANNELS_GROUP_ID)
                    );
                    $color = DEF_LABEL_TEXT_COLOR_GOLD;
                    break;

                case HISTORY_GROUP_ID:
                    $show = $this->plugin->get_bool_setting(PARAM_SHOW_HISTORY);
                    if (!$show) break;

                    $visible = 0;
                    foreach ($this->plugin->get_tv_history() as $channel_id => $channel_ts) {
                        $channel = $this->plugin->get_channel_info($channel_id);
                        if (!empty($channel)) {
                            $visible++;
                        }
                    }
                    $item_detailed_info = TR::t('tv_screen_group_info__2',
                        TR::load_string(HISTORY_GROUP_CAPTION),
                        $visible);
                    $color = DEF_LABEL_TEXT_COLOR_TURQUOISE;
                    break;

                case CHANGED_CHANNELS_GROUP_ID:
                    $new = $this->plugin->get_changed_channels_count('new');
                    $removed = $this->plugin->get_changed_channels_count('removed');
                    $show = $this->plugin->get_bool_setting(PARAM_SHOW_CHANGED_CHANNELS) && ($new + $removed !== 0);
                    if (!$show) break;

                    $item_detailed_info = TR::t('tv_screen_group_changed_info__3',
                        TR::load_string(CHANGED_CHANNELS_GROUP_CAPTION),
                        $new,
                        $removed);
                    $color = DEF_LABEL_TEXT_COLOR_RED;
                    break;

                case VOD_GROUP_ID:
                    $show = $this->plugin->is_vod_enabled() && $this->plugin->get_bool_setting(PARAM_SHOW_VOD);
                    if (!$show) break;

                    $item_detailed_info = TR::load_string(VOD_GROUP_CAPTION);
                    $color = DEF_LABEL_TEXT_COLOR_LIGHTGREEN;
                    break;

                default:
                    $item_detailed_info = '';
                    $color = DEF_LABEL_TEXT_COLOR_WHITE;
                    break;
            }

            if (!$show) continue;

            $items[] = array(
                PluginRegularFolderItem::media_url => Default_Dune_Plugin::get_group_media_url_str($group_row['group_id']),
                PluginRegularFolderItem::caption => TR::t($group_row['title']),
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::item_caption_color => $color,
                    ViewItemParams::icon_path => $group_row['icon'],
                    ViewItemParams::item_detailed_icon_path => $group_row['icon'],
                    ViewItemParams::item_detailed_info => $item_detailed_info,
                )
            );
        }

        foreach ($this->plugin->get_groups_by_order() as $group_row) {
            $channels_cnt = $this->plugin->get_channels_order_count($group_row['group_id']);
            $disabled_channels_cnt = $this->plugin->get_channels_count($group_row['group_id'], 1);
            if (strpos($group_row['icon'], "plugin_file://") === false && file_exists(get_cached_image_path($group_row['icon']))) {
                $icon = get_cached_image_path($group_row['icon']);
            } else {
                $icon = $group_row['icon'];
            }

            $items[] = array(
                PluginRegularFolderItem::media_url => Default_Dune_Plugin::get_group_media_url_str($group_row['group_id']),
                PluginRegularFolderItem::caption => $group_row['title'],
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::icon_path => $icon,
                    ViewItemParams::item_detailed_icon_path => $icon,
                    ViewItemParams::item_detailed_info => TR::t('tv_screen_group_info__3',
                        str_replace('|', '¦', $group_row['title']),
                        $channels_cnt,
                        $disabled_channels_cnt
                    ),
                ),
            );
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
}
