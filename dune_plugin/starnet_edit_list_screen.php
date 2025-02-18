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

    const ACTION_FILE_PLAYLIST = 'play_list_file';
    const ACTION_FILE_TEXT_LIST = 'text_list_file';

    const ACTION_CLEAR_APPLY = 'clear_apply';
    const ACTION_REMOVE_ITEM_DLG_APPLY = 'remove_item_apply';
    const ACTION_CHOOSE_FOLDER = 'choose_folder';
    const ACTION_CHOOSE_FILE = 'choose_file';
    const ACTION_ADD_PROVIDER_POPUP = 'add_provider';
    const ACTION_CONFIRM_CLEAR_DLG_APPLY = 'clear_apply_dlg';
    const ACTION_XMLTV_CACHE_POPUP = 'xmltv_cache_time';
    const ACTION_XMLTV_CACHE_TIME_SELECTED = 'xmltv_cache_selected';

    const CONTROL_EDIT_TYPE = 'playlist_type';
    const CONTROL_EDIT_DETECT_ID = 'detect_id';

    const ITEM_SET_NAME = 'set_name';
    const ITEM_EDIT = 'edit';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $actions = array();
        $edit_list = $media_url->edit_list;

        if ($edit_list === self::SCREEN_EDIT_PLAYLIST && $this->plugin->get_all_playlists_count() !== 0) {
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

        $action_return = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_RETURN] = $action_return;
        $actions[GUI_EVENT_KEY_TOP_MENU] = $action_return;
        $actions[GUI_EVENT_KEY_ENTER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER);
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

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_RETURN:
                if (!$this->force_parent_reload) {
                    return Action_Factory::close_and_run();
                }

                $this->force_parent_reload = false;
                return Action_Factory::invalidate_folders(
                    array($parent_media_url->source_media_url_str),
                    Action_Factory::close_and_run(
                        User_Input_Handler_Registry::create_action_screen(
                            $parent_media_url->source_window_id,
                            $parent_media_url->end_action,
                            null,
                            array('reload_action' => $edit_list)
                        )
                    )
                );

            case GUI_EVENT_KEY_ENTER:
                if ($edit_list === self::SCREEN_EDIT_PLAYLIST) {
                    if ($this->plugin->get_active_playlist_key() !== $selected_id) {
                        $this->plugin->set_active_playlist_key($selected_id);
                        $this->force_parent_reload = true;
                    }
                    return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
                }

                $selected_sources = $this->plugin->get_selected_xmltv_sources();
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
                $this->plugin->set_selected_xmltv_sources($selected_sources);
                return $this->invalidate_current_folder($parent_media_url, $plugin_cookies);

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
                /** @var Named_Storage $item */
                hd_debug_print("item: " . $selected_id, true);

                if ($edit_list === self::SCREEN_EDIT_PLAYLIST) {
                    $item = $this->plugin->get_playlist($selected_id);
                } else {
                    $item = $this->plugin->get_ext_xmltv_source($selected_id);
                }

                if ($item === null) {
                    hd_debug_print("Unknown playlist", true);
                    return null;
                }

                hd_debug_print("playlist: " . $item, true);

                if (($item[PARAM_TYPE] === PARAM_LINK || empty($item[PARAM_TYPE]))
                    && isset($item[PARAM_PARAMS][PARAM_URI])
                    && is_proto_http($item[PARAM_PARAMS][PARAM_URI])) {
                    return $this->do_edit_url_dlg($edit_list, $selected_id);
                }

                if ($edit_list === self::SCREEN_EDIT_PLAYLIST
                    && $item[PARAM_TYPE] === PARAM_FILE
                    && isset($item[PARAM_PARAMS][PARAM_URI])) {
                    $playlist_type = safe_get_value($item[PARAM_PARAMS], PARAM_PL_TYPE, CONTROL_PLAYLIST_IPTV);
                    return $this->do_edit_m3u_type($playlist_type, $selected_id);
                }

                if ($item[PARAM_TYPE] === PARAM_PROVIDER) {
                    return $this->plugin->do_edit_provider_dlg($this, $item[PARAM_PARAMS][PARAM_PROVIDER], $selected_id);
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
                if (!$this->plugin->arrange_playlist_order_rows($selected_id, Ordered_Array::UP)) {
                    return null;
                }

                $user_input->sel_ndx--;
                if ($user_input->sel_ndx < 0) {
                    $user_input->sel_ndx = 0;
                }

                break;

            case ACTION_ITEM_DOWN:
                if (!$this->plugin->arrange_playlist_order_rows($selected_id, Ordered_Array::DOWN)) {
                    return null;
                }

                $max_sel = $this->plugin->get_all_playlists_count() - 1;
                $user_input->sel_ndx++;
                if ($user_input->sel_ndx > $max_sel) {
                    $user_input->sel_ndx = $max_sel;
                }
                break;

            case ACTION_ITEM_TOP:
                if (!$this->plugin->arrange_playlist_order_rows($selected_id, Ordered_Array::TOP)) {
                    return null;
                }

                $user_input->sel_ndx = 0;
                break;

            case ACTION_ITEM_BOTTOM:
                if (!$this->plugin->arrange_playlist_order_rows($selected_id, Ordered_Array::BOTTOM)) {
                    return null;
                }

                $user_input->sel_ndx = $this->plugin->get_all_playlists_count() - 1;
                break;

            case ACTION_ITEM_DELETE:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::ACTION_REMOVE_ITEM_DLG_APPLY);

            case self::ACTION_REMOVE_ITEM_DLG_APPLY:
                hd_debug_print(null, true);

                hd_debug_print("edit_list: $parent_media_url->edit_list", true);
                if ($parent_media_url->edit_list === self::SCREEN_EDIT_EPG_LIST) {
                    if ($this->plugin->get_ext_xmltv_source($selected_id) === null) {
                        hd_debug_print("remove xmltv source: $selected_id", true);
                        return Action_Factory::show_error(false, TR::t('edit_list_title_cant_delete'));
                    }

                    $this->plugin->safe_clear_selected_epg_cache($selected_id);
                    $selected_sources = $this->plugin->get_selected_xmltv_sources();
                    $offset = array_search($selected_id, $selected_sources);
                    if ($offset !== false) {
                        $selected_sources = array_splice($selected_sources, $offset, 1);
                        $this->plugin->set_selected_xmltv_sources($selected_sources);
                        $this->force_parent_reload = true;
                    }
                }

                if ($parent_media_url->edit_list === self::SCREEN_EDIT_PLAYLIST) {
                    $this->force_parent_reload = $this->plugin->get_active_playlist_key() === $selected_id;
                    hd_debug_print("remove playlist settings: $selected_id", true);
                    $this->plugin->remove_playlist_data($selected_id, true);

                    if ($this->force_parent_reload) {
                        $this->plugin->get_active_playlist_key();
                        if (!$this->plugin->reload_channels($plugin_cookies)) {
                            return Action_Factory::invalidate_all_folders(
                                $plugin_cookies,
                                null,
                                Action_Factory::show_title_dialog(TR::t('err_load_playlist'),
                                    null,
                                    HD::get_last_error($this->plugin->get_pl_error_name())
                                )
                            );
                        }
                    }
                }
                break;

            case GUI_EVENT_KEY_POPUP_MENU:
                return $this->create_popup_menu($user_input);

            case ACTION_ITEMS_CLEAR:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_clear_all_msg'),
                    $this, self::ACTION_CONFIRM_CLEAR_DLG_APPLY);

            case self::ACTION_CONFIRM_CLEAR_DLG_APPLY:
                if ($edit_list === self::SCREEN_EDIT_EPG_LIST) {
                    if ($this->plugin->get_epg_manager() !== null) {
                        foreach ($this->plugin->get_ext_xmltv_sources() as $hash => $source) {
                            $this->plugin->safe_clear_selected_epg_cache($hash);
                        }
                    }
                    $this->plugin->set_selected_xmltv_sources(array());
                }

                if ($edit_list === self::SCREEN_EDIT_PLAYLIST) {
                    foreach ($this->plugin->get_all_playlists()->get_keys() as $key) {
                        $this->plugin->remove_playlist_data($key, true);
                    }
                }

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
                hd_debug_print(null, true);
                switch (MediaURL::decode($user_input->selected_data)->choose_file) {
                    case self::ACTION_FILE_TEXT_LIST:
                        return $this->selected_text_file($user_input);
                    case self::ACTION_FILE_PLAYLIST:
                        return $this->selected_m3u_file($user_input);
                }

                break;

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
                $params = array(
                    'screen_id' => Starnet_Edit_List_Screen::ID,
                    'source_window_id' => self::ID,
                    'source_media_url_str' => self::ID,
                    'windowCounter' => 1,
                    'end_action' => ACTION_EDIT_PROVIDER_DLG,
                    'cancel_action' => RESET_CONTROLS_ACTION_ID
                );
                return Action_Factory::open_folder(MediaURL::encode($params), TR::t('edit_list_add_provider'));

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

                $this->force_parent_reload = $this->plugin->get_active_playlist_key() === $id;
                if (!$this->plugin->reload_channels($plugin_cookies)) {
                    return Action_Factory::invalidate_all_folders(
                        $plugin_cookies,
                        null,
                        Action_Factory::show_title_dialog(TR::t('err_load_playlist'),
                            null,
                            HD::get_last_error($this->plugin->get_pl_error_name())
                        )
                    );
                }

                $user_input->sel_ndx = $this->plugin->get_all_playlists()->get_idx($id);
                break;

            case ACTION_FOLDER_SELECTED:
                return $this->do_select_folder($user_input);

            case self::ACTION_XMLTV_CACHE_POPUP:
                return $this->create_cache_time_popup($user_input);

            case self::ACTION_XMLTV_CACHE_TIME_SELECTED:
                if (!isset($user_input->{LIST_IDX})) break;

                // if source exist in playlist and user list - user list is preferred
                hd_debug_print("update xmltv cache: $user_input->source_type ($selected_id) => " . $user_input->{LIST_IDX}, true);
                $this->plugin->update_xmltv_source_cache($user_input->source_type, $selected_id, $user_input->{LIST_IDX});
                break;
        }

        return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->sel_ndx);
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

        if ($edit_list === self::SCREEN_EDIT_PLAYLIST && $this->plugin->get_all_playlists_count() !== 0) {
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
        $souce_type = XMLTV_SOURCE_EXTERNAL;
        $source = $this->plugin->get_ext_xmltv_source($selected_media_url->id);
        if ($source === null) {
            $souce_type = XMLTV_SOURCE_PLAYLIST;
            $source = $this->plugin->get_playlist_xmltv_sources()->get($selected_media_url->id);
        }

        if ($source === null) {
            return null;
        }

        $selected = safe_get_value($source, PARAM_CACHE, XMLTV_CACHE_AUTO);

        $menu_items = array();
        $menu_items[] = $this->plugin->create_menu_item($this,
            self::ACTION_XMLTV_CACHE_TIME_SELECTED,
            TR::t('auto'),
            $selected === XMLTV_CACHE_AUTO ? "check.png" : null,
            array(LIST_IDX => XMLTV_CACHE_AUTO, 'source_type' => $souce_type)
        );

        $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);

        $list = array(0.25, 0.5, 1, 2, 3, 4, 5, 6, 7);
        foreach ($list as $item) {
            $menu_items[] = $this->plugin->create_menu_item($this,
                self::ACTION_XMLTV_CACHE_TIME_SELECTED,
                $item,
                (int)$selected === $item ? "check.png" : null,
                array(LIST_IDX => XMLTV_CACHE_AUTO, 'source_type' => $souce_type)
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
            if ($edit_list === self::SCREEN_EDIT_PLAYLIST) {
                $item = $this->plugin->get_playlist($id);
            } else {
                $item = $this->plugin->get_ext_xmltv_source($id);
            }

            if (is_null($item)) {
                return $defs;
            }

            $window_title = TR::t('edit_list_edit_item');
            $name = $item->name;
            $url = $item[PARAM_PARAMS][PARAM_URI];
            $opts_idx = safe_get_value($item[PARAM_PARAMS], PARAM_PL_TYPE, CONTROL_PLAYLIST_IPTV);
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

        $name = safe_get_member($user_input, CONTROL_EDIT_NAME, '');
        $url = safe_get_member($user_input, CONTROL_URL_PATH, '');
        if (!is_proto_http($url)) {
            return Action_Factory::show_title_dialog(TR::t('err_incorrect_url'));
        }

        if (empty($name)) {
            if (($pos = strpos($name, '?')) !== false) {
                $name = substr($name, 0, $pos);
            }
            $name = ($edit_list === self::SCREEN_EDIT_PLAYLIST) ? basename($name) : $url;
        }

        $id = null;
        $item = array();
        if (isset($user_input->{CONTROL_ACTION_EDIT}, $user_input->selected_media_url)) {
            // edit existing url
            $id = MediaURL::decode($user_input->selected_media_url)->id;
            if ($edit_list === self::SCREEN_EDIT_EPG_LIST) {
                $this->plugin->remove_ext_xmltv_source($id);
                $this->plugin->safe_clear_selected_epg_cache($id);
            } else if ($edit_list === self::SCREEN_EDIT_PLAYLIST) {
                $item = $this->plugin->get_playlist($id);
            }
        }

        if (empty($item)) {
            $id = Hashed_Array::hash($url);
            if ($edit_list === self::SCREEN_EDIT_PLAYLIST) {
                $order = $this->plugin->get_all_playlists();
            } else {
                $order = $this->plugin->get_ext_xmltv_sources();
            }
            while ($order->has($id)) {
                $id = Hashed_Array::hash("$id.$url");
            }
        }

        $reload = false;
        if ($edit_list === self::SCREEN_EDIT_PLAYLIST) {
            $item[PARAM_NAME] = $name;
            $item[PARAM_TYPE] = PARAM_LINK;
            $item[PARAM_URI] = $url;
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
                $type = safe_get_member($user_input, self::CONTROL_EDIT_TYPE, CONTROL_PLAYLIST_IPTV);
                if ($type === CONTROL_PLAYLIST_IPTV && $item[PARAM_PARAMS][PARAM_PL_TYPE] === CONTROL_PLAYLIST_IPTV) {
                    $db = new Sql_Wrapper(":memory:");
                    $db->exec("ATTACH DATABASE '::memory:' AS " . M3uParser::IPTV_DB);
                    if ($parser->parseIptvPlaylist($db)) {
                        $table_name = M3uParser::CHANNELS_TABLE;
                        $result = $db->query_value("SELECT count(*) FROM $table_name;");
                    }

                    if (empty($result)) {
                        throw new Exception(TR::load_string('err_empty_playlist') . " '$url'\n\n$contents");
                    }

                    $pl_header = $parser->getM3uInfo();
                    $detect = safe_get_member($user_input, self::CONTROL_EDIT_DETECT_ID, SetupControlSwitchDefs::switch_on);
                    if ($detect === SetupControlSwitchDefs::switch_on) {
                        $item[PARAM_PARAMS][PARAM_ID_MAPPER] = M3uParser::detectBestChannelId($db);
                        hd_debug_print("detected id: " . $item[PARAM_PARAMS][PARAM_ID_MAPPER]);
                    }
                }

                hd_debug_print("Playlist info: " . $pl_header);
                $pl_tag = $pl_header->getEntryTag(TAG_PLAYLIST);
                if ($pl_tag !== null) {
                    $pl_name = $pl_tag->getTagValue();
                    $item[PARAM_NAME] = empty($pl_name) ? $name : $pl_name;
                }

                $item[PARAM_PARAMS][PARAM_PL_TYPE] = $type;
                unlink($tmp_file);
                hd_debug_print("Playlist: '$url' imported successfully");
                $this->plugin->clear_playlist_cache($id);
                $reload = ($this->plugin->get_active_playlist_key() === $id && !$this->plugin->reload_channels($plugin_cookies));
                $this->plugin->set_playlist($id, $item);
            } catch (Exception $ex) {
                hd_debug_print("Problem with download playlist");
                print_backtrace_exception($ex);
                return Action_Factory::show_title_dialog(TR::t('err_load_playlist'), null, $ex->getMessage());
            }
        }

        if ($edit_list === self::SCREEN_EDIT_EPG_LIST) {
            $item = array(
                PARAM_HASH => $id,
                PARAM_TYPE => PARAM_LINK,
                PARAM_NAME => $name,
                PARAM_URI => $url,
                PARAM_CACHE => XMLTV_CACHE_AUTO
            );
            $this->plugin->set_ext_xmltv_source($item);
        }

        if ($reload) {
            return Action_Factory::invalidate_all_folders(
                $plugin_cookies,
                null,
                Action_Factory::show_title_dialog(TR::t('err_load_playlist'),
                    null,
                    HD::get_last_error($this->plugin->get_pl_error_name())
                )
            );
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

        $item = $this->plugin->get_playlist($id);
        $detect = (is_null($item) || !isset($item[PARAM_PARAMS][PARAM_ID_MAPPER])) ? SetupControlSwitchDefs::switch_on : SetupControlSwitchDefs::switch_off;

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

        $item = $this->plugin->get_playlist($user_input->{CONTROL_EDIT_ITEM});
        if (is_null($item)) {
            return null;
        }

        $item[PARAM_PARAMS][PARAM_PL_TYPE] = safe_get_member($user_input, self::CONTROL_EDIT_TYPE, CONTROL_PLAYLIST_IPTV);
        $parser = new M3uParser();
        $parser->setPlaylist($item[PARAM_PARAMS][PARAM_URI], true);
        if ($item[PARAM_PARAMS][PARAM_PL_TYPE] === CONTROL_PLAYLIST_VOD) {
            $pl_header = $parser->parseHeader(false);
        } else {
            $db = new Sql_Wrapper(":memory:");
            $db->exec("ATTACH DATABASE '::memory:' AS " . M3uParser::IPTV_DB);
            if ($parser->parseIptvPlaylist($db)) {
                $table_name = M3uParser::CHANNELS_TABLE;
                $result = $db->query_value("SELECT count(*) FROM $table_name;");
            }

            if (empty($result)) {
                return Action_Factory::show_title_dialog(TR::t('err_empty_playlist'));
            }

            $pl_header = $parser->getM3uInfo();
            $detect = safe_get_member($user_input, self::CONTROL_EDIT_DETECT_ID, SetupControlSwitchDefs::switch_on);
            if ($detect === SetupControlSwitchDefs::switch_on) {
                $item[PARAM_PARAMS][PARAM_ID_MAPPER] = M3uParser::detectBestChannelId($db);
                hd_debug_print("detected id: " . $item[PARAM_PARAMS][PARAM_ID_MAPPER]);
            }
        }

        hd_debug_print("Playlist info: " . $pl_header);
        $pl_tag = $pl_header->getEntryTag(TAG_PLAYLIST);
        if ($pl_tag !== null) {
            $pl_name = $pl_tag->getTagValue();
            $item[PARAM_NAME] = empty($pl_name) ? $item[PARAM_NAME] : $pl_name;
        }
        $this->plugin->set_playlist($user_input->{CONTROL_EDIT_ITEM}, $item);

        if ($this->plugin->get_active_playlist_key() === $user_input->{CONTROL_EDIT_ITEM} && !$this->plugin->reload_channels($plugin_cookies)) {
            return Action_Factory::invalidate_all_folders(
                $plugin_cookies,
                null,
                Action_Factory::show_title_dialog(TR::t('err_load_playlist'),
                    null,
                    HD::get_last_error($this->plugin->get_pl_error_name())
                )
            );
        }

        return Action_Factory::change_behaviour($this->get_action_map($parent_media_url, $plugin_cookies), 0,
            $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->sel_ndx));
    }

    protected function selected_text_file($user_input)
    {
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $selected_media_url = MediaURL::decode($user_input->selected_data);

        hd_debug_print("Choosed file: $selected_media_url->filepath", true);
        $lines = file($selected_media_url->filepath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || (count($lines) === 1 && trim($lines[0]) === '')) {
            return Action_Factory::show_title_dialog(TR::t('edit_list_empty_file'));
        }

        if ($parent_media_url->edit_list === self::SCREEN_EDIT_PLAYLIST) {
            $old_count = $this->plugin->get_all_playlists_count();
        } else if ($parent_media_url->edit_list === self::SCREEN_EDIT_EPG_LIST) {
            $old_count = $this->plugin->get_ext_xmltv_sources_count();
        } else {
            return null;
        }

        $new_count = $old_count;
        $lines[0] = trim($lines[0], "\x0B\xEF\xBB\xBF");
        foreach ($lines as $line) {
            $line = trim($line);
            hd_debug_print("Load string: '$line'", true);
            $hash = Hashed_Array::hash($line);
            if ($parent_media_url->edit_list === self::SCREEN_EDIT_PLAYLIST) {
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
                            $playlist[PARAM_TYPE] = PARAM_LINK;
                            $playlist[PARAM_NAME] = basename($m[2]);
                            $playlist[PARAM_PARAMS][PARAM_URI] = $line;
                            unlink($tmp_file);
                        } else {
                            throw new Exception("Can't download file: $line");
                        }
                    } catch (Exception $ex) {
                        HD::set_last_error($this->plugin->get_pl_error_name(), null);
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
                    if ($playlist === false) continue;
                } else {
                    hd_debug_print("can't recognize: $line");
                    continue;
                }

                if ($this->plugin->get_playlist($hash) !== null) {
                    hd_debug_print("already exist: $hash", true);
                } else {
                    $new_count++;
                    hd_debug_print("imported playlist: " . $playlist, true);
                    $this->plugin->set_playlist($hash, $playlist);
                }
            } else if ($parent_media_url->edit_list === self::SCREEN_EDIT_EPG_LIST) {
                if (preg_match(HTTP_PATTERN, $line, $m)) {
                    if ($this->plugin->get_ext_xmltv_source($hash) !== null) {
                        hd_debug_print("already exist: $hash", true);
                    } else {
                        $new_count++;
                        $item = array(
                            PARAM_HASH => $hash,
                            PARAM_TYPE => PARAM_LINK,
                            PARAM_NAME => $m[2],
                            PARAM_URI => $line,
                            PARAM_CACHE => XMLTV_CACHE_AUTO
                        );
                        $this->plugin->set_ext_xmltv_source($item);
                        hd_debug_print("import link: '$line'");
                    }
                } else {
                    hd_debug_print("line skipped: '$line'");
                }
            }
        }

        if ($old_count === $new_count) {
            return Action_Factory::show_title_dialog(TR::t('edit_list_no_files'));
        }

        $window_title = ($parent_media_url->edit_list === self::SCREEN_EDIT_PLAYLIST)
            ? TR::t('setup_channels_src_edit_playlists')
            : TR::t('setup_edit_xmltv_list');

        return Action_Factory::show_title_dialog(TR::t('edit_list_added__2', $new_count - $old_count, count($lines)),
            Action_Factory::close_and_run(Action_Factory::open_folder($parent_media_url->get_media_url_str(), $window_title))
        );
    }

    protected function selected_m3u_file($user_input)
    {
        $selected_media_url = MediaURL::decode($user_input->selected_data);
        $order = $this->plugin->get_all_playlists();
        $hash = Hashed_Array::hash($selected_media_url->filepath);
        if ($order->has($hash)) {
            return Action_Factory::show_title_dialog(TR::t('err_file_exist'));
        }

        $contents = file_get_contents($selected_media_url->filepath, false, null, 0, 512);
        if ($contents === false || strpos($contents, TAG_EXTM3U) === false) {
            hd_debug_print("Problem with import playlist: $selected_media_url->filepath");
            return Action_Factory::show_title_dialog(TR::t('err_bad_m3u_file'));
        }

        $playlist[PARAM_TYPE] = PARAM_FILE;
        $playlist[PARAM_NAME] = basename($selected_media_url->filepath);
        $playlist[PARAM_PARAMS][PARAM_URI] = $selected_media_url->filepath;
        $this->plugin->set_playlist($hash, $playlist);
        return $this->do_edit_m3u_type(CONTROL_PLAYLIST_IPTV, $hash);
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

        $order = $this->plugin->get_all_playlists();
        $old_count = $order->size();
        foreach ($files as $file) {
            $hash = Hashed_Array::hash($file);
            if ($order->has($hash)) continue;

            $contents = file_get_contents($file);
            if ($contents === false || strpos($contents, TAG_EXTM3U) === false) {
                hd_debug_print("Problem with import playlist: $file");
                continue;
            }

            $playlist[PARAM_TYPE] = PARAM_FILE;
            $playlist[PARAM_NAME] = basename($file);
            $playlist[PARAM_PARAMS][PARAM_URI] = $file;
            $this->plugin->set_playlist($hash, $playlist);
        }

        return Action_Factory::show_title_dialog(TR::t('edit_list_added__1', $order->size() - $old_count),
            Action_Factory::close_and_run(
                Action_Factory::open_folder($parent_media_url->get_media_url_str(), TR::t('setup_channels_src_edit_playlists')))
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

    /**
     * @inheritDoc
     */
    public function get_folder_view(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);

        $folder_view = parent::get_folder_view($media_url, $plugin_cookies);

        $folder_view[PluginFolderView::data][PluginRegularFolderView::view_params][ViewParams::extra_content_objects] = null;
        if (($media_url->edit_list === static::SCREEN_EDIT_PLAYLIST && $this->plugin->get_all_playlists_count() === 0) ||
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

    /////////////////////////////////////////////////////////////////////////////////////////////
    /// protected methods

    protected function collect_playlists()
    {
        $items = array();
        foreach ($this->plugin->get_all_playlists() as $key => $playlist) {
            $starred = ($key === $this->plugin->get_active_playlist_key());
            $title = safe_get_value($playlist, PARAM_NAME, $playlist[PARAM_NAME]);
            if (empty($title)) {
                $title = "Unrecognized or bad playlist entry";
            }

            $detailed_info = '';
            if ($playlist[PARAM_TYPE] === PARAM_PROVIDER) {
                $provider = $this->plugin->create_provider_class($playlist[PARAM_PARAMS][PARAM_PROVIDER]);
                if (is_null($provider)) continue;

                $icon_file = $provider->getLogo();
                $title = $playlist[PARAM_NAME];
                if ($title !== $provider->getName()) {
                    $title .= " ({$provider->getName()})";
                }
                $detailed_info = $playlist[PARAM_NAME];
            } else if ($playlist[PARAM_TYPE] === PARAM_FILE) {
                if (isset($playlist[PARAM_PARAMS][PARAM_URI])) {
                    $detailed_info = "{$playlist[PARAM_NAME]} ({$playlist[PARAM_PARAMS][PARAM_PL_TYPE]})||{$playlist[PARAM_NAME][PARAM_URI]}";
                }
                $icon_file = get_image_path("m3u_file.png");
            } else {
                if (isset($playlist[PARAM_NAME][PARAM_URI])) {
                    $detailed_info = "{$playlist[PARAM_NAME]} ({$playlist[PARAM_NAME][PARAM_PL_TYPE]})||{$playlist[PARAM_NAME][PARAM_URI]}";
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

        $all_sources = $this->plugin->get_all_xmltv_sources();
        $pl_sources = $this->plugin->get_playlist_xmltv_sources();
        $selected_sources = $this->plugin->get_selected_xmltv_sources();
        foreach ($all_sources as $key => $item) {
            $detailed_info = '';
            $order_key = false;
            $title = empty($item[PARAM_NAME]) ? $item[PARAM_PARAMS][PARAM_URI] : $item[PARAM_NAME];
            if (empty($title)) {
                $title = "Unrecognized or bad xmltv entry";
            } else {
                $order_key = array_search($key, $selected_sources);
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

                $cache = safe_get_value($item[PARAM_PARAMS], PARAM_CACHE, XMLTV_CACHE_AUTO);
                if ($cache === XMLTV_CACHE_AUTO) {
                    $expired = TR::load_string('setup_epg_cache_type_auto');
                } else {
                    $max_cache_time = $check_time_file + 3600 * 24 * $cache;
                    $expired = date("d.m H:i", $max_cache_time);
                }

                $detailed_info = TR::load_string('edit_list_detail_info__5',
                    $item[PARAM_PARAMS][PARAM_URI],
                    $size,
                    $dl_date,
                    $expired,
                    $info
                );
            }

            if (empty($detailed_info)) {
                if (isset($item[PARAM_PARAMS][PARAM_URI])) {
                    $detailed_info = TR::t('edit_list_detail_info__2',
                        $item[PARAM_PARAMS][PARAM_URI],
                        safe_get_value($item[PARAM_PARAMS], PARAM_CACHE, TR::load_string('setup_epg_cache_type_auto')));
                } else {
                    $detailed_info = $item->name;
                }
            }

            if ($locked) {
                $icon_file = get_image_path("refresh$plugin_cookies->ticker.png");
                hd_debug_print("icon: $icon_file");
            } else if ($pl_sources->has($key)) {
                if ($item[PARAM_TYPE] === PARAM_CONF) {
                    $icon_file = get_image_path("config.png");
                } else {
                    $icon_file = get_image_path("m3u_file.png");
                }
            } else if ($item[PARAM_TYPE] === PARAM_FILE) {
                $icon_file = get_image_path("xmltv_file.png");
            } else {
                $icon_file = get_image_path("link.png");
            }
            $items[] = self::add_item($key, $title, $order_key !== false, $icon_file, $detailed_info);
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
}
