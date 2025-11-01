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

class Starnet_Setup_Provider_Screen extends Abstract_Controls_Screen
{
    const ID = 'setup_provider_screen';

    const CONTROL_CUSTOM_URL = 'custom_url';
    const CONTROL_SELECTED_PLAYLIST = 'selected_playlist';
    const ACTION_COPY_FAVORITE = 'copy_favorite';
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

        $defs = array();

        //////////////////////////////////////
        // Plugin name
        $this->plugin->create_setup_header($defs);

        $has_vod_cache = false;
        $provider = $this->plugin->get_provider($playlist_id);
        //////////////////////////////////////
        // Account

        Control_Factory::add_image_button($defs, $this, null, ACTION_EDIT_PROVIDER_DLG,
            TR::t('edit_account'), TR::t('setup_change_settings'), get_image_path('info.png'), static::CONTROLS_WIDTH);

        if ($provider->hasApiCommand(API_COMMAND_GET_VOD) !== null
            && $provider->getConfigValue(CONFIG_VOD_PARSER) !== null) {
            $has_vod_cache = true;
        }

        $provider->check_config_values();

        //////////////////////////////////////
        // Streams settings

        $streams = $provider->GetStreams();
        if (!empty($streams) && count($streams) > 1) {
            $idx = $provider->GetProviderParameter(MACRO_STREAM_ID);
            hd_debug_print("streams ($idx): " . json_encode($streams), true);

            Control_Factory::add_combobox($defs, $this, null, api_default::CONTROL_STREAM,
                TR::t('stream'), $idx, $streams, static::CONTROLS_WIDTH, true);
        }

        //////////////////////////////////////
        // Domains settings

        $domains = $provider->GetDomains();
        if (!empty($domains) && count($domains) > 1) {
            $idx = $provider->GetProviderParameter(MACRO_DOMAIN_ID);
            hd_debug_print("domains ($idx): " . json_encode($domains), true);

            Control_Factory::add_combobox($defs, $this, null, api_default::CONTROL_DOMAIN,
                TR::t('domain'), $idx, $domains, static::CONTROLS_WIDTH, true);
        }

        //////////////////////////////////////
        // Servers settings

        $servers = $provider->GetServers();
        if (!empty($servers) && count($servers) > 1) {
            $idx = $provider->GetProviderParameter(MACRO_SERVER_ID);
            hd_debug_print("servers ($idx): " . json_encode($servers), true);

            Control_Factory::add_combobox($defs, $this, null, api_default::CONTROL_SERVER,
                TR::t('server'), $idx, $servers, static::CONTROLS_WIDTH, true);
        }

        //////////////////////////////////////
        // Devices settings

        $devices = $provider->GetDevices();
        if (!empty($devices) && count($devices) > 1) {
            $idx = $provider->GetProviderParameter(MACRO_DEVICE_ID);
            hd_debug_print("devices ($idx): " . json_encode($devices), true);

            Control_Factory::add_combobox($defs, $this, null, api_default::CONTROL_DEVICE,
                TR::t('device'), $idx, $devices, static::CONTROLS_WIDTH, true);
        }

        //////////////////////////////////////
        // Qualities settings

        $qualities = $provider->GetQualities();
        if (!empty($qualities) && count($qualities) > 1) {
            $idx = $provider->GetProviderParameter(MACRO_QUALITY_ID);
            hd_debug_print("qualities ($idx): " . json_encode($qualities), true);

            Control_Factory::add_combobox($defs, $this, null, api_default::CONTROL_QUALITY,
                TR::t('quality'), $idx, $qualities, static::CONTROLS_WIDTH, true);
        }

        //////////////////////////////////////
        // Playlists settings

        $playlists = $provider->GetPlaylistsIptv();
        $pl_names = extract_column($playlists, COLUMN_NAME);
        if (isset($pl_names['default'])) {
            $pl_names['default'] = TR::t('by_default');
        }
        $pl_idx = $provider->GetPlaylistIptvId();

        Control_Factory::add_combobox($defs, $this, null, self::CONTROL_SELECTED_PLAYLIST,
            TR::t('provider_playlist'), $pl_idx, $pl_names, static::CONTROLS_WIDTH, true);

