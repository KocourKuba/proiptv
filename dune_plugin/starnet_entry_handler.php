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
    const ACTION_DO_CHANNELS_SETTINGS = 'do_channels_setup';
    const ACTION_DO_PLUGIN_SETTINGS = 'do_setup';
    const ACTION_DO_REBOOT = 'do_reboot';
    const ACTION_DO_SEND_LOG = 'do_send_log';
    const ACTION_DO_CLEAR_EPG = 'do_clear_epg';

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

        switch ($user_input->control_id) {
            case self::ACTION_DO_REBOOT:
                hd_debug_print("do reboot", true);
                return Action_Factory::restart(true);

            case self::ACTION_DO_PLUGIN_SETTINGS:
                hd_debug_print("do setup", true);
                $this->plugin->init_plugin();
                return $this->plugin->show_password_dialog($this, ACTION_SETTINGS);

            case ACTION_PASSWORD_APPLY:
                if ($this->plugin->get_parameter(PARAM_SETTINGS_PASSWORD) !== $user_input->pass) {
                    return null;
                }
                return User_Input_Handler_Registry::create_action($this, $user_input->param_action);

            case ACTION_SETTINGS:
                return Action_Factory::open_folder(Starnet_Setup_Screen::ID, TR::t('entry_setup'));

            case self::ACTION_DO_CHANNELS_SETTINGS:
                hd_debug_print("do setup", true);
                $this->plugin->init_plugin();
                return $this->plugin->show_password_dialog($this, ACTION_CHANNELS_SETTINGS);

            case ACTION_CHANNELS_SETTINGS:
                hd_debug_print("do channels setup", true);
                $this->plugin->init_plugin();
                return Action_Factory::open_folder(Starnet_Playlists_Setup_Screen::ID, TR::t('tv_screen_playlists_setup'));

            case self::ACTION_DO_SEND_LOG:
                hd_debug_print("do_send_log", true);
                $error_msg = '';
                $msg = HD::send_log_to_developer($this->plugin->plugin_info['app_version'], $error_msg) ? TR::t('entry_log_sent') : TR::t('entry_log_not_sent');
                return Action_Factory::show_title_dialog($msg, null, $error_msg);

            case self::ACTION_DO_CLEAR_EPG:
                $this->plugin->init_plugin();
                $this->plugin->get_epg_manager()->clear_all_epg_cache();
                $this->plugin->init_epg_manager();
                $this->plugin->tv->unload_channels();
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
                if (!is_newer_versions()) {
                    return  Action_Factory::show_error(true, TR::t('err_too_old_player'),
                        array(
                            TR::load_string('err_too_old_player'),
                            "Dune Product ID: " .get_product_id(),
                            "Dune Firmware: " . get_raw_firmware_version(),
                            "Dune Serial: " . get_serial_number(),
                        ));
                }

                switch ($user_input->action_id) {
                    case self::ACTION_LAUNCH:

                        $this->plugin->init_plugin(true);
                        if ($this->plugin->get_playlists()->size() === 0) {
                            return User_Input_Handler_Registry::create_action($this, 'do_setup');
                        }

                        $this->plugin->tv->load_channels();
                        if ((int)$user_input->mandatory_playback === 1
                            || (isset($plugin_cookies->auto_play) && $plugin_cookies->auto_play === SetupControlSwitchDefs::switch_on)) {
                            hd_debug_print("launch play", true);

                            $media_url = null;
                            if (file_exists('/config/resume_state.properties')) {
                                $resume_state = parse_ini_file('/config/resume_state.properties', 0, INI_SCANNER_RAW);

                                if (strpos($resume_state['plugin_name'], get_plugin_name()) !== false) {
                                    $media_url = MediaURL::decode();
                                    $media_url->is_favorite = $resume_state['plugin_tv_is_favorite'];
                                    $media_url->group_id = $resume_state['plugin_tv_is_favorite'] ? Starnet_Tv_Favorites_Screen::ID : $resume_state['plugin_tv_group'];
                                    $media_url->channel_id = $resume_state['plugin_tv_channel'];
                                    $media_url->archive_tm = ((time() - $resume_state['plugin_tv_archive_tm']) < 259200) ? $resume_state['plugin_tv_archive_tm'] : -1;
                                }
                            }
                            $action = Action_Factory::tv_play($media_url);
                        } else {
                            hd_debug_print("action: launch open", true);
                            $action = Action_Factory::open_folder(
                                Starnet_Tv_Groups_Screen::ID, $this->plugin->create_plugin_title());
                        }

                        return $action;

                    case self::ACTION_LAUNCH_VOD:
                        $this->plugin->init_plugin();
                        if ($this->plugin->get_playlists()->size() === 0) {
                            return User_Input_Handler_Registry::create_action($this, 'do_setup');
                        }

                        $this->plugin->tv->load_channels();
                        if ($this->plugin->vod) {
                            return Action_Factory::open_folder(Starnet_Vod_Category_List_Screen::get_media_url_string(VOD_GROUP_ID));
                        }

                        return Action_Factory::show_error(false, TR::t('err_vod_not_available'));

                    case self::ACTION_AUTO_RESUME:
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
                            }
                        }

                        $this->plugin->tv->load_channels();
                        return Action_Factory::tv_play($media_url);

                    case self::ACTION_UPDATE_EPFS:
                        $this->plugin->init_plugin();
                        $this->plugin->tv->load_channels();
                        hd_debug_print("update_epfs", true);
                        return Starnet_Epfs_Handler::update_all_epfs($plugin_cookies,
                            isset($user_input->first_run_after_boot) || isset($user_input->restore_from_sleep));

                    case self::ACTION_INSTALL:
                    case self::ACTION_UPDATE:
                        $this->plugin->upgrade_parameters($plugin_cookies);
                        break;

                    case self::ACTION_UNINSTALL:
                        $this->plugin->init_plugin();
                        $this->plugin->get_epg_manager()->clear_all_epg_cache();
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
}
