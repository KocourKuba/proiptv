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

    ///////////////////////////////////////////////////////////////////////

    /**
     * Get MediaURL string representation (json encoded)
     *
     * @param string $parent_id
     * @param int $return_index
     * @param string|null $playlist_id
     * @return false|string
     */
    public static function make_custom_media_url_str($parent_id, $return_index = -1, $playlist_id = null)
    {
        return MediaURL::encode(
            array(
                PARAM_SCREEN_ID => static::ID,
                PARAM_SOURCE_WINDOW_ID => $parent_id,
                PARAM_RETURN_INDEX => $return_index,
                PARAM_PLAYLIST_ID => $playlist_id
            )
        );
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
        $ret_index = 1;

        $parent_media_url = MediaURL::decode($media_url);
        $playlist_id = isset($parent_media_url->{PARAM_PLAYLIST_ID}) ? $parent_media_url->{PARAM_PLAYLIST_ID} : $this->plugin->get_active_playlist_id();
        $params = $this->plugin->get_playlist_parameters($playlist_id);
        $provider = $this->plugin->get_provider($playlist_id);

        $type = safe_get_value($params, PARAM_TYPE);
        $uri = safe_get_value($params, PARAM_URI);

        //////////////////////////////////////
        // Playlist name

        $name = safe_get_value($params, PARAM_NAME, basename($uri));
        Control_Factory::add_text_field($defs, $this, null, CONTROL_EDIT_NAME, TR::t('name'),
            $name, false, false, false, true, self::CONTROLS_WIDTH, true);
        $ret_index += 1;

        if ($type === PARAM_PROVIDER) {
            if ($provider !== null && $provider->GetPlaylists() !== null ) {

                //////////////////////////////////////
                // Account

                Control_Factory::add_image_button($defs, $this, null, ACTION_EDIT_PROVIDER_DLG,
                    TR::t('edit_account'), TR::t('setup_change_settings'), get_image_path('folder.png'), self::CONTROLS_WIDTH);
                $ret_index += 2;

                //////////////////////////////////////
                // Provider settings

                Control_Factory::add_image_button($defs, $this, array('return_index' => $ret_index), ACTION_SETUP_PROVIDER,
                    TR::t('edit_ext_account'), TR::t('setup_change_settings'), get_image_path('folder.png'), self::CONTROLS_WIDTH);
                $ret_index += 2;
            }
        } else {
            //////////////////////////////////////
            // URI

            if ($type === PARAM_FILE) {
                $uri_str = HD::string_ellipsis($uri, 30);
                Control_Factory::add_image_button($defs, $this, null, CONTROL_URL_PATH,
                    TR::t('playlist'), $uri_str, get_image_path('folder.png'), self::CONTROLS_WIDTH);
                $ret_index += 2;
            } else if ($type === PARAM_LINK) {
                Control_Factory::add_text_field($defs, $this, null, CONTROL_URL_PATH, TR::t('playlist'),
                    $uri, false, false, false, true, self::CONTROLS_WIDTH, true);
                $ret_index += 1;
            }

            //////////////////////////////////////
            // Type

            $opts[CONTROL_PLAYLIST_IPTV] = TR::t('edit_list_playlist_iptv');
            $opts[CONTROL_PLAYLIST_VOD] = TR::t('edit_list_playlist_vod');
            $playlist_type = safe_get_value($params, PARAM_PL_TYPE, CONTROL_PLAYLIST_IPTV);
            Control_Factory::add_combobox($defs, $this, null, CONTROL_EDIT_TYPE,
                TR::t('edit_list_playlist_type'), $playlist_type, $opts, self::CONTROLS_WIDTH, true);
            $ret_index += 1;

            //////////////////////////////////////
            // ID Mapper

            $id_mapper = safe_get_value($params, PARAM_ID_MAPPER, CONTROL_DETECT_ID);
            $mapper_ops = Default_Dune_Plugin::get_id_detect_mapper();
            Control_Factory::add_combobox($defs, $this, null, CONTROL_DETECT_ID,
                TR::t('edit_list_playlist_detect_id'), $id_mapper, $mapper_ops, self::CONTROLS_WIDTH, true);
            $ret_index += 1;
        }

        //////////////////////////////////////
        // Cache time

        foreach (array(1, 6, 12) as $hour) {
            $caching_range[$hour] = TR::t('setup_cache_time_h__1', $hour);
        }
        foreach (array(24, 48, 96, 168) as $hour) {
            $caching_range[$hour] = TR::t('setup_cache_time_d__1', $hour / 24);
        }
        $cache_time = $this->plugin->get_setting(PARAM_PLAYLIST_CACHE_TIME, 1);
        Control_Factory::add_combobox($defs, $this, null,
            PARAM_PLAYLIST_CACHE_TIME, TR::t('setup_cache_time'),
            $cache_time, $caching_range, self::CONTROLS_WIDTH, true);
        $ret_index += 1;

        //////////////////////////////////////
        // Ext playlist settings

        Control_Factory::add_image_button($defs, $this, array('return_index' => $ret_index), CONTROL_EXT_PARAMS,
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

        $sel_ndx = -1;
        $post_action = null;
        $control_id = $user_input->control_id;
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $playlist_id = isset($parent_media_url->{PARAM_PLAYLIST_ID}) ? $parent_media_url->{PARAM_PLAYLIST_ID} : $this->plugin->get_active_playlist_id();
        $params = $this->plugin->get_playlist_parameters($playlist_id);
        $type = safe_get_value($params, PARAM_TYPE);
        $uri = safe_get_value($params, PARAM_URI);
        $pl_type = safe_get_member($user_input, CONTROL_EDIT_TYPE, CONTROL_PLAYLIST_IPTV);

        switch ($control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                return self::make_return_action($parent_media_url);

            case CONTROL_EDIT_NAME:
                $this->plugin->set_playlist_parameter($playlist_id, PARAM_NAME, $user_input->{CONTROL_EDIT_NAME});
                break;

            case ACTION_EDIT_PROVIDER_DLG:
                return $this->plugin->show_protect_settings_dialog($this, ACTION_DO_EDIT_PROVIDER);

            case ACTION_DO_EDIT_PROVIDER:
                $defs = array();
                Control_Factory::add_vgap($defs, 20);

                $provider = $this->plugin->get_provider($playlist_id);
                if (empty($name)) {
                    $name = $provider->getName();
                }

                $defs = $provider->GetSetupUI($name, $playlist_id, $this);
                if (empty($defs)) {
                    return null;
                }

                return Action_Factory::show_dialog("{$provider->getName()} ({$provider->getId()})", $defs, true);

            case ACTION_SETUP_PROVIDER:
                return $this->plugin->show_protect_settings_dialog($this, ACTION_DO_SETUP_PROVIDER, array('return_index' => $user_input->return_index));

            case ACTION_DO_SETUP_PROVIDER:
                $provider = $this->plugin->get_provider($playlist_id);
                if (!$provider->request_provider_token()) {
                    hd_debug_print("Can't get provider token");
                    return Action_Factory::show_error(false, TR::t('err_incorrect_access_data'), array(TR::t('err_cant_get_token')));
                }

                return Action_Factory::open_folder(
                    Starnet_Setup_Provider_Screen::make_custom_media_url_str(self::ID, $user_input->return_index, $playlist_id),
                    TR::t('edit_ext_account')
                );

            case ACTION_EDIT_PROVIDER_DLG_APPLY:
                $res = $this->plugin->get_provider($playlist_id)->ApplySetupUI($user_input);
                if (is_array($res)) {
                    return $res;
                }
                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case CONTROL_URL_PATH:
                if ($type === PARAM_FILE) {
                    $media_url = Starnet_Folder_Screen::make_custom_media_url_str(static::ID,
                        array(
                            PARAM_END_ACTION => ACTION_REFRESH_SCREEN,
                            PARAM_EXTENSION => PLAYLIST_PATTERN,
                            Starnet_Folder_Screen::PARAM_CHOOSE_FILE => ACTION_FILE_SELECTED,
                        )
                    );
                    return Action_Factory::open_folder($media_url, TR::t('setup_epg_xmltv_cache_caption'));
                }

                $this->plugin->set_playlist_parameter($playlist_id, PARAM_URI, $user_input->{CONTROL_URL_PATH});
                break;

            case PARAM_PLAYLIST_CACHE_TIME:
                $this->plugin->set_setting($control_id, (int)$user_input->{$control_id});
                break;

            case ACTION_FILE_SELECTED:
                $data = MediaURL::decode($user_input->{Starnet_Folder_Screen::PARAM_SELECTED_DATA});
                $this->plugin->set_playlist_parameter($playlist_id, PARAM_URI, $data->{PARAM_FILEPATH});
                break;

            case CONTROL_EXT_PARAMS:
                return Action_Factory::open_folder(
                    Starnet_Setup_Ext_Playlists_Screen::make_custom_media_url_str(self::ID, $user_input->return_index, $playlist_id),
                    TR::t('setup_extended_setup')
                );

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
                        if (!is_proto_http($uri)) {
                            throw new Exception(TR::load('err_incorrect_url') . " '$uri'");
                        }

                        $tmp_file = get_temp_path(Hashed_Array::hash($uri));
                        $curl_wrapper = Curl_Wrapper::getInstance();
                        $this->plugin->set_curl_timeouts($curl_wrapper);
                        $res = $curl_wrapper->download_file($uri, $tmp_file, false);
                        if (!$res) {
                            $logfile = "Error code: " . $curl_wrapper->get_error_no() . "\n" . $curl_wrapper->get_error_desc();
                            throw new Exception(TR::load('err_load_playlist') . " '$uri'\n$logfile");
                        }
                    }

                    $contents = file_get_contents($tmp_file, false, null, 0, 512);
                    if ($contents === false || strpos($contents, TAG_EXTM3U) === false) {
                        throw new Exception(TR::load('err_bad_m3u_file') . " '$uri'\n\n" . substr($contents, 0, 512));
                    }

                    $detect_id = safe_get_member($user_input, CONTROL_DETECT_ID, CONTROL_DETECT_ID);
                    if ($pl_type === CONTROL_PLAYLIST_IPTV) {
                        if ($detect_id === CONTROL_DETECT_ID) {
                            list($detect_id, $detect_info) = $this->plugin->collect_detect_info($tmp_file);
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

            case self::ACTION_RESET_PLAYLIST_DLG_APPLY:
                $this->plugin->clear_playlist_epg_cache();
                $this->plugin->remove_playlist_data($playlist_id);
                return User_Input_Handler_Registry::create_action($this, ACTION_RELOAD);

            case ACTION_RELOAD:
                $post_action = User_Input_Handler_Registry::create_action($this, ACTION_REFRESH_SCREEN);
                $this->plugin->reset_channels_loaded();
                $this->plugin->init_playlist_db(true);
                return Action_Factory::invalidate_all_folders($plugin_cookies, null, $post_action);

            case ACTION_REFRESH_SCREEN:
            case RESET_CONTROLS_ACTION_ID:
                $sel_ndx = safe_get_member($user_input, 'initial_sel_ndx', -1);
        }

        return Action_Factory::reset_controls($this->get_control_defs(MediaURL::decode($user_input->parent_media_url), $plugin_cookies), $post_action, $sel_ndx);
    }
}
