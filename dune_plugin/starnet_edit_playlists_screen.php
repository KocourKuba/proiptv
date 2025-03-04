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

    const ACTION_REMOVE_ITEM_DLG_APPLY = 'remove_item_apply';
    const ACTION_CHOOSE_FOLDER = 'choose_folder';
    const ACTION_ADD_PROVIDER = 'add_provider';
    const ACTION_CONFIRM_CLEAR_DLG_APPLY = 'clear_apply_dlg';
    const ACTION_ASSIGN_SHORTCUT_POPUP = 'assign_shortcut';
    const ACTION_SHORTCUT_SELECTED = 'shortcut_selected';

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

        $selected_id = isset($user_input->selected_media_url) ? MediaURL::decode($user_input->selected_media_url)->id : 0;

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $sel_idx = $user_input->sel_ndx;

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_RETURN:
                if (!$this->force_parent_reload) {
                    return Action_Factory::close_and_run();
                }

                $this->force_parent_reload = false;
                $this->plugin->load_channels($plugin_cookies);
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
                if ($this->plugin->get_active_playlist_id() !== $selected_id) {
                    $this->plugin->set_active_playlist_id($selected_id);
                    $this->force_parent_reload = true;
                }
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

            case ACTION_SETTINGS:
                if (!$this->plugin->is_playlist_exist($selected_id)) {
                    hd_debug_print("Unknown playlist: $selected_id", true);
                    return null;
                }

                return Action_Factory::open_folder(Starnet_Setup_Playlists_Screen::get_media_url_string($selected_id), TR::t('tv_screen_playlists_setup'));

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
                $this->force_parent_reload = $this->plugin->get_active_playlist_id() === $selected_id;
                hd_debug_print("remove playlist settings: $selected_id", true);
                $this->plugin->remove_playlist_data($selected_id, true);
                break;

            case GUI_EVENT_KEY_POPUP_MENU:
                if (isset($user_input->{self::ACTION_ASSIGN_SHORTCUT_POPUP})) {
                    return $this->create_shortcuts_popup($user_input);
                }
                return $this->create_popup_menu($user_input);

            case ACTION_ITEMS_CLEAR:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_clear_all_msg'),
                    $this, ACTION_CONFIRM_CLEAR_DLG_APPLY);

            case ACTION_CONFIRM_CLEAR_DLG_APPLY:
                foreach ($this->plugin->get_all_playlists_ids() as $key) {
                    $this->plugin->remove_playlist_data($key, true);
                }

                if ($this->plugin->get_all_playlists_count() !== 0) break;

                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

            case ACTION_ADD_URL_DLG:
                return $this->do_add_url_dlg();

            case ACTION_URL_DLG_APPLY:
                return $this->apply_add_url_dlg($user_input);

            case ACTION_PL_TYPE_DLG_APPLY:
                return $this->apply_add_m3u_type($user_input);

            case ACTION_CHOOSE_FILE:
                $media_url_str = MediaURL::encode(
                    array(
                        'screen_id' => Starnet_Folder_Screen::ID,
                        'source_window_id' => static::ID,
                        'choose_file' => $user_input->selected_action,
                        'extension' => PLAYLIST_PATTERN,
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
                $playlist_id = safe_get_member($user_input, COLUMN_PLAYLIST_ID, '');
                if (empty($playlist_id)) {
                    // add new provider
                    $provider = $this->plugin->create_provider_class($user_input->{PARAM_PROVIDER});
                } else {
                    // Edit existing
                    $provider = $this->plugin->get_provider($playlist_id);
                }

                if (is_null($provider)) {
                    return null;
                }

                $defs = array();
                Control_Factory::add_vgap($defs, 20);

                if (empty($name)) {
                    $name = $provider->getName();
                }

                $defs = $provider->GetSetupUI($name, $playlist_id, $this);
                if (empty($defs)) {
                    return null;
                }

                return Action_Factory::show_dialog("{$provider->getName()} ({$provider->getId()})", $defs, true);

            case ACTION_EDIT_PROVIDER_DLG_APPLY:
                hd_debug_print(null, true);

                // edit existing or new provider in starnet_edit_list_screen
                $playlist_id = safe_get_member($user_input, CONTROL_EDIT_NAME, '');
                if (empty($playlist_id)) {
                    // create new provider
                    $provider = $this->plugin->create_provider_class($user_input->{PARAM_PROVIDER});
                } else {
                    // edit existing
                    $provider = $this->plugin->get_provider($playlist_id);
                }

                if (is_null($provider)) {
                    return null;
                }

                $res = $provider->ApplySetupUI($user_input);

                if ($res === null) {
                    return Action_Factory::show_error(false, TR::t('err_incorrect_access_data'));
                }

                if (is_array($res)) {
                    return $res;
                }

                $this->force_parent_reload = $this->plugin->get_active_playlist_id() === $res;
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

                $sel_idx = array_search($res, $this->plugin->get_all_playlists_ids());
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

            case ACTION_INVALIDATE:
                if (isset($user_input->playlist_id)) {
                    $sel_idx = array_search($user_input->playlist_id, $this->plugin->get_all_playlists_ids());
                }
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
            ACTION_CHOOSE_FILE,
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
            ACTION_CHOOSE_FILE,
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
     * @return array|null
     */
    protected function do_add_url_dlg()
    {
        hd_debug_print(null, true);
        $defs = array();

        $window_title = TR::t('edit_list_add_url');
        $mapper_ops = Default_Dune_Plugin::get_id_detect_mapper();

        Control_Factory::add_vgap($defs, 20);

        $name = '';
        Control_Factory::add_label($defs, '', TR::t('name'), -10);
        Control_Factory::add_text_field($defs, $this, null, CONTROL_EDIT_NAME, '',
            $name, false, false, false, true, self::DLG_CONTROLS_WIDTH);

        $opts_idx = CONTROL_PLAYLIST_IPTV;
        $opts[CONTROL_PLAYLIST_IPTV] = TR::t('edit_list_playlist_iptv');
        $opts[CONTROL_PLAYLIST_VOD] = TR::t('edit_list_playlist_vod');
        Control_Factory::add_label($defs, '', TR::t('edit_list_playlist_type'), -10);
        Control_Factory::add_combobox($defs, $this, null, CONTROL_EDIT_TYPE,
            '', $opts_idx, $opts, self::DLG_CONTROLS_WIDTH);

        $url = 'http://';
        Control_Factory::add_label($defs, '', TR::t('url'), -10);
        Control_Factory::add_text_field($defs, $this, null, CONTROL_URL_PATH, '',
            $url, false, false, false, true, self::DLG_CONTROLS_WIDTH);

        $mapper = CONTROL_DETECT_ID;
        Control_Factory::add_label($defs, '', TR::t('edit_list_playlist_detect_id'), -10);
        Control_Factory::add_combobox($defs, $this, null, CONTROL_DETECT_ID,
            '', $mapper, $mapper_ops, self::DLG_CONTROLS_WIDTH, true);

        Control_Factory::add_vgap($defs, 50);
        Control_Factory::add_close_dialog_and_apply_button($defs, $this, ACTION_URL_DLG_APPLY, TR::t('ok'), 300);
        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
        Control_Factory::add_vgap($defs, 10);

        return Action_Factory::show_dialog($window_title, $defs, true);
    }

    /**
     * @param object $user_input
     * @return array|null
     */
    protected function apply_add_url_dlg($user_input)
    {
        hd_debug_print(null, true);

        $uri = safe_get_member($user_input, CONTROL_URL_PATH, '');
        if (!is_proto_http($uri)) {
            return Action_Factory::show_title_dialog(TR::t('err_incorrect_url'));
        }

        $playlist_id = Hashed_Array::hash($uri);
        $order = $this->plugin->get_all_playlists_ids();
        while (in_array($playlist_id, $order)) {
            $playlist_id = Hashed_Array::hash("$playlist_id.$uri");
        }

        $params[PARAM_TYPE] = PARAM_LINK;
        $params[PARAM_URI] = $uri;

        $tmp_file = get_temp_path(Hashed_Array::hash($playlist_id));
        try {
            list($res, $log) = Curl_Wrapper::simple_download_file($uri, $tmp_file);
            if (!$res) {
                throw new Exception(TR::load('err_load_playlist') . " '$uri'\n\n" . $log);
            }

            $contents = file_get_contents($tmp_file, false, null, 0, 512);
            if ($contents === false || strpos($contents, TAG_EXTM3U) === false) {
                unlink($tmp_file);
                throw new Exception(TR::load('err_empty_playlist') . " '$uri'\n\n$contents");
            }

            $parser = new M3uParser();
            $parser->setPlaylist($tmp_file, true);
            $params[PARAM_PL_TYPE] = safe_get_member($user_input, CONTROL_EDIT_TYPE, CONTROL_PLAYLIST_IPTV);
            if ($params[PARAM_PL_TYPE] === CONTROL_PLAYLIST_IPTV) {
                $detect_id = safe_get_member($user_input, CONTROL_DETECT_ID, CONTROL_DETECT_ID);
                if ($detect_id === CONTROL_DETECT_ID) {
                    $db = new Sql_Wrapper(":memory:");
                    $db->exec("ATTACH DATABASE ':memory:' AS " . M3uParser::IPTV_DB);
                    $entries_cnt = $parser->parseIptvPlaylist($db);
                    if (empty($entries_cnt)) {
                        throw new Exception(TR::load('err_empty_playlist') . " '$uri'\n\n$contents");
                    }

                    $detect_info = '';
                    $detect_id = $this->plugin->collect_detect_info($db, $detect_info);
                }
                $params[PARAM_ID_MAPPER] = $detect_id;
            }

            $name = safe_get_member($user_input, CONTROL_EDIT_NAME, '');
            if (empty($name)) {
                $pl_header = $parser->parseHeader(false);
                hd_debug_print("Playlist info: " . $pl_header);
                $pl_tag = $pl_header->getEntryTag(TAG_PLAYLIST);
                if ($pl_tag !== null) {
                    $pl_name = $pl_tag->getTagValue();
                    $name = empty($pl_name) ? $name : $pl_name;
                }

                if (empty($name)) {
                    if (($pos = strpos($uri, '?')) !== false) {
                        $name = substr($uri, 0, $pos - 1);
                    } else if (($pos = strrpos($uri, '/')) !== false) {
                        $name = substr($uri, 0, $pos);
                    } else {
                        $name = $uri;
                    }
                    $name = basename($name);
                }
            }
            $params[PARAM_NAME] = $name;

            unlink($tmp_file);
            hd_debug_print("Playlist: '$uri' edit successfully");

            $this->plugin->clear_playlist_cache($playlist_id);
            $this->plugin->set_playlist_parameters($playlist_id, $params);

            $post_action = User_Input_Handler_Registry::create_action($this,ACTION_INVALIDATE, null, array('playlist_id' => $playlist_id));

            if (!empty($detect_info)) {
                $post_action = Action_Factory::show_title_dialog(TR::t('info'), $post_action, $detect_info);
            }

            $this->force_parent_reload = $this->plugin->get_active_playlist_id() === $playlist_id;
        } catch (Exception $ex) {
            hd_debug_print("Problem with download playlist");
            print_backtrace_exception($ex);
            $post_action = Action_Factory::show_title_dialog(TR::t('err_load_playlist'), null, $ex->getMessage());
        }

        if ($tmp_file !== $uri && file_exists($tmp_file)) {
            unlink($tmp_file);
        }

        return $post_action;
    }

    /**
     * @param string $uri
     * @return array|null
     */
    protected function do_add_m3u_type($uri)
    {
        hd_debug_print(null, true);
        $defs = array();

        Control_Factory::add_vgap($defs, 20);

        $name = basename($uri);
        Control_Factory::add_label($defs, '', TR::t('name'), -10);
        Control_Factory::add_text_field($defs, $this, null, CONTROL_EDIT_NAME, '',
            $name, false, false, false, true, self::DLG_CONTROLS_WIDTH);

        $opts[CONTROL_PLAYLIST_IPTV] = TR::t('edit_list_playlist_iptv');
        $opts[CONTROL_PLAYLIST_VOD] = TR::t('edit_list_playlist_vod');
        Control_Factory::add_label($defs, '', TR::t('edit_list_playlist_type'), -10);
        Control_Factory::add_combobox($defs, $this, null, CONTROL_EDIT_TYPE,
            '', CONTROL_PLAYLIST_IPTV, $opts, self::DLG_CONTROLS_WIDTH);

        $mapper_ops = Default_Dune_Plugin::get_id_detect_mapper();
        Control_Factory::add_label($defs, '', TR::t('edit_list_playlist_detect_id'), -10);
        Control_Factory::add_combobox($defs, $this, null, CONTROL_DETECT_ID,
            '', CONTROL_DETECT_ID, $mapper_ops, self::DLG_CONTROLS_WIDTH, true);

        $param = array(CONTROL_URL_PATH => $uri);
        Control_Factory::add_vgap($defs, 50);
        Control_Factory::add_close_dialog_and_apply_button($defs, $this, ACTION_PL_TYPE_DLG_APPLY, TR::t('ok'), 300, $param);
        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
        Control_Factory::add_vgap($defs, 10);

        return Action_Factory::show_dialog(TR::t('edit_list_playlist_type'), $defs, true);
    }

    /**
     * @param object $user_input
     * @return array|null
     */
    protected function apply_add_m3u_type($user_input)
    {
        hd_debug_print(null, true);

        try {
            $uri = safe_get_member($user_input, CONTROL_URL_PATH);
            if (empty($uri)) {
                throw new Exception(TR::load('err_load_playlist'));
            }
            $playlist_id = Hashed_Array::hash($uri);

            hd_debug_print("Edit new playlist: $uri");

            $parser = new M3uParser();
            $parser->setPlaylist($uri, true);

            $pl_type = safe_get_member($user_input, CONTROL_EDIT_TYPE, CONTROL_PLAYLIST_IPTV);
            $detect_id = safe_get_member($user_input, CONTROL_DETECT_ID, CONTROL_DETECT_ID);
            if ($pl_type === CONTROL_PLAYLIST_IPTV) {
                hd_debug_print("Detect playlist id: $detect_id");
                if ($detect_id === CONTROL_DETECT_ID) {
                    $db = new Sql_Wrapper(":memory:");
                    $db->exec("ATTACH DATABASE ':memory:' AS " . M3uParser::IPTV_DB);
                    $entries_cnt = $parser->parseIptvPlaylist($db);
                    if (empty($entries_cnt)) {
                        throw new Exception(TR::load('err_empty_playlist'));
                    }

                    $detect_info = '';
                    $detect_id = $this->plugin->collect_detect_info($db, $detect_info);
                }
            }

            $name = safe_get_member($user_input, CONTROL_EDIT_NAME);
            if (empty($name)) {
                $pl_header = $parser->parseHeader(false);
                hd_debug_print("Playlist info: " . $pl_header);
                $pl_tag = $pl_header->getEntryTag(TAG_PLAYLIST);
                if ($pl_tag !== null) {
                    $pl_name = $pl_tag->getTagValue();
                    $name = empty($pl_name) ? $name : $pl_name;
                }

                if (empty($name)) {
                    $name = basename($uri);
                }
            }

            $params[PARAM_NAME] = $name;
            $params[PARAM_TYPE] = PARAM_FILE;
            $params[PARAM_URI] = $uri;
            $params[PARAM_PL_TYPE] = $pl_type;
            $params[PARAM_ID_MAPPER] = $detect_id;

            $this->plugin->set_playlist_parameters($playlist_id, $params);
            $post_action = User_Input_Handler_Registry::create_action($this,ACTION_INVALIDATE, null, array('playlist_id' => $playlist_id));

            if (!empty($detect_info)) {
                $post_action = Action_Factory::show_title_dialog(TR::t('info'), $post_action, $detect_info);
            }

            $this->force_parent_reload = $this->plugin->get_active_playlist_id() === $playlist_id;
        } catch (Exception $ex) {
            hd_debug_print("Problem with download playlist");
            print_backtrace_exception($ex);
            $post_action = Action_Factory::show_title_dialog(TR::t('err_load_playlist'), null, $ex->getMessage());
        }

        return $post_action;
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
            $playlist_id = Hashed_Array::hash($line);
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
                        $params[PARAM_TYPE] = PARAM_LINK;
                        $params[PARAM_NAME] = basename($m[2]);
                        $params[PARAM_URI] = $line;
                        $params[PARAM_PL_TYPE] = CONTROL_PLAYLIST_IPTV;
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

                $params = $provider->fill_default_provider_info($m, $playlist_id);
                if ($params === false) continue;
            } else {
                hd_debug_print("can't recognize: $line");
                continue;
            }

            if ($this->plugin->is_playlist_exist($playlist_id)) {
                hd_debug_print("already exist: $playlist_id", true);
            } else {
                $new_count++;
                hd_debug_print("imported playlist: " . $params, true);
                $this->plugin->set_playlist_parameters($playlist_id, $params);
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
        $order = $this->plugin->get_all_playlists_ids();
        $hash = Hashed_Array::hash($selected_media_url->filepath);
        if (in_array($hash, $order)) {
            return Action_Factory::show_title_dialog(TR::t('err_file_exist'));
        }

        $contents = file_get_contents($selected_media_url->filepath, false, null, 0, 512);
        if ($contents === false || strpos($contents, TAG_EXTM3U) === false) {
            hd_debug_print("Problem with import playlist: $selected_media_url->filepath");
            return Action_Factory::show_title_dialog(TR::t('err_bad_m3u_file'));
        }

        return $this->do_add_m3u_type($selected_media_url->filepath);
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

        $order = $this->plugin->get_all_playlists_ids();
        $old_count = count($order);
        foreach ($files as $file) {
            $hash = Hashed_Array::hash($file);
            if (in_array($hash, $order)) continue;

            $contents = file_get_contents($file);
            if ($contents === false || strpos($contents, TAG_EXTM3U) === false) {
                hd_debug_print("Problem with import playlist: $file");
                continue;
            }

            $playlist[PARAM_TYPE] = PARAM_FILE;
            $playlist[PARAM_NAME] = basename($file);
            $playlist[PARAM_URI] = $file;
            $playlist[PARAM_PL_TYPE] = CONTROL_PLAYLIST_IPTV;
            $this->plugin->set_playlist_parameters($hash, $playlist);
        }

        return Action_Factory::show_title_dialog(TR::t('edit_list_added__1', count($order) - $old_count),
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
        hd_debug_print($media_url, true);

        $sticker = Control_Factory::create_sticker(get_image_path('star_small.png'), -55, -2);
        $mapper_ops = Default_Dune_Plugin::get_id_detect_mapper();

        $items = array();
        foreach ($this->plugin->get_all_playlists_ids() as $playlist_id) {
            $starred = ($playlist_id === $this->plugin->get_active_playlist_id());
            $params = $this->plugin->get_playlist_parameters($playlist_id);
            $title = safe_get_value($params, PARAM_NAME, '');
            if (empty($title)) {
                $title = "Unnamed";
            }

            $type = safe_get_value($params, PARAM_TYPE);
            if ($type === PARAM_PROVIDER) {
                $provider = $this->plugin->create_provider_class(safe_get_value($params, PARAM_PROVIDER));
                if (is_null($provider)) continue;

                if ($title !== $provider->getName()) {
                    $title .= " ({$provider->getName()})";
                }
                $icon_file = $provider->getLogo();
                $detailed_info = $title;
            } else {
                $uri = safe_get_value($params, PARAM_URI);
                if (!empty($uri)) {
                    $id_map = safe_get_value($params, PARAM_ID_MAPPER);
                    if (empty($id_map) || $id_map === 'by_default') {
                        $id_map = ATTR_CHANNEL_HASH;
                    }
                    $detailed_info = TR::t('setup_channels_info__4',
                        $title,
                        $uri,
                        safe_get_value($params, PARAM_PL_TYPE),
                        $mapper_ops[$id_map]
                    );
                } else {
                    $detailed_info = $title;
                }
                $icon_file = get_image_path($type === PARAM_LINK ? "link.png" : "m3u_file.png");
            }

            $shortcut = $this->plugin->get_playlist_shortcut($playlist_id);
            if (!empty($shortcut)) {
                $title = "$title - ($shortcut)";
            }

            $items[] = array(
                PluginRegularFolderItem::media_url => MediaURL::encode(array('screen_id' => static::ID, 'id' => $playlist_id)),
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
