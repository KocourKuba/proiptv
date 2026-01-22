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
require_once 'lib/user_input_handler_registry.php';

class Starnet_Edit_Playlists_Screen extends Abstract_Preloaded_Regular_Screen
{
    const ID = 'edit_playlists';

    const SCREEN_EDIT_PLAYLIST = 'playlist';

    const ACTION_FILE_TEXT_LIST = 'text_list_file';

    const ACTION_REMOVE_ITEM_DLG_APPLY = 'remove_item_apply';
    const ACTION_CHOOSE_FOLDER = 'choose_folder';
    const ACTION_ADD_PROVIDER = 'add_provider';
    const ACTION_CONFIRM_CLEAR_DLG_APPLY = 'clear_apply_dlg';
    const ACTION_ASSIGN_SHORTCUT_POPUP = 'assign_shortcut';
    const ACTION_SHORTCUT_SELECTED = 'shortcut_selected';
    const ACTION_EXPORT_FOLDER_SELECTED = 'export_folder_selected';
    const ACTION_IMPORT_FOLDER_SELECTED = 'import_folder_selected';

    const PARAM_ALLOW_ORDER = 'allow_order';

    ///////////////////////////////////////////////////////////////////////

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        return $this->do_get_action_map($plugin_cookies);
    }

    protected function do_get_action_map(&$plugin_cookies)
    {
        hd_debug_print(null, true);

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

        $actions[GUI_EVENT_KEY_D_BLUE] = User_Input_Handler_Registry::create_action($this, ACTION_PLUGIN_SETTINGS, TR::t('edit'));

        $action_return = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        $actions[GUI_EVENT_KEY_RETURN] = $action_return;
        $actions[GUI_EVENT_KEY_TOP_MENU] = $action_return;
        $actions[GUI_EVENT_KEY_ENTER] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_ENTER);
        $actions[GUI_EVENT_KEY_POPUP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_POPUP_MENU);
        $actions[GUI_EVENT_KEY_CLEAR] = User_Input_Handler_Registry::create_action($this, ACTION_ITEM_DELETE);

        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        $selected_id = isset($user_input->selected_media_url) ? MediaURL::decode($user_input->selected_media_url)->id : 0;

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $sel_idx = $user_input->sel_ndx;

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_RETURN:
                $target_action = null;
                if ($this->force_parent_reload && isset($parent_media_url->{PARAM_SOURCE_WINDOW_ID}, $parent_media_url->{PARAM_END_ACTION})) {
                    $this->force_parent_reload = false;
                    $this->plugin->reset_channels();
                    $source_window = safe_get_value($parent_media_url, PARAM_SOURCE_WINDOW_ID);
                    $end_action = safe_get_value($parent_media_url, PARAM_END_ACTION);
                    hd_debug_print("Force parent reload: $source_window action: $end_action", true);
                    $target_action = User_Input_Handler_Registry::create_screen_action($source_window, $end_action);
                }

                return Action_Factory::close_and_run($target_action);

            case GUI_EVENT_KEY_ENTER:
                $type = $this->plugin->get_playlist_parameter($selected_id, PARAM_TYPE);
                $uri = $this->plugin->get_playlist_parameter($selected_id, PARAM_URI);
                if ($type === PARAM_FILE && !file_exists($uri)) {
                    return Action_Factory::show_title_dialog(TR::t('err_error'), TR::t('err_error_file_not_found'));
                }

                $this->plugin->set_active_playlist_id($selected_id);
                $this->force_parent_reload = true;
                return User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);

            case ACTION_PLUGIN_SETTINGS:
                if (!$this->plugin->is_playlist_entry_exist($selected_id)) {
                    hd_debug_print("Unknown playlist: $selected_id", true);
                    return null;
                }

                return $this->plugin->show_protect_settings_dialog($this,
                    Action_Factory::open_folder(
                        Starnet_Setup_Playlist_Screen::make_controls_media_url_str(static::ID, $user_input->sel_ndx, $selected_id),
                        TR::t('setup_playlist')
                    )
                );

            case ACTION_ITEM_TOGGLE_MOVE:
                $plugin_cookies->toggle_move = !$plugin_cookies->toggle_move;
                $actions = $this->do_get_action_map($plugin_cookies);
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

            case ACTION_EXPORT:
                return $this->plugin->show_export_dialog($this, 'playlists_export.txt');

            case ACTION_EXPORT_APPLY_DLG:
                $media_url = Starnet_Folder_Screen::make_callback_media_url_str(static::ID,
                    array(
                        Starnet_Folder_Screen::PARAM_CHOOSE_FOLDER => self::ACTION_EXPORT_FOLDER_SELECTED,
                        Starnet_Folder_Screen::PARAM_ADD_PARAMS => $user_input->{CONTROL_EDIT_NAME},
                        Starnet_Folder_Screen::PARAM_ALLOW_NETWORK => !is_limited_apk(),
                    )
                );
                return Action_Factory::open_folder($media_url, TR::t('select_folder'));

            case self::ACTION_EXPORT_FOLDER_SELECTED:
                return $this->do_export_playlist($user_input);

            case ACTION_ADD_URL_DLG:
                return $this->do_add_url_dlg();

            case ACTION_URL_DLG_APPLY:
                return $this->apply_add_url_dlg($user_input);

            case ACTION_PL_TYPE_DLG_APPLY:
                return $this->apply_add_m3u_type($user_input);

            case ACTION_CHOOSE_FILE:
                $media_url = Starnet_Folder_Screen::make_callback_media_url_str(static::ID,
                    array(
                        PARAM_EXTENSION => $user_input->{PARAM_EXTENSION},
                        Starnet_Folder_Screen::PARAM_CHOOSE_FILE => $user_input->{PARAM_SELECTED_ACTION},
                        Starnet_Folder_Screen::PARAM_ALLOW_NETWORK => !is_limited_apk(),
                        Starnet_Folder_Screen::PARAM_READ_ONLY => true,
                    )
                );

                return Action_Factory::open_folder($media_url, TR::t('select_file'));

            case self::ACTION_FILE_TEXT_LIST:
                return $this->selected_text_file($user_input);

            case ACTION_FILE_PLAYLIST:
                return $this->selected_m3u_file($user_input);

            case self::ACTION_CHOOSE_FOLDER:
                $media_url = Starnet_Folder_Screen::make_callback_media_url_str(static::ID,
                    array(
                        PARAM_EXTENSION => $user_input->{PARAM_EXTENSION},
                        Starnet_Folder_Screen::PARAM_CHOOSE_FOLDER => self::ACTION_IMPORT_FOLDER_SELECTED,
                        Starnet_Folder_Screen::PARAM_ALLOW_NETWORK => !is_limited_apk(),
                        Starnet_Folder_Screen::PARAM_READ_ONLY => true,
                    )
                );

                return Action_Factory::open_folder($media_url, TR::t('edit_list_src_folder'));

            case self::ACTION_ADD_PROVIDER:
                $params = array(
                    PARAM_SCREEN_ID => Starnet_Edit_Providers_List_Screen::ID,
                    PARAM_SOURCE_WINDOW_ID => static::ID,
                    PARAM_WINDOW_COUNTER => 1,
                    PARAM_END_ACTION => ACTION_EDIT_PROVIDER_DLG,
                );
                return Action_Factory::open_folder(MediaURL::encode($params), TR::t('edit_list_add_provider'));

            case ACTION_EDIT_PROVIDER_DLG:
                return $this->edit_provider_dlg($user_input);

            case ACTION_EDIT_PROVIDER_DLG_APPLY:
                return $this->apply_edit_provider_dlg($user_input, $parent_media_url);

            case self::ACTION_IMPORT_FOLDER_SELECTED:
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

            case ACTION_RELOAD:
                $this->force_parent_reload = true;
                break;

            case ACTION_INVALIDATE:
                if (isset($user_input->{PARAM_PLAYLIST_ID})) {
                    $sel_idx = array_search($user_input->{PARAM_PLAYLIST_ID}, $this->plugin->get_all_playlists_ids());
                }
                break;
        }

        return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $sel_idx);
    }

    /////////////////////////////////////////////////////////////////////////////////////////////
    /// protected methods

    protected function edit_provider_dlg($user_input)
    {
        $playlist_id = safe_get_value($user_input, COLUMN_PLAYLIST_ID, '');
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

        return Action_Factory::show_dialog($defs, "{$provider->getName()} ({$provider->getId()})");
    }

    protected function apply_edit_provider_dlg($user_input, $parent_media_url)
    {
        hd_debug_print(null, true);

        // edit existing or new provider in starnet_edit_list_screen
        $playlist_id = safe_get_value($user_input, CONTROL_EDIT_ITEM, '');
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

        $sel_idx = array_search($res, $this->plugin->get_all_playlists_ids());
        $this->force_parent_reload = $this->plugin->get_active_playlist_id() === $res;
        if ($this->plugin->load_channels($plugin_cookies, true)) {
            return $this->invalidate_current_folder($parent_media_url, $plugin_cookies, $sel_idx);
        }

        $actions[] = Action_Factory::invalidate_all_folders($plugin_cookies);
        $actions[] = Action_Factory::show_title_dialog(TR::t('err_load_playlist'), Dune_Last_Error::get_last_error(LAST_ERROR_PLAYLIST));
        return Action_Factory::composite($actions);
    }

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
            array(PARAM_SELECTED_ACTION => ACTION_FILE_PLAYLIST)
        );

        // Add File
        $menu_items[] = $this->plugin->create_menu_item($this,
            ACTION_CHOOSE_FILE,
            TR::t('select_file'),
            "m3u_file.png",
            array(
                PARAM_SELECTED_ACTION => ACTION_FILE_PLAYLIST,
                PARAM_EXTENSION => PLAYLIST_PATTERN
            )
        );

        $menu_items[] = $this->plugin->create_menu_item($this,
            self::ACTION_CHOOSE_FOLDER,
            TR::t('edit_list_folder_path'),
            "folder.png",
            array(PARAM_EXTENSION => $parent_media_url->{PARAM_EXTENSION})
        );

        // Add list file
        $menu_items[] = $this->plugin->create_menu_item($this,
            ACTION_CHOOSE_FILE,
            TR::t('edit_list_import_list'),
            "text_file.png",
            array(
                PARAM_SELECTED_ACTION => self::ACTION_FILE_TEXT_LIST,
                PARAM_EXTENSION => TEXT_FILE_PATTERN
            )
        );

        $menu_items[] = $this->plugin->create_menu_item($this, ACTION_EXPORT, TR::t('export_list'));
        $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);

        if ($this->plugin->get_all_playlists_count() !== 0) {
            $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEM_TOGGLE_MOVE, TR::t('tv_screen_toggle_move'), "move.png");
        }

        $menu_items[] = $this->plugin->create_menu_item($this, GuiMenuItemDef::is_separator);
        $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEM_DELETE, TR::t('delete2'), "remove.png");
        $menu_items[] = $this->plugin->create_menu_item($this, ACTION_ITEMS_CLEAR, TR::t('clear'), "brush.png");

        if ($this->plugin->is_full_size_remote()) {
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
        if (!isset($user_input->selected_media_url)) {
            return null;
        }

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
        Control_Factory::add_text_field($defs, $this, CONTROL_EDIT_NAME, '', $name,
            false, false, false, true, Control_Factory::DLG_CONTROLS_WIDTH);

        $opts_idx = CONTROL_PLAYLIST_IPTV;
        $opts[CONTROL_PLAYLIST_IPTV] = TR::t('edit_list_playlist_iptv');
        $opts[CONTROL_PLAYLIST_VOD] = TR::t('edit_list_playlist_vod');
        Control_Factory::add_label($defs, '', TR::t('edit_list_playlist_type'), -10);
        Control_Factory::add_combobox($defs, $this, CONTROL_EDIT_TYPE, '', $opts_idx,
            $opts, Control_Factory::DLG_CONTROLS_WIDTH);

        $url = 'http://';
        Control_Factory::add_label($defs, '', TR::t('url'), -10);
        Control_Factory::add_text_field($defs, $this, CONTROL_URL_PATH, '', $url,
            false, false, false, true, Control_Factory::DLG_CONTROLS_WIDTH);

        $mapper = CONTROL_DETECT_ID;
        Control_Factory::add_label($defs, '', TR::t('edit_list_playlist_detect_id'), -10);
        Control_Factory::add_combobox($defs, $this, CONTROL_DETECT_ID, '',
            $mapper, $mapper_ops, Control_Factory::DLG_CONTROLS_WIDTH, $params, true);

        Control_Factory::add_vgap($defs, 50);
        Control_Factory::add_close_dialog_and_apply_button($defs, $this, ACTION_URL_DLG_APPLY, TR::t('ok'));
        Control_Factory::add_cancel_button($defs);
        Control_Factory::add_vgap($defs, 10);

        return Action_Factory::show_dialog($defs, $window_title);
    }

    /**
     * @param object $user_input
     * @return array|null
     */
    protected function apply_add_url_dlg($user_input)
    {
        hd_debug_print(null, true);

        $uri = safe_get_value($user_input, CONTROL_URL_PATH, '');
        if (!is_proto_http($uri)) {
            return Action_Factory::show_title_dialog(TR::t('err_error'), TR::t('err_incorrect_url'));
        }

        try {
            $name = safe_get_value($user_input, CONTROL_EDIT_NAME, '');
            $pl_type = safe_get_value($user_input, CONTROL_EDIT_TYPE, CONTROL_PLAYLIST_IPTV);
            $detect_id = safe_get_value($user_input, CONTROL_DETECT_ID, CONTROL_DETECT_ID);

            $post_action = $this->add_playlist($uri, PARAM_LINK, $name, $detect_id, $pl_type);
        } catch (Exception $ex) {
            hd_debug_print("Problem with download playlist");
            print_backtrace_exception($ex);
            $post_action = Action_Factory::show_title_dialog(TR::t('err_load_playlist'), $ex->getMessage());
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
        $params = array();

        Control_Factory::add_vgap($defs, 20);

        $name = basename($uri);
        Control_Factory::add_label($defs, '', TR::t('name'), -10);
        Control_Factory::add_text_field($defs, $this, CONTROL_EDIT_NAME, '', $name,
            false, false, false, true, Control_Factory::DLG_CONTROLS_WIDTH);

        $opts[CONTROL_PLAYLIST_IPTV] = TR::t('edit_list_playlist_iptv');
        $opts[CONTROL_PLAYLIST_VOD] = TR::t('edit_list_playlist_vod');
        Control_Factory::add_label($defs, '', TR::t('edit_list_playlist_type'), -10);
        Control_Factory::add_combobox($defs, $this, CONTROL_EDIT_TYPE, '',
            CONTROL_PLAYLIST_IPTV, $opts, Control_Factory::DLG_CONTROLS_WIDTH);

        $mapper_ops = Default_Dune_Plugin::get_id_detect_mapper();
        Control_Factory::add_label($defs, '', TR::t('edit_list_playlist_detect_id'), -10);
        Control_Factory::add_combobox($defs, $this, CONTROL_DETECT_ID, '',
            CONTROL_DETECT_ID, $mapper_ops, Control_Factory::DLG_CONTROLS_WIDTH, $params, true);

        $param = array(CONTROL_URL_PATH => $uri);
        Control_Factory::add_vgap($defs, 50);
        Control_Factory::add_close_dialog_and_apply_button($defs, $this, ACTION_PL_TYPE_DLG_APPLY, TR::t('ok'), $param);
        Control_Factory::add_cancel_button($defs);
        Control_Factory::add_vgap($defs, 10);

        return Action_Factory::show_dialog($defs, TR::t('edit_list_playlist_type'));
    }

    /**
     * @param object $user_input
     * @return array|null
     */
    protected function apply_add_m3u_type($user_input)
    {
        hd_debug_print(null, true);

        try {
            $file_path = safe_get_value($user_input, CONTROL_URL_PATH);
            if (empty($file_path)) {
                return null;
            }

            $pl_type = safe_get_value($user_input, CONTROL_EDIT_TYPE, CONTROL_PLAYLIST_IPTV);
            $detect_id = safe_get_value($user_input, CONTROL_DETECT_ID, CONTROL_DETECT_ID);
            $name = safe_get_value($user_input, CONTROL_EDIT_NAME);

            $post_action = $this->add_playlist($file_path, PARAM_FILE, $name, $detect_id, $pl_type);
        } catch (Exception $ex) {
            hd_debug_print("Problem with download playlist");
            print_backtrace_exception($ex);
            $post_action = Action_Factory::show_title_dialog(TR::t('err_load_playlist'), $ex->getMessage());
        }

        return $post_action;
    }

    protected function selected_text_file($user_input)
    {
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $selected_media_url = MediaURL::decode($user_input->{Starnet_Folder_Screen::PARAM_SELECTED_DATA});

        hd_debug_print("Choosed file: " . $selected_media_url->{PARAM_FILEPATH}, true);
        $lines = file($selected_media_url->{PARAM_FILEPATH}, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false || (count($lines) === 1 && trim($lines[0]) === '')) {
            return Action_Factory::show_title_dialog(TR::t('err_error'), TR::t('edit_list_empty_file'));
        }

        $old_count = $this->plugin->get_all_playlists_count();
        $new_count = $old_count;
        $lines[0] = trim($lines[0], "\x0B\xEF\xBB\xBF");
        foreach ($lines as $uri) {
            $uri = trim($uri);
            hd_debug_print("Load string: '$uri'", true);

            /** @var array $m */
            if (preg_match(HTTP_PATTERN, $uri, $m)) {
                hd_debug_print("import link: '$uri'", true);
                try {
                    $this->add_playlist($uri, PARAM_LINK, '', CONTROL_DETECT_ID, CONTROL_PLAYLIST_IPTV);
                    $new_count++;
                } catch (Exception $ex) {
                    hd_debug_print("Problem importing '$uri' " . $ex->getMessage());
                }
                continue;
            }

            if (!preg_match(PROVIDER_PATTERN, $uri, $m)) {
                hd_debug_print("can't recognize: $uri");
                continue;
            }

            hd_debug_print("import provider $m[1]:", true);
            $provider = $this->plugin->create_provider_class($m[1]);
            if (is_null($provider)) {
                hd_debug_print("Unknown provider ID: $m[1]");
                continue;
            }

            $params = $provider->fill_default_provider_info($m, $playlist_id);
            if ($params === false) {
                hd_debug_print("Incorrect provider parameters: $m[2]");
                continue;
            }

            $playlist_id = Hashed_Array::hash($uri);
            if ($this->plugin->is_playlist_entry_exist($playlist_id)) {
                hd_debug_print("already exist: $playlist_id", true);
                continue;
            }

            $new_count++;
            hd_debug_print("imported playlist: " . $params, true);
            $this->plugin->set_playlist_parameters($playlist_id, $params);
        }

        if ($old_count === $new_count) {
            return Action_Factory::show_title_dialog(TR::t('edit_list_no_files'));
        }

        $actions[] = Action_Factory::show_title_dialog(TR::t('edit_list_added__2', $new_count - $old_count, count($lines)));
        $actions[] = Action_Factory::close_and_run();
        $actions[] = Action_Factory::open_folder($parent_media_url->get_media_url_string(), TR::t('setup_channels_src_edit_playlists'));
        return Action_Factory::composite($actions);
    }

    protected function selected_m3u_file($user_input)
    {
        $selected_media_url = MediaURL::decode($user_input->{Starnet_Folder_Screen::PARAM_SELECTED_DATA});

        $hash = Hashed_Array::hash($selected_media_url->{PARAM_FILEPATH});
        if ($this->plugin->is_playlist_entry_exist($hash)) {
            return Action_Factory::show_title_dialog(TR::t('err_error'), TR::t('err_file_exist'));
        }

        return $this->do_add_m3u_type($selected_media_url->{PARAM_FILEPATH});
    }

    /**
     * @param object $user_input
     * @return array
     */
    protected function do_select_folder($user_input)
    {
        hd_debug_print(null, true);

        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $selected_media_url = MediaURL::decode($user_input->{Starnet_Folder_Screen::PARAM_SELECTED_DATA});
        $files = glob_dir($selected_media_url->{PARAM_FILEPATH}, "/\." . $parent_media_url->{PARAM_EXTENSION} . "$/i");
        if (empty($files)) {
            return Action_Factory::show_title_dialog(TR::t('edit_list_no_files'));
        }

        $old_count = $this->plugin->get_all_playlists_count();
        foreach ($files as $file) {
            if ($this->plugin->is_playlist_entry_exist(Hashed_Array::hash($file))) continue;
            try {
                $this->add_playlist($file, PARAM_FILE, '', CONTROL_DETECT_ID, CONTROL_PLAYLIST_IPTV);
            } catch (Exception $ex) {
                hd_debug_print("Problem importing '$file' " . $ex->getMessage());
            }
        }
        $actions[] = Action_Factory::show_title_dialog(TR::t('edit_list_added__2', $this->plugin->get_all_playlists_count() - $old_count, count($files)));
        $actions[] = Action_Factory::close_and_run();
        $actions[] = Action_Factory::open_folder($parent_media_url->get_media_url_string(), TR::t('setup_channels_src_edit_playlists'));
        return Action_Factory::composite($actions);
    }

    /**
     * @param object $user_input
     * @return array
     */
    protected function do_export_playlist($user_input)
    {
        $list_playlists = '';
        $playlists = $this->plugin->get_all_playlists_ids();
        foreach ($playlists as $playlist_id) {
            $str_value = '';
            $params = $this->plugin->get_playlist_parameters($playlist_id);
            $type = safe_get_value($params, PARAM_TYPE);
            switch ($type) {
                case PARAM_PROVIDER:
                    $provider_id = safe_get_value($params, PARAM_PROVIDER);
                    /** @var api_default $provider */
                    $provider = $this->plugin->get_providers()->get($provider_id);
                    if (!empty($provider)) {
                        $str_value = "$provider_id@";
                        switch ($provider->getType()) {
                            case PROVIDER_TYPE_PIN:
                                $str_value = sprintf("%s@%s",
                                    $provider_id,
                                    safe_get_value($params, MACRO_PASSWORD));
                                break;

                            case PROVIDER_TYPE_LOGIN:
                                $str_value = sprintf("%s@%s:%s",
                                    $provider_id,
                                    safe_get_value($params, MACRO_LOGIN),
                                    safe_get_value($params, MACRO_PASSWORD));
                                break;
                            case $provider_id:
                                $str_value = sprintf("%s@%s|%s",
                                    $provider_id,
                                    safe_get_value($params, MACRO_OTTKEY),
                                    safe_get_value($params, MACRO_VPORTAL));
                                break;
                            default:
                        }
                    }

                    break;
                case PARAM_LINK:
                    $str_value = safe_get_value($params, PARAM_URI);
                    break;
                default:
            }

            if (!empty($str_value)) {
                $list_playlists .= $str_value . PHP_EOL;
            }
        }

        if (empty($list_playlists)) {
            return Action_Factory::show_title_dialog(TR::t('err_error'), TR::t('err_empty_export_list'));
        }

        $folder_screen = MediaURL::decode($user_input->{Starnet_Folder_Screen::PARAM_SELECTED_DATA});
        $path = $folder_screen->{PARAM_FILEPATH} . '/' . $folder_screen->{Starnet_Folder_Screen::PARAM_ADD_PARAMS};
        file_put_contents($path, $list_playlists);
        return null;
    }

    /**
     * @throws Exception
     */
    protected function add_playlist($uri, $type, $name, $detect_id, $pl_type)
    {
        $playlist_id = Hashed_Array::hash($uri);
        if ($this->plugin->is_playlist_entry_exist($playlist_id)) {
            hd_debug_print("already exist: $playlist_id", true);
            throw new Exception(TR::load('err_file_exist'));
        }

        hd_debug_print("Adding new playlist: $uri");

        $params[PARAM_TYPE] = $type;
        $params[PARAM_URI] = $uri;
        $params[PARAM_PLAYLIST_TYPE] = $pl_type;

        $tmp_file = get_temp_path($playlist_id);
        if ($type === PARAM_FILE) {
            $res = copy($uri, $tmp_file);
            $errors = error_get_last();
            $logfile = "Copy error: " . $errors['type'] . "\n" .$errors['message'];
        } else {
            $curl_wrapper = Curl_Wrapper::getInstance();
            $res = $curl_wrapper->download_file($uri, $tmp_file);
            $logfile = "Error code: " . Curl_Wrapper::get_error_no() . "\n" . Curl_Wrapper::get_error_desc();
        }

        if (!$res) {
            throw new Exception(TR::load('err_load_playlist') . " '$uri'\n$logfile");
        }

        $contents = file_get_contents($tmp_file, false, null, 0, 512);
        if ($contents === false || (strpos($contents, TAG_EXTM3U) === false && strpos($contents, TAG_EXTINF) === false)) {
            safe_unlink($tmp_file);
            throw new Exception(TR::load('err_bad_m3u_file') . " '$uri'\n\n$contents");
        }

        $post_action = User_Input_Handler_Registry::create_action($this,ACTION_INVALIDATE, null, array(PARAM_PLAYLIST_ID => $playlist_id));
        if ($pl_type === CONTROL_PLAYLIST_IPTV  && $detect_id === CONTROL_DETECT_ID) {
            hd_debug_print("Detect playlist id: $detect_id");
            $detect_info = $this->plugin->collect_detect_info($tmp_file);
            hd_debug_print($detect_info);
            $post_action = Action_Factory::show_title_dialog(TR::t('info'), $detect_info, $post_action);
        }
        $params[PARAM_ID_MAPPER] = $detect_id;

        $parser = new M3uParser();
        $parser->setPlaylistFile($tmp_file, true);
        $pl_header = $parser->parseHeader(false);
        hd_debug_print("Playlist info: " . $pl_header);
        unset($tmp_file);

        $saved_source = new Hashed_Array();
        foreach ($pl_header->getEpgSources() as $url) {
            $item = array();
            $hash = Hashed_Array::hash($url);
            hd_debug_print("playlist source: ($hash) $url", true);

            $item[PARAM_HASH] = $hash;
            $item[PARAM_TYPE] = PARAM_LINK;
            $item[PARAM_NAME] = basename($url);
            $item[PARAM_URI] = $url;
            $item[PARAM_CACHE] = XMLTV_CACHE_AUTO;
            $saved_source->put($hash, $item);
        }

        if (empty($name)) {
            $pl_tag = $pl_header->getEntryTag(TAG_PLAYLIST);
            if ($pl_tag !== null) {
                $pl_name = $pl_tag->getTagValue();
                $name = empty($pl_name) ? $name : $pl_name;
            }

            if (empty($name)) {
                if (($pos = strpos($uri, '?')) !== false) {
                    $uri = substr($uri, 0, $pos - 1);
                }
                $name = basename($uri);
            }
        }
        $params[PARAM_NAME] = $name;

        $this->plugin->set_playlist_parameters($playlist_id, $params);
        if ($saved_source->size() !== 0) {
            $this->plugin->set_playlist_xmltv_sources($playlist_id, $saved_source);
            $this->plugin->set_selected_xmltv_ids($playlist_id, $saved_source->key());
        }

        return $post_action;
    }

    /**
     * @inheritDoc
     */
    public function get_all_folder_items(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $sel_sticker = Control_Factory::create_sticker(get_image_path('star_small.png'), -55, -2);
        $del_sticker = Control_Factory::create_sticker(get_image_path('del.png'), -55, -2);
        $mapper_ops = Default_Dune_Plugin::get_id_detect_mapper();

        $items = array();
        foreach ($this->plugin->get_all_playlists_ids() as $playlist_id) {
            $starred = ($playlist_id === $this->plugin->get_active_playlist_id());
            $params = $this->plugin->get_playlist_parameters($playlist_id);
            $title = safe_get_value($params, PARAM_NAME, '');
            if (empty($title)) {
                $title = "Unnamed";
            }

            $missed = false;
            $type = safe_get_value($params, PARAM_TYPE);
            if ($type === PARAM_PROVIDER) {
                $provider = $this->plugin->create_provider_class(safe_get_value($params, PARAM_PROVIDER));
                if (is_null($provider)) {
                    $missed = true;
                    $title = "Unknown provider - $playlist_id";
                    $icon_file = get_image_path("iptv.png");
                    $detailed_info = TR::load("err_error_no_data");
                } else {
                    if ($title !== $provider->getName()) {
                        $title .= " ({$provider->getName()})";
                    }
                    $icon_file = $provider->getLogo();
                    if ($provider->hasApiCommand(API_COMMAND_GET_VOD)) {
                        $detailed_info = "$title||" . TR::load('plugin_vod__1', ': ' . TR::load('yes'));
                    } else {
                        $detailed_info = "$title||" . TR::load('plugin_vod__1', ': ' . TR::load('no'));
                    }
                }
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
                        safe_get_value($params, PARAM_PLAYLIST_TYPE),
                        $mapper_ops[$id_map]
                    );
                    $missed = ($type === PARAM_FILE && !file_exists($uri));
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
                PluginRegularFolderItem::media_url => MediaURL::encode(array(PARAM_SCREEN_ID => static::ID, 'id' => $playlist_id)),
                PluginRegularFolderItem::caption => $title,
                PluginRegularFolderItem::view_item_params => array(
                    ViewItemParams::item_sticker => ($starred ? $sel_sticker : ($missed ? $del_sticker : null)),
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
