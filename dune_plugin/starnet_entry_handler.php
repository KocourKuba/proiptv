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
            case 'do_reboot':
                hd_debug_print("do reboot", true);
                return Action_Factory::restart(true);

            case 'do_setup':
                hd_debug_print("do setup", true);
                $this->plugin->init_plugin();
                return Action_Factory::open_folder(Starnet_Setup_Screen::ID, TR::t('entry_setup'));

            case 'do_channels_setup':
                hd_debug_print("do channels setup", true);
                $this->plugin->init_plugin();
                return Action_Factory::open_folder(Starnet_Playlists_Setup_Screen::ID, TR::t('tv_screen_playlists_setup'));

            case 'do_send_log':
                hd_debug_print("do_send_log", true);
                $error_msg = '';
                $msg = HD::send_log_to_developer($this->plugin->plugin_info['app_version'], $error_msg) ? TR::t('entry_log_sent') : TR::t('entry_log_not_sent');
                return Action_Factory::show_title_dialog($msg, null, $error_msg);

            case 'do_clear_epg':
                $this->plugin->init_plugin();
                $this->plugin->get_epg_manager()->clear_all_epg_cache();
                $this->plugin->init_epg_manager();
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

                hd_debug_print("plugin_entry $user_input->action_id");
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

                        $this->plugin->init_plugin(true);
                        if ($this->plugin->get_playlists()->size() === 0) {
                            return User_Input_Handler_Registry::create_action($this, 'do_setup');
                        }

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

                    case 'auto_resume':
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

                        return Action_Factory::tv_play($media_url);

                    case 'update_epfs':
                        $this->plugin->init_plugin();
                        hd_debug_print("update_epfs", true);
                        return Starnet_Epfs_Handler::update_all_epfs($plugin_cookies,
                            isset($user_input->first_run_after_boot) || isset($user_input->restore_from_sleep));

                    case 'uninstall':
                        $this->plugin->init_plugin();
                        $this->plugin->get_epg_manager()->clear_all_epg_cache();
                        Default_Archive::clear_cache();
                        break;

                    case 'update':
                    case 'install':
                        $this->plugin->upgrade_parameters($plugin_cookies);
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
