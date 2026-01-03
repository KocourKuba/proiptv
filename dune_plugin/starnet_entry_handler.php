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

class Starnet_Entry_Handler implements User_Input_Handler
{
    const ID = 'entry';

    const ACTION_PLUGIN_ENTRY = 'plugin_entry';
    const ACTION_LAUNCH = 'launch';
    const ACTION_LAUNCH_VOD = 'launch_vod';
    const ACTION_AUTO_RESUME = 'auto_resume';
    const ACTION_UPDATE_EPFS = 'update_epfs';
    const ACTION_INSTALL = 'install';
    const ACTION_UNINSTALL = 'uninstall';
    const ACTION_CONTINUE_UNINSTALL = 'continue_uninstall';
    const ACTION_UPDATE = 'update';
    const ACTION_CALL_PLUGIN_SETTINGS = 'call_plugin_setup'; // this action coded in manifest
    const ACTION_CALL_PLAYLIST_SETTINGS = 'call_playlist_settings'; // this action coded in manifest
    const ACTION_CALL_PLAYLIST_SCREEN = 'call_playlists_setup'; // this action coded in manifest
    const ACTION_CALL_BACKUP_SETTINGS = 'call_backup'; // this action coded in manifest
    const ACTION_CALL_XMLTV_SOURCES_SCREEN = 'call_xmltv_setup'; // this action coded in manifest
    const ACTION_CALL_REBOOT = 'call_reboot'; // this action coded in manifest
    const ACTION_CALL_SEND_LOG = 'call_send_log'; // this action coded in manifest
    const ACTION_CALL_CLEAR_ALL_EPG = 'call_clear_all_epg'; // this action coded in manifest
    const ACTION_CONFIRM_BACKUP_DLG = 'create_backup';
    const ACTION_RUN_PLAYLIST_SCREEN = 'playlist_screen';
    const OLD_LINK = "aHR0cHM6Ly9naXRodWIuY29tL0tvY291ckt1YmEvcHJvaXB0di9yZWxlYXNlcy9kb3dubG9hZC81LjEuOTYyL2R1bmVfcGx1Z2luX3Byb2lwdHYuNS4xLjk2Mi56aXA=";

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
        dump_input_handler($user_input, true);

        if (!isset($user_input->control_id)) {
            hd_debug_print("user input control id not set");
            return null;
        }

        hd_debug_print("user input control: $user_input->control_id");

        if (!is_r22_or_higher()) {
            hd_debug_print("Too old Dune HD firmware! " . get_raw_firmware_version());
            return $this->show_old_player(TR::t('err_too_old_player'));
        }

        if (!class_exists('SQLite3')) {
            hd_debug_print("No SQLite3 support! " . get_raw_firmware_version());
            return $this->show_old_player(TR::t('err_no_sqlite'));
        }

