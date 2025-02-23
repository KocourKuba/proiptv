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

class Starnet_Edit_Playlists_Screen extends Abstract_Preloaded_Regular_Screen implements User_Input_Handler
{
    const ID = 'edit_playlists';

    const SCREEN_EDIT_PLAYLIST = 'playlist';

    const ACTION_FILE_PLAYLIST = 'play_list_file';
    const ACTION_FILE_TEXT_LIST = 'text_list_file';

    const ACTION_CLEAR_APPLY = 'clear_apply';
    const ACTION_REMOVE_ITEM_DLG_APPLY = 'remove_item_apply';
    const ACTION_CHOOSE_FOLDER = 'choose_folder';
    const ACTION_CHOOSE_FILE = 'choose_file';
    const ACTION_ADD_PROVIDER = 'add_provider';
    const ACTION_CONFIRM_CLEAR_DLG_APPLY = 'clear_apply_dlg';
    const ACTION_ASSIGN_SHORTCUT_POPUP = 'assign_shortcut';
    const ACTION_SHORTCUT_SELECTED = 'shortcut_selected';

    const CONTROL_EDIT_TYPE = 'playlist_type';
    const CONTROL_EDIT_DETECT_ID = 'detect_id';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $actions = array();

        if ($this->plugin->get_all_playlists_count() !== 0) {
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
        $sel_idx = $user_input->sel_ndx;

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
                            array(ACTION_RELOAD_SOURCE => self::SCREEN_EDIT_PLAYLIST)
                        )
                    )
                );

            case GUI_EVENT_KEY_ENTER:
                if ($this->plugin->get_active_playlist_key() !== $selected_id) {
                    $this->plugin->set_active_playlist_key($selected_id);
                    $this->force_parent_reload = true;
                }
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

            case ACTION_SETTINGS:
                /** @var Named_Storage $item */
                hd_debug_print("item: " . $selected_id, true);

                $item = $this->plugin->get_playlist($selected_id);
                if ($item === null) {
                    hd_debug_print("Unknown playlist", true);
                    return null;
                }

                hd_debug_print("item: " . json_encode($item), true);

                if (($item[PARAM_TYPE] === PARAM_LINK || empty($item[PARAM_TYPE]))
                    && isset($item[PARAM_URI]) && is_proto_http($item[PARAM_URI])) {
                    return $this->do_edit_url_dlg($selected_id);
                }

                if ($item[PARAM_TYPE] === PARAM_FILE) {
                    $playlist_type = safe_get_value($item, PARAM_PL_TYPE, CONTROL_PLAYLIST_IPTV);
                    return $this->do_edit_m3u_type($playlist_type, $selected_id);
                }

                if ($item[PARAM_TYPE] === PARAM_PROVIDER) {
                    return $this->plugin->do_edit_provider_dlg($this, $item[PARAM_PARAMS][PARAM_PROVIDER], $selected_id);
                }
                return null;

            case ACTION_ITEM_TOGGLE_MOVE:
                $plugin_cookies->toggle_move = !$plugin_cookies->toggle_move;
                $actions = $this->get_action_map($parent_media_url, $plugin_cookies);
                return Action_Factory::change_behaviour($actions);

            case ACTION_ITEM_UP:
                $sel_idx--;
                if ($sel_idx < 0) {
                    return null;
                }

                $this->plugin->arrange_playlist_order_rows($selected_id, Ordered_Array::UP);
                break;

            case ACTION_ITEM_DOWN:
                $max_sel = $this->plugin->get_all_playlists_count() - 1;
                $sel_idx++;
                if ($sel_idx > $max_sel) {
                    return null;
                }
                $this->plugin->arrange_playlist_order_rows($selected_id, Ordered_Array::DOWN);
                break;

            case ACTION_ITEM_TOP:
                if ($sel_idx === 0) {
                    return null;
                }

                $sel_idx = 0;
                $this->plugin->arrange_playlist_order_rows($selected_id, Ordered_Array::TOP);
                break;

            case ACTION_ITEM_BOTTOM:
                $max_sel = $this->plugin->get_all_playlists_count() - 1;
                if ($sel_idx === $max_sel) {
                    return null;
                }
                $sel_idx = $max_sel;
                $this->plugin->arrange_playlist_order_rows($selected_id, Ordered_Array::BOTTOM);
                break;

            case ACTION_ITEM_DELETE:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::ACTION_REMOVE_ITEM_DLG_APPLY);

            case self::ACTION_REMOVE_ITEM_DLG_APPLY:
                hd_debug_print(null, true);

                hd_debug_print("edit_list: $parent_media_url->edit_list", true);
                $this->force_parent_reload = $this->plugin->get_active_playlist_key() === $selected_id;
                hd_debug_print("remove playlist settings: $selected_id", true);
                $this->plugin->remove_playlist_data($selected_id, true);

                if (!$this->force_parent_reload) break;

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
                break;

            case GUI_EVENT_KEY_POPUP_MENU:
                if (isset($user_input->{self::ACTION_ASSIGN_SHORTCUT_POPUP})) {
                    return $this->create_shortcuts_popup($user_input);
                }
                return $this->create_popup_menu($user_input);

            case ACTION_ITEMS_CLEAR:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_clear_all_msg'),
                    $this, self::ACTION_CONFIRM_CLEAR_DLG_APPLY);

            case self::ACTION_CONFIRM_CLEAR_DLG_APPLY:
                foreach ($this->plugin->get_all_playlists()->get_keys() as $key) {
                    $this->plugin->remove_playlist_data($key, true);
                }

                if ($this->plugin->get_all_playlists_count() !== 0) break;

                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

            case ACTION_ADD_URL_DLG:
                return $this->do_edit_url_dlg();

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

            case self::ACTION_ADD_PROVIDER:
                $params = array(
                    'screen_id' => Starnet_Edit_Providers_List_Screen::ID,
                    'source_window_id' => self::ID,
                    'source_media_url_str' => self::ID,
                    'windowCounter' => 1,
                    'end_action' => ACTION_EDIT_PROVIDER_DLG,
                    'cancel_action' => RESET_CONTROLS_ACTION_ID
                );
                return Action_Factory::open_folder(MediaURL::encode($params), TR::t('edit_list_add_provider'));

            case ACTION_EDIT_PROVIDER_DLG:
                $playlist_id = empty($user_input->{COLUMN_PLAYLIST_ID}) ? '' : $user_input->{COLUMN_PLAYLIST_ID};
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

                $sel_idx = $this->plugin->get_all_playlists()->get_idx($id);
                break;

            case ACTION_FOLDER_SELECTED:
                return $this->do_select_folder($user_input);

            case self::ACTION_ASSIGN_SHORTCUT_POPUP:
                hd_debug_print("Start event popup menu for assign shortcut", true);
                return User_Input_Handler_Registry::create_action($this,
                    GUI_EVENT_KEY_POPUP_MENU,
                    null,
                    array(self::ACTION_ASSIGN_SHORTCUT_POPUP => true)
                );

            case self::ACTION_SHORTCUT_SELECTED:
                if (!isset($user_input->{LIST_IDX})) break;

                $this->force_parent_reload = true;
                $this->plugin->set_playlist_shortcut($selected_id, $user_input->{LIST_IDX});
                break;
        }

        return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $sel_idx);
    }

    /////////////////////////////////////////////////////////////////////////////////////////////
    /// protected methods

    /**
     * @param object $user_input
     * @return array|null
     */
    protected function create_popup_menu($user_input)
    {
        hd_debug_print(null, true);

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);

        // Add provider
        $menu_items[] = $this->plugin->create_menu_item($this,
            self::ACTION_ADD_PROVIDER,
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

        if ($this->plugin->get_all_playlists_count() !== 0) {
            $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEM_TOGGLE_MOVE, TR::t('tv_screen_toggle_move'), "move.png");
        }

        $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
        $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEM_DELETE, TR::t('delete'), "remove.png");
        $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_CLEAR, TR::t('clear'), "brush.png");

        if (!is_limited_apk()) {
            $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
            $menu_items[] = $this->plugin->create_menu_item($this, self::ACTION_ASSIGN_SHORTCUT_POPUP, TR::t('tv_screen_assign_shortcut'));
        }

        return Action_Factory::show_popup_menu($menu_items);
    }

    /**
     * @param object $user_input
     * @return array|null
     */
    protected function create_shortcuts_popup($user_input)
    {
        hd_debug_print(null, true);

        $selected_media_url = MediaURL::decode($user_input->selected_media_url);

        $selected = '';
        $used = array();
        foreach ($this->plugin->get_playlists_shortcuts() as $row) {
            $used[] = $row[PARAM_SHORTCUT];
            if ($row[COLUMN_PLAYLIST_ID] === $selected_media_url->id) {
                $selected = $row[PARAM_SHORTCUT];
            }
        }

        $menu_items = array();
        $keys = array(GUI_EVENT_KEY_1, GUI_EVENT_KEY_2, GUI_EVENT_KEY_3, GUI_EVENT_KEY_4, GUI_EVENT_KEY_5,
            GUI_EVENT_KEY_6, GUI_EVENT_KEY_7, GUI_EVENT_KEY_8, GUI_EVENT_KEY_9, GUI_EVENT_KEY_0);
        $keys = array_diff($keys, $used);
        $menu_items[] = $this->plugin->create_menu_item($this,
            self::ACTION_SHORTCUT_SELECTED,
            TR::t('no'),
            empty($selected) ? "check.png" : null,
            array(LIST_IDX => '')
        );
        foreach ($keys as $key) {
            $menu_items[] = $this->plugin->create_menu_item($this,
                self::ACTION_SHORTCUT_SELECTED,
                $key,
                $selected === $key ? "check.png" : null,
                array(LIST_IDX => $key)
            );
        }

        return Action_Factory::show_popup_menu($menu_items);
    }

    /**
     * @param string $id
     * @return array|null
     */
    protected function do_edit_url_dlg($id = '')
    {
        hd_debug_print(null, true);
        $defs = array();

        $window_title = TR::t('edit_list_add_url');
        $name = '';
        $url = 'http://';
        $param = null;
        $opts_idx = CONTROL_PLAYLIST_IPTV;

        if (!empty($id)) {
            $item = $this->plugin->get_playlist($id);
            $params = safe_get_value($item, PARAM_PARAMS);
            if (is_null($item)) {
                return $defs;
            }

            $window_title = TR::t('edit_list_edit_item');
            $name = safe_get_value($item, PARAM_NAME);
            $url = safe_get_value($params, PARAM_URI, '');
            $opts_idx = safe_get_value($params, PARAM_PL_TYPE, CONTROL_PLAYLIST_IPTV);
            $param = array(CONTROL_ACTION_EDIT => CONTROL_EDIT_ITEM);
        }

        Control_Factory::add_vgap($defs, 20);

        Control_Factory::add_text_field($defs, $this, null, CONTROL_EDIT_NAME, TR::t('name'),
            $name, false, false, false, true, self::DLG_CONTROLS_WIDTH);

        $opts[CONTROL_PLAYLIST_IPTV] = TR::t('edit_list_playlist_iptv');
        $opts[CONTROL_PLAYLIST_VOD] = TR::t('edit_list_playlist_vod');
        Control_Factory::add_combobox($defs, $this, null, self::CONTROL_EDIT_TYPE,
            TR::t('edit_list_playlist_type'), $opts_idx, $opts, self::DLG_CONTROLS_WIDTH);

        Control_Factory::add_text_field($defs, $this, null, CONTROL_URL_PATH, TR::t('url'),
            $url, false, false, false, true, self::DLG_CONTROLS_WIDTH);

        Control_Factory::add_vgap($defs, 50);
        Control_Factory::add_close_dialog_and_apply_button($defs, $this, $param, ACTION_URL_DLG_APPLY, TR::t('ok'), 300);
        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
        Control_Factory::add_vgap($defs, 10);

        return Action_Factory::show_dialog($window_title, $defs, true);
    }

    /**
     * @param object $user_input
     * @param object $plugin_cookies
     * @return array|null
     */
    protected function apply_edit_url_dlg($user_input, $plugin_cookies)
    {
        hd_debug_print(null, true);

        $name = safe_get_member($user_input, CONTROL_EDIT_NAME, '');
        $url = safe_get_member($user_input, CONTROL_URL_PATH, '');
        if (!is_proto_http($url)) {
            return Action_Factory::show_title_dialog(TR::t('err_incorrect_url'));
        }

        if (empty($name)) {
            if (($pos = strpos($name, '?')) !== false) {
                $name = substr($name, 0, $pos);
            }
            $name = basename($name);
        }

        $id = null;
        $item = array();
        if (isset($user_input->{CONTROL_ACTION_EDIT}, $user_input->selected_media_url)) {
            // edit existing url
            $id = MediaURL::decode($user_input->selected_media_url)->id;
            $item = $this->plugin->get_playlist($id);
        }

        if (empty($item)) {
            $id = Hashed_Array::hash($url);
            $order = $this->plugin->get_all_playlists();
            while ($order->has($id)) {
                $id = Hashed_Array::hash("$id.$url");
            }
        }

        $item[PARAM_NAME] = $name;
        $item[PARAM_TYPE] = PARAM_LINK;
        $item[PARAM_URI] = $url;
        try {
            $tmp_file = get_temp_path(Hashed_Array::hash($url));
            list($res, $log) = Curl_Wrapper::simple_download_file($url, $tmp_file);
            if (!$res) {
                throw new Exception(TR::load('err_load_playlist') . " '$url'\n\n" . $log);
            }

            $contents = file_get_contents($tmp_file, false, null, 0, 512);
            if ($contents === false || strpos($contents, TAG_EXTM3U) === false) {
                unlink($tmp_file);
                throw new Exception(TR::load('err_empty_playlist') . " '$url'\n\n$contents");
            }

            $parser = new M3uParser();
            $parser->setPlaylist($tmp_file,true);
            $pl_header = $parser->parseHeader(false);
            $type = safe_get_member($user_input, self::CONTROL_EDIT_TYPE, CONTROL_PLAYLIST_IPTV);
            if ($type === CONTROL_PLAYLIST_IPTV && $item[PARAM_PL_TYPE] === CONTROL_PLAYLIST_IPTV) {
                $db = new Sql_Wrapper(":memory:");
                $db->exec("ATTACH DATABASE ':memory:' AS " . M3uParser::IPTV_DB);
                if ($parser->parseIptvPlaylist($db)) {
                    $table_name = M3uParser::CHANNELS_TABLE;
                    $result = $db->query_value("SELECT COUNT(*) FROM $table_name;");
                }

                if (empty($result)) {
                    throw new Exception(TR::load('err_empty_playlist') . " '$url'\n\n$contents");
                }

                $pl_header = $parser->getM3uInfo();
                $detect = SwitchOnOff::to_bool(safe_get_member($user_input, self::CONTROL_EDIT_DETECT_ID, SwitchOnOff::on));
                if ($detect) {
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

            $item[PARAM_PL_TYPE] = $type;
            unlink($tmp_file);
            hd_debug_print("Playlist: '$url' imported successfully");
            $this->plugin->clear_playlist_cache($id);
            $reload = ($this->plugin->get_active_playlist_key() === $id && !$this->plugin->reload_channels($plugin_cookies));
            $this->plugin->set_playlist($id, $item);
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

            $parent_media_url = MediaURL::decode($user_input->parent_media_url);
            return Action_Factory::change_behaviour($this->get_action_map($parent_media_url, $plugin_cookies), 0,
                $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $user_input->sel_ndx));

        } catch (Exception $ex) {
            hd_debug_print("Problem with download playlist");
            print_backtrace_exception($ex);
            return Action_Factory::show_title_dialog(TR::t('err_load_playlist'), null, $ex->getMessage());
        }
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
        $detect = SwitchOnOff::to_def(is_null($item) || !isset($item[PARAM_PARAMS][PARAM_ID_MAPPER]));
        Control_Factory::add_combobox($defs, $this, null, self::CONTROL_EDIT_DETECT_ID,
            TR::t('edit_list_playlist_detect_id'), $detect, SwitchOnOff::$translated, self::DLG_CONTROLS_WIDTH);

        Control_Factory::add_vgap($defs, 50);

        Control_Factory::add_close_dialog_and_apply_button($defs,
            $this,
            array(CONTROL_ACTION_EDIT => CONTROL_EDIT_ITEM, CONTROL_EDIT_ITEM => $id),
            ACTION_PL_TYPE_DLG_APPLY, TR::t('ok'), 300);

        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
        Control_Factory::add_vgap($defs, 10);

        return Action_Factory::show_dialog(TR::t('edit_list_playlist_type'), $defs, true);
    }

    /**
     * @param object $user_input
     * @param object $plugin_cookies
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

        $item[PARAM_PL_TYPE] = safe_get_member($user_input, self::CONTROL_EDIT_TYPE, CONTROL_PLAYLIST_IPTV);
        $parser = new M3uParser();
        $parser->setPlaylist($item[PARAM_URI], true);
        if ($item[PARAM_PL_TYPE] === CONTROL_PLAYLIST_VOD) {
            $pl_header = $parser->parseHeader(false);
        } else {
            $db = new Sql_Wrapper(":memory:");
            $db->exec("ATTACH DATABASE ':memory:' AS " . M3uParser::IPTV_DB);
            if ($parser->parseIptvPlaylist($db)) {
                $table_name = M3uParser::CHANNELS_TABLE;
                $result = $db->query_value("SELECT COUNT(*) FROM $table_name;");
            }

            if (empty($result)) {
                return Action_Factory::show_title_dialog(TR::t('err_empty_playlist'));
            }

            $pl_header = $parser->getM3uInfo();
            $detect = SwitchOnOff::to_bool(safe_get_member($user_input, self::CONTROL_EDIT_DETECT_ID, SwitchOnOff::on));
            if ($detect) {
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

        $old_count = $this->plugin->get_all_playlists_count();
        $new_count = $old_count;
        $lines[0] = trim($lines[0], "\x0B\xEF\xBB\xBF");
        foreach ($lines as $line) {
            $line = trim($line);
            hd_debug_print("Load string: '$line'", true);
            $hash = Hashed_Array::hash($line);
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
                        $playlist[PARAM_URI] = $line;
                        $playlist[PARAM_PL_TYPE] = CONTROL_PLAYLIST_IPTV;
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
        }

        if ($old_count === $new_count) {
            return Action_Factory::show_title_dialog(TR::t('edit_list_no_files'));
        }

        return Action_Factory::show_title_dialog(TR::t('edit_list_added__2', $new_count - $old_count, count($lines)),
            Action_Factory::close_and_run(
                Action_Factory::open_folder(
                    $parent_media_url->get_media_url_str(), TR::t('setup_channels_src_edit_playlists')
                )
            )
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
        $playlist[PARAM_URI] = $selected_media_url->filepath;
        $playlist[PARAM_PL_TYPE] = CONTROL_PLAYLIST_IPTV;
        $playlist[PARAM_PARAMS] = array();
        $this->plugin->set_playlist($hash, $playlist);
        return $this->do_edit_m3u_type(CONTROL_PLAYLIST_IPTV, $hash);
    }

    /**
     * @param object $user_input
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
            $playlist[PARAM_URI] = $file;
            $playlist[PARAM_PL_TYPE] = CONTROL_PLAYLIST_IPTV;
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

        $sticker = Control_Factory::create_sticker(get_image_path('star_small.png'), -55, -2);

        $items = array();
        foreach ($this->plugin->get_all_playlists() as $key => $playlist) {
            $starred = ($key === $this->plugin->get_active_playlist_key());
            $title = safe_get_value($playlist, PARAM_NAME, '');
            if (empty($title)) {
                $title = "Unnamed";
            }

            $detailed_info = '';
            if ($playlist[PARAM_TYPE] === PARAM_PROVIDER) {
                $provider = $this->plugin->create_provider_class($playlist[PARAM_PARAMS][PARAM_PROVIDER]);
                if (is_null($provider)) continue;

                $icon_file = $provider->getLogo();
                if ($title !== $provider->getName()) {
                    $title .= " ({$provider->getName()})";
                }
                $detailed_info = $playlist[PARAM_NAME];
            } else {
                if (isset($playlist[PARAM_URI])) {
                    $detailed_info = "{$playlist[PARAM_NAME]} ({$playlist[PARAM_PL_TYPE]})||{$playlist[PARAM_URI]}";
                }
                $icon = $playlist[PARAM_TYPE] === PARAM_LINK ? "link.png" : "m3u_file.png";
                $icon_file = get_image_path($icon);
            }

            $shortcut = safe_get_value($playlist, PARAM_SHORTCUT, '');
            if (!empty($shortcut)) {
                $title = "$title - ($shortcut)";
            }

            $items[] = array(
                PluginRegularFolderItem::media_url => MediaURL::encode(array('screen_id' => static::ID, 'id' => $key)),
                PluginRegularFolderItem::caption => $title,
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::item_sticker => ($starred ? $sticker : null),
                    ViewItemParams::icon_path => $icon_file,
                    ViewItemParams::item_detailed_info => $detailed_info,
                    ViewItemParams::item_detailed_icon_path => $icon_file,
                ),
            );
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
        if ($this->plugin->get_all_playlists_count() === 0) {
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
