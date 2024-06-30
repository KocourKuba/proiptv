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

        $actions[GUI_EVENT_KEY_ENTER]      = User_Input_Handler_Registry::create_action($this, ACTION_OPEN_FOLDER);
        $actions[GUI_EVENT_KEY_PLAY]       = User_Input_Handler_Registry::create_action($this, ACTION_PLAY_FOLDER);

        $actions[GUI_EVENT_KEY_RETURN]     = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_TOP_MENU]   = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_TOP_MENU);
        $actions[GUI_EVENT_KEY_STOP]       = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_STOP);
        $actions[GUI_EVENT_TIMER]          = User_Input_Handler_Registry::create_action($this, GUI_EVENT_TIMER);

        $order = $this->plugin->tv->get_groups_order();
        if (!is_null($order) && $order->size() !== 0) {
            $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('up'));
            $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('down'));
        }

        $actions[GUI_EVENT_KEY_D_BLUE]     = User_Input_Handler_Registry::create_action($this, ACTION_PLUGIN_INFO, TR::t('plugin_info'));
        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);

        $provider = $this->plugin->get_current_provider();
        if (!is_null($provider) && $provider->hasApiCommand(API_COMMAND_ACCOUNT_INFO)) {
            $actions[GUI_EVENT_KEY_INFO] = User_Input_Handler_Registry::create_action($this, ACTION_INFO_DLG);
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

        if (!isset($user_input->parent_media_url)) {
            return null;
        }

        $sel_ndx = isset($user_input->sel_ndx) ? $user_input->sel_ndx : 0;
        if (isset($user_input->selected_media_url)) {
            $sel_media_url = MediaURL::decode($user_input->selected_media_url);
        } else {
            $sel_media_url = MediaURL::make(array());
        }

        switch ($user_input->control_id)
        {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                $this->save_if_changed();
                if ($this->plugin->get_bool_parameter(PARAM_ASK_EXIT)) {
                    return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::ACTION_CONFIRM_DLG_APPLY);
                }

            return User_Input_Handler_Registry::create_action($this, self::ACTION_CONFIRM_DLG_APPLY);

            case GUI_EVENT_TIMER:
                clearstatcache();
                $epg_manager = $this->plugin->get_epg_manager();
                if (!is_null($epg_manager)) {
                    if ($epg_manager->is_index_locked()) {
                        $actions = $this->get_action_map(MediaURL::decode($user_input->parent_media_url), $plugin_cookies);
                        return Action_Factory::change_behaviour($actions, 2000);
                    }

                    $epg_manager->import_indexing_log();
                }
                return null;

            case GUI_EVENT_KEY_STOP:
                $this->plugin->save_orders(true);
                $this->set_no_changes();
                return Action_Factory::invalidate_all_folders($plugin_cookies);

            case ACTION_OPEN_FOLDER:
            case ACTION_PLAY_FOLDER:
                if ($this->save_if_changed()) {
                    Starnet_Epfs_Handler::update_all_epfs($plugin_cookies);
                }

                $has_error = HD::get_last_error();
                if (empty($has_error)) {
                    if ($sel_media_url->group_id !== VOD_GROUP_ID) {
                        return Action_Factory::open_folder();
                    }

                    $category_list = array();
                    $category_index = array();
                    if ($this->plugin->vod->fetchVodCategories($category_list, $category_index)) {
                        return Action_Factory::open_folder();
                    }
                    $has_error = HD::get_last_error('vod_last_error');
                }

                return Action_Factory::show_title_dialog(TR::t('err_load_any'),null, $has_error);

            case ACTION_ITEM_UP:
                if (!$this->plugin->tv->get_groups_order()->arrange_item($sel_media_url->group_id, Ordered_Array::UP))
                    return null;

                $min_sel = $this->plugin->tv->get_special_groups_count();
                $sel_ndx--;
                if ($sel_ndx < $min_sel) {
                    $sel_ndx = $min_sel;
                }

                $this->set_changes();
                break;

            case ACTION_ITEM_DOWN:
                if (!$this->plugin->tv->get_groups_order()->arrange_item($sel_media_url->group_id, Ordered_Array::DOWN))
                    return null;

                $special_group_cnt = $this->plugin->tv->get_special_groups_count();
                $groups_cnt = $this->plugin->tv->get_groups_order()->size();
                hd_debug_print("special groups: $special_group_cnt");
                hd_debug_print("groups: $groups_cnt");
                hd_debug_print("sel_ndx: $sel_ndx");
                $groups_cnt += $special_group_cnt;
                $sel_ndx++;
                if ($sel_ndx >= $groups_cnt) {
                    $sel_ndx = $groups_cnt - 1;
                }

                $this->set_changes();
                break;

            case ACTION_ITEM_DELETE:
                // hide group
                $this->plugin->tv->disable_group($sel_media_url->group_id);
                $this->set_changes();
                break;

            case ACTION_ITEMS_SORT:
                $group = $this->plugin->tv->get_group($sel_media_url->group_id);
                if (is_null($group)) {
                    return null;
                }

                $this->plugin->tv->get_groups_order()->sort_order();
                $this->set_changes();
                break;

            case ACTION_RESET_ITEMS_SORT:
                if (!isset($user_input->{ACTION_RESET_TYPE})) {
                    return null;
                }

                /** @var Default_Channel $channel */
                switch ($user_input->{ACTION_RESET_TYPE}) {
                    case ACTION_SORT_CHANNELS:
                        if (!is_null($sel_group = $this->plugin->tv->get_group($sel_media_url->group_id))) {
                            $sel_group->sort_group_items(true);
                            $this->set_changes();
                        }
                        break;

                    case ACTION_SORT_GROUPS:
                        $order = &$this->plugin->tv->get_groups_order();
                        $order->clear();
                        foreach ($this->plugin->tv->get_enabled_groups() as $group) {
                            $order->add_item($group->get_id());
                        }
                        $this->set_changes();
                        break;

                    case ACTION_SORT_ALL:
                        $order = &$this->plugin->tv->get_groups_order();
                        $order->clear();
                        foreach ($this->plugin->tv->get_enabled_groups() as $group) {
                            $order->add_item($group->get_id());
                            $group->sort_group_items(true);
                        }
                        $this->set_changes();
                        break;

                    default:
                        return null;
                }

                break;

            case ACTION_ITEMS_EDIT:
                $this->save_if_changed();
                return $this->plugin->do_edit_list_screen(self::ID, $user_input->action_edit, $sel_media_url);

            case ACTION_SETTINGS:
                $this->save_if_changed();
                return $this->plugin->show_protect_settings_dialog($this, ACTION_DO_SETTINGS);

            case ACTION_DO_SETTINGS:
                return Action_Factory::open_folder(Starnet_Setup_Screen::get_media_url_str(), TR::t('entry_setup'));

            case ACTION_PASSWORD_APPLY:
                if ($this->plugin->get_parameter(PARAM_SETTINGS_PASSWORD) !== $user_input->pass) {
                    return null;
                }
                return User_Input_Handler_Registry::create_action($this, $user_input->param_action);

            case self::ACTION_CONFIRM_DLG_APPLY:
                return Action_Factory::invalidate_all_folders($plugin_cookies, Action_Factory::close_and_run());

            case ACTION_PLUGIN_INFO:
                return $this->plugin->get_plugin_info_dlg($this);

            case ACTION_DONATE_DLG: // show donate QR codes
                return $this->plugin->do_donate_dialog();

            case GUI_EVENT_KEY_POPUP_MENU:
                if (isset($user_input->{ACTION_CHANGE_PLAYLIST})) {
                    $menu_items = $this->plugin->playlist_menu($this);
                } else if (isset($user_input->{ACTION_CHANGE_EPG_SOURCE})) {
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
                    $group_id = isset($sel_media_url->group_id) ? $sel_media_url->group_id : null;
                    $menu_items = $this->plugin->common_categories_menu($this, $group_id);
                    $menu_items[] = $this->plugin->create_menu_item($this,
                        ACTION_RELOAD,
                        TR::t('refresh_playlist'),
                        "refresh.png",
                        array('reload_action' => 'playlist'));
                }

                return empty($menu_items) ? null : Action_Factory::show_popup_menu($menu_items);

            case ACTION_CHANGE_PLAYLIST:
                hd_debug_print("Start event popup menu for playlist", true);
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU, null, array(ACTION_CHANGE_PLAYLIST => true));

            case ACTION_PLAYLIST_SELECTED:
                if (!isset($user_input->{LIST_IDX}) || $user_input->{LIST_IDX} === $this->plugin->get_active_playlist_key()) break;

                hd_debug_print("Select playlist: " . $user_input->{LIST_IDX}, true);
                $this->save_if_changed();
                $this->plugin->set_active_playlist_key($user_input->{LIST_IDX});
                HD::set_last_error("pl_last_error", null);

                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD, null, array('reload_action' => 'playlist'));

            case ACTION_CHANGE_EPG_SOURCE:
                hd_debug_print("Start event popup menu for epg source", true);
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU, null, array(ACTION_CHANGE_EPG_SOURCE => true));

            case ACTION_EPG_SOURCE_SELECTED:
                if (!isset($user_input->{LIST_IDX})) break;

                $this->save_if_changed();
                $this->plugin->set_active_xmltv_source_key($user_input->{LIST_IDX});

                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD, null, array('reload_action' => 'epg_change'));

            case ACTION_EPG_CACHE_ENGINE:
                hd_debug_print("Start event popup menu for epg source", true);
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU, null, array(ACTION_EPG_CACHE_ENGINE => true));

            case ACTION_CHANGE_PICONS_SOURCE:
                hd_debug_print("Start event popup menu for picons source", true);
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU, null, array(ACTION_CHANGE_PICONS_SOURCE => true));

            case ENGINE_XMLTV:
            case ENGINE_JSON:
                if ($this->plugin->get_setting(PARAM_EPG_CACHE_ENGINE) !== $user_input->control_id) {
                    hd_debug_print("Selected engine: $user_input->control_id", true);
                    $this->plugin->tv->unload_channels();
                    $this->plugin->set_setting(PARAM_EPG_CACHE_ENGINE, $user_input->control_id);
                    $this->plugin->init_epg_manager();
                    return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);
                }
                break;

            case PLAYLIST_PICONS:
            case XMLTV_PICONS:
                if ($this->plugin->get_setting(PARAM_USE_PICONS) !== $user_input->control_id) {
                    hd_debug_print("Selected icons source: $user_input->control_id", true);
                    $this->plugin->tv->unload_channels();
                    $this->plugin->set_setting(PARAM_USE_PICONS, $user_input->control_id);
                    $this->plugin->init_epg_manager();
                    return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);
                }
                break;

            case ACTION_EDIT_PROVIDER_DLG:
            case ACTION_EDIT_PROVIDER_EXT_DLG:
                $this->save_if_changed();
                return $this->plugin->show_protect_settings_dialog($this,
                    ($user_input->control_id === ACTION_EDIT_PROVIDER_DLG)
                        ? ACTION_DO_EDIT_PROVIDER
                        : ACTION_DO_EDIT_PROVIDER_EXT);

            case ACTION_DO_EDIT_PROVIDER:
            case ACTION_DO_EDIT_PROVIDER_EXT:
                if ($user_input->control_id === ACTION_DO_EDIT_PROVIDER) {
                    return $this->plugin->do_edit_provider_dlg($this, 'current');
                }

                return $this->plugin->do_edit_provider_ext_dlg($this);

            case ACTION_EDIT_PROVIDER_DLG_APPLY:
            case ACTION_EDIT_PROVIDER_EXT_DLG_APPLY:
                $this->set_no_changes();
                if ($user_input->control_id === ACTION_EDIT_PROVIDER_DLG_APPLY) {
                    $res = $this->plugin->apply_edit_provider_dlg($user_input);
                } else {
                    $res = $this->plugin->apply_edit_provider_ext_dlg($user_input);
                }

                if ($res === false) {
                    return null;
                }

                if (is_array($res)) {
                    return $res;
                }

                return User_Input_Handler_Registry::create_action($this,ACTION_RELOAD);

            case ACTION_SORT_POPUP:
                hd_debug_print("Start event popup menu for playlist", true);
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU, null, array(ACTION_SORT_POPUP => true));

            case ACTION_CHANGE_GROUP_ICON:
                $this->save_if_changed();
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'source_window_id' => static::ID,
                        'choose_file' => $user_input->control_id,
                        'extension'	=> IMAGE_PREVIEW_PATTERN,
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

                    $group = $this->plugin->tv->get_any_group($sel_media_url->group_id);
                    if (is_null($group)) break;

                    $cached_image_name = "{$this->plugin->get_active_playlist_key()}_$data->caption";
                    $cached_image = get_cached_image_path($cached_image_name);
                    hd_print("copy from: $data->filepath to: $cached_image");
                    if (!copy($data->filepath, $cached_image)) {
                        return Action_Factory::show_title_dialog(TR::t('err_copy'));
                    }

                    $old_cached_image = $group->get_icon_url();
                    $group->set_icon_url($cached_image);
                    hd_debug_print("Assign icon: $cached_image to group: $sel_media_url->group_id");

                    /** @var Hashed_Array $group_icons */
                    $group_icons = $this->plugin->get_orders(PARAM_GROUPS_ICONS, new Hashed_Array());
                    $group_icons->set($sel_media_url->group_id, $cached_image_name);
                    $this->plugin->save_orders(true);
                    $this->set_no_changes(PLUGIN_SETTINGS);

                    /** @var Default_Group $known_group */
                    if (strpos($old_cached_image, 'plugin_file://') === false) break;

                    foreach ($this->plugin->tv->get_groups() as $known_group) {
                        $icon_path = $known_group->get_icon_url();
                        if (strpos($icon_path, 'plugin_file://') !== false) {
                            $icons[] = $icon_path;
                        }
                    }
                    if (isset($icons) && !in_array($old_cached_image, $icons) && file_exists($old_cached_image)) {
                        unlink($old_cached_image);
                    }
                }
                break;

            case ACTION_ITEMS_CLEAR:
                $group_id = isset($sel_media_url->group_id) ? $sel_media_url->group_id : null;
                if ($group_id === HISTORY_GROUP_ID) {
                    $this->plugin->get_playback_points()->clear_points();
                    return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);
                }

                if ($group_id === FAVORITES_GROUP_ID) {
                    $this->set_changes();
                    $this->plugin->tv->change_tv_favorites(ACTION_ITEMS_CLEAR, null);
                    return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);
                }

                if ($group_id === CHANGED_CHANNELS_GROUP_ID) {
                    $this->set_changes();
                    $all_channels = $this->plugin->tv->get_channels();
                    $order = &$this->plugin->tv->get_known_channels();
                    $this->plugin->tv->get_special_group(CHANGED_CHANNELS_GROUP_ID)->set_disabled(true);
                    $order->clear();
                    /** @var Default_Channel $channel */
                    foreach ($all_channels as $channel) {
                        $order->set($channel->get_id(), $channel->get_title());
                    }
                    return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);
                }
                break;

            case ACTION_RESET_DEFAULT:
                $data = MediaURL::decode($user_input->selected_data);
                if ($data->choose_file === ACTION_CHANGE_GROUP_ICON) {

                    $group = $this->plugin->tv->get_any_group($sel_media_url->group_id);
                    if (is_null($group)) break;

                    hd_debug_print("Reset icon for group: $sel_media_url->group_id to default");

                    switch ($sel_media_url->group_id) {
                        case ALL_CHANNEL_GROUP_ID:
                            $group->set_icon_url(Default_Group::ALL_CHANNEL_GROUP_ICON);
                            break;

                        case FAVORITES_GROUP_ID:
                            $group->set_icon_url(Default_Group::FAV_CHANNEL_GROUP_ICON);
                            break;

                        case HISTORY_GROUP_ID:
                            $group->set_icon_url(Default_Group::HISTORY_GROUP_ICON);
                            break;

                        case CHANGED_CHANNELS_GROUP_ID:
                            $group->set_icon_url(Default_Group::CHANGED_CHANNELS_GROUP_ICON);
                            break;

                        case VOD_GROUP_ID:
                            $group->set_icon_url(Default_Group::VOD_GROUP_ICON);
                            break;

                        default:
                            $group->set_icon_url(Default_Group::DEFAULT_GROUP_ICON);
                    }

                    /** @var Hashed_Array<string> $group_icons */
                    $group_icons = $this->plugin->get_orders(PARAM_GROUPS_ICONS, new Hashed_Array());
                    $group_icons->erase($sel_media_url->group_id);
                    $this->plugin->save_orders(true);
                    $this->set_no_changes(PLUGIN_SETTINGS);
                }
                break;

            case ACTION_RELOAD:
                hd_debug_print("Action reload", true);
                $this->save_if_changed();

                if (isset($user_input->reload_action)) {
                    if ($user_input->reload_action === 'playlist') {
                        $this->plugin->clear_playlist_cache();
                    } else if ($user_input->reload_action === 'epg' || $user_input->reload_action === 'epg_change') {
                        $this->plugin->safe_clear_epg_cache();
                        $this->plugin->init_epg_manager();
                        $this->plugin->get_epg_manager()->clear_epg_files($this->plugin->get_active_xmltv_source_key());
                        $res = $this->plugin->get_epg_manager()->reload_xmltv_source();
                        if ($res === -1) {
                            return Action_Factory::show_title_dialog(TR::t('err_load_xmltv_epg'), null, HD::get_last_error("xmltv_last_error"));
                        }
                    }
                }

                $this->plugin->safe_clear_epg_cache();

                if ($this->plugin->tv->reload_channels($plugin_cookies) === 0) {
                    $post_action = Action_Factory::show_title_dialog(TR::t('err_load_playlist'), null, HD::get_last_error());
                    $post_action = Action_Factory::close_and_run(
                        Action_Factory::open_folder(self::ID, $this->plugin->create_plugin_title(), null, null, $post_action));

                    return Action_Factory::invalidate_all_folders($plugin_cookies, $post_action);
                }

                return User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);

            case ACTION_INFO_DLG:
                return $this->plugin->do_show_subscription($this);

            case ACTION_ADD_MONEY_DLG:
                return $this->plugin->do_show_add_money();

            case ACTION_REFRESH_SCREEN:
                $this->save_if_changed();
                $parent_media_url = MediaURL::decode($user_input->parent_media_url);
                $post_action = Action_Factory::close_and_run(Action_Factory::open_folder(self::ID, $this->plugin->create_plugin_title()));
                return Action_Factory::invalidate_all_folders($plugin_cookies,
                    Action_Factory::change_behaviour($this->get_action_map($parent_media_url, $plugin_cookies), 0, $post_action)
                );

            case CONTROL_PLAYLIST:
                if ($user_input->action_type !== 'confirm' || $user_input->{CONTROL_PLAYLIST} !== CUSTOM_PLAYLIST_ID) {
                    return null;
                }

                $provider = $this->plugin->get_current_provider();
                if (is_null($provider)) {
                    return null;
                }

                $url = $provider->getCredential(MACRO_CUSTOM_PLAYLIST);

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
                $provider = $this->plugin->get_current_provider();
                if (!is_null($provider)) {
                    hd_debug_print("set custom playlist $user_input->url_path");
                    $provider->setCredential(MACRO_CUSTOM_PLAYLIST, $user_input->url_path);
                }
                return null;

            case ACTION_EMPTY:
            default:
                return null;
        }

        $post_action = $this->get_folder_range(MediaURL::decode($user_input->parent_media_url),0, $plugin_cookies);
        return Action_Factory::update_regular_folder($post_action, true, $sel_ndx);
    }

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $items = array();
        $res = $this->plugin->tv->load_channels($plugin_cookies);
        if ($res === 0) {
            hd_debug_print("Channels not loaded!");
            return $items;
        }

        /** @var Default_Group $group */
        foreach ($this->plugin->tv->get_special_groups() as $group) {
            if (is_null($group)) continue;

            hd_debug_print("group: '{$group->get_title()}' disabled: " . var_export($group->is_disabled(), true), true);

            if ($group->is_disabled()) continue;

            switch ($group->get_id()) {
                case ALL_CHANNEL_GROUP_ID:
                    $total = 0;
                    foreach ($this->plugin->tv->get_enabled_groups() as $egroup) {
                        $total += $egroup->get_group_channels()->size();
                    }
                    $item_detailed_info = TR::t('tv_screen_group_info__3',
                        $group->get_title(),
                        $total,
                        $this->plugin->tv->get_disabled_channel_ids()->size());
                    $color = DEF_LABEL_TEXT_COLOR_SKYBLUE;
                    break;

                case FAVORITES_GROUP_ID:
                    $item_detailed_info = TR::t('tv_screen_group_info__2',
                        $group->get_title(),
                        $group->get_items_order()->size());
                    $color = DEF_LABEL_TEXT_COLOR_GOLD;
                    break;

                case HISTORY_GROUP_ID:
                    $visible = 0;
                    foreach ($this->plugin->get_playback_points()->get_all() as $channel_id => $channel_ts) {
                        $channel = $this->plugin->tv->get_channel($channel_id);
                        if (is_null($channel) || $channel->is_disabled()) continue;
                        $visible++;
                    }
                    $item_detailed_info = TR::t('tv_screen_group_info__2', $group->get_title(), $visible);
                    $color = DEF_LABEL_TEXT_COLOR_TURQUOISE;
                    break;

                case CHANGED_CHANNELS_GROUP_ID:
                    $item_detailed_info = TR::t('tv_screen_group_changed_info__3',
                        $group->get_title(),
                        count($this->plugin->tv->get_changed_channels_ids('new')),
                        count($this->plugin->tv->get_changed_channels_ids('removed')));
                    $color = DEF_LABEL_TEXT_COLOR_RED;
                    break;

                case VOD_GROUP_ID:
                    $item_detailed_info = $group->get_title();
                    $color = DEF_LABEL_TEXT_COLOR_LIGHTGREEN;
                    break;

                default:
                    $item_detailed_info = TR::t('tv_screen_group_info__2',
                        $group->get_title(),
                        $group->get_items_order()->size());
                    $color = DEF_LABEL_TEXT_COLOR_WHITE;
                    break;
            }

            hd_debug_print("special group: " . $group->get_media_url_str(), true);

            $items[] = array(
                PluginRegularFolderItem::media_url => $group->get_media_url_str(),
                PluginRegularFolderItem::caption => $group->get_title(),
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::item_caption_color => $color,
                    ViewItemParams::icon_path => $group->get_icon_url(),
                    ViewItemParams::item_detailed_icon_path => $group->get_icon_url(),
                    ViewItemParams::item_detailed_info => $item_detailed_info,
                    )
                );
        }

        /** @var Default_Group $group */
        foreach ($this->plugin->tv->get_enabled_groups()->filter($this->plugin->tv->get_groups_order()->get_order()) as $group) {
            $group_url = $group->get_icon_url();
            $items[] = array(
                PluginRegularFolderItem::media_url => $group->get_media_url_str(),
                PluginRegularFolderItem::caption => $group->get_title(),
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::icon_path => $group_url,
                    ViewItemParams::item_detailed_icon_path => $group_url,
                    ViewItemParams::item_detailed_info => TR::t('tv_screen_group_info__3',
                        str_replace('|', '¦', $group->get_title()),
                        $group->get_group_channels()->size(),
                        $group->get_group_channels()->size() - $group->get_items_order()->size()
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