        switch ($user_input->control_id) {
            case self::ACTION_CALL_REBOOT:
                return Action_Factory::restart(true);

            case self::ACTION_CALL_PLUGIN_SETTINGS:
                $this->plugin->init_plugin();
                return $this->plugin->show_protect_settings_dialog($this,
                    Action_Factory::open_folder(Starnet_Setup_Screen::make_controls_media_url_str(static::ID), TR::t('entry_setup')));

            case self::ACTION_CALL_PLAYLIST_SETTINGS:
                $this->plugin->init_plugin();
                if (!$this->plugin->init_playlist_db()) {
                    return Action_Factory::show_title_dialog(TR::t('err_error'), TR::t('err_init_database'));
                }
                return $this->plugin->show_protect_settings_dialog($this,
                    Action_Factory::open_folder(Starnet_Setup_Playlist_Screen::make_controls_media_url_str(static::ID), TR::t('setup_playlist')));

            case ACTION_PASSWORD_APPLY:
                return $this->plugin->apply_protect_settings_dialog($user_input);

            case self::ACTION_CALL_PLAYLIST_SCREEN:
                $this->plugin->init_plugin();
                return $this->plugin->show_protect_settings_dialog($this, $this->open_playlist_screen($plugin_cookies));

            case self::ACTION_CALL_XMLTV_SOURCES_SCREEN:
                $this->plugin->init_plugin();
                if (!$this->plugin->init_playlist_db()) {
                    return Action_Factory::show_title_dialog(TR::t('err_error'), TR::t('err_init_database'));
                }

                $this->plugin->init_user_agent();

                if (!$this->plugin->is_vod_playlist()
                    && (!$this->plugin->init_playlist_parser() || !$this->plugin->load_and_parse_m3u_iptv_playlist(true))) {
                    return Action_Factory::show_title_dialog(TR::t('err_load_playlist'), Dune_Last_Error::get_last_error(LAST_ERROR_PLAYLIST));
                }

                $this->plugin->init_epg_manager();

                $callback = Starnet_Edit_Xmltv_List_Screen::make_callback_media_url_str(
                    Starnet_Entry_Handler::ID,
                    array(
                        PARAM_END_ACTION => ACTION_RELOAD,
                        PARAM_CANCEL_ACTION => RESET_CONTROLS_ACTION_ID,
                    )
                );

                return $this->plugin->show_protect_settings_dialog($this, Action_Factory::open_folder($callback, TR::t('setup_edit_xmltv_list')));

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
                $msg = Default_Dune_Plugin::send_log_to_developer($this->plugin, $error_msg)
                    ? TR::t('entry_log_sent__3', get_dune_model(), get_product_id(), format_datetime('Y-m-d H:i', time()))
                    : TR::t('entry_log_not_sent');
                return Action_Factory::show_title_dialog(TR::t('entry_send_log'), $msg);

            case self::ACTION_CALL_CLEAR_ALL_EPG:
                $this->plugin->init_plugin();
                if ($this->plugin->get_all_playlists_count() === 0 || !$this->plugin->init_playlist_db()) break;

                hd_debug_print(null, true);
                $this->plugin->init_epg_manager();
                Epg_Manager_Json::clear_epg_files();
                Epg_Manager_Xmltv::clear_epg_files();
                $this->plugin->reset_channels_loaded();
                $actions[] = Action_Factory::clear_rows_info_cache();
                $actions[] = Action_Factory::show_title_dialog(TR::t('entry_epg_cache_cleared'));
                return Action_Factory::composite($actions);

            case ACTION_FORCE_OPEN:
                hd_debug_print_separator();
                hd_debug_print("FORCE LANUCH PLUGIN");
                hd_debug_print_separator();

                $this->plugin->init_plugin(true);
                if ($this->plugin->get_all_playlists_count() === 0 || !$this->plugin->init_playlist_db()) {
                    return $this->open_playlist_screen($plugin_cookies);
                }

                if (!$this->plugin->init_playlist_parser() || !$this->plugin->load_and_parse_m3u_iptv_playlist(true)) {
                    return $this->open_playlist_screen($plugin_cookies);
                }

                hd_debug_print("action: launch open", true);
                $actions[] = Action_Factory::invalidate_all_folders($plugin_cookies);
                $actions[] = Action_Factory::open_folder(Starnet_Tv_Groups_Screen::ID, $this->plugin->get_plugin_title());
                $actions[] = User_Input_Handler_Registry::create_screen_action(Starnet_Tv_Groups_Screen::ID, ACTION_RELOAD);
                return Action_Factory::composite($actions);

            case self::ACTION_CONFIRM_BACKUP_DLG:
                hd_debug_print("Call select backup folder");
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
                if (Default_Dune_Plugin::do_backup_settings($this->plugin, $data->{PARAM_FILEPATH}) === false) {
                    return Action_Factory::show_title_dialog(TR::t('err_error'), TR::t('err_backup'));
                }

                $actions[] = Action_Factory::show_title_dialog(TR::t('setup_copy_done'));
                $actions[] = User_Input_Handler_Registry::create_action(
                    $this,
                    self::ACTION_PLUGIN_ENTRY,
                    null,
                    array(PARAM_ACTION_ID => $data->{PARAM_ACTION_ID}, PARAM_MANDATORY_PLAYBACK => 0)
                );
                return Action_Factory::composite($actions);

            case ACTION_RELOAD:
                $first_run = isset($user_input->first_run_after_boot) || isset($user_input->restore_from_sleep);
                $this->plugin->load_channels($plugin_cookies, true);
                $actions[] = Action_Factory::refresh_entry_points();
                $actions[] = Starnet_Epfs_Handler::update_epfs_file($plugin_cookies, $first_run);
                return Action_Factory::composite($actions);

            case self::ACTION_CONTINUE_UNINSTALL:
                $action = color_palette_restore();
                if ($action === null) break;

                hd_debug_print("Palette restored");
                return Action_Factory::show_title_dialog(TR::t('setup_settings_patch_palette'), TR::t('setup_patch_success'));

            case self::ACTION_PLUGIN_ENTRY:
                if (!isset($user_input->action_id)) {
                    break;
                }

                hd_debug_print("plugin_entry $user_input->action_id");

                switch ($user_input->action_id) {
                    case self::ACTION_LAUNCH:
                    case self::ACTION_AUTO_RESUME:
                        return $this->run_resume_state($user_input, $plugin_cookies);

                    case self::ACTION_LAUNCH_VOD:
                        $action = $this->check_upgrade($user_input);
                        if ($action !== null) {
                            return $action;
                        }

                        hd_debug_print_separator();
                        hd_debug_print("LANUCH PLUGIN VOD");
                        hd_debug_print_separator();

                        $this->plugin->init_plugin(true);
                        if ($this->plugin->get_all_playlists_count() === 0 || !$this->plugin->init_playlist_db()) {
                            return $this->open_playlist_screen($plugin_cookies);
                        }

                        if (!$this->plugin->init_playlist_parser() || !$this->plugin->load_and_parse_m3u_iptv_playlist(true)) {
                            return $this->open_playlist_screen($plugin_cookies);
                        }

                        if ($this->plugin->load_channels($plugin_cookies)
                            && $this->plugin->is_vod_enabled()
                            && SwitchOnOff::to_bool($plugin_cookies->{PARAM_SHOW_VOD_ICON})) {
                            $actions[] = Action_Factory::invalidate_all_folders($plugin_cookies);
                            $actions[] = Action_Factory::open_folder(Default_Dune_Plugin::get_group_media_url_str(VOD_GROUP_ID));
                            return Action_Factory::composite($actions);
                        }

                        return Action_Factory::show_error(false, TR::t('err_vod_not_available'));

                    case self::ACTION_UPDATE_EPFS:
                        $first_run = isset($user_input->first_run_after_boot) || isset($user_input->restore_from_sleep);
                        $this->plugin->load_channels($plugin_cookies);
                        $actions[] = Action_Factory::refresh_entry_points();
                        $actions[] = Starnet_Epfs_Handler::update_epfs_file($plugin_cookies, $first_run);
                        return Action_Factory::composite($actions);

                    case self::ACTION_UNINSTALL:
                        Default_Archive::clear_cache();
                        if (!color_palette_check()) break;

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

    private function show_old_player($title) {
        $qr_code = get_temp_path("link_to_old.jpg");
        $url = "https://api.qrserver.com/v1/create-qr-code/?size=450x450&format=jpg&data=" . urlencode(base64_decode(self::OLD_LINK));
        Curl_Wrapper::getInstance()->download_file($url, $qr_code);

        $defs = array();
        Control_Factory::add_label($defs, TR::t('required_firmware'), TR::t('err_required_firmware'));
        Control_Factory::add_label($defs, "Dune Product ID:",  get_product_id());
        Control_Factory::add_label($defs, "Dune Firmware:", get_raw_firmware_version());
        Control_Factory::add_label($defs, TR::t('download_link'), "");
        Control_Factory::add_vgap($defs, 20);
        Control_Factory::add_smart_label($defs, "", "<gap width=25/><icon width=450 height=450>$qr_code</icon>");
        Control_Factory::add_vgap($defs, 450);
        return Action_Factory::show_dialog($defs, $title);
    }

    /**
     * @param $plugin_cookies
     * @return array
     */
    private function open_playlist_screen($plugin_cookies)
    {
        $media_url = Starnet_Edit_Playlists_Screen::make_callback_media_url_str(Starnet_Entry_Handler::ID,
            array(
                PARAM_END_ACTION => ACTION_RELOAD,
                PARAM_CANCEL_ACTION => RESET_CONTROLS_ACTION_ID,
                PARAM_EXTENSION => PLAYLIST_PATTERN,
                Starnet_Edit_Playlists_Screen::PARAM_ALLOW_ORDER => true,
            )
        );

        $actions[] = Action_Factory::invalidate_all_folders($plugin_cookies);
        $actions[] = Action_Factory::open_folder($media_url, TR::t('setup_channels_src_edit_playlists'));
        return Action_Factory::composite($actions);
    }

    /**
     * @return array
     */
    public function run_resume_state($user_input, &$plugin_cookies)
    {
        $action = $this->check_upgrade($user_input);
        if ($action !== null) {
            return $action;
        }

        hd_debug_print_separator();
        hd_debug_print("LANUCH PLUGIN");

        $this->plugin->init_plugin(true);
        if ($this->plugin->get_all_playlists_count() === 0) {
            hd_debug_print("No playlists found. Open playlists page");
            return $this->open_playlist_screen($plugin_cookies);
        }

        if (!$this->plugin->init_playlist_db()) {
            return $this->open_playlist_screen($plugin_cookies);
        }

        if (!$this->plugin->init_playlist_parser() || !$this->plugin->load_and_parse_m3u_iptv_playlist(true)) {
            return Action_Factory::show_title_dialog(
                TR::t('err_load_playlist'),
                Dune_Last_Error::get_last_error(LAST_ERROR_PLAYLIST),
                $this->open_playlist_screen($plugin_cookies)
            );
        }

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

        $auto_play = false;
        $mandatory_playback = (int)safe_get_member($user_input, PARAM_MANDATORY_PLAYBACK);
        $auto_resume = safe_get_member($plugin_cookies,PARAM_COOKIE_AUTO_RESUME);

        if ($user_input->action_id === self::ACTION_LAUNCH) {
            $auto_play = safe_get_member($plugin_cookies,PARAM_COOKIE_AUTO_PLAY);
            hd_debug_print("Play button used: $mandatory_playback");
            hd_debug_print("Auto play:        $auto_play");

            if ($mandatory_playback !== 1 && !SwitchOnOff::to_bool($auto_play)) {
                hd_debug_print("action: launch open", true);
                return Action_Factory::open_folder(Starnet_Tv_Groups_Screen::ID, $this->plugin->get_plugin_title());
            }
        } else if ($user_input->action_id === self::ACTION_AUTO_RESUME) {
            hd_debug_print("LANUCH PLUGIN AUTO RESUME MODE");
            hd_debug_print("Auto resume:      $auto_resume");
            if (!SwitchOnOff::to_bool($auto_resume)) {
                hd_debug_print("auto resume disabled");
                return null;
            }
        }

        hd_debug_print("launch resume state", true);
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

        $mode = safe_get_member($user_input, 'resume_mode');
        $resume_owner = strpos(safe_get_member($user_input, 'plugin_name', ''), get_plugin_name()) !== false;
        $media_url = MediaURL::decode();
        $media_url->is_favorite = safe_get_member($user_input, 'resume_tv_is_favorite');
        $media_url->group_id = safe_get_member($user_input, 'resume_tv_group');
        $media_url->channel_id = safe_get_member($user_input, 'resume_tv_channel');
        $archive_tm = safe_get_member($user_input, 'resume_tv_archive_tm');
        $media_url->archive_tm = ((time() - $archive_tm) < 259200) ? $archive_tm : -1;
        // Check if previous state is TV playback
        if ($resume_owner && $mode === "PLUGIN_TV_PLAYBACK") {
            hd_debug_print("Resumed media url: " . $media_url);
            return Action_Factory::tv_play($media_url);
        }

        if ($resume_owner && $mode === "PLUGIN_VOD_PLAYBACK") {
            $vod_info = $this->plugin->vod->get_vod_info(MediaURL::decode(safe_get_member($user_input, 'resume_media_url')));
            if ($vod_info !== null) {
                return Action_Factory::vod_play($vod_info);
            }
        }

        if ($auto_play || $mandatory_playback) {
            return Action_Factory::tv_play($media_url);
        }

        hd_debug_print("action: launch open", true);
        return Action_Factory::open_folder(Starnet_Tv_Groups_Screen::ID, $this->plugin->get_plugin_title());
    }
}