        if ($pl_idx === DIRECT_PLAYLIST_ID) {
            //////////////////////////////////////
            // Direct playlist url
            $url = $provider->GetProviderParameter(PARAM_CUSTOM_PLAYLIST_IPTV);
            Control_Factory::add_text_field($defs, $this, null, self::CONTROL_CUSTOM_URL, TR::t('direct_url'),
                $url, false, false, false, true, static::CONTROLS_WIDTH, true);
        } else if ($pl_idx === DIRECT_FILE_PLAYLIST_ID) {
            //////////////////////////////////////
            // Direct playlist file
            $file_path = $provider->GetProviderParameter(PARAM_CUSTOM_FILE_PLAYLIST_IPTV);
            $path_str = HD::string_ellipsis($file_path);
            Control_Factory::add_image_button($defs, $this, null, ACTION_CHOOSE_FILE,
                TR::t('select_file'), $path_str, get_image_path('m3u_file.png'), static::CONTROLS_WIDTH);
        } else {
            //////////////////////////////////////
            // Icon replacements settings

            $icon_replacements = $provider->getConfigValue(CONFIG_ICON_REPLACE);
            if (!empty($icon_replacements)) {
                $icon_idx = $provider->GetProviderParameter(PARAM_REPLACE_ICON, SwitchOnOff::on);
                Control_Factory::add_combobox($defs, $this, null, PARAM_REPLACE_ICON,
                    TR::t('setup_channels_square_icons'), $icon_idx, SwitchOnOff::$translated,
                    static::CONTROLS_WIDTH, true);
            }

            //////////////////////////////////////
            // Playlist mirrors settings

            $playlist_mirrors = $provider->getConfigValue(CONFIG_PLAYLIST_MIRRORS);
            if (!empty($playlist_mirrors)) {
                $idx = $provider->GetProviderParameter(PARAM_SELECTED_MIRROR);
                if (empty($idx) || !isset($playlist_mirrors[$idx])) {
                    $idx = key($playlist_mirrors);
                    $provider->SetProviderParameter(PARAM_SELECTED_MIRROR, $idx);
                }
                $pairs = array();
                foreach ($playlist_mirrors as $key => $value) {
                    $pairs[$key] = $key;
                }
                Control_Factory::add_combobox($defs, $this, null, PARAM_SELECTED_MIRROR,
                    TR::t('setup_channels_using_mirror'), $idx, $pairs,
                    static::CONTROLS_WIDTH, true);
            }
        }

        $fav_id = $this->plugin->get_setting(PARAM_USE_COMMON_FAV, SwitchOnOff::off);
        Control_Factory::add_image_button($defs, $this, null,
            PARAM_USE_COMMON_FAV, TR::t('setup_use_common_fav'), SwitchOnOff::translate($fav_id),
            SwitchOnOff::to_image($fav_id), static::CONTROLS_WIDTH);

        //////////////////////////////////////
        // Cache time

        $caching_range[PHP_INT_MAX] = TR::t('setup_cache_time_never');

        foreach (array(1, 6, 12) as $hour) {
            $caching_range[$hour] = TR::t('setup_cache_time_h__1', $hour);
        }
        foreach (array(24, 48, 96, 168) as $hour) {
            $caching_range[$hour] = TR::t('setup_cache_time_d__1', $hour / 24);
        }

        $param = PARAM_PLAYLIST_CACHE_TIME_IPTV . ($pl_idx === 'default' ? '' : "_$pl_idx");
        $cache_time = $this->plugin->get_setting($param, 1);
        hd_debug_print("Playlist $param = $cache_time");
        Control_Factory::add_combobox($defs, $this, null,
            PARAM_PLAYLIST_CACHE_TIME_IPTV, TR::t('setup_cache_time_iptv'),
            $cache_time, $caching_range, static::CONTROLS_WIDTH, true);

