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

    const CONTROL_DEVICE = 'device';
    const CONTROL_SERVER = 'server';
    const CONTROL_DOMAIN = 'domain';
    const CONTROL_QUALITY = 'quality';
    const CONTROL_STREAM = 'stream';
    const CONTROL_CUSTOM_URL = 'custom_url';
    const CONTROL_SELECTED_PLAYLIST = 'selected_playlist';

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
            TR::t('edit_account'), TR::t('setup_change_settings'), get_image_path('folder.png'), static::CONTROLS_WIDTH);

        if ($provider->hasApiCommand(API_COMMAND_GET_VOD) !== null
            && $provider->getConfigValue(CONFIG_VOD_PARSER) !== null) {
            $has_vod_cache = true;
        }

        //////////////////////////////////////
        // Streams settings

        $streams = $provider->GetStreams();
        if (!empty($streams) && count($streams) > 1) {
            $idx = $provider->GetProviderParameter(MACRO_STREAM_ID);
            if (empty($idx) || !isset($streams[$idx])) {
                $idx = key($streams);
            }
            hd_debug_print("streams ($idx): " . json_encode($streams), true);

            Control_Factory::add_combobox($defs, $this, null, self::CONTROL_STREAM,
                TR::t('stream'), $idx, $streams, static::CONTROLS_WIDTH, true);
        }

        //////////////////////////////////////
        // Domains settings

        $domains = $provider->GetDomains();
        if (!empty($domains) && count($domains) > 1) {
            $idx = $provider->GetProviderParameter(MACRO_DOMAIN_ID);
            if (empty($idx)) {
                $idx = key($domains);
            }
            hd_debug_print("domains ($idx): " . json_encode($domains), true);

            Control_Factory::add_combobox($defs, $this, null, self::CONTROL_DOMAIN,
                TR::t('domain'), $idx, $domains, static::CONTROLS_WIDTH, true);
        }

        //////////////////////////////////////
        // Servers settings

        $servers = $provider->GetServers();
        if (!empty($servers) && count($servers) > 1) {
            $idx = $provider->GetProviderParameter(MACRO_SERVER_ID);
            if (empty($idx)) {
                $idx = key($servers);
            }
            hd_debug_print("servers ($idx): " . json_encode($servers), true);

            Control_Factory::add_combobox($defs, $this, null, self::CONTROL_SERVER,
                TR::t('server'), $idx, $servers, static::CONTROLS_WIDTH, true);
        }

        //////////////////////////////////////
        // Devices settings

        $devices = $provider->GetDevices();
        if (!empty($devices) && count($devices) > 1) {
            $idx = $provider->GetProviderParameter(MACRO_DEVICE_ID);
            if (empty($idx)) {
                $idx = key($devices);
            }
            hd_debug_print("devices ($idx): " . json_encode($devices), true);

            Control_Factory::add_combobox($defs, $this, null, self::CONTROL_DEVICE,
                TR::t('device'), $idx, $devices, static::CONTROLS_WIDTH, true);
        }

        //////////////////////////////////////
        // Qualities settings

        $qualities = $provider->GetQualities();
        if (!empty($qualities) && count($qualities) > 1) {
            $idx = $provider->GetProviderParameter(MACRO_QUALITY_ID);
            if (empty($idx)) {
                $idx = key($qualities);
            }
            hd_debug_print("qualities ($idx): " . json_encode($qualities), true);

            Control_Factory::add_combobox($defs, $this, null, self::CONTROL_QUALITY,
                TR::t('quality'), $idx, $qualities, static::CONTROLS_WIDTH, true);
        }

        //////////////////////////////////////
        // Playlists settings

        $playlists = $provider->GetPlaylistsIptv();
        $pl_names = extract_column($playlists, COLUMN_NAME);
        if (isset($pl_names['default'])) {
            $pl_names['default'] = TR::t('by_default');
        }
        $idx = $provider->GetPlaylistIptvId();

        Control_Factory::add_combobox($defs, $this, null, self::CONTROL_SELECTED_PLAYLIST,
            TR::t('playlist'), $idx, $pl_names, static::CONTROLS_WIDTH, true);

        if ($idx === DIRECT_PLAYLIST_ID) {
            //////////////////////////////////////
            // Direct playlist url
            $url = $provider->GetProviderParameter(PARAM_CUSTOM_PLAYLIST_IPTV);
            Control_Factory::add_text_field($defs, $this, null, self::CONTROL_CUSTOM_URL, TR::t('url'),
                $url, false, false, false, true, static::CONTROLS_WIDTH, true);
        } else {
            //////////////////////////////////////
            // Icon replacements settings

            $icon_replacements = $provider->getConfigValue(CONFIG_ICON_REPLACE);
            if (!empty($icon_replacements)) {
                $idx = $provider->GetProviderParameter(PARAM_REPLACE_ICON, SwitchOnOff::on);
                Control_Factory::add_combobox($defs, $this, null, PARAM_REPLACE_ICON,
                    TR::t('setup_channels_square_icons'), $idx, SwitchOnOff::$translated,
                    static::CONTROLS_WIDTH, true);
            }

            //////////////////////////////////////
            // Playlist mirrors settings

            $playlist_mirrors = $provider->getConfigValue(CONFIG_PLAYLIST_MIRRORS);
            if (!empty($playlist_mirrors)) {
                $idx = $provider->GetProviderParameter(PARAM_SELECTED_MIRROR);
                if (empty($idx) || !isset($playlist_mirrors[$idx])) {
                    $idx =  key($playlist_mirrors);
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
        hd_debug_print(null, true);

        $post_action = null;
        $parent_media_url = MediaURL::decode($user_input->parent_media_url);
        $playlist_id = isset($parent_media_url->playlist_id) ? $parent_media_url->playlist_id : $this->plugin->get_active_playlist_id();
        $provider = $this->plugin->get_provider($playlist_id);

        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                $ret_action = ACTION_REFRESH_SCREEN;
                if ($this->force_parent_reload) {
                    $this->plugin->reset_channels_loaded();
                    $this->plugin->clear_playlist_cache($playlist_id);
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

            case self::CONTROL_STREAM:
                $provider->SetStream($user_input->{self::CONTROL_STREAM});
                $this->force_parent_reload = true;
                break;

            case self::CONTROL_DOMAIN:
                $provider->SetDomain($user_input->{self::CONTROL_DOMAIN});
                $this->force_parent_reload = true;
                break;

            case self::CONTROL_SERVER:
                $provider->SetServer($user_input->{self::CONTROL_SERVER}, $msg);
                if (!empty($msg)) {
                    return Action_Factory::show_error(false, TR::t('err_error'), explode(PHP_EOL, $msg));
                }

                $this->force_parent_reload = true;
                break;

            case self::CONTROL_DEVICE:
                $provider->SetDevice($user_input->{self::CONTROL_DEVICE});
                $this->force_parent_reload = true;
                break;

            case self::CONTROL_QUALITY:
                $provider->SetQuality($user_input->{self::CONTROL_QUALITY});
                $this->force_parent_reload = true;
                break;

            case self::CONTROL_SELECTED_PLAYLIST:
                $provider->SetProviderParameter(PARAM_PLAYLIST_IPTV_ID, $user_input->{self::CONTROL_SELECTED_PLAYLIST});
                $this->force_parent_reload = true;
                break;

            case self::CONTROL_CUSTOM_URL:
                $provider->SetProviderParameter(PARAM_CUSTOM_PLAYLIST_IPTV, $user_input->{CONTROL_URL_PATH});
                $this->force_parent_reload = true;
                break;

            case PARAM_REPLACE_ICON:
                $provider->SetProviderParameter(PARAM_REPLACE_ICON, $user_input->{PARAM_REPLACE_ICON});
                $this->force_parent_reload = true;
                break;

            case PARAM_SELECTED_MIRROR:
                $provider->SetProviderParameter(PARAM_SELECTED_MIRROR, $user_input->{PARAM_SELECTED_MIRROR});
                $this->force_parent_reload = true;
                break;
        }

        return Action_Factory::reset_controls($this->do_get_control_defs($parent_media_url), $post_action);
    }
}
