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

class Starnet_Setup_Simple_IPTV_Screen extends Abstract_Controls_Screen
{
    const ID = 'setup_simple_iptv_screen';

    ///////////////////////////////////////////////////////////////////////

    /**
     * Get MediaURL string representation (json encoded)
     *
     * @param string $parent_id
     * @param int $return_index
     * @param string $playlist_id
     * @return false|string
     */
    public static function make_controls_media_url_str($parent_id, $return_index = -1, $playlist_id = null)
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
    public function get_control_defs(MediaURL $media_url, &$plugin_cookies)
    {
        return $this->do_get_control_defs($media_url);
    }

    /**
     * @param MediaURL $media_url
     * @return array
     */
    protected function do_get_control_defs($media_url)
    {
        hd_debug_print(null, true);
        hd_debug_print($media_url, true);

        $playlist_id = isset($media_url->{PARAM_PLAYLIST_ID}) ? $media_url->{PARAM_PLAYLIST_ID} : $this->plugin->get_active_playlist_id();
        $params = $this->plugin->get_playlist_parameters($playlist_id);
        $type = safe_get_value($params, PARAM_TYPE);

        $defs = array();

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        //////////////////////////////////////
        // URI

        $uri = safe_get_value($params, PARAM_URI);
        if ($type === PARAM_FILE) {
            $uri_str = HD::string_ellipsis($uri);
            Control_Factory::add_image_button($defs, $this, ACTION_CHOOSE_FILE, TR::t('playlist'), $uri_str, get_image_path('m3u_file.png'));
        } else if ($type === PARAM_LINK) {
            Control_Factory::add_text_field($defs, $this, CONTROL_URL_PATH, TR::t('playlist'), $uri,
                false, false, false, true, Control_Factory::SCR_CONTROLS_WIDTH, true);
        }

        //////////////////////////////////////
        // Type

        $playlist_type = safe_get_value($params, PARAM_PL_TYPE, CONTROL_PLAYLIST_IPTV);
        $opts[CONTROL_PLAYLIST_IPTV] = TR::t('edit_list_playlist_iptv');
        $opts[CONTROL_PLAYLIST_VOD] = TR::t('edit_list_playlist_vod');
        Control_Factory::add_combobox($defs, $this, CONTROL_EDIT_TYPE, TR::t('edit_list_playlist_type'),
            $playlist_type, $opts, Control_Factory::SCR_CONTROLS_WIDTH, $params, true);

        //////////////////////////////////////
        // ID Mapper

        $id_mapper = safe_get_value($params, PARAM_ID_MAPPER, CONTROL_DETECT_ID);
        $mapper_ops = Default_Dune_Plugin::get_id_detect_mapper();
        Control_Factory::add_combobox($defs, $this, CONTROL_DETECT_ID, TR::t('edit_list_playlist_detect_id'),
            $id_mapper, $mapper_ops, Control_Factory::SCR_CONTROLS_WIDTH, $params, true);

        //////////////////////////////////////
        // Cache time

        $caching_range[PHP_INT_MAX] = TR::t('setup_cache_time_never');

        foreach (array(1, 6, 12) as $hour) {
            $caching_range[$hour] = TR::t('setup_cache_time_h__1', $hour);
        }
        foreach (array(24, 48, 96, 168) as $hour) {
            $caching_range[$hour] = TR::t('setup_cache_time_d__1', $hour / 24);
        }

        $cache_time = $this->plugin->get_setting(PARAM_PLAYLIST_CACHE_TIME_IPTV, 1);
        Control_Factory::add_combobox($defs, $this, PARAM_PLAYLIST_CACHE_TIME_IPTV,
            TR::t('setup_cache_time_iptv'), $cache_time,
            $caching_range, Control_Factory::SCR_CONTROLS_WIDTH, $params, true);

        if ($playlist_type === CONTROL_PLAYLIST_VOD) {
            $cache_time = $this->plugin->get_setting(PARAM_PLAYLIST_CACHE_TIME_VOD, 1);
            Control_Factory::add_combobox($defs, $this, PARAM_PLAYLIST_CACHE_TIME_VOD,
                TR::t('setup_cache_time_vod'), $cache_time,
                $caching_range, Control_Factory::SCR_CONTROLS_WIDTH, $params, true);
        }

        return $defs;
    }

