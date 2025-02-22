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

class Starnet_Edit_List_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'edit_list';

    const SCREEN_EDIT_PLAYLIST = 'playlist';
    const SCREEN_EDIT_EPG_LIST = 'epg_list';
    const SCREEN_EDIT_PROVIDERS = 'providers';

    const ACTION_FILE_PLAYLIST = 'play_list_file';
    const ACTION_FILE_XMLTV = 'xmltv_file';
    const ACTION_FILE_TEXT_LIST = 'text_list_file';

    const ACTION_CLEAR_APPLY = 'clear_apply';
    const ACTION_REMOVE_ITEM_DLG_APPLY = 'remove_item_apply';
    const ACTION_CHOOSE_FOLDER = 'choose_folder';
    const ACTION_CHOOSE_FILE = 'choose_file';
    const ACTION_ADD_PROVIDER_POPUP = 'add_provider';
    const ACTION_CONFIRM_CLEAR_DLG_APPLY = 'clear_apply_dlg';
    const ACTION_SHOW_QR = 'show_qr';
    const ACTION_XMLTV_CACHE_POPUP = 'xmltv_cache_time';
    const ACTION_XMLTV_CACHE_SELECTED = 'xmltv_cache_selected';

    const CONTROL_EDIT_TYPE = 'playlist_type';
    const CONTROL_EDIT_DETECT_ID = 'detect_id';

    const ITEM_SET_NAME = 'set_name';
    const ITEM_EDIT = 'edit';

    ///////////////////////////////////////////////////////////////////////

    protected $force_parent_reload = false;

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $actions = array();
        $edit_list = $media_url->edit_list;

        if ($edit_list === self::SCREEN_EDIT_PLAYLIST || $edit_list === self::SCREEN_EDIT_EPG_LIST) {
            if (isset($media_url->allow_order) && $this->get_order($edit_list)->size() !== 0) {
                $actions[GUI_EVENT_KEY_SELECT] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_TOGGLE_MOVE);
                if (isset($plugin_cookies->toggle_move) && $plugin_cookies->toggle_move) {
                    $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_TOP, TR::t('top'));
                    $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_BOTTOM, TR::t('bottom'));
                } else {
                    $actions[GUI_EVENT_KEY_B_GREEN] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_UP, TR::t('up'));
                    $actions[GUI_EVENT_KEY_C_YELLOW] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DOWN, TR::t('down'));
                }
            }

            $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, ACTION_SETTINGS, TR::t('edit'));
        }

        if ($edit_list === self::SCREEN_EDIT_PROVIDERS) {
            $info = User_Input_Handler_Registry::create_action($this, self::ACTION_SHOW_QR, TR::t('info'));
            $actions[GUI_EVENT_KEY_INFO] = $info;
            $actions[GUI_EVENT_KEY_D_BLUE] = $info;
        }

        $action_return = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_RETURN] = $action_return;
        $actions[GUI_EVENT_KEY_TOP_MENU] = $action_return;
        $actions[GUI_EVENT_KEY_ENTER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER);
        $actions[GUI_EVENT_KEY_STOP] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_STOP);
        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);
        $actions[GUI_EVENT_TIMER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_TIMER);

        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        $selected_id = isset($user_input->selected_media_url) ? MediaURL::decode($user_input->selected_media_url)->id : 0;

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $edit_list = $parent_media_url->edit_list;

        $only_refresh = false;
        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_RETURN:
                if ($edit_list === self::SCREEN_EDIT_PROVIDERS) {
                    return Action_Factory::close_and_run(
                        User_Input_Handler_Registry::create_action_screen(
                            $parent_media_url->source_window_id,
                            $parent_media_url->cancel_action
                        )
                    );
                }

                $reload = $this->force_parent_reload;
                $this->force_parent_reload = false;

                hd_debug_print("Need reload: " . var_export($reload, true), true);
                if ($reload) {
                    $this->plugin->set_dirty(true, $parent_media_url->save_data);
                }

                $this->plugin->set_postpone_save(false, $parent_media_url->save_data);

                $post_action = User_Input_Handler_Registry::create_action_screen(
                    $parent_media_url->source_window_id,
                    $reload ? $parent_media_url->end_action : $parent_media_url->cancel_action,
                    null,
                    array('reload_action' => $edit_list)
                );
                hd_debug_print("post action: " . pretty_json_format($post_action));

                return Action_Factory::invalidate_folders(
                    $reload ? array($parent_media_url->source_media_url_str) : array(),
                    Action_Factory::close_and_run($post_action)
                );

            case GUI_EVENT_KEY_STOP:
                $this->force_save($user_input);
                break;

            case GUI_EVENT_KEY_ENTER:
                if ($edit_list === self::SCREEN_EDIT_PROVIDERS) {
                    return Action_Factory::close_and_run(
                        User_Input_Handler_Registry::create_action_screen(
                            $parent_media_url->source_window_id,
                            $parent_media_url->end_action,
                            null,
                            array(PARAM_PROVIDER => $selected_id)
                        )
                    );
                }

                if ($edit_list === self::SCREEN_EDIT_PLAYLIST) {
                    $this->plugin->set_active_playlist_key($selected_id);
                    $this->force_parent_reload = true;
                    $this->force_save($user_input);

                    return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
                }

                if ($edit_list === self::SCREEN_EDIT_EPG_LIST) {
                    $selected_sources = $this->plugin->get_setting(PARAM_SELECTED_XMLTV_SOURCES, array());
                    $offset = array_search($selected_id, $selected_sources);
                    if ($offset !== false) {
                        hd_debug_print("Removed Source: $selected_id", true);
                        array_splice($selected_sources, $offset, 1);
                    } else if ($this->plugin->get_all_xmltv_sources()->has($selected_id)) {
                        hd_debug_print("Added Source: $selected_id", true);
                        $selected_sources[] = $selected_id;
                    }
                    hd_debug_print("Updated Selected Sources: " . json_encode($selected_sources), true);

                    $this->force_parent_reload = true;
                    $this->plugin->set_setting(PARAM_SELECTED_XMLTV_SOURCES, $selected_sources);
                    $this->save(PLUGIN_SETTINGS);
                    return $this->invalidate_current_folder($parent_media_url, $plugin_cookies);
                }

                return null;

            case GUI_EVENT_TIMER:
                if ($edit_list !== self::SCREEN_EDIT_EPG_LIST) {
                    return null;
                }

                $epg_manager = $this->plugin->get_epg_manager();
                if ($epg_manager === null) {
                    return null;
                }

                clearstatcache();

                if (!isset($plugin_cookies->ticker)) {
                    $plugin_cookies->ticker = 0;
                }
                $res = $epg_manager->import_indexing_log($this->plugin->get_all_xmltv_sources()->get_ordered_keys());
                $post_action = Action_Factory::update_regular_folder($this->get_folder_range($parent_media_url, 0, $plugin_cookies),true);

                if ($res !== false) {
                    hd_debug_print("Return post action. Timer stopped");
                    return $post_action;
                }

                $actions = $this->get_action_map($parent_media_url, $plugin_cookies);
                return Action_Factory::change_behaviour($actions, 1000, $post_action);

            case ACTION_SETTINGS:
                if ($edit_list !== self::SCREEN_EDIT_PLAYLIST && $edit_list !== self::SCREEN_EDIT_EPG_LIST) {
                    return null;
                }

                $this->force_save($user_input);
                $item = $this->get_order($edit_list)->get($selected_id);

                hd_debug_print("item: " . $selected_id, true);
                hd_debug_print("playlist: " . $item, true);
                if ($item === null) {
                    hd_debug_print("Unknown playlist", true);
                    return null;
                }

                if (($item->type === PARAM_LINK || empty($item->type))
                    && isset($item->params[PARAM_URI])
                    && is_http($item->params[PARAM_URI])) {
                    return $this->do_edit_url_dlg($edit_list, $selected_id);
                }

                if ($item->type === PARAM_FILE
                    && $edit_list === self::SCREEN_EDIT_PLAYLIST
                    && isset($item->params[PARAM_URI])) {
                    $playlist_type = isset($item->params[PARAM_PL_TYPE]) ? $item->params[PARAM_PL_TYPE] : CONTROL_PLAYLIST_IPTV;
                    return $this->do_edit_m3u_type($playlist_type, $selected_id);
                }

                if ($item->type === PARAM_PROVIDER) {
                    return $this->plugin->do_edit_provider_dlg($this, $item->params[PARAM_PROVIDER], $selected_id);
                }
                return null;

            case ACTION_INDEX_EPG:
                $this->plugin->run_bg_epg_indexing($selected_id);
                return Action_Factory::change_behaviour($this->get_action_map($parent_media_url, $plugin_cookies), 500);

            case ACTION_CLEAR_CACHE:
                $this->plugin->safe_clear_selected_epg_cache($selected_id);
                break;

            case ACTION_ITEM_TOGGLE_MOVE:
                $plugin_cookies->toggle_move = !$plugin_cookies->toggle_move;
                $actions = $this->get_action_map($parent_media_url, $plugin_cookies);
                return Action_Factory::change_behaviour($actions);

            case ACTION_ITEM_UP:
                if (!$this->get_order($edit_list)->arrange_item($selected_id, Ordered_Array::UP)) {
                    return null;
                }

                $user_input->sel_ndx--;
                if ($user_input->sel_ndx < 0) {
                    $user_input->sel_ndx = 0;
                }

                $this->set_changes($parent_media_url->save_data);
                $only_refresh = true;
                break;

            case ACTION_ITEM_DOWN:
                $order = $this->get_order($edit_list);
                if (!$order->arrange_item($selected_id, Ordered_Array::DOWN)) {
                    return null;
                }

                $groups_cnt = $order->size();
                $user_input->sel_ndx++;
                if ($user_input->sel_ndx >= $groups_cnt) {
                    $user_input->sel_ndx = $groups_cnt - 1;
                }

                $this->set_changes($parent_media_url->save_data);
                $only_refresh = true;
                break;

            case ACTION_ITEM_TOP:
                $order = $this->get_order($edit_list);
                if (!$order->arrange_item($selected_id, Ordered_Array::DOWN)) {
                    return null;
                }

                $user_input->sel_ndx = $this->plugin->get_groups_count(true);
                break;

            case ACTION_ITEM_BOTTOM:
                $order = $this->get_order($edit_list);
                if (!$order->arrange_item($selected_id, Ordered_Array::DOWN)) {
                    return null;
                }

                $user_input->sel_ndx = $this->plugin->get_groups_order_count() + $this->plugin->get_groups_count(true) - 1;
                break;

            case ACTION_ITEM_DELETE:
                if ($edit_list === self::SCREEN_EDIT_PLAYLIST || $edit_list === self::SCREEN_EDIT_EPG_LIST) {
                    return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::ACTION_REMOVE_ITEM_DLG_APPLY);
                }

                hd_debug_print("unknown edit list");
                return null;

            case self::ACTION_REMOVE_ITEM_DLG_APPLY:
                hd_debug_print(null, true);

                hd_debug_print("edit_list: $parent_media_url->edit_list", true);
                if ($parent_media_url->edit_list === self::SCREEN_EDIT_EPG_LIST) {
                    if (!$this->get_order($edit_list)->has($selected_id)) {
                        hd_debug_print("remove xmltv source: $selected_id", true);
                        return Action_Factory::show_error(false, TR::t('edit_list_title_cant_delete'));
                    }

                    $this->plugin->safe_clear_selected_epg_cache($selected_id);
                    $selected_sources = $this->plugin->get_setting(PARAM_SELECTED_XMLTV_SOURCES, array());
                    $offset = array_search($selected_id, $selected_sources);
                    if ($offset !== false) {
                        $selected_sources = array_splice($selected_sources, $offset, 1);
                        $this->plugin->set_setting(PARAM_SELECTED_XMLTV_SOURCES, $selected_sources);
                    }
                } else if ($parent_media_url->edit_list === self::SCREEN_EDIT_PLAYLIST) {
                    hd_debug_print("remove playlist settings: $selected_id", true);
                    if ($this->plugin->get_active_playlist_key() === $selected_id) {
                        $this->force_parent_reload = true;
                        $this->plugin->get_playlists()->rewind();
                        $this->plugin->set_active_playlist_key($this->plugin->get_playlists()->key());
                    }
                    $this->plugin->remove_playlist_data($selected_id);
                }

                $this->get_order($edit_list)->erase($selected_id);
                $this->set_changes($parent_media_url->save_data);

                if ($this->force_parent_reload && $this->plugin->reload_channels($plugin_cookies) === 0) {
                    return Action_Factory::invalidate_all_folders($plugin_cookies,
                        Action_Factory::show_title_dialog(TR::t('err_load_playlist'), null, HD::get_last_error()));
                }

                return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->sel_ndx);

            case GUI_EVENT_KEY_POPUP_MENU:
                return $this->create_popup_menu($user_input);

            case ACTION_ITEMS_CLEAR:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_clear_all_msg'),
                    $this, self::ACTION_CONFIRM_CLEAR_DLG_APPLY);

            case self::ACTION_CONFIRM_CLEAR_DLG_APPLY:
                switch ($edit_list) {
                    case self::SCREEN_EDIT_EPG_LIST:
                        $epg_manager = $this->plugin->get_epg_manager();
                        if ($epg_manager !== null) {
                            foreach ($this->get_order($edit_list) as $offset) {
                                $this->plugin->safe_clear_selected_epg_cache($offset);
                            }
                        }
                        $this->get_order($edit_list)->clear();
                        $this->plugin->remove_setting(PARAM_SELECTED_XMLTV_SOURCES);
                        break;

                    case self::SCREEN_EDIT_PLAYLIST:
                        foreach ($this->get_order($edit_list)->get_keys() as $offset) {
                            $this->plugin->remove_playlist_data($offset);
                        }
                        $this->get_order($edit_list)->clear();
                        break;

                    default:
                        return null;
                }

                $this->set_changes($parent_media_url->save_data);

                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

            case ACTION_ADD_URL_DLG:
                return $this->do_edit_url_dlg($edit_list);

            case ACTION_URL_DLG_APPLY: // handle streaming settings dialog result
                return $this->apply_edit_url_dlg($user_input, $plugin_cookies);

            case ACTION_PL_TYPE_DLG_APPLY: // handle streaming settings dialog result
                return $this->apply_edit_m3u_type($user_input, $plugin_cookies);

            case self::ACTION_CHOOSE_FILE:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'source_window_id' => static::ID,
                        'choose_file' => $user_input->selected_action,
                        'extension' => $user_input->extension,
                        'allow_network' => ($user_input->selected_action === self::ACTION_FILE_TEXT_LIST) && !is_limited_apk(),
                        'read_only' => true,
                        'windowCounter' => 1,
                    )
                );

                return Action_Factory::open_folder($media_url_str, TR::t('select_file'));

            case ACTION_FILE_SELECTED:
                return $this->do_select_file($user_input, $plugin_cookies);

            case self::ACTION_CHOOSE_FOLDER:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'source_window_id' => static::ID,
                        'choose_folder' => $user_input->control_id,
                        'extension' => $user_input->extension,
                        'allow_network' => false,
                        'read_only' => true,
                        'windowCounter' => 1,
                    )
                );

                return Action_Factory::open_folder($media_url_str, TR::t('edit_list_src_folder'));

            case self::ACTION_ADD_PROVIDER_POPUP:
                if ($edit_list === self::SCREEN_EDIT_PROVIDERS) break;

                return $this->plugin->do_edit_list_screen(self::ID, self::SCREEN_EDIT_PROVIDERS);

            case ACTION_EDIT_PROVIDER_DLG:
                $playlist_id = empty($user_input->{PARAM_PLAYLIST_ID}) ? '' : $user_input->{PARAM_PLAYLIST_ID};
                return $this->plugin->do_edit_provider_dlg($this, $user_input->{PARAM_PROVIDER}, $playlist_id);

            case ACTION_EDIT_PROVIDER_DLG_APPLY:
                $id = $this->plugin->apply_edit_provider_dlg($user_input);
                if ($id === false) {
                    return Action_Factory::show_error(false, TR::t('err_incorrect_access_data'));
                }

                if ($id === null) {
                    return null;
                }

                if (is_array($id)) {
                    return $id;
                }

                $this->set_changes($parent_media_url->save_data);
                $this->force_parent_reload = $this->plugin->get_active_playlist_key() === $id;
                if ($this->force_parent_reload && $this->plugin->reload_channels($plugin_cookies) === 0) {
                    return Action_Factory::invalidate_all_folders($plugin_cookies,
                        Action_Factory::show_title_dialog(TR::t('err_load_playlist'), null, HD::get_last_error()));
                }

                $idx = $this->plugin->get_playlists()->get_idx($id);
                return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $idx);

            case ACTION_FOLDER_SELECTED:
                return $this->do_select_folder($user_input);

            case self::ACTION_XMLTV_CACHE_POPUP:
                return $this->create_cache_time_popup($user_input);

            case self::ACTION_XMLTV_CACHE_SELECTED:
                if (!isset($user_input->{LIST_IDX})) break;

                // if source exist in playlist and user list - user list is preferred
                $ext_sources = $this->plugin->get_ext_xmltv_sources();
                $source = $ext_sources->get($selected_id);
                if ($source !== null) {
                    $source->params[PARAM_CACHE] = $user_input->{LIST_IDX};
                    hd_debug_print("update ext source: " . json_encode($source), true);
                    $ext_sources->set($selected_id, $source);
                    $this->plugin->set_parameter(PARAM_EXT_XMLTV_SOURCES, $ext_sources);
                    $this->plugin->save_parameters(true);
                    break;
                }

                $pl_sources = $this->plugin->get_playlist_xmltv_sources();
                $source = $pl_sources->get($selected_id);
                if ($source !== null) {
                    $source->params[PARAM_CACHE] = $user_input->{LIST_IDX};
                    hd_debug_print("update pl source: " . json_encode($source), true);
                    $pl_sources->set($selected_id, $source);
                    $this->plugin->set_setting(PARAM_EPG_PLAYLIST, $pl_sources);
                    $this->plugin->save_settings(true);
                }
                break;

            case self::ACTION_SHOW_QR:
                /** @var api_default $provider */
                $provider = $this->get_order($edit_list)->get($selected_id);
                if (is_null($provider)) break;

                $qr_code = get_temp_path($provider->getId()) . ".jpg";
                if (!file_exists($qr_code)) {
                    $url = "https://api.qrserver.com/v1/create-qr-code/?size=450x450&format=jpg&data=" . urlencode($provider->getProviderUrl());
                    list($res,) = Curl_Wrapper::simple_download_file($url, $qr_code);
                    if (!$res) break;
                }

                Control_Factory::add_vgap($defs, 20);
                Control_Factory::add_smart_label($defs, "", "<gap width=25/><icon width=450 height=450>$qr_code</icon>");
                Control_Factory::add_vgap($defs, 450);
                return Action_Factory::show_dialog(TR::t('provider_info'), $defs, true, 600);
        }

        if ($only_refresh) {
            $post_action = $this->get_folder_range(MediaURL::decode($user_input->parent_media_url), 0, $plugin_cookies);
            return Action_Factory::update_regular_folder($post_action, true, $user_input->sel_ndx);
        }

        return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->sel_ndx);
    }

    protected function force_save($user_input)
    {
        if (isset($parent_media_url->save_data)) {
            $parent_media_url = MediaURL::decode($user_input->parent_media_url);
            $this->set_changes($parent_media_url->save_data);
        }
    }

    protected function save($parameter)
    {
        $this->plugin->set_postpone_save(false, $parameter);
        $this->plugin->set_postpone_save(true, $parameter);
    }

    /**
     * @param string $edit_list
     * @return Hashed_Array
     */
    protected function &get_order($edit_list)
    {
        switch ($edit_list) {
            case static::SCREEN_EDIT_PLAYLIST:
                $order = $this->plugin->get_playlists();
                break;

            case static::SCREEN_EDIT_EPG_LIST:
                $order = $this->plugin->get_ext_xmltv_sources();
                break;

            case static::SCREEN_EDIT_PROVIDERS:
                $order = $this->plugin->get_providers();
                break;

            default:
                $order = new Hashed_Array();
        }

        return $order;
    }

    /////////////////////////////////////////////////////////////////////////////////////////////
    /// protected methods

    /**
     * @param Object $user_input
     * @return array|null
     */
    protected function create_popup_menu($user_input)
    {
        hd_debug_print(null, true);

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $edit_list = $parent_media_url->edit_list;

        if ($edit_list !== self::SCREEN_EDIT_PLAYLIST && $edit_list !== self::SCREEN_EDIT_EPG_LIST) {
            return null;
        }

        $menu_items = array();
        if ($edit_list === self::SCREEN_EDIT_EPG_LIST) {
            $menu_items[] = $this->plugin->create_menu_item($this, ACTION_INDEX_EPG, TR::t('entry_index_epg'), 'settings.png');
            $menu_items[] = $this->plugin->create_menu_item($this, ACTION_CLEAR_CACHE, TR::t('entry_epg_cache_clear'), 'brush.png');
            $menu_items[] = $this->plugin->create_menu_item($this, self::ACTION_XMLTV_CACHE_POPUP, TR::t('entry_epg_cache_time'));

            $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);

            // Add URL
            $menu_items[] = $this->plugin->create_menu_item($this,
                ACTION_ADD_URL_DLG,
                TR::t('edit_list_add_url'),
                "link.png"
            );
        } else {
            // Add provider
            $menu_items[] = $this->plugin->create_menu_item($this,
                self::ACTION_ADD_PROVIDER_POPUP,
                TR::t('edit_list_add_provider'),
                "iptv.png"
            );

            // Add URL
            $menu_items[] = $this->plugin->create_menu_item($this,
                ACTION_ADD_URL_DLG,
                TR::t('edit_list_add_url'),
                "link.png",
                array('selected_action' => self::ACTION_FILE_PLAYLIST)
            );

            // Add File
            $menu_items[] = $this->plugin->create_menu_item($this,
                self::ACTION_CHOOSE_FILE,
                TR::t('select_file'),
                "m3u_file.png",
                array(
                    'selected_action' => self::ACTION_FILE_PLAYLIST,
                    'extension' => $parent_media_url->extension
                )
            );

            $menu_items[] = $this->plugin->create_menu_item($this,
                self::ACTION_CHOOSE_FOLDER,
                TR::t('edit_list_folder_path'),
                "folder.png",
                array('extension' => $parent_media_url->extension)
            );
        }

        // Add list file
        $menu_items[] = $this->plugin->create_menu_item($this,
            self::ACTION_CHOOSE_FILE,
            TR::t('edit_list_import_list'),
            "text_file.png",
            array(
                'selected_action' => self::ACTION_FILE_TEXT_LIST,
                'extension' => 'txt|lst'
            )
        );

        $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);

        if (isset($parent_media_url->allow_order) && $this->get_order($edit_list)->size() !== 0) {
            $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEM_TOGGLE_MOVE, TR::t('tv_screen_toggle_move'), "move.png");
        }

        $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
        $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEM_DELETE, TR::t('delete'), "remove.png");
        $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_CLEAR, TR::t('clear'), "brush.png");

        return Action_Factory::show_popup_menu($menu_items);
    }

    protected function create_cache_time_popup($user_input)
    {
        hd_debug_print(null, true);

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $edit_list = $parent_media_url->edit_list;

        if ($edit_list !== self::SCREEN_EDIT_EPG_LIST) {
            return null;
        }

        $selected_media_url = MediaURL::decode($user_input->selected_media_url);
        $all_sources = $this->plugin->get_all_xmltv_sources();

        $source = $all_sources->get($selected_media_url->id);
        $selected = isset($source->params[PARAM_CACHE]) ? $source->params[PARAM_CACHE] : XMLTV_CACHE_AUTO;

        $menu_items = array();
        $menu_items[] = $this->plugin->create_menu_item($this,
            self::ACTION_XMLTV_CACHE_SELECTED,
            TR::t('auto'),
            $selected === XMLTV_CACHE_AUTO ? "check.png" : null,
            array(LIST_IDX => 'auto')
        );

        $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);

        $list = array(0.25, 0.5, 1, 2, 3, 4, 5, 6, 7);
        foreach ($list as $item) {
            $menu_items[] = $this->plugin->create_menu_item($this,
                self::ACTION_XMLTV_CACHE_SELECTED,
                $item,
                (int)$selected === $item ? "check.png" : null,
                array(LIST_IDX => $item)
            );
        }

        return Action_Factory::show_popup_menu($menu_items);
    }

    /**
     * @param string $edit_list
     * @param string $id
     * @return array|null
     */
    protected function do_edit_url_dlg($edit_list, $id = '')
    {
        hd_debug_print(null, true);
        $defs = array();

        if (!empty($id)) {
            $item = $this->get_order($edit_list)->get($id);
            if (is_null($item)) {
                return $defs;
            }

            $window_title = TR::t('edit_list_edit_item');
            $name = $item->name;
            $url = $item->params[PARAM_URI];
            $opts_idx = isset($item->params[PARAM_PL_TYPE]) ? $item->params[PARAM_PL_TYPE] : CONTROL_PLAYLIST_IPTV;
            $param = array(CONTROL_ACTION_EDIT => CONTROL_EDIT_ITEM);
        } else {
            $window_title = TR::t('edit_list_add_url');
            $name = '';
            $url = 'http://';
            $param = null;
            $opts_idx = CONTROL_PLAYLIST_IPTV;
        }

        Control_Factory::add_vgap($defs, 20);

        Control_Factory::add_text_field($defs, $this, null, CONTROL_EDIT_NAME, TR::t('name'),
            $name, false, false, false, true, self::DLG_CONTROLS_WIDTH);

        if ($edit_list === self::SCREEN_EDIT_PLAYLIST) {
            $opts[CONTROL_PLAYLIST_IPTV] = TR::t('edit_list_playlist_iptv');
            $opts[CONTROL_PLAYLIST_VOD] = TR::t('edit_list_playlist_vod');
            Control_Factory::add_combobox($defs, $this, null, self::CONTROL_EDIT_TYPE,
                TR::t('edit_list_playlist_type'), $opts_idx, $opts, self::DLG_CONTROLS_WIDTH);
        }

        Control_Factory::add_text_field($defs, $this, null, CONTROL_URL_PATH, TR::t('url'),
            $url, false, false, false, true, self::DLG_CONTROLS_WIDTH);

        Control_Factory::add_vgap($defs, 50);

        Control_Factory::add_close_dialog_and_apply_button($defs, $this, $param,
            ACTION_URL_DLG_APPLY, TR::t('ok'), 300);

        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
        Control_Factory::add_vgap($defs, 10);

        return Action_Factory::show_dialog($window_title, $defs, true);
    }

    /**
     * @param Object $user_input
     * @param Object $plugin_cookies
     * @return array|null
     */
    protected function apply_edit_url_dlg($user_input, $plugin_cookies)
    {
        hd_debug_print(null, true);

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $edit_list = $parent_media_url->edit_list;

        $name = isset($user_input->{CONTROL_EDIT_NAME}) ? $user_input->{CONTROL_EDIT_NAME} : '';
        $url = isset($user_input->{CONTROL_URL_PATH}) ? $user_input->{CONTROL_URL_PATH} : '';
        if (!is_http($url)) {
            return Action_Factory::show_title_dialog(TR::t('err_incorrect_url'));
        }

        if (empty($name)) {
            if (($pos = strpos($name, '?')) !== false) {
                $name = substr($name, 0, $pos);
            }
            $name = ($edit_list === self::SCREEN_EDIT_PLAYLIST) ? basename($name) : $url;
        }

        $id = null;
        $order = $this->get_order($edit_list);
        if (isset($user_input->{CONTROL_ACTION_EDIT}, $user_input->selected_media_url)) {
            // edit existing url
            $id = MediaURL::decode($user_input->selected_media_url)->id;
            if ($edit_list === self::SCREEN_EDIT_EPG_LIST) {
                $order->erase($id);
                $item = null;
                $this->plugin->safe_clear_selected_epg_cache($id);
            } else {
                $item = $order->get($id);
            }
        } else {
            $item = null;
        }

        if (is_null($item)) {
            $id = Hashed_Array::hash($url);
            while ($order->has($id)) {
                $id = Hashed_Array::hash("$id.$url");
            }
            $item = new Named_Storage();
        }

        $item->name = $name;
        $item->type = PARAM_LINK;
        $item->params[PARAM_URI] = $url;

        $reload = false;
        if ($edit_list === self::SCREEN_EDIT_PLAYLIST) {
            try {
                $tmp_file = get_temp_path(Hashed_Array::hash($url));
                list($res, $log) = Curl_Wrapper::simple_download_file($url, $tmp_file);
                if (!$res) {
                    throw new Exception(TR::load_string('err_load_playlist') . " '$url'\n\n" . $log);
                }

                $contents = file_get_contents($tmp_file, false, null, 0, 512);
                if ($contents === false || strpos($contents, TAG_EXTM3U) === false) {
                    unlink($tmp_file);
                    throw new Exception(TR::load_string('err_empty_playlist') . " '$url'\n\n$contents");
                }

                $parser = new M3uParser();
                $parser->setPlaylist($tmp_file,true);
                $pl_header = $parser->parseHeader(false);
                $type = isset($user_input->{self::CONTROL_EDIT_TYPE}) ? $user_input->{self::CONTROL_EDIT_TYPE} : CONTROL_PLAYLIST_IPTV;
                if ($type === CONTROL_PLAYLIST_IPTV && $item->params[PARAM_PL_TYPE] === CONTROL_PLAYLIST_IPTV) {
                    $db = new Sql_Wrapper(new SQLite3(":memory:", SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE, ''));
                    if ($parser->parseIptvPlaylist($db)) {
                        $result = $db->query_value("SELECT count(hash) FROM iptv.iptv_channels;");
                    }

                    if (empty($result)) {
                        throw new Exception(TR::load_string('err_empty_playlist') . " '$url'\n\n$contents");
                    }

                    $pl_header = $parser->getM3uInfo();
                    $detect = isset($user_input->{self::CONTROL_EDIT_DETECT_ID}) ? $user_input->{self::CONTROL_EDIT_DETECT_ID} : SetupControlSwitchDefs::switch_on;
                    if ($detect === SetupControlSwitchDefs::switch_on) {
                        $item->params[PARAM_ID_MAPPER] = Default_Dune_Plugin::detectBestChannelId($db);
                        hd_debug_print("detected id: " . $item->params[PARAM_ID_MAPPER]);
                    }
                }

                hd_debug_print("Playlist info: " . $pl_header);
                $pl_tag = $pl_header->getEntryTag(TAG_PLAYLIST);
                if ($pl_tag !== null) {
                    $pl_name = $pl_tag->getTagValue();
                    $item->name = empty($pl_name) ? $name : $pl_name;
                }

                $item->params[PARAM_PL_TYPE] = $type;
                unlink($tmp_file);
                hd_debug_print("Playlist: '$url' imported successfully");
                $this->plugin->clear_playlist_cache($id);
                $reload = ($this->plugin->get_active_playlist_key() === $id && $this->plugin->reload_channels($plugin_cookies) === 0);
            } catch (Exception $ex) {
                hd_debug_print("Problem with download playlist");
                print_backtrace_exception($ex);
                return Action_Factory::show_title_dialog(TR::t('err_load_playlist'), null, $ex->getMessage());
            }
        }

        $order->set($id, $item);
        $this->set_changes($parent_media_url->save_data);

        if ($reload) {
            return Action_Factory::invalidate_all_folders($plugin_cookies,
                Action_Factory::show_title_dialog(TR::t('err_load_playlist'), null, HD::get_last_error()));
        }

        return Action_Factory::change_behaviour($this->get_action_map($parent_media_url, $plugin_cookies), 0,
            $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->sel_ndx));
    }

    /**
     * @param string $playlist_type
     * @param string $id
     * @return array|null
     */
    protected function do_edit_m3u_type($playlist_type, $id)
    {
        hd_debug_print(null, true);
        $defs = array();

        Control_Factory::add_vgap($defs, 20);

        $opts[CONTROL_PLAYLIST_IPTV] = TR::t('edit_list_playlist_iptv');
        $opts[CONTROL_PLAYLIST_VOD] = TR::t('edit_list_playlist_vod');

        Control_Factory::add_combobox($defs, $this, null, self::CONTROL_EDIT_TYPE,
            TR::t('edit_list_playlist_type'), $playlist_type, $opts, self::DLG_CONTROLS_WIDTH);

        $order = $this->get_order(self::SCREEN_EDIT_PLAYLIST);
        $item = $order->get($id);
        $detect = (is_null($item) || !isset($item->params[PARAM_ID_MAPPER])) ? SetupControlSwitchDefs::switch_on : SetupControlSwitchDefs::switch_off;

        $detect_opt[SetupControlSwitchDefs::switch_on] = TR::t('yes');
        $detect_opt[SetupControlSwitchDefs::switch_off] = TR::t('no');

        Control_Factory::add_combobox($defs, $this, null, self::CONTROL_EDIT_DETECT_ID,
            TR::t('edit_list_playlist_detect_id'), $detect, $detect_opt, self::DLG_CONTROLS_WIDTH);

        Control_Factory::add_vgap($defs, 50);

        Control_Factory::add_close_dialog_and_apply_button($defs, $this,
            array(
                CONTROL_ACTION_EDIT => CONTROL_EDIT_ITEM,
                CONTROL_EDIT_ITEM => $id
            ),
            ACTION_PL_TYPE_DLG_APPLY, TR::t('ok'), 300);

        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
        Control_Factory::add_vgap($defs, 10);

        return Action_Factory::show_dialog(TR::t('edit_list_playlist_type'), $defs, true);
    }

    /**
     * @param Object $user_input
     * @param Object $plugin_cookies
     * @return array|null
     */
    protected function apply_edit_m3u_type($user_input, $plugin_cookies)
    {
        hd_debug_print(null, true);

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        if (!isset($user_input->{CONTROL_ACTION_EDIT}, $user_input->{CONTROL_EDIT_ITEM})) {
            return null;
        }

        $order = $this->get_order($parent_media_url->edit_list);
        $id = $user_input->{CONTROL_EDIT_ITEM};
        $item = $order->get($id);
        if (is_null($item)) {
            return null;
        }

        $item->params[PARAM_PL_TYPE] = isset($user_input->{self::CONTROL_EDIT_TYPE}) ? $user_input->{self::CONTROL_EDIT_TYPE} : CONTROL_PLAYLIST_IPTV;
        $parser = new M3uParser();
        $parser->setPlaylist($item->params[PARAM_URI], true);
        if ($item->params[PARAM_PL_TYPE] === CONTROL_PLAYLIST_VOD) {
            $pl_header = $parser->parseHeader(false);
        } else {
            $db = new Sql_Wrapper(new SQLite3(":memory:", SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE, ''));
            if ($parser->parseIptvPlaylist($db)) {
                $result = $db->query_value("SELECT count(hash) FROM iptv.iptv_channels;");
            }

            if (empty($result)) {
                return Action_Factory::show_title_dialog(TR::t('err_empty_playlist'));
            }

            $pl_header = $parser->getM3uInfo();
            $detect = isset($user_input->{self::CONTROL_EDIT_DETECT_ID}) ? $user_input->{self::CONTROL_EDIT_DETECT_ID} : SetupControlSwitchDefs::switch_on;
            if ($detect === SetupControlSwitchDefs::switch_on) {
                $item->params[PARAM_ID_MAPPER] = Default_Dune_Plugin::detectBestChannelId($db);
                hd_debug_print("detected id: " . $item->params[PARAM_ID_MAPPER]);
            }
        }

        hd_debug_print("Playlist info: " . $pl_header);
        $pl_tag = $pl_header->getEntryTag(TAG_PLAYLIST);
        if ($pl_tag !== null) {
            $pl_name = $pl_tag->getTagValue();
            $item->name = empty($pl_name) ? $item->name : $pl_name;
        }

        $order->set($id, $item);
        $this->set_changes($parent_media_url->save_data);

        if ($this->plugin->get_active_playlist_key() === $id && $this->plugin->reload_channels($plugin_cookies) === 0) {
            return Action_Factory::invalidate_all_folders($plugin_cookies,
                Action_Factory::show_title_dialog(TR::t('err_load_playlist'), null, HD::get_last_error()));
        }

        return Action_Factory::change_behaviour($this->get_action_map($parent_media_url, $plugin_cookies), 0,
            $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->sel_ndx));
    }

    /**
     * @param Object $user_input
     * @param Object $plugin_cookies
     * @return array
     */
    protected function do_select_file($user_input, $plugin_cookies)
    {
        hd_debug_print(null, true);

        switch (MediaURL::decode($user_input->selected_data)->choose_file) {
            case self::ACTION_FILE_TEXT_LIST:
                return $this->selected_text_file($user_input);
            case self::ACTION_FILE_PLAYLIST:
                return $this->selected_m3u_file($user_input);
            case self::ACTION_FILE_XMLTV:
                return $this->selected_xmltv_list($user_input, $plugin_cookies);
        }

        return $this->invalidate_current_folder(MediaURL::decode($user_input->parent_media_url), $plugin_cookies, $user_input->sel_ndx);
    }

    protected function selected_text_file($user_input)
    {
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $selected_media_url = MediaURL::decode($user_input->selected_data);
        $order = $this->get_order($parent_media_url->edit_list);

        hd_debug_print("Choosed file: $selected_media_url->filepath", true);
        $lines = file($selected_media_url->filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || (count($lines) === 1 && trim($lines[0]) === '')) {
            return Action_Factory::show_title_dialog(TR::t('edit_list_empty_file'));
        }

        $old_count = $order->size();
        $lines[0] = trim($lines[0], "\x0B\xEF\xBB\xBF");
        foreach ($lines as $line) {
            $line = trim($line);
            hd_debug_print("Load string: '$line'", true);
            $hash = Hashed_Array::hash($line);
            if ($parent_media_url->edit_list === self::SCREEN_EDIT_PLAYLIST) {
                $playlist = new Named_Storage();
                if (preg_match(HTTP_PATTERN, $line, $m)) {
                    hd_debug_print("import link: '$line'", true);
                    try {
                        $tmp_file = get_temp_path(Hashed_Array::hash($line));
                        list($res, $log) = Curl_Wrapper::simple_download_file($line, $tmp_file);
                        if (!$res) {
                            throw new Exception("Ошибка скачивания : $line\n\n" . $log);
                        }

                        if (file_exists($tmp_file)) {
                            $contents = file_get_contents($tmp_file, false, null, 0, 512);
                            if ($contents === false || strpos($contents, TAG_EXTM3U) === false) {
                                unlink($tmp_file);
                                throw new Exception("Bad M3U file: $line");
                            }
                            $playlist->type = PARAM_LINK;
                            $playlist->name = basename($m[2]);
                            $playlist->params[PARAM_URI] = $line;
                            unlink($tmp_file);
                        } else {
                            throw new Exception("Can't download file: $line");
                        }
                    } catch (Exception $ex) {
                        HD::set_last_error("pl_last_error", null);
                        print_backtrace_exception($ex);
                        continue;
                    }
                } else if (preg_match(PROVIDER_PATTERN, $line, $m)) {
                    hd_debug_print("import provider $m[1]:", true);
                    $provider = $this->plugin->create_provider_class($m[1]);
                    if (is_null($provider)) {
                        hd_debug_print("Unknown provider ID: $m[1]");
                        continue;
                    }

                    $playlist = $provider->fill_default_provider_info($m, $hash);
                    if (!$playlist) continue;
                } else {
                    hd_debug_print("can't recognize: $line");
                    continue;
                }

                if ($order->has($hash)) {
                    hd_debug_print("already exist: $playlist", true);
                } else {
                    hd_debug_print("imported playlist: $playlist", true);
                    $order->put($hash, $playlist);
                }
            } else if ($parent_media_url->edit_list === self::SCREEN_EDIT_EPG_LIST) {
                if (preg_match(HTTP_PATTERN, $line, $m)) {
                    $old = $order->get($hash);
                    $item = new Named_Storage();
                    $item->params[PARAM_URI] = $line;
                    $item->name = $m[2];
                    if (is_null($old)) {
                        $order->put($hash, $item);
                        hd_debug_print("import link: '$line'");
                    } else if (!($old instanceof Hashed_Array)) {
                        $old_count--;
                        $order->set($hash, $item);
                        hd_debug_print("replace link: '$line'");
                    }
                } else {
                    hd_debug_print("line skipped: '$line'");
                }
            }
        }

        if ($old_count === $order->size()) {
            return Action_Factory::show_title_dialog(TR::t('edit_list_no_files'));
        }

        $this->set_changes($parent_media_url->save_data);

        $window_title = ($parent_media_url->edit_list === self::SCREEN_EDIT_PLAYLIST)
            ? TR::t('setup_channels_src_edit_playlists')
            : TR::t('setup_edit_xmltv_list');

        return Action_Factory::show_title_dialog(TR::t('edit_list_added__2', $order->size() - $old_count, count($lines)),
            Action_Factory::close_and_run(Action_Factory::open_folder($parent_media_url->get_media_url_str(), $window_title))
        );
    }

    protected function selected_m3u_file($user_input)
    {
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $selected_media_url = MediaURL::decode($user_input->selected_data);
        $order = $this->get_order($parent_media_url->edit_list);

        $hash = Hashed_Array::hash($selected_media_url->filepath);
        if ($order->has($hash)) {
            return Action_Factory::show_title_dialog(TR::t('err_file_exist'));
        }

        $contents = file_get_contents($selected_media_url->filepath, false, null, 0, 512);
        if ($contents === false || strpos($contents, TAG_EXTM3U) === false) {
            hd_debug_print("Problem with import playlist: $selected_media_url->filepath");
            return Action_Factory::show_title_dialog(TR::t('err_bad_m3u_file'));
        }

        $playlist = new Named_Storage();
        $playlist->type = PARAM_FILE;
        $playlist->name = basename($selected_media_url->filepath);
        $playlist->params[PARAM_URI] = $selected_media_url->filepath;
        $order->put($hash, $playlist);
        $this->set_changes($parent_media_url->save_data);
        return $this->do_edit_m3u_type(CONTROL_PLAYLIST_IPTV, $hash);
    }

    protected function selected_xmltv_list($user_input, $plugin_cookies)
    {
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $selected_media_url = MediaURL::decode($user_input->selected_data);
        $order = $this->get_order($parent_media_url->edit_list);
        $hash = Hashed_Array::hash($selected_media_url->filepath);
        if ($order->has($hash)) {
            return Action_Factory::show_title_dialog(TR::t('err_file_exist'));
        }

        $xmltv = new Named_Storage();
        $xmltv->type = PARAM_FILE;
        $xmltv->name = basename($selected_media_url->filepath);
        $xmltv->params[PARAM_URI] = $selected_media_url->filepath;
        $order->put($hash, $xmltv);
        $this->set_changes($parent_media_url->save_data);

        return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->sel_ndx);
    }

    /**
     * @param Object $user_input
     * @return array
     */
    protected function do_select_folder($user_input)
    {
        hd_debug_print(null, true);

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $selected_media_url = MediaURL::decode($user_input->selected_data);
        $files = glob_dir($selected_media_url->filepath, "/\.$parent_media_url->extension$/i");
        if (empty($files)) {
            return Action_Factory::show_title_dialog(TR::t('edit_list_no_files'));
        }

        $edit_list = $parent_media_url->edit_list;
        $order = $this->get_order($edit_list);
        $old_count = $order->size();
        foreach ($files as $file) {
            $hash = Hashed_Array::hash($file);
            if ($order->has($hash)) continue;

            if ($user_input->selected_action === self::ACTION_FILE_PLAYLIST) {
                $contents = file_get_contents($file);
                if ($contents === false || strpos($contents, TAG_EXTM3U) === false) {
                    hd_debug_print("Problem with import playlist: $file");
                    continue;
                }
            }

            $playlist = new Named_Storage();
            $playlist->type = PARAM_FILE;
            $playlist->name = basename($file);
            $playlist->params[PARAM_URI] = $file;
            $order->put($hash, $playlist);
            $this->set_changes($parent_media_url->save_data);
        }

        $window_title = ($parent_media_url->edit_list === self::SCREEN_EDIT_PLAYLIST)
            ? TR::t('setup_channels_src_edit_playlists')
            : TR::t('setup_edit_xmltv_list');

        return Action_Factory::show_title_dialog(TR::t('edit_list_added__1', $order->size() - $old_count),
            Action_Factory::close_and_run(
                Action_Factory::open_folder($parent_media_url->get_media_url_str(), $window_title))
        );
    }

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print("MediaUrl: " . $media_url, true);

        $items = array();
        switch ($media_url->edit_list) {
            case self::SCREEN_EDIT_PROVIDERS:
                /** @var api_default $provider */
                foreach ($this->plugin->get_providers() as $provider) {
                    $items[] = self::add_item($provider->getId(), $provider->getName(), false, $provider->getLogo(), $provider->getProviderUrl());
                }
                break;

            case self::SCREEN_EDIT_PLAYLIST:
                $items = $this->collect_playlists();
                break;

            case self::SCREEN_EDIT_EPG_LIST:
                if (++$plugin_cookies->ticker > 3) {
                    $plugin_cookies->ticker = 1;
                }
                $items = $this->collect_epg_lists($plugin_cookies);
                break;

            default:
        }

        return $items;
    }

    protected static function add_item($id, $title, $starred, $icon_file, $detailed_info)
    {
        return array(
            PluginRegularFolderItem::media_url => MediaURL::encode(array('screen_id' => static::ID, 'id' => $id)),
            PluginRegularFolderItem::caption => $title,
            PluginRegularFolderItem::view_item_params => array(
                ViewItemParams::item_sticker => ($starred ? Control_Factory::create_sticker(get_image_path('star_small.png'), -55, -2) : null),
                ViewItemParams::icon_path => $icon_file,
                ViewItemParams::item_detailed_info => $detailed_info,
                ViewItemParams::item_detailed_icon_path => $icon_file,
            ),
        );
    }

    protected function collect_playlists()
    {
        $items = array();
        /** @var Named_Storage $item */
        foreach ($this->plugin->get_playlists() as $key => $playlist) {
            $starred = ($key === $this->plugin->get_active_playlist_key());
            $title = empty($playlist->name) ? $playlist->params[PARAM_URI] : $playlist->name;
            if (empty($title)) {
                $title = "Unrecognized or bad playlist entry";
            }

            $detailed_info = '';
            if ($playlist->type === PARAM_PROVIDER) {
                $provider = $this->plugin->create_provider_class($playlist->params[PARAM_PROVIDER]);
                if (is_null($provider)) continue;

                $icon_file = $provider->getLogo();
                $title = $playlist->name;
                if ($playlist->name !== $provider->getName()) {
                    $title .= " ({$provider->getName()})";
                }
                $detailed_info = $playlist->name;
            } else if ($playlist->type === PARAM_FILE) {
                if (isset($playlist->params[PARAM_URI])) {
                    $detailed_info = "$playlist->name ({$playlist->params[PARAM_PL_TYPE]})||{$playlist->params[PARAM_URI]}";
                }
                $icon_file = get_image_path("m3u_file.png");
            } else {
                if (isset($playlist->params[PARAM_URI])) {
                    $detailed_info = "$playlist->name ({$playlist->params[PARAM_PL_TYPE]})||{$playlist->params[PARAM_URI]}";
                }
                $icon_file = get_image_path("link.png");
            }

            $items[] = self::add_item($key, $title, $starred, $icon_file, $detailed_info);
        }

        return $items;
    }

    protected function collect_epg_lists($plugin_cookies)
    {
        $items = array();
        $epg_manager = $this->plugin->get_epg_manager();
        if ($epg_manager === null) {
            return $items;
        }

        $all_sources = new Hashed_Array();
        $pl_sources = $this->plugin->get_playlist_xmltv_sources();
        $all_sources->add_items($pl_sources);
        $ext_sources = $this->plugin->get_ext_xmltv_sources();
        $all_sources->add_items($ext_sources);

        $active_sources = $this->plugin->get_setting(PARAM_SELECTED_XMLTV_SOURCES, array());
        foreach ($all_sources as $key => $item) {
            $detailed_info = '';
            $order_key = false;
            $title = empty($item->name) ? $item->params[PARAM_URI] : $item->name;
            if (empty($title)) {
                $title = "Unrecognized or bad xmltv entry";
            } else {
            $order_key = array_search($key, $active_sources);
                $title = $order_key !== false ? ($order_key + 1) .  " - $title" : $title;
            }

            $cached_xmltv_file = $this->plugin->get_cache_dir() . '/' . "$key.xmltv";
            $locked = $epg_manager->is_index_locked($key);
            if ($locked) {
                $title = file_exists($cached_xmltv_file) ? TR::t('edit_list_title_info__1', $title) : TR::t('edit_list_title_info_download__1', $title);
            } else if (file_exists($cached_xmltv_file)) {
                $size = HD::get_file_size($cached_xmltv_file);
                $check_time_file = filemtime($cached_xmltv_file);
                $dl_date = date("d.m H:i", $check_time_file);
                $title = TR::t('edit_list_title_info__2', $title, $dl_date);
                $info = '';
                foreach ($epg_manager->get_indexes_info($key) as $index => $cnt) {
                    $cnt = ($cnt !== -1) ? $cnt : TR::load_string('err_error_no_data');
                    $info .= "$index: $cnt|";
                }

                $etag = $epg_manager->get_curl_wrapper()->get_cached_etag($key);
                $info .= TR::load_string('edit_list_cache_suport__1',
                        empty($etag) ? TR::load_string('no') : TR::load_string('yes'));

                $cache = isset($item->params[PARAM_CACHE]) ? $item->params[PARAM_CACHE] : XMLTV_CACHE_AUTO;
                if ($cache === XMLTV_CACHE_AUTO) {
                    $expired = TR::load_string('setup_epg_cache_type_auto');
                } else {
                    $max_cache_time = $check_time_file + 3600 * 24 * $cache;
                    $expired = date("d.m H:i", $max_cache_time);
                }

                $detailed_info = TR::load_string('edit_list_detail_info__5',
                    $item->params[PARAM_URI],
                    $size,
                    $dl_date,
                    $expired,
                    $info
                );
            }

            if (empty($detailed_info)) {
                if (isset($item->params[PARAM_URI])) {
                $detailed_info = TR::t('edit_list_detail_info__2',
                    $item->params[PARAM_URI],
                    isset($item->params[PARAM_CACHE]) ? $item->params[PARAM_CACHE] : TR::load_string('setup_epg_cache_type_auto'));
                } else {
                    $detailed_info = $item->name;
                }
            }

            if ($locked) {
                $icon_file = get_image_path("refresh$plugin_cookies->ticker.png");
                hd_debug_print("icon: $icon_file");
            } else if ($pl_sources->has($key)) {
                if ($item->type === PARAM_CONF) {
                    $icon_file = get_image_path("config.png");
                } else {
                    $icon_file = get_image_path("m3u_file.png");
                }
            } else if ($item->type === PARAM_FILE) {
                $icon_file = get_image_path("xmltv_file.png");
            } else {
                $icon_file = get_image_path("link.png");
            }
            $items[] = self::add_item($key, $title, $order_key !== false, $icon_file, $detailed_info);
        }

        return $items;
    }

    /**
     * @inheritDoc
     */
    public function get_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $folder_view = parent::get_folder_view($media_url, $plugin_cookies);

        $folder_view[PluginFolderView::data][PluginRegularFolderView::view_params][ViewParams::extra_content_objects] = null;
        if (($media_url->edit_list === static::SCREEN_EDIT_PLAYLIST && $this->plugin->get_playlists()->size() === 0) ||
            ($media_url->edit_list === static::SCREEN_EDIT_EPG_LIST && $this->plugin->get_ext_xmltv_sources()->size() === 0)) {
            $msg = is_limited_apk()
                ? TR::t('edit_list_add_prompt_apk__3', 100, 300, DEF_LABEL_TEXT_COLOR_YELLOW)
                : TR::t('edit_list_add_prompt__3', 100, 300, DEF_LABEL_TEXT_COLOR_YELLOW);
            $folder_view[PluginFolderView::data][PluginRegularFolderView::view_params][ViewParams::extra_content_objects] = $msg;
        }

        return $folder_view;
    }

    /**
     * @inheritDoc
     */
    public function get_timer(MediaURL $media_url, $plugin_cookies)
    {
        return Action_Factory::timer(100);
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
