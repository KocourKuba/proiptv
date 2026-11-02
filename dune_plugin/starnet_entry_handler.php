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

require_once 'lib/mediaurl.php';
require_once 'lib/user_input_handler_registry.php';
require_once 'lib/list_utils.php';

class Starnet_Entry_Handler implements User_Input_Handler
{
    const ID = 'entry';

    const ACTION_PLUGIN_ENTRY = 'plugin_entry';
    const ACTION_LAUNCH = 'launch';
    const ACTION_LAUNCH_VOD_LIBRARIES = 'launch_vod_libraries';
    const ACTION_AUTO_RESUME = 'auto_resume';
    const ACTION_UPDATE_EPFS = 'update_epfs';
    const ACTION_INSTALL = 'install';
    const ACTION_UNINSTALL = 'uninstall';
    const ACTION_CONTINUE_UNINSTALL = 'continue_uninstall';
    const ACTION_UPDATE = 'update';
    const ACTION_CONFIRM_UPDATE = 'confirm_update'; // r24+, this action coded in manifest
    const ACTION_CALL_PLUGIN_SETTINGS = 'call_plugin_setup'; // this action coded in manifest
    const ACTION_CALL_PLAYLIST_SETTINGS = 'call_playlist_settings'; // this action coded in manifest
    const ACTION_CALL_PLAYLIST_SCREEN = 'call_playlists_setup'; // this action coded in manifest
    const ACTION_CALL_BACKUP_SETTINGS = 'call_backup'; // this action coded in manifest
    const ACTION_CALL_REBOOT = 'call_reboot'; // this action coded in manifest
    const ACTION_CALL_SEND_LOG = 'call_send_log'; // this action coded in manifest
    const ACTION_CONFIRM_BACKUP_DLG = 'create_backup';
    const ACTION_EXTERNAL_SEARCH = 'external_search'; // this action coded in manifest (public_actions/vod_search)
    const ACTION_EXTERNAL_SEARCH_APPLY = 'external_search_apply';
    const ACTION_EXTERNAL_SEARCH_SELECT = 'external_search_select';
    const ACTION_EXTERNAL_SEARCH_SELECTED = 'external_search_selected';
    const VOD_SEARCH_CONFIG_ID = 'proiptv_vod_search';
    const OLD_LINK = "aHR0cHM6Ly9naXRodWIuY29tL0tvY291ckt1YmEvcHJvaXB0dg==";

    private $plugin;

