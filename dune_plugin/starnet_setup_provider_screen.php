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

class Starnet_Setup_Provider_Screen extends Abstract_Controls_Screen implements User_Input_Handler
{
    const ID = 'setup_provider_screen';

    const CONTROL_DEVICE = 'device';
    const CONTROL_SERVER = 'server';
    const CONTROL_DOMAIN = 'domain';
    const CONTROL_QUALITY = 'quality';
    const CONTROL_STREAM = 'stream';

    ///////////////////////////////////////////////////////////////////////

    /**
     * Get MediaURL string representation (json encoded)
     *
     * @param string $parent_id
     * @param int $return_index
     * @param string $playlist_id
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

        $parent_media_url = MediaURL::decode($media_url);
        $playlist_id = isset($parent_media_url->{PARAM_PLAYLIST_ID}) ? $parent_media_url->{PARAM_PLAYLIST_ID} : $this->plugin->get_active_playlist_id();
        $provider = $this->plugin->get_provider($playlist_id);

        //////////////////////////////////////
        // Streams settings

        $streams = $provider->GetStreams();
        if (!empty($streams) && count($streams) > 1) {
            $idx = $provider->GetParameter(MACRO_STREAM_ID);
            if (empty($idx)) {
                $idx = key($streams);
            }
            hd_debug_print("streams ($idx): " . json_encode($streams), true);

            Control_Factory::add_combobox($defs, $this, null, self::CONTROL_STREAM,
                TR::t('stream'), $idx, $streams, self::CONTROLS_WIDTH);
        }

        //////////////////////////////////////
        // Domains settings

        $domains = $provider->GetDomains();
        if (!empty($domains) && count($domains) > 1) {
            $idx = $provider->GetParameter(MACRO_DOMAIN_ID);
            if (empty($idx)) {
                $idx = key($domains);
            }
            hd_debug_print("domains ($idx): " . json_encode($domains), true);

            Control_Factory::add_combobox($defs, $this, null, self::CONTROL_DOMAIN,
                TR::t('domain'), $idx, $domains, self::CONTROLS_WIDTH, true);
        }

        //////////////////////////////////////
        // Servers settings

        $servers = $provider->GetServers();
        if (!empty($servers) && count($servers) > 1) {
            $idx = $provider->GetParameter(MACRO_SERVER_ID);
            if (empty($idx)) {
                $idx = key($servers);
            }
            hd_debug_print("servers ($idx): " . json_encode($servers), true);

            Control_Factory::add_combobox($defs, $this, null, self::CONTROL_SERVER,
                TR::t('server'), $idx, $servers, self::CONTROLS_WIDTH, true);
        }

        //////////////////////////////////////
        // Devices settings

        $devices = $provider->GetDevices();
        if (!empty($devices) && count($devices) > 1) {
            $idx = $provider->GetParameter(MACRO_DEVICE_ID);
            if (empty($idx)) {
                $idx = key($devices);
            }
            hd_debug_print("devices ($idx): " . json_encode($devices), true);

            Control_Factory::add_combobox($defs, $this, null, self::CONTROL_DEVICE,
                TR::t('device'), $idx, $devices, self::CONTROLS_WIDTH, true);
        }

        //////////////////////////////////////
        // Qualities settings

        $qualities = $provider->GetQualities();
        if (!empty($qualities) && count($qualities) > 1) {
            $idx = $provider->GetParameter(MACRO_QUALITY_ID);
            if (empty($idx)) {
                $idx = key($qualities);
            }
            hd_debug_print("qualities ($idx): " . json_encode($qualities), true);

            Control_Factory::add_combobox($defs, $this, null, self::CONTROL_QUALITY,
                TR::t('quality'), $idx, $qualities, self::CONTROLS_WIDTH, true);
        }

        //////////////////////////////////////
        // Playlists settings

        $playlists = $provider->GetPlaylists();
        $pl_names = extract_column($playlists, COLUMN_NAME);
        $idx = $provider->GetParameter(MACRO_PLAYLIST_ID);
        if (empty($idx)) {
            $idx = (string)key($pl_names);
            $provider->SetParameter(MACRO_PLAYLIST_ID, $idx);
        }

        Control_Factory::add_combobox($defs, $this, null, CONTROL_SELECTED_PLAYLIST,
            TR::t('playlist'), $idx, $pl_names, self::CONTROLS_WIDTH, true);

        if ($idx === DIRECT_PLAYLIST_ID) {
            //////////////////////////////////////
            // Direct playlist url
            $url = $provider->GetParameter(MACRO_CUSTOM_PLAYLIST);
            Control_Factory::add_text_field($defs, $this, null, CONTROL_URL_PATH, TR::t('url'),
                $url, false, false, false, true, self::CONTROLS_WIDTH);
        } else {
            //////////////////////////////////////
            // Icon replacements settings

            $icon_replacements = $provider->getConfigValue(CONFIG_ICON_REPLACE);
            if (!empty($icon_replacements)) {
                $val = $provider->GetParameter(PARAM_REPLACE_ICON, SwitchOnOff::on);
                Control_Factory::add_combobox($defs, $this, null, PARAM_REPLACE_ICON,
                    TR::t('setup_channels_square_icons'), $val, SwitchOnOff::$translated,
                    self::CONTROLS_WIDTH, true);
            }

            //////////////////////////////////////
            // Playlist mirrors settings

            $playlist_mirrors = $provider->getConfigValue(CONFIG_PLAYLIST_MIRRORS);
            if (!empty($playlist_mirrors)) {
                reset($playlist_mirrors);
                $val = $provider->GetParameter(PARAM_SELECTED_MIRROR, key($playlist_mirrors));
                $pairs = array();
                foreach ($playlist_mirrors as $key => $value) {
                    $pairs[$key] = $key;
                }
                Control_Factory::add_combobox($defs, $this, null, PARAM_SELECTED_MIRROR,
                    TR::t('setup_channels_using_mirror'), $val, $pairs,
                    self::CONTROLS_WIDTH, true);
            }
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
        hd_debug_print("Setup provider: {$provider->getName()}");
        switch ($user_input->control_id) {
            case GUI_EVENT_KEY_TOP_MENU:
            case GUI_EVENT_KEY_RETURN:
                $this->plugin->reset_channels_loaded();
                $this->plugin->clear_playlist_cache($playlist_id);
                return self::make_return_action($parent_media_url, ACTION_RELOAD);

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

            case CONTROL_SELECTED_PLAYLIST:
                $provider->SetPlaylist($user_input->{CONTROL_SELECTED_PLAYLIST});
                $this->force_parent_reload = true;
                break;

            case CONTROL_URL_PATH:
                $provider->SetParameter(MACRO_CUSTOM_PLAYLIST, $user_input->url_path);
                $this->force_parent_reload = true;
                break;

            case PARAM_REPLACE_ICON:
                $provider->SetParameter(PARAM_REPLACE_ICON, $user_input->{PARAM_REPLACE_ICON});
                $this->force_parent_reload = true;
                break;

            case PARAM_SELECTED_MIRROR:
                $provider->SetParameter(PARAM_SELECTED_MIRROR, $user_input->{PARAM_SELECTED_MIRROR});
                $this->force_parent_reload = true;
                break;
        }

        return Action_Factory::reset_controls($this->get_control_defs($parent_media_url, $plugin_cookies), $post_action);
    }
}
