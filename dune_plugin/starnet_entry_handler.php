<?php

require_once 'lib/user_input_handler_registry.php';

class Starnet_Entry_Handler implements User_Input_Handler
{
    const ID = 'entry';

    private $plugin;

    /**
     * @return string
     */
    public static function get_handler_id()
    {
        return static::ID . '_handler';
    }

    /**
     * @param Default_Dune_Plugin $plugin
     */
    public function __construct(Default_Dune_Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    /**
     * @param $user_input
     * @param $plugin_cookies
     * @return array|null
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, LOG_LEVEL_DEBUG);
        dump_input_handler($user_input);

        if (!isset($user_input->control_id)) {
            return null;
        }

        switch ($user_input->control_id) {
            case 'do_reboot':
                hd_debug_print("do reboot", LOG_LEVEL_DEBUG);
                return Action_Factory::restart(true);

            case 'power_off':
                hd_debug_print("do power off", LOG_LEVEL_DEBUG);
                if (is_apk()) {
                    return Action_Factory::show_title_dialog(TR::t('entry_not_available'));
                }
                return array(send_ir_code(GUI_EVENT_DISCRETE_POWER_OFF));

            case 'do_setup':
                hd_debug_print("do setup", LOG_LEVEL_DEBUG);
                return Action_Factory::open_folder(Starnet_Setup_Screen::ID, TR::t('entry_setup'));

            case 'do_channels_setup':
                hd_debug_print("do channels setup", LOG_LEVEL_DEBUG);
                $media_url_str = MediaURL::make(array('screen_id' => Starnet_Playlists_Setup_Screen::ID, 'source_window_id' => self::ID));
                return Action_Factory::open_folder($media_url_str, TR::t('tv_screen_playlists_setup'));

            case 'do_send_log':
                hd_debug_print("do_send_log", LOG_LEVEL_DEBUG);
                $error_msg = '';
                $msg = HD::send_log_to_developer($this->plugin->plugin_info['app_version'], $error_msg) ? TR::t('entry_log_sent') : TR::t('entry_log_not_sent');
                return Action_Factory::show_title_dialog($msg, null, $error_msg);

            case 'do_clear_epg':
                $this->plugin->epg_man->init_cache_dir();
                $this->plugin->epg_man->clear_all_epg_cache();
                $this->plugin->tv->unload_channels();
                $action = Action_Factory::show_title_dialog(TR::t('entry_epg_cache_cleared'));
                if (HD::rows_api_support()) {
                    $action = Action_Factory::clear_rows_info_cache($action);
                }

                return $action;

            case 'plugin_entry':
                if (!isset($user_input->action_id)) {
                    break;
                }

                hd_debug_print("plugin_entry $user_input->action_id", LOG_LEVEL_DEBUG);
                clearstatcache();
                $this->plugin->epg_man->init_cache_dir();
                switch ($user_input->action_id) {
                    case 'launch':
                        if (!is_newer_versions()) {
                            return  Action_Factory::show_error(true, TR::t('err_too_old_player'),
                                array(
                                    TR::load_string('err_too_old_player'),
                                    "Dune Product ID: " .get_product_id(),
                                    "Dune Firmware: " . get_raw_firmware_version(),
                                    "Dune Serial: " . get_serial_number(),
                                ));
                        }

                        if ($this->plugin->get_playlists()->size() === 0) {
                            return User_Input_Handler_Registry::create_action($this, 'do_setup');
                        }

                        $this->plugin->clear_playlist_cache();
                        if ((int)$user_input->mandatory_playback === 1
                            || (isset($plugin_cookies->auto_play) && $plugin_cookies->auto_play === SetupControlSwitchDefs::switch_on)) {
                            hd_debug_print("launch play", LOG_LEVEL_DEBUG);

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
                            hd_debug_print("action: launch open", LOG_LEVEL_DEBUG);
                            $action = Action_Factory::open_folder();
                        }

                        return $action;

                    case 'auto_resume':
                        if ((int)$user_input->mandatory_playback !== 1
                            || (isset($plugin_cookies->auto_resume) && $plugin_cookies->auto_resume === SetupControlSwitchDefs::switch_off)) {
                            break;
                        }

                        $this->plugin->clear_playlist_cache();
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

                        return Action_Factory::tv_play($media_url);

                    case 'update_epfs':
                        hd_debug_print("update_epfs", LOG_LEVEL_DEBUG);
                        return Starnet_Epfs_Handler::update_all_epfs($plugin_cookies, isset($user_input->first_run_after_boot) || isset($user_input->restore_from_sleep));

                    case 'uninstall':
                        $this->plugin->uninstall_plugin();
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
