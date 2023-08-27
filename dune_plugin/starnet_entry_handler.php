<?php

require_once 'lib/user_input_handler_registry.php';

class Starnet_Entry_Handler implements User_Input_Handler
{
    const ID = 'entry';

    private $plugin;

    /**
     * @return string
     */
    public function get_handler_id()
    {
        return self::ID . '_handler';
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
        //dump_input_handler(__METHOD__, $user_input);

        if (!isset($user_input->control_id)) {
            return null;
        }

        switch ($user_input->control_id) {
            case 'do_reboot':
                hd_print(__METHOD__ . ": do reboot");
                return Action_Factory::restart(true);

            case 'power_off':
                hd_print(__METHOD__ . ": do power off");
                if (is_apk()) {
                    return Action_Factory::show_title_dialog(TR::t('entry_not_available'));
                }
                return array(send_ir_code(GUI_EVENT_DISCRETE_POWER_OFF));

            case 'do_setup':
                hd_print(__METHOD__ . ": do setup");
                return Action_Factory::open_folder(Starnet_Setup_Screen::ID, TR::t('entry_setup'));

            case 'do_channels_setup':
                hd_print(__METHOD__ . ": do channels setup");
                return Action_Factory::open_folder(Starnet_Playlists_Setup_Screen::ID, TR::t('tv_screen_channels_setup'));

            case 'do_send_log':
                hd_print(__METHOD__ . ": do_send_log");
                $error_msg = '';
                $msg = HD::send_log_to_developer($plugin_cookies, $error_msg) ? TR::t('entry_log_sent') : TR::t('entry_log_not_sent') . " $error_msg";
                return Action_Factory::show_title_dialog($msg);

            case 'do_clear_epg':
                $this->plugin->epg_man->clear_all_epg_cache($plugin_cookies);
                $this->plugin->tv->unload_channels();
                return Action_Factory::clear_rows_info_cache(Action_Factory::show_title_dialog(TR::t('entry_epg_cache_cleared')));

            case 'plugin_entry':
                if (!isset($user_input->action_id)) {
                    break;
                }

                hd_print(__METHOD__ . ": plugin_entry $user_input->action_id");
                clearstatcache();
                Playback_Points::load_points(smb_tree::get_folder_info($plugin_cookies, PARAM_HISTORY_PATH));

                switch ($user_input->action_id) {
                    case 'launch':
                        if ($this->plugin->get_playlists()->size() === 0) {
                            hd_print(__METHOD__ . ": Open setup");
                            return Action_Factory::open_folder(Starnet_Playlists_Setup_Screen::ID, TR::t('tv_screen_channels_setup'));
                        }

                        //hd_print("auto_play: $plugin_cookies->auto_play");
                        if ((int)$user_input->mandatory_playback === 1
                            || (isset($plugin_cookies->auto_play) && $plugin_cookies->auto_play === SetupControlSwitchDefs::switch_on)) {
                            hd_print(__METHOD__ . ": launch play");

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
                            hd_print(__METHOD__ . ": action: launch open");
                            $action = Action_Factory::open_folder();
                        }

                        return $action;

                    case 'auto_resume':
                        //hd_print("auto_resume: $plugin_cookies->auto_resume");
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

                        return Action_Factory::tv_play($media_url);

                    case 'update_epfs':
                        hd_print(__METHOD__ . ": update_epfs");
                        return Starnet_Epfs_Handler::update_all_epfs($plugin_cookies, isset($user_input->first_run_after_boot) || isset($user_input->restore_from_sleep));
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
