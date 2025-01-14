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
    const ACTION_UPDATE = 'update';
    const ACTION_CALL_PLUGIN_SETTINGS = 'call_setup';
    const ACTION_CALL_PLAYLIST_SETTINGS = 'call_playlist_setup';
    const ACTION_CALL_XMLTV_SOURSES_SETTINGS = 'call_xmltv_setup';
    const ACTION_PLAYLIST_SETTINGS = 'channels_settings';
    const ACTION_XMLTV_SOURCES_SETTINGS = 'xmltv_settings';
    const ACTION_CALL_REBOOT = 'call_reboot';
    const ACTION_CALL_SEND_LOG = 'call_send_log';
    const ACTION_CALL_CLEAR_EPG = 'call_clear_epg';
    const OLD_LINK = "aHR0cHM6Ly9naXRodWIuY29tL0tvY291ckt1YmEvcHJvaXB0di9yZWxlYXNlcy9kb3dubG9hZC81LjEuOTYyL2R1bmVfcGx1Z2luX3Byb2lwdHYuNS4xLjk2Mi56aXA=";

    private $plugin;

    /**
     * @param Default_Dune_Plugin $plugin
     */
    public function __construct(Default_Dune_Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * @inheritDoc
     */
    public static function get_handler_id()
    {
        return static::ID . '_handler';
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        if (!isset($user_input->control_id)) {
            return null;
        }

        hd_debug_print("user input control: $user_input->control_id", true);

        if (!is_newer_versions()) {
            hd_debug_print("Too old Dune HD firmware! " . get_raw_firmware_version());
            return $this->show_old_player(TR::load_string('err_too_old_player'));
        }

        if (!class_exists('SQLite3')) {
            hd_debug_print("No SQLite3 support! " . get_raw_firmware_version());
            return $this->show_old_player(TR::load_string('err_no_sqlite'));
        }

        switch ($user_input->control_id) {
            case self::ACTION_CALL_REBOOT:
                return Action_Factory::restart(true);

            case self::ACTION_CALL_PLUGIN_SETTINGS:
                $this->plugin->init_plugin();
                return $this->plugin->show_protect_settings_dialog($this, ACTION_SETTINGS);

            case ACTION_SETTINGS:
                return Action_Factory::open_folder(Starnet_Setup_Screen::ID, TR::t('entry_setup'));

            case ACTION_PASSWORD_APPLY:
                if ($this->plugin->get_parameter(PARAM_SETTINGS_PASSWORD) !== $user_input->pass) {
                    return null;
                }
                return User_Input_Handler_Registry::create_action($this, $user_input->param_action);

            case self::ACTION_CALL_PLAYLIST_SETTINGS:
                $this->plugin->init_plugin();
                $this->plugin->init_playlist();
                return $this->plugin->show_protect_settings_dialog($this, self::ACTION_PLAYLIST_SETTINGS);

            case self::ACTION_PLAYLIST_SETTINGS:
                return $this->plugin->do_edit_list_screen(Starnet_Tv_Groups_Screen::ID,
                    Starnet_Edit_List_Screen::SCREEN_EDIT_PLAYLIST);

            case self::ACTION_CALL_XMLTV_SOURSES_SETTINGS:
                $this->plugin->init_plugin();
                $this->plugin->init_playlist();
                return $this->plugin->show_protect_settings_dialog($this, self::ACTION_XMLTV_SOURCES_SETTINGS);

            case self::ACTION_XMLTV_SOURCES_SETTINGS:
                return $this->plugin->do_edit_list_screen(Starnet_Tv_Groups_Screen::ID,
                    Starnet_Edit_List_Screen::SCREEN_EDIT_EPG_LIST);

            case self::ACTION_CALL_SEND_LOG:
                if (!is_newer_versions()) {
                    return Action_Factory::show_title_dialog(TR::t('entry_send_log'), null, TR::t('entry_log_not_sent_too_old'));
                }

                if (!LogSeverity::$is_debug) {
                    return Action_Factory::show_title_dialog(TR::t('entry_send_log'), null, TR::t('entry_log_not_enabled'));
                }

                $error_msg = '';
                $msg = HD::send_log_to_developer($this->plugin, $error_msg)
                    ? TR::t('entry_log_sent')
                    : TR::t('entry_log_not_sent');
                return Action_Factory::show_title_dialog(TR::t('entry_send_log'), null, $msg);

            case self::ACTION_CALL_CLEAR_EPG:
                $this->plugin->init_plugin();
                $this->plugin->init_epg_manager();
                $this->plugin->safe_clear_selected_epg_cache();
                $action = Action_Factory::show_title_dialog(TR::t('entry_epg_cache_cleared'));
                if (HD::rows_api_support()) {
                    $action = Action_Factory::clear_rows_info_cache($action);
                }

                return $action;

            case self::ACTION_PLUGIN_ENTRY:
                if (!isset($user_input->action_id)) {
                    break;
                }

                hd_debug_print("plugin_entry $user_input->action_id");

                switch ($user_input->action_id) {
                    case self::ACTION_LAUNCH:
                        hd_debug_print_separator();
                        hd_debug_print("LANUCH PLUGIN");
                        hd_debug_print_separator();

                        $this->plugin->init_plugin(true);
                        if ($this->plugin->get_playlists()->size() === 0) {
                            return $this->plugin->do_edit_list_screen(Starnet_Tv_Groups_Screen::ID,
                                Starnet_Edit_List_Screen::SCREEN_EDIT_PLAYLIST);
                        }

                        if ((int)$user_input->mandatory_playback === 1
                            || (isset($plugin_cookies->auto_play) && $plugin_cookies->auto_play === SetupControlSwitchDefs::switch_on)) {
                            hd_debug_print("launch auto play", true);

                            $media_url = null;
                            if (file_exists('/config/resume_state.properties')) {
                                $resume_state = parse_ini_file('/config/resume_state.properties', 0, INI_SCANNER_RAW);

                                if (strpos($resume_state['plugin_name'], get_plugin_name()) !== false) {
                                    $media_url = MediaURL::decode();
                                    $media_url->is_favorite = $resume_state['plugin_tv_is_favorite'];
                                    $media_url->group_id = $resume_state['plugin_tv_is_favorite'] ? Starnet_Tv_Favorites_Screen::ID : $resume_state['plugin_tv_group'];
                                    $media_url->channel_id = $resume_state['plugin_tv_channel'];
                                    $media_url->archive_tm = ((time() - $resume_state['plugin_tv_archive_tm']) < 259200) ? $resume_state['plugin_tv_archive_tm'] : -1;
                                    hd_debug_print("Auto play: " . $media_url);
                                }
                            }

                            if ($this->plugin->tv->load_channels($plugin_cookies) === 0) {
                                return Action_Factory::open_folder(
                                    Starnet_Tv_Groups_Screen::ID,
                                    $this->plugin->create_plugin_title(),
                                    null,
                                    null,
                                    Action_Factory::show_title_dialog(TR::t('err_load_playlist'), null, HD::get_last_error())
                                );
                            }

                            return Action_Factory::tv_play($media_url);
                        }

                        hd_debug_print("action: launch open", true);
                        return Action_Factory::open_folder(Starnet_Tv_Groups_Screen::ID, $this->plugin->create_plugin_title());

                    case self::ACTION_LAUNCH_VOD:
                        hd_debug_print_separator();
                        hd_debug_print("LANUCH PLUGIN VOD");
                        hd_debug_print_separator();

                        $this->plugin->init_plugin($plugin_cookies);
                        if ($this->plugin->get_playlists()->size() === 0) {
                            return User_Input_Handler_Registry::create_action($this, self::ACTION_CALL_PLUGIN_SETTINGS);
                        }

                        if ($this->plugin->vod_enabled && $plugin_cookies->{PARAM_SHOW_VOD_ICON} === SetupControlSwitchDefs::switch_on) {
                            $this->plugin->tv->load_channels($plugin_cookies);
                            return Action_Factory::open_folder(Starnet_Vod_Category_List_Screen::get_media_url_string(VOD_GROUP_ID));
                        }

                        return Action_Factory::show_error(false, TR::t('err_vod_not_available'));

                    case self::ACTION_AUTO_RESUME:
                        hd_debug_print_separator();
                        hd_debug_print("LANUCH PLUGIN AUTO RESUME MODE");
                        hd_debug_print_separator();

                        $this->plugin->init_plugin(true);
                        if ((int)$user_input->mandatory_playback !== 1
                            || (isset($plugin_cookies->auto_resume) && $plugin_cookies->auto_resume === SetupControlSwitchDefs::switch_off)) {
                            break;
                        }

                        $media_url = null;
                        if (file_exists('/config/resume_state.properties')) {
                            $resume_state = parse_ini_file('/config/resume_state.properties', 0, INI_SCANNER_RAW);

                            if (strpos($resume_state['plugin_name'], get_plugin_name()) !== false) {
                                $media_url = MediaURL::decode();
                                $media_url->is_favorite = $resume_state['plugin_tv_is_favorite'];
                                $media_url->group_id = $resume_state['plugin_tv_is_favorite'] ? Starnet_Tv_Favorites_Screen::ID : $resume_state['plugin_tv_group'];
                                $media_url->channel_id = $resume_state['plugin_tv_channel'];
                                $media_url->archive_tm = ((time() - $resume_state['plugin_tv_archive_tm']) < 259200) ? $resume_state['plugin_tv_archive_tm'] : -1;
                                hd_debug_print("Auto resume: " . $media_url);
                            }

                            if ($this->plugin->tv->load_channels($plugin_cookies) === 0) {
                                $post_action = Action_Factory::show_title_dialog(TR::t('err_load_playlist'), null, HD::get_last_error());
                                return Action_Factory::open_folder(
                                    Starnet_Tv_Groups_Screen::ID,
                                    $this->plugin->create_plugin_title(),
                                    null,
                                    null,
                                    $post_action
                                );
                            }

                            return Action_Factory::tv_play($media_url);
                        }

                        hd_debug_print("auto resume channel not exist. action: launch open", true);
                        return Action_Factory::open_folder(Starnet_Tv_Groups_Screen::ID, $this->plugin->create_plugin_title());

                    case self::ACTION_UPDATE_EPFS:
                        $this->plugin->init_plugin();
                        $this->plugin->tv->load_channels($plugin_cookies);
                        return Starnet_Epfs_Handler::update_all_epfs($plugin_cookies,
                            isset($user_input->first_run_after_boot) || isset($user_input->restore_from_sleep));

                    case self::ACTION_INSTALL:
                    case self::ACTION_UPDATE:
                        $this->plugin->upgrade_parameters();
                        break;

                    case self::ACTION_UNINSTALL:
                        $this->plugin->init_plugin();
                        $this->plugin->init_epg_manager();
                        $this->plugin->safe_clear_selected_epg_cache();
                        Default_Archive::clear_cache();
                        break;

                    default:
                        break;
                }
                break;
            default:
                break;
        }

        return null;
    }

    private function show_old_player($title) {
        $qr_code = get_temp_path("link_to_old.jpg");
        $url = "https://api.qrserver.com/v1/create-qr-code/?size=450x450&format=jpg&data=" . urlencode(base64_decode(self::OLD_LINK));
        Curl_Wrapper::simple_download_file($url, $qr_code);

        $defs = array();
        Control_Factory::add_label($defs, TR::t('required_firmware'), TR::load_string('err_required_firmware'));
        Control_Factory::add_label($defs, "Dune Product ID:",  get_product_id());
        Control_Factory::add_label($defs, "Dune Firmware:", get_raw_firmware_version());
        Control_Factory::add_label($defs, TR::t('download_link'), "");
        Control_Factory::add_vgap($defs, 20);
        Control_Factory::add_smart_label($defs, "", "<gap width=25/><icon width=450 height=450>$qr_code</icon>");
        Control_Factory::add_vgap($defs, 450);
        return Action_Factory::show_dialog($title, $defs, true, 1000);
    }
}