    /**
     * @param Default_Dune_Plugin $plugin
     */
    public function __construct(Default_Dune_Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    ///////////////////////////////////////////////////////////////////////
    // User_Input_Handler interface

    /**
     * @inheritDoc
     */
    public function get_handler_id()
    {
        return static::ID . '_handler';
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print();
        dump_input_handler($user_input, true);
        set_debug_log(safe_get_value($plugin_cookies, PARAM_COOKIE_ENABLE_DEBUG, SwitchOnOff::off));

        if (!isset($user_input->control_id)) {
            hd_debug_print('user input control id not set');
            return null;
        }

        if ($user_input->control_id === self::ACTION_PLUGIN_ENTRY
            && safe_get_value($user_input, PARAM_ACTION_ID) === self::ACTION_CONFIRM_UPDATE) {
            // The firmware takes the exec status of the returned GUI action (a bare bool can not be
            // deserialized and ends in an "application error" dialog). plugin_updater in libduneshell:
            // 0 - enabled (update proposed as usual), 1 - skipped, 2 - updated by the plugin itself,
            // anything else - logged and update proposed as usual.
            $disabled = SwitchOnOff::to_bool(safe_get_value($plugin_cookies, PARAM_COOKIE_DISABLE_UPDATE, SwitchOnOff::off));
            hd_debug_print('Confirm update, update disabled: ' . var_export($disabled, true));
            return Action_Factory::status($disabled ? 1 : 0);
        }

        if (!is_r22_or_higher()) {
            hd_debug_print('Too old Dune HD firmware! ' . get_raw_firmware_version());
            return $this->show_old_player(TR::t('err_too_old_player'));
        }

        if (!class_exists('SQLite3')) {
            hd_debug_print('No SQLite3 support! ' . get_raw_firmware_version());
            return $this->show_old_player(TR::t('err_no_sqlite'));
        }

        // server message: on OK re-enter with the same input (the config is loaded by then, so no message again)
        $params = array();
        foreach ($user_input as $key => $value) {
            if ($key !== 'handler_id' && $key !== 'control_id') {
                $params[$key] = $value;
            }
        }
        $post_action = User_Input_Handler_Registry::create_action($this, $user_input->control_id, null, $params);
        $message_action = $this->plugin->init_providers_config($post_action);
        if ($message_action !== null) {
            return $message_action;
        }

        $this->plugin->init_desc_parser();

        switch ($user_input->control_id) {
            case self::ACTION_CALL_REBOOT:
                return Action_Factory::restart(true);

            case self::ACTION_CALL_PLUGIN_SETTINGS:
            case self::ACTION_CALL_PLAYLIST_SETTINGS:
            case self::ACTION_CALL_PLAYLIST_SCREEN:
                $this->plugin->init_plugin();
                return $this->plugin->show_protect_settings_dialog($this,
                    User_Input_Handler_Registry::create_action($this,
                        ACTION_PASSWORD_APPLY, null, array(PARAM_ACTION => $user_input->control_id)));

            case ACTION_PASSWORD_APPLY:
                return $this->handle_protect_action($user_input);

            case self::ACTION_CALL_BACKUP_SETTINGS:
                $this->plugin->init_plugin();
                return Action_Factory::open_folder(Starnet_Setup_Backup_Screen::ID, TR::t('entry_backup'));

            case self::ACTION_CALL_SEND_LOG:
                if (!is_r22_or_higher()) {
                    return Action_Factory::show_title_dialog(TR::t('entry_send_log'), TR::t('entry_log_not_sent_too_old'));
                }

                if (!LogSeverity::$is_debug) {
                    return Action_Factory::show_title_dialog(TR::t('entry_send_log'), TR::t('entry_log_not_enabled'));
                }

                $error_msg = '';
                $msg = Default_Dune_Plugin::send_log_to_developer($error_msg)
                    ? TR::t('entry_log_sent__3', get_dune_model(), get_product_id(), format_datetime('Y-m-d H:i', time()))
                    : TR::t('entry_log_not_sent');
                return Action_Factory::show_title_dialog(TR::t('entry_send_log'), $msg);

            case ACTION_CALL_CLEAR_ALL_EPG:
                $this->plugin->init_plugin();
                if ($this->plugin->get_all_playlists_count() === 0 || !$this->plugin->init_playlist_settings_db()) break;

                Epg_Manager_Json::clear_epg_files();
                Epg_Manager_Xmltv::clear_epg_files();
                $this->plugin->reset_channels_loaded();
                $actions[] = Action_Factory::clear_rows_info_cache();
                $actions[] = Action_Factory::show_title_dialog(TR::t('information'), TR::t('entry_epg_cache_cleared'));
                return Action_Factory::composite($actions);

            case ACTION_FORCE_OPEN:
                hd_print_separator();
                hd_debug_print('FORCE LANUCH PLUGIN');
                hd_print_separator();

                if (!$this->plugin->load_channels($plugin_cookies)) {
                    $actions[] = Action_Factory::invalidate_all_folders($plugin_cookies);
                    $actions[] = Action_Factory::open_folder(
                        Starnet_Tv_Groups_Screen::ID,
                        $this->plugin->get_plugin_title(),
                        null,
                        null,
                        Action_Factory::show_title_dialog(TR::t('err_load_playlist'), Dune_Last_Error::get_last_error(LAST_ERROR_PLAYLIST))
                    );
                    return Action_Factory::composite($actions);
                }

                hd_debug_print('action: launch open', true);
                $actions[] = Action_Factory::refresh_entry_points();
                $actions[] = Action_Factory::invalidate_all_folders($plugin_cookies);
                $actions[] = Action_Factory::open_folder(Starnet_Tv_Groups_Screen::ID, $this->plugin->get_plugin_title());
                return Action_Factory::composite($actions);

            case self::ACTION_CONFIRM_BACKUP_DLG:
                hd_debug_print('Call select backup folder');
                $media_url = Starnet_Folder_Screen::make_callback_media_url_str(static::ID,
                    array(
                        PARAM_END_ACTION => self::ACTION_PLUGIN_ENTRY,
                        PARAM_ACTION_ID => $user_input->{PARAM_ACTION_ID},
                        PARAM_EXTENSION => 'zip',
                        Starnet_Folder_Screen::PARAM_CHOOSE_FOLDER => Starnet_Setup_Backup_Screen::ACTION_BACKUP_FOLDER_SELECTED,
                        Starnet_Folder_Screen::PARAM_ALLOW_NETWORK => !is_limited_apk(),
                    )
                );
                return Action_Factory::open_folder($media_url, TR::t('setup_backup_folder_path'));

            case Starnet_Setup_Backup_Screen::ACTION_BACKUP_FOLDER_SELECTED:
                $data = MediaURL::decode($user_input->{Starnet_Folder_Screen::PARAM_SELECTED_DATA});
                if (Default_Dune_Plugin::do_backup_settings($data->{PARAM_FILEPATH}) === false) {
                    return Action_Factory::show_title_dialog(TR::t('error'), TR::t('err_backup'));
                }

                $actions[] = Action_Factory::show_title_dialog(TR::t('information'), TR::t('setup_copy_done'));
                $actions[] = User_Input_Handler_Registry::create_action(
                    $this,
                    self::ACTION_PLUGIN_ENTRY,
                    null,
                    array(PARAM_ACTION_ID => $data->{PARAM_ACTION_ID}, PARAM_MANDATORY_PLAYBACK => 0)
                );
                return Action_Factory::composite($actions);

            case ACTION_RELOAD:
                $first_run = isset($user_input->first_run_after_boot) || isset($user_input->restore_from_sleep);
                if ($first_run) {
                    file_put_contents(Starnet_Epfs_Handler::first_run_path(), '');
                }

                if (!$this->plugin->load_channels($plugin_cookies)) {
                    hd_debug_print('Failed to load channels!');
                    return Action_Factory::show_title_dialog(
                        TR::t('err_load_playlist'),
                        Dune_Last_Error::get_last_error(LAST_ERROR_PLAYLIST),
                        $this->open_playlist_screen()
                    );
                }
                $actions[] = Action_Factory::refresh_entry_points();
                $actions[] = Action_Factory::invalidate_all_folders($plugin_cookies);
                Action_Factory::invalidate_epfs_folders($plugin_cookies);
                return Action_Factory::composite($actions);

            case self::ACTION_CONTINUE_UNINSTALL:
                $action = HD::color_palette_restore();
                if ($action === null) break;

                hd_debug_print('Palette restored');
                return Action_Factory::show_title_dialog(TR::t('setup_settings_patch_palette'), TR::t('setup_patch_success'));

            case self::ACTION_EXTERNAL_SEARCH:
                return $this->do_external_search($user_input);

            case self::ACTION_EXTERNAL_SEARCH_APPLY:
                return $this->apply_external_search($user_input);

            case self::ACTION_EXTERNAL_SEARCH_SELECT:
                return $this->select_external_search_libraries($user_input);

            case self::ACTION_EXTERNAL_SEARCH_SELECTED:
                return $this->apply_external_search_libraries($user_input);

            case self::ACTION_PLUGIN_ENTRY:
                if (!isset($user_input->action_id)) {
                    break;
                }

                hd_debug_print("plugin_entry $user_input->action_id");

                switch ($user_input->action_id) {
                    case self::ACTION_LAUNCH:
                    case self::ACTION_AUTO_RESUME:
                        return $this->run_resume_state($user_input, $plugin_cookies);

                    case self::ACTION_LAUNCH_VOD_LIBRARIES:
                        $action = $this->check_upgrade($user_input);
                        if ($action !== null) {
                            return $action;
                        }

                        hd_print_separator();
                        hd_debug_print('LANUCH PLUGIN VOD LIBRARIES');
                        hd_print_separator();

                        $this->plugin->init_plugin(true);
                        if ($this->plugin->get_all_playlists_count() === 0) {
                            return Action_Factory::show_error(false, TR::t('err_no_playlists'));
                        }

                        $has_vod = false;
                        foreach ($this->plugin->get_all_playlists_ids() as $playlist_id) {
                            if ($this->plugin->has_playlist_vod($playlist_id)) {
                                $has_vod = true;
                                break;
                            }
                        }

                        if (!$has_vod) {
                            return Action_Factory::show_error(false, TR::t('err_no_vod_libraries'));
                        }

                        // every media library is loaded when it is opened, the TV playlist is not needed
                        return Action_Factory::open_folder(Starnet_Vod_Libraries_Screen::ID, TR::t('plugin_vod_libraries'));

                    case self::ACTION_UPDATE_EPFS:
                        $first_run = isset($user_input->first_run_after_boot) || isset($user_input->restore_from_sleep);
                        if ($first_run) {
                            file_put_contents(Starnet_Epfs_Handler::first_run_path(), '');
                        }

                        if (!$this->plugin->load_channels($plugin_cookies)) {
                            hd_debug_print('Failed to load channels!');
                            return Action_Factory::show_title_dialog(
                                TR::t('err_load_playlist'),
                                Dune_Last_Error::get_last_error(LAST_ERROR_PLAYLIST),
                                $this->open_playlist_screen()
                            );
                        }
                        Starnet_Epfs_Handler::update_epfs_file($plugin_cookies);
                        return Action_Factory::refresh_entry_points();

                    case self::ACTION_UNINSTALL:
                        Default_Archive::clear_cache();
                        if (!is_color_palette_patched()) break;

                        return Action_Factory::show_confirmation_dialog(
                            TR::t('setup_settings_patch_palette'),
                            $this,
                            self::ACTION_CONTINUE_UNINSTALL,
                            TR::t('setup_restore_patch')
                        );

                    default:
                        break;
                }
                break;
            default:
                break;
        }

        return null;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param object $user_input
     * @return array|null
     */
    private function check_upgrade($user_input)
    {
        $flag = get_data_path('upgrade.flag');
        if (file_exists($flag)) {
            unlink($flag);
        } else if (!file_exists(get_data_path('common.db')) && file_exists(get_data_path('common.settings'))) {
            file_put_contents($flag, '');
            $defs = array();

            $ret_action = array(PARAM_ACTION_ID => $user_input->{PARAM_ACTION_ID}, PARAM_MANDATORY_PLAYBACK => 0);
            Control_Factory::add_close_dialog_and_apply_button($defs, $this, self::ACTION_CONFIRM_BACKUP_DLG, TR::t('yes'), $ret_action);
            Control_Factory::add_close_dialog_and_apply_button($defs, $this, self::ACTION_PLUGIN_ENTRY, TR::t('no'), $ret_action);

            return Action_Factory::show_dialog($defs, TR::t('yes_no_confirm_backup'));
        }

        return null;
    }

    /**
     * Search in VOD called by other plugins (Play in apps) through public_actions/vod_search.
     * Param: search - plain string or json encoded array with title and original_title (results of both are merged)
     * The media libraries to search in are selected in a dialog, the selection is remembered.
     * The TV playlist is not loaded and the active playlist is not changed.
     *
     * @param object $user_input
     * @return array
     */
    private function do_external_search($user_input)
    {
        hd_print_separator();
        hd_debug_print('EXTERNAL VOD SEARCH');
        hd_print_separator();

        $search_string = trim((string)safe_get_value($user_input, 'search', ''));
        $keywords = $search_string === '' ? array() : vod_standard::decode_search_keywords($search_string);
        if (empty($keywords)) {
            hd_debug_print('Nothing to search');
            return Action_Factory::show_title_dialog(TR::t('search'), TR::t('err_search_empty'));
        }

        hd_debug_print("search: $search_string");

        $this->plugin->init_plugin(true);
        if ($this->plugin->get_all_playlists_count() === 0) {
            hd_debug_print('No playlists');
            return Action_Factory::show_title_dialog(TR::t('search'), TR::t('err_no_playlists'));
        }

        $vod_ids = Starnet_Vod_Libraries_Screen::get_vod_playlists_ids($this->plugin);
        if (empty($vod_ids)) {
            hd_debug_print('No playlists with VOD');
            return Action_Factory::show_title_dialog(TR::t('search'), TR::t('err_no_vod_libraries'));
        }

        if (count($vod_ids) === 1) {
            return $this->run_external_search($search_string, $vod_ids);
        }

        return $this->show_external_search_dialog($search_string, $vod_ids);
    }

    /**
     * Dialog of the search in several media libraries: the remembered libraries, search, change the selection, cancel.
     * The list of the libraries (edit_list_config) has no OK, it is closed only by RETURN and always applied,
     * so the search is started and cancelled here.
     *
     * @param string $search_string
     * @param string[] $vod_ids
     * @return array
     */
    private function show_external_search_dialog($search_string, $vod_ids)
    {
        $names = array();
        foreach ($this->get_external_search_libraries($vod_ids) as $playlist_id) {
            $info = Starnet_Vod_Libraries_Screen::get_library_info($this->plugin, $playlist_id);
            $names[] = $info[PARAM_NAME];
        }

        $params = array('search' => $search_string);
        $keywords = vod_standard::decode_search_keywords($search_string);

        $defs = array();
        Control_Factory::add_label($defs, TR::t('search'), implode(' / ', $keywords));
        Control_Factory::add_multiline_label($defs, TR::t('vod_libraries_selected'), implode(', ', $names), 8);
        Control_Factory::add_vgap($defs, 50);
        Control_Factory::add_close_dialog_and_apply_button($defs, $this, self::ACTION_EXTERNAL_SEARCH_APPLY, TR::t('search'), $params);
        if (defined('EditListConfigActionData::config_id')) {
            Control_Factory::add_close_dialog_and_apply_button($defs, $this, self::ACTION_EXTERNAL_SEARCH_SELECT,
                TR::t('vod_select_libraries'), $params);
        }
        Control_Factory::add_cancel_button($defs);

        return Action_Factory::show_dialog($defs, TR::t('vod_search_libraries'));
    }

    /**
     * List of the media libraries to search in, the remembered ones checked first in their order
     *
     * @param object $user_input
     * @return array|null
     */
    private function select_external_search_libraries($user_input)
    {
        hd_debug_print(null, true);

        $this->plugin->init_plugin();
        $vod_ids = Starnet_Vod_Libraries_Screen::get_vod_playlists_ids($this->plugin);
        $selected_ids = $this->get_external_search_libraries($vod_ids);
        $cfg = array();
        foreach ($selected_ids as $playlist_id) {
            $cfg[$playlist_id] = 1;
        }
        foreach ($vod_ids as $playlist_id) {
            if (!isset($cfg[$playlist_id])) {
                $cfg[$playlist_id] = 0;
            }
        }

        $all_items = array();
        $all_ids = array();
        foreach ($cfg as $playlist_id => $enabled) {
            $info = Starnet_Vod_Libraries_Screen::get_library_info($this->plugin, (string)$playlist_id);
            Action_Factory::add_gui_item($all_items, $all_ids, (string)$playlist_id, $info[PARAM_NAME], $info['icon']);
        }

        List_Utils::write_config_file(self::VOD_SEARCH_CONFIG_ID, $cfg);

        return Action_Factory::edit_list_config(self::VOD_SEARCH_CONFIG_ID, TR::t('vod_select_libraries'),
            $all_items, $selected_ids,
            null, null, null, null,
            User_Input_Handler_Registry::create_action($this, self::ACTION_EXTERNAL_SEARCH_SELECTED, null,
                array('search' => (string)safe_get_value($user_input, 'search', ''))));
    }

    /**
     * The list of the media libraries is closed: remember the selection and show the search dialog again
     *
     * @param object $user_input
     * @return array|null
     */
    private function apply_external_search_libraries($user_input)
    {
        hd_debug_print(null, true);

        $cfg = List_Utils::read_config_file(self::VOD_SEARCH_CONFIG_ID);
        safe_unlink(List_Utils::config_file_path(self::VOD_SEARCH_CONFIG_ID));

        $this->plugin->init_plugin();
        $vod_ids = Starnet_Vod_Libraries_Screen::get_vod_playlists_ids($this->plugin);
        $selected_ids = array();
        foreach ($cfg as $playlist_id => $enabled) {
            if ($enabled && in_array((string)$playlist_id, $vod_ids, true)) {
                $selected_ids[] = (string)$playlist_id;
            }
        }

        if (!empty($selected_ids)) {
            $this->plugin->set_parameter(PARAM_VOD_SEARCH_LIBRARIES, json_encode($selected_ids));
        }

        return $this->show_external_search_dialog((string)safe_get_value($user_input, 'search', ''), $vod_ids);
    }

    /**
     * Search in the remembered media libraries
     *
     * @param object $user_input
     * @return array|null
     */
    private function apply_external_search($user_input)
    {
        hd_debug_print(null, true);

        $this->plugin->init_plugin();
        $vod_ids = Starnet_Vod_Libraries_Screen::get_vod_playlists_ids($this->plugin);
        if (empty($vod_ids)) {
            return Action_Factory::show_title_dialog(TR::t('search'), TR::t('err_no_vod_libraries'));
        }

        return $this->run_external_search((string)safe_get_value($user_input, 'search', ''),
            $this->get_external_search_libraries($vod_ids));
    }

    /**
     * Remembered media libraries of the external search that still have VOD, all of them if nothing is remembered
     *
     * @param string[] $vod_ids
     * @return string[]
     */
    private function get_external_search_libraries($vod_ids)
    {
        $remembered = json_decode($this->plugin->get_parameter(PARAM_VOD_SEARCH_LIBRARIES, ''), true);
        $selected_ids = array();
        if (is_array($remembered)) {
            foreach ($remembered as $playlist_id) {
                if (in_array((string)$playlist_id, $vod_ids, true)) {
                    $selected_ids[] = (string)$playlist_id;
                }
            }
        }

        return empty($selected_ids) ? $vod_ids : $selected_ids;
    }

    /**
     * @param string $search_string
     * @param string[] $playlist_ids
     * @return array
     */
    private function run_external_search($search_string, $playlist_ids)
    {
        hd_debug_print("Search in media libraries: " . implode(', ', $playlist_ids));
        $caption = TR::t('search__1', implode(' / ', vod_standard::decode_search_keywords($search_string)));

        if (count($playlist_ids) > 1) {
            return Starnet_Vod_Merged_Search_Screen::make_search_action($this->plugin, $search_string, $playlist_ids, $caption);
        }

        // one library: the search result of this library with all its actions
        if (!$this->plugin->select_vod_playlist($playlist_ids[0])) {
            hd_debug_print('VOD not supported or disabled');
            return Action_Factory::show_title_dialog(TR::t('search'), TR::t('err_vod_not_available'));
        }

        // as on entering the VOD: M3U VOD playlist is loaded to the search database, providers init their data
        if (!$this->plugin->vod->fetchVodCategories()) {
            hd_debug_print('Error: Fetch categories');
            return Action_Factory::show_title_dialog(TR::t('err_load_vod'), Dune_Last_Error::get_last_error(LAST_ERROR_VOD_LIST));
        }

        return Starnet_Vod_Movie_List_Screen::make_search_action($this->plugin, $search_string, $caption);
    }

    /**
     * @param string $title
     * @return array
     */
    private function show_old_player($title) {
        $qr_code = HD::get_qr_code(base64_decode(self::OLD_LINK), 'link_to_old.png');

        $defs = array();
        Control_Factory::add_label($defs, TR::t('required_firmware'), TR::t('err_required_firmware'));
        Control_Factory::add_label($defs, "Dune Product ID:",  get_product_id());
        Control_Factory::add_label($defs, "Dune Firmware:", get_raw_firmware_version());
        Control_Factory::add_label($defs, TR::t('download_link'), "");
        Control_Factory::add_vgap($defs, 20);
        if ($qr_code !== null) {
            HD::add_qr_code_defs($defs, $qr_code);
        }
        return Action_Factory::show_dialog($defs, $title);
    }

    /**
     * @return array
     */
    private function open_playlist_screen()
    {
        $media_url = Starnet_Edit_Playlists_Screen::make_callback_media_url_str(Starnet_Entry_Handler::ID,
            array(
                PARAM_END_ACTION => ACTION_RELOAD,
                PARAM_CANCEL_ACTION => RESET_CONTROLS_ACTION_ID,
                PARAM_EXTENSION => PLAYLIST_PATTERN,
                Starnet_Edit_Playlists_Screen::PARAM_ALLOW_ORDER => true,
            )
        );

        $actions[] = Action_Factory::open_folder($media_url, TR::t('entry_edit_playlists_title'));

        $current_playlist = $this->plugin->get_active_playlist_id() ? array(PARAM_PLAYLIST_ID => $this->plugin->get_active_playlist_id()) : null;
        $actions[] = User_Input_Handler_Registry::create_screen_action(
            Starnet_Edit_Playlists_Screen::ID,
            ACTION_INVALIDATE,
            null,
            $current_playlist);

        return Action_Factory::composite($actions);
    }

    /**
     * @param object $user_input
     * @param object $plugin_cookies
     * @return array|null
     */
    public function run_resume_state($user_input, &$plugin_cookies)
    {
        $action = $this->check_upgrade($user_input);
        if ($action !== null) {
            return $action;
        }

        hd_print_separator();
        hd_debug_print('LANUCH PLUGIN');

        $this->plugin->init_plugin(true);
        if ($this->plugin->get_all_playlists_count() === 0) {
            hd_debug_print('No playlists found. Open playlists page');
            return $this->open_playlist_screen();
        }

        $is_mandatory_playback = (int)safe_get_value($user_input, PARAM_MANDATORY_PLAYBACK);
        $auto_resume = $this->plugin->get_parameter(PARAM_AUTO_RESUME, SwitchOnOff::off);
        $auto_play = $this->plugin->get_parameter(PARAM_AUTO_PLAY, SwitchOnOff::off);
        hd_debug_print("Play button used: $is_mandatory_playback");
        hd_debug_print("Auto play:        $auto_play");
        hd_debug_print("Auto resume:      $auto_resume");

        $resume_owner = safe_get_value(get_resume_state_assoc(), 'plugin_name', '');
        hd_debug_print("Resume owner:     $resume_owner");

        $archive_tm = -1;
        $resume_is_favorite = 0;
        $mode = '';
        if ($user_input->action_id === self::ACTION_LAUNCH
            && ($is_mandatory_playback === 1 || SwitchOnOff::to_bool($auto_play))) {
            hd_debug_print('LANUCH PLUGIN AUTO PLAY MODE');
            $resume_state = get_resume_state_assoc();
            $mode = safe_get_value($resume_state, 'mode');
            $resume_group = safe_get_value($resume_state, 'plugin_tv_group', '');
            $resume_channel = safe_get_value($resume_state, 'plugin_tv_channel', '');
            $resume_is_favorite = safe_get_value($resume_state, 'plugin_tv_is_favorite', 0);
            $archive_tm = safe_get_value($resume_state, 'plugin_tv_archive_tm', -1);
            $simple_start = false;
        } else if ($user_input->action_id === self::ACTION_AUTO_RESUME) {
            if (!SwitchOnOff::to_bool($auto_resume)) {
                return null;
            }
            hd_debug_print('LANUCH PLUGIN AUTO RESUME MODE');
            $mode = safe_get_value($user_input, 'resume_mode');
            $resume_group = safe_get_value($user_input, 'resume_tv_group', '');
            $resume_channel = safe_get_value($user_input, 'resume_tv_channel', '');
            $resume_is_favorite = safe_get_value($user_input, 'resume_tv_is_favorite', 0);
            $archive_tm = safe_get_value($user_input, 'resume_tv_archive_tm', -1);
            $simple_start = false;
        } else {
            $simple_start = true;
        }

        if ($simple_start) {
            return $this->simple_start($plugin_cookies);
        }

        // $user_input:
        // handler_id => entry_handler
        // control_id => plugin_entry
        // action_id => auto_resume
        // mandatory_playback => 1
        // resume_mode => PLUGIN_TV_PLAYBACK
        // resume_media_url => {"channel_id":"213","group_id":"Общие"} // only in classic!
        // resume_media_url => {"screen_id":"vod_series","movie_id":"121681","episode_id":"121681"} // only for VOD
        // resume_tv_group => Общие
        // resume_tv_channel => 14035
        // resume_tv_is_favorite => 0
        // resume_tv_archive_tm => -1
        // resume_tv_trick_play => 0
        // resume_tv_trick_play_duration => -1
        // resume_vod_series_ndx => 0
        // play_mode => none
        // selected_media_url => tv_groups
        // orig_selected_media_url => tv_groups

        $is_playlist_changed = $this->plugin->get_parameter(PARAM_LAST_PLAYLIST) !== $this->plugin->get_active_playlist_id();
        hd_debug_print('current playlist changed: ' . var_export($is_playlist_changed, true));

        $is_owner = strpos($resume_owner, get_plugin_name()) !== false;
        if ($archive_tm !== -1 && (time() - $archive_tm) > 259200) {
            $archive_tm = -1;
        }

        $media_url = null;
        if (!empty($resume_group) && !empty($resume_channel)) {
            $media_url = MediaURL::decode();
            $media_url->{PARAM_GROUP_ID} = $resume_group;
            $media_url->{PARAM_CHANNEL_ID} = $resume_channel;
            $media_url->{PARAM_IS_FAVORITE} = $resume_is_favorite;
            $media_url->{PARAM_ARCHIVE_TM} = $archive_tm;
            hd_debug_print("MediaUrl" . $media_url, true);
        }

        if (!$this->plugin->load_channels($plugin_cookies)) {
            return $this->show_error($plugin_cookies);
        }

        if (!$is_playlist_changed && $is_owner && $mode === "PLUGIN_TV_PLAYBACK") {
            hd_debug_print('action: continue TV playback', true);
            return Action_Factory::tv_play($media_url);
        }

        if ($is_owner && $mode === "PLUGIN_VOD_PLAYBACK") {
            // the url of a VOD screen keeps the playlist of the movie, an url without it belongs to the active playlist
            $resume_url = MediaURL::decode(safe_get_value($user_input, 'resume_media_url'));
            $vod_playlist_id = safe_get_value($resume_url, PARAM_PLAYLIST_ID);
            hd_debug_print("VOD playlist: $vod_playlist_id");
            if ((!empty($vod_playlist_id) || !$is_playlist_changed) && $this->plugin->select_vod_playlist($vod_playlist_id)) {
                $vod_info = $this->plugin->vod->get_vod_info($resume_url);
                if ($vod_info !== null) {
                    hd_debug_print('action: continue VOD playback', true);
                    return Action_Factory::vod_play($vod_info);
                }
            }
        }

        if (!$is_playlist_changed && (SwitchOnOff::to_bool($auto_play) || $is_mandatory_playback === 1)) {
            hd_debug_print('action: TV playback', true);
            return Action_Factory::tv_play($media_url);
        }

        return $this->simple_start($plugin_cookies, false);
    }

    /**
     * @param object $plugin_cookies
     * @param bool $load_channels
     * @return array
     */
    public function simple_start(&$plugin_cookies, $load_channels = true)
    {
        $playlist_first = $this->plugin->get_parameter(PARAM_PLAYLIST_FIRST, SwitchOnOff::off);
        hd_debug_print('action: Simple start', true);
        if ($playlist_first === SwitchOnOff::on) {

            $actions[] = Action_Factory::open_folder(
                Starnet_Edit_Playlists_Screen::ID,
                TR::t('setup_channels_src_choose_playlists'));

            $actions[] = User_Input_Handler_Registry::create_screen_action(Starnet_Edit_Playlists_Screen::ID,
                ACTION_INVALIDATE,
                null,
                array(PARAM_PLAYLIST_ID => $this->plugin->get_active_playlist_id()));
            return Action_Factory::composite($actions);
        }

        if ($load_channels && !$this->plugin->load_channels($plugin_cookies)) {
            return $this->show_error($plugin_cookies);
        }

        return Action_Factory::open_folder(Starnet_Tv_Groups_Screen::ID, $this->plugin->get_plugin_title());
    }

    /**
     * @param object $plugin_cookies
     * @return array
     */
    public function show_error($plugin_cookies)
    {
        $actions[] = Action_Factory::invalidate_all_folders($plugin_cookies);
        $actions[] = Action_Factory::open_folder(
            Starnet_Tv_Groups_Screen::ID,
            $this->plugin->get_plugin_title(),
            null,
            null,
            Action_Factory::show_title_dialog(TR::t('err_load_playlist'), Dune_Last_Error::get_last_error(LAST_ERROR_PLAYLIST))
        );
        return Action_Factory::composite($actions);
    }

    /**
     * @param object $user_input
     * @return array|null
     */
    protected function handle_protect_action($user_input)
    {
        hd_debug_print(null, true);

        if (!isset($user_input->{PARAM_ACTION})) {
            return null;
        }

        $action = json_decode($user_input->{PARAM_ACTION});
        if (empty($action)) {
            $action = $user_input->{PARAM_ACTION};
        } else {
            $action = $action->params->{PARAM_ACTION};
        }

        $pass_settings = $this->plugin->get_parameter(PARAM_SETTINGS_PASSWORD);
        if (!empty($pass_settings) && $pass_settings != $user_input->pass || is_null($action)) {
            return Action_Factory::show_error(false, TR::t('err_bad_pass'));
        }

        switch ($action) {
            case self::ACTION_CALL_PLAYLIST_SETTINGS:
                if (!$this->plugin->init_playlist_settings_db()) {
                    return Action_Factory::show_title_dialog(TR::t('error'), TR::t('err_init_database'));
                }
                return Action_Factory::open_folder(
                    Starnet_Setup_Playlist_Screen::make_controls_media_url_str(static::ID), TR::t('setup_playlist'));

            case self::ACTION_CALL_PLUGIN_SETTINGS:
                return Action_Factory::open_folder(
                    Starnet_Setup_Plugin_Screen::make_controls_media_url_str(static::ID), TR::t('entry_setup'));

            case self::ACTION_CALL_PLAYLIST_SCREEN:
                return $this->open_playlist_screen();
        }

        return null;
    }
}