    /**
     * @inheritDoc
     */
    public function handle_user_input(&$user_input, &$plugin_cookies)
    {
        $post_action = null;
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $playlist_id = isset($parent_media_url->playlist_id) ? $parent_media_url->playlist_id : $this->plugin->get_active_playlist_id();
        $params = $this->plugin->get_playlist_parameters($playlist_id);
        $type = safe_get_value($params, PARAM_TYPE);
        $control_id = $user_input->control_id;

        switch ($control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                $ret_action = ACTION_REFRESH_SCREEN;
                if ($this->force_parent_reload) {
                    $this->plugin->reset_channels_loaded();
                    $ret_action = ACTION_RELOAD;
                }
                return self::make_return_action($parent_media_url, $ret_action);

            case CONTROL_URL_PATH:
                $this->force_parent_reload = true;
                $this->plugin->set_playlist_parameter($playlist_id, PARAM_URI, $user_input->{$control_id});
                break;

            case CONTROL_PLAYLIST_IPTV:
                $this->force_parent_reload = true;
                $this->plugin->set_playlist_parameter($playlist_id,
                    PARAM_PL_TYPE,
                    safe_get_member($user_input, CONTROL_EDIT_TYPE, $control_id));
                break;

            case ACTION_CHOOSE_FILE:
                $media_url = Starnet_Folder_Screen::make_callback_media_url_str(static::ID,
                    array(
                        PARAM_EXTENSION => PLAYLIST_PATTERN,
                        Starnet_Folder_Screen::PARAM_CHOOSE_FILE => ACTION_FILE_PLAYLIST,
                        Starnet_Folder_Screen::PARAM_ALLOW_NETWORK => !is_limited_apk(),
                        Starnet_Folder_Screen::PARAM_READ_ONLY => true,
                    )
                );

                return Action_Factory::open_folder($media_url, TR::t('select_file'));

            case ACTION_FILE_PLAYLIST:
                $selected_media_url = MediaURL::decode($user_input->{Starnet_Folder_Screen::PARAM_SELECTED_DATA});
                $this->plugin->set_playlist_parameter($playlist_id, PARAM_URI, $selected_media_url->{PARAM_FILEPATH});
                break;

            case PARAM_PLAYLIST_CACHE_TIME_IPTV:
            case PARAM_PLAYLIST_CACHE_TIME_VOD:
                $this->plugin->set_setting($control_id, (int)$user_input->{$control_id});
                break;

            case CONTROL_DETECT_ID:
                $uri = safe_get_value($params, PARAM_URI);
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
                        $res = $curl_wrapper->download_file($uri, $tmp_file);
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
                    $pl_type = safe_get_member($user_input, CONTROL_EDIT_TYPE, CONTROL_PLAYLIST_IPTV);
                    if ($pl_type === CONTROL_PLAYLIST_IPTV) {
                        if ($detect_id === CONTROL_DETECT_ID) {
                            list($detect_id, $detect_info) = $this->plugin->collect_detect_info($tmp_file);
                            $post_action = Action_Factory::show_title_dialog(TR::t('info'), $detect_info, $post_action);
                        }
                        $this->plugin->set_playlist_parameter($playlist_id, PARAM_ID_MAPPER, $detect_id);
                        $this->force_parent_reload = true;
                    }
                } catch (Exception $ex) {
                    hd_debug_print("Problem with download playlist");
                    print_backtrace_exception($ex);
                    $post_action = Action_Factory::show_title_dialog(TR::t('err_load_playlist'), $ex->getMessage());
                }

                if ($tmp_file !== $uri) {
                    safe_unlink($tmp_file);
                }
                break;
        }

        return Action_Factory::reset_controls($this->do_get_control_defs($parent_media_url), $post_action);
    }
}
