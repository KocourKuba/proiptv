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

require_once 'lib/abstract_controls_screen.php';
require_once 'lib/user_input_handler.php';
require_once 'lib/m3u/KnownCatchupSourceTags.php';

///////////////////////////////////////////////////////////////////////////

class Starnet_Setup_Playlists_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'playlist_setup';

    const CONTROL_RESET_PLAYLIST_DLG = 'reset_playlist';
    const ACTION_RESET_PLAYLIST_DLG_APPLY = 'reset_playlist_apply';

    private $ret_idx = 4;

    ///////////////////////////////////////////////////////////////////////

    /**
     * Get MediaURL string representation (json encoded)
     *
     * @return false|string
     */
    public static function get_media_url_string($playlist_id)
    {
        return MediaURL::encode(array('screen_id' => static::ID, 'playlist_id' => $playlist_id));
    }

    /**
     * @inheritDoc
     */
    public function get_action_map(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        $actions[GUI_EVENT_KEY_TOP_MENU] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_TOP_MENU);
        $actions[GUI_EVENT_KEY_RETURN] = User_Input_Handler_Registry::create_action($this, GUI_EVENT_KEY_RETURN);
        return $actions;
    }

    /**
     * @inheritDoc
     */
    public function get_control_defs(MediaURL $media_url, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $defs = array();

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        $parent_media_url = MediaURL::decode($media_url);
        $playlist_id = isset($parent_media_url->playlist_id) ? $parent_media_url->playlist_id : $this->plugin->get_active_playlist_id();
        $params = $this->plugin->get_playlist_parameters($playlist_id);

        $type = safe_get_value($params, PARAM_TYPE);

        $uri = safe_get_value($params, PARAM_URI);

        //////////////////////////////////////
        // Name

        $name = safe_get_value($params, PARAM_NAME, basename($uri));
        Control_Factory::add_text_field($defs, $this, null, CONTROL_EDIT_NAME, TR::t('name'),
            $name, false, false, false, true, self::CONTROLS_WIDTH);

        if ($type === PARAM_PROVIDER) {
            $provider = $this->plugin->get_provider($playlist_id);
            if ($provider !== null) {
                Control_Factory::add_image_button($defs, $this, null, ACTION_EDIT_PROVIDER_DLG,
                    TR::t('edit_account'), TR::t('setup_change_settings'), get_image_path('folder.png'), self::CONTROLS_WIDTH);

                if ($provider->getConfigValue(PROVIDER_EXT_PARAMS) === true) {
                    Control_Factory::add_image_button($defs, $this, null, ACTION_EDIT_PROVIDER_EXT_DLG,
                        TR::t('edit_ext_account'), TR::t('setup_change_settings'), get_image_path('folder.png'), self::CONTROLS_WIDTH);
                    $this->ret_idx = 6;
                }
            }
        } else {
            //////////////////////////////////////
            // URI

            if ($type === PARAM_FILE) {
                $uri_str = HD::string_ellipsis($uri);
                Control_Factory::add_image_button($defs, $this, null, CONTROL_URL_PATH,
                    TR::t('playlist'), $uri_str, get_image_path('folder.png'), self::CONTROLS_WIDTH);
            } else if ($type === PARAM_LINK) {
                Control_Factory::add_text_field($defs, $this, null, CONTROL_URL_PATH, TR::t('playlist'),
                    $uri, false, false, false, true, self::CONTROLS_WIDTH);
            }

            //////////////////////////////////////
            // Type

            $opts[CONTROL_PLAYLIST_IPTV] = TR::t('edit_list_playlist_iptv');
            $opts[CONTROL_PLAYLIST_VOD] = TR::t('edit_list_playlist_vod');
            $playlist_type = safe_get_value($params, PARAM_PL_TYPE, CONTROL_PLAYLIST_IPTV);
            Control_Factory::add_combobox($defs, $this, null, CONTROL_EDIT_TYPE,
                TR::t('edit_list_playlist_type'), $playlist_type, $opts, self::CONTROLS_WIDTH, true);

            //////////////////////////////////////
            // ID Mapper

            $id_mapper = safe_get_value($params, PARAM_ID_MAPPER, CONTROL_DETECT_ID);
            $mapper_ops = Default_Dune_Plugin::get_id_detect_mapper();
            Control_Factory::add_combobox($defs, $this, null, CONTROL_DETECT_ID,
                TR::t('edit_list_playlist_detect_id'), $id_mapper, $mapper_ops, self::CONTROLS_WIDTH, true);
        }

        //////////////////////////////////////
        // Ext playlist settings

        Control_Factory::add_image_button($defs, $this, null, CONTROL_EXT_PARAMS,
            TR::t('setup_extended_setup'), TR::t('setup_change_settings'), get_image_path('settings.png'), self::CONTROLS_WIDTH);

        //////////////////////////////////////
        // reset playlist settings

        Control_Factory::add_image_button($defs, $this, null, self::CONTROL_RESET_PLAYLIST_DLG,
            TR::t('setup_channels_src_reset_playlist'), TR::t('clear'),
            get_image_path('brush.png'), self::CONTROLS_WIDTH);

        Control_Factory::add_vgap($defs, 10);

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        hd_debug_print(null, true);
        dump_input_handler($user_input);

        $sel_ndx = -1;
        $post_action = null;
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $playlist_id = isset($parent_media_url->playlist_id) ? $parent_media_url->playlist_id : $this->plugin->get_active_playlist_id();
        $params = $this->plugin->get_playlist_parameters($playlist_id);
        $type = safe_get_value($params, PARAM_TYPE);
        $uri = safe_get_value($params, PARAM_URI);
        $detect_id = safe_get_member($user_input, CONTROL_DETECT_ID);
        $pl_type = safe_get_member($user_input, CONTROL_EDIT_TYPE, CONTROL_PLAYLIST_IPTV);

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                if ($detect_id === CONTROL_DETECT_ID) {
                    return User_Input_Handler_Registry::create_action($this, CONTROL_DETECT_ID);
                }

                return Action_Factory::close_and_run(
                    User_Input_Handler_Registry::create_action_screen(
                        Starnet_Setup_Screen::ID,
                        RESET_CONTROLS_ACTION_ID,
                        null,
                        array('initial_sel_ndx' => $this->return_index)
                    )
                );

            case CONTROL_EXT_PARAMS:
                return Action_Factory::open_folder(
                    Starnet_Setup_Ext_Playlists_Screen::get_media_url_string($playlist_id, $this->ret_idx),
                    TR::t('setup_extended_setup')
                );

            case CONTROL_URL_PATH:
                if ($type === PARAM_FILE) {
                    $media_url_str = MediaURL::encode(
                        array(
                            'screen_id' => Starnet_Folder_Screen::ID,
                            'source_window_id' => static::ID,
                            'allow_network' => !is_limited_apk(),
                            'choose_file' => $uri,
                            'extension' => $user_input->extension,
                            'end_action' => ACTION_REFRESH_SCREEN,
                            'windowCounter' => 1,
                        )
                    );
                    return Action_Factory::open_folder($media_url_str, TR::t('setup_epg_xmltv_cache_caption'));
                }

                $this->plugin->set_playlist_parameter($playlist_id, PARAM_URI, $user_input->{$user_input->control_id});
                break;


            case ACTION_FILE_SELECTED:
                $data = MediaURL::decode($user_input->selected_data);
                $this->plugin->set_playlist_parameter($playlist_id, PARAM_URI, $data->filepath);
                break;

            case ACTION_EDIT_PROVIDER_DLG:
            case ACTION_EDIT_PROVIDER_EXT_DLG:
                return $this->plugin->show_protect_settings_dialog($this,
                    ($user_input->control_id === ACTION_EDIT_PROVIDER_DLG)
                        ? ACTION_DO_EDIT_PROVIDER
                        : ACTION_DO_EDIT_PROVIDER_EXT);

            case ACTION_DO_EDIT_PROVIDER:
            case ACTION_DO_EDIT_PROVIDER_EXT:
                $provider = $this->plugin->get_provider($playlist_id);
                if (is_null($provider)) {
                    break;
                }

                if ($user_input->control_id === ACTION_DO_EDIT_PROVIDER) {
                    hd_debug_print(pretty_json_format($provider));
                    return $this->plugin->do_edit_provider_dlg($this, $provider->getId(), $playlist_id);
                }

                if ($provider->request_provider_token()) {
                    return $this->plugin->do_edit_provider_ext_dlg($this, $provider->getId(), $playlist_id);
                }

                hd_debug_print("Can't get provider token");
                return Action_Factory::show_error(false, TR::t('err_incorrect_access_data'), array(TR::t('err_cant_get_token')));

            case ACTION_EDIT_PROVIDER_DLG_APPLY:
            case ACTION_EDIT_PROVIDER_EXT_DLG_APPLY:
                if ($user_input->control_id === ACTION_EDIT_PROVIDER_DLG_APPLY) {
                    $res = $this->plugin->apply_edit_provider_dlg($user_input);
                } else {
                    $res = $this->plugin->apply_edit_provider_ext_dlg($user_input);
                }

                if (is_array($res)) {
                    return $res;
                }

                break;

            case CONTROL_PLAYLIST_IPTV:
                if ($type !== PARAM_PROVIDER) {
                    $this->plugin->set_playlist_parameter($playlist_id, PARAM_PL_TYPE, $pl_type);
                }
                break;

            case CONTROL_DETECT_ID:
                $tmp_file = $uri;
                try
                {
                    if (empty($uri)) {
                        break;
                    }

                    if ($type === PARAM_LINK) {
                        $tmp_file = get_temp_path(Hashed_Array::hash($playlist_id));
                        if (!is_proto_http($params[PARAM_URI])) {
                            throw new Exception(TR::load('err_incorrect_url') . " '$uri'");
                        }

                        list($res, $log) = Curl_Wrapper::simple_download_file($uri, $tmp_file);
                        if (!$res) {
                            throw new Exception(TR::load('err_load_playlist') . " '$uri'\n\n" . $log);
                        }
                    }

                    $contents = file_get_contents($tmp_file, false, null, 0, 512);
                    if ($contents === false || strpos($contents, TAG_EXTM3U) === false) {
                        throw new Exception(TR::load('err_load_playlist') . " '$uri'\n\n$contents");
                    }

                    $parser = new M3uParser();
                    $parser->setPlaylist(safe_get_value($params, PARAM_URI), true);
                    if ($pl_type === CONTROL_PLAYLIST_IPTV) {
                        $detect_id = safe_get_member($user_input, CONTROL_DETECT_ID, CONTROL_DETECT_ID);
                        if ($detect_id === CONTROL_DETECT_ID) {
                            $db = new Sql_Wrapper(":memory:");
                            $db->exec("ATTACH DATABASE ':memory:' AS " . M3uParser::IPTV_DB);
                            $entries_cnt = $parser->parseIptvPlaylist($db);
                            if (empty($entries_cnt)) {
                                throw new Exception(TR::load('err_load_playlist') . " '$uri'\n\n$contents");
                            }

                            $detect_info = '';
                            $detect_id = $this->plugin->collect_detect_info($db, $detect_info);
                            $post_action = Action_Factory::show_title_dialog(TR::t('info'), $post_action, $detect_info);
                        }
                        $this->plugin->set_playlist_parameter($playlist_id, PARAM_ID_MAPPER, $detect_id);
                    }
                } catch (Exception $ex) {
                    hd_debug_print("Problem with download playlist");
                    print_backtrace_exception($ex);
                    $post_action = Action_Factory::show_title_dialog(TR::t('err_load_playlist'), null, $ex->getMessage());
                }

                if ($tmp_file !== $uri && file_exists($tmp_file)) {
                    unlink($tmp_file);
                }
                break;

            case self::CONTROL_RESET_PLAYLIST_DLG:
                return Action_Factory::show_confirmation_dialog(TR::t('yes_no_confirm_msg'), $this, self::ACTION_RESET_PLAYLIST_DLG_APPLY);

            case self::ACTION_RESET_PLAYLIST_DLG_APPLY: // handle streaming settings dialog result
                $this->plugin->safe_clear_current_epg_cache();
                $this->plugin->remove_playlist_data($this->plugin->get_active_playlist_id());
                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case RESET_CONTROLS_ACTION_ID:
                $sel_ndx = safe_get_member($user_input, 'initial_sel_ndx', -1);
        }

        return Action_Factory::reset_controls($this->get_control_defs(MediaURL::decode($user_input->parent_media_url), $plugin_cookies), $post_action, $sel_ndx);
    }
}