        if ($has_vod_cache) {
            $cache_time = $this->plugin->get_setting(PARAM_PLAYLIST_CACHE_TIME_VOD, 1);
            Control_Factory::add_combobox($defs, $this, null,
                PARAM_PLAYLIST_CACHE_TIME_VOD, TR::t('setup_cache_time_vod'),
                $cache_time, $caching_range, static::CONTROLS_WIDTH, true);
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
        $provider = $this->plugin->get_provider($playlist_id);
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

            case ACTION_EDIT_PROVIDER_DLG:
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

            case ACTION_EDIT_PROVIDER_DLG_APPLY:
                $res = $this->plugin->get_provider($playlist_id)->ApplySetupUI($user_input);
                if (is_array($res)) {
                    return $res;
                }
                $this->force_parent_reload = true;
                break;

            case PARAM_PLAYLIST_CACHE_TIME_IPTV:
                $pl_idx = $provider->GetPlaylistIptvId();
                $param = PARAM_PLAYLIST_CACHE_TIME_IPTV . ($pl_idx === 'default' ? '' : "_$pl_idx");
                $this->plugin->set_setting($param, (int)$user_input->{$control_id});
                break;

            case PARAM_PLAYLIST_CACHE_TIME_VOD:
                $this->plugin->set_setting($control_id, (int)$user_input->{$control_id});
                break;

            case api_default::CONTROL_STREAM:
                $provider->SetStream($user_input->{$control_id});
                $this->force_parent_reload = true;
                break;

            case api_default::CONTROL_DOMAIN:
                $provider->SetDomain($user_input->{$control_id});
                $this->force_parent_reload = true;
                break;

            case api_default::CONTROL_SERVER:
                $provider->SetServer($user_input->{$control_id}, $msg);
                if (!empty($msg)) {
                    return Action_Factory::show_error(false, TR::t('err_error'), explode(PHP_EOL, $msg));
                }

                $this->force_parent_reload = true;
                break;

            case api_default::CONTROL_DEVICE:
                $provider->SetDevice($user_input->{$control_id});
                $this->force_parent_reload = true;
                break;

            case api_default::CONTROL_QUALITY:
                $provider->SetQuality($user_input->{$control_id});
                $this->force_parent_reload = true;
                break;

            case self::CONTROL_SELECTED_PLAYLIST:
                $provider->SetProviderParameter(PARAM_PLAYLIST_IPTV_ID, $user_input->{$control_id});
                $this->force_parent_reload = true;
                break;

            case self::CONTROL_CUSTOM_URL:
                $provider->SetProviderParameter(PARAM_CUSTOM_PLAYLIST_IPTV, $user_input->{$control_id});
                $this->force_parent_reload = true;
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
                $provider->SetProviderParameter(PARAM_CUSTOM_FILE_PLAYLIST_IPTV, $selected_media_url->{PARAM_FILEPATH});
                $this->force_parent_reload = true;
                break;

            case PARAM_REPLACE_ICON:
            case PARAM_SELECTED_MIRROR:
                $provider->SetProviderParameter($control_id, $user_input->{$control_id});
                $this->force_parent_reload = true;
                break;

            case PARAM_USE_COMMON_FAV:
                $this->plugin->toggle_setting($control_id, false);
                $msg = $this->plugin->get_bool_setting(PARAM_USE_COMMON_FAV)
                    ? TR::t('yes_no_confirm_to_cmn_msg')
                    : TR::t('yes_no_confirm_to_pl_msg');
                $post_action = Action_Factory::show_confirmation_dialog($msg, $this, self::ACTION_COPY_FAVORITE);
                break;

            case self::ACTION_COPY_FAVORITE:
                $cmn_fav_ids = $this->plugin->get_channels_order(TV_FAV_COMMON_GROUP_ID);
                $pl_fav_ids = $this->plugin->get_channels_order(TV_FAV_GROUP_ID);
                $target = $this->plugin->get_fav_id();
                if ($target === TV_FAV_COMMON_GROUP_ID) {
                    $add_fav_ids = array_diff($pl_fav_ids, $cmn_fav_ids);
                } else {
                    $add_fav_ids = array_diff($cmn_fav_ids, $pl_fav_ids);
                }

                if (!empty($add_fav_ids)) {
                    $this->plugin->bulk_change_channels_order($target, $add_fav_ids, false);
                    $this->force_parent_reload = true;
                }
                break;
        }

        return Action_Factory::reset_controls($this->do_get_control_defs($parent_media_url), $post_action);
    }
}
