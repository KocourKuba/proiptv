<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
 * Original code from DUNE HD
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

require_once 'lib/curl_wrapper.php';
require_once 'lib/json_serializer.php';

/*
    {
      "enable": true,
      "id": "viplime",
      "name": "VipLime",
      "type": "pin",
      "logo": "http://iptv.esalecrm.net/res/logo_viplime.png",
      "api_commands": {
        "playlist": "http://cdntv.online/{QUALITY_ID}/{PASSWORD}/playlist.m3u8"
      },
      "provider_config": {
        "id_map": "tvg-id",
        "epg_preset": "drm",
        "qualities": {
          "high": "High",
          "medium": "Medium",
          "low": "Medium",
          "variant": "Adaptive",
          "hls": "Optimal"
        }
      }
    },
*/

class api_default
{
    const CONTROL_LOGIN = 'login';
    const CONTROL_PASSWORD = 'password';
    const CONTROL_SERVER = 'server';
    const CONTROL_PL_DOMAIN = 'domain';
    const CONTROL_API_DOMAIN = 'api_domain';
    const CONTROL_QUALITY = 'quality';
    const CONTROL_STREAM = 'stream';
    const CONTROL_DEVICE = 'device';

    /**
     * @var string
     */
    protected $id = '';

    /**
     * @var string
     */
    protected $class = '';

    /**
     * @var string
     */
    protected $vod = '';

    /**
     * @var string
     */
    protected $name = '';

    /**
     * @var string
     */
    protected $type = '';

    /**
     * @var string
     */
    protected $logo = '';

    /**
     * @var string
     */
    protected $provider_url = '';

    /**
     * @var string
     */
    protected $api_url = '';

    /**
     * @var string
     */
    protected $vod_api_url = '';

    /**
     * @var array
     */
    protected $api_commands = array();

    /**
     * @var bool
     */
    protected $enable = false;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var array
     */
    protected $account_info;

    /**
     * @var string
     */
    protected $playlist_id;

    /**
     * @var Default_Dune_Plugin
     */
    protected $plugin;

    /**
     * @var array
     */
    protected $servers = array();

    /**
     * @var array
     */
    protected $devices = array();

    /**
     * @var array
     */
    protected $packages = array();

    public function __construct(DunePlugin $plugin)
    {
        $this->plugin = $plugin;
    }

    ////////////////////////////////////////////////////////////////////////
    /// non configurable vars

    /**
     * @return string|null
     */
    public function get_vod_class()
    {
        if ($this->hasApiCommand(API_COMMAND_GET_VOD)) {
            $vod_class = "vod_" . $this->getVod();
            if ($vod_class !== 'vod_' && class_exists($vod_class)) {
                hd_debug_print("Used VOD class: $vod_class");
                return $vod_class;
            }

            hd_debug_print("Used VOD class: vod_standard");
            return "vod_standard";
        }

        return null;
    }

    ////////////////////////////////////////////////////////////////////////
    /// Getters/Setters

    /**
     * @return string
     */
    public function getLogo()
    {
        return $this->logo;
    }

    /**
     * @param string $logo
     */
    public function setLogo($logo)
    {
        $this->logo = $logo;
    }

    /**
     * @return string
     */
    public function getProviderUrl()
    {
        return $this->provider_url;
    }

    /**
     * @param string $provider_url
     */
    public function setProviderUrl($provider_url)
    {
        $this->provider_url = $provider_url;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @param string $type
     */
    public function setType($type)
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return string
     */
    public function getClass()
    {
        return $this->id;
    }

    /**
     * @param string $class
     */
    public function setClass($class)
    {
        $this->class = $class;
    }

    /**
     * @return string
     */
    public function getVod()
    {
        return $this->vod;
    }

    /**
     * @param string $vod
     */
    public function setVod($vod)
    {
        $this->vod = $vod;
    }

    /**
     * @return array
     */
    public function getApiCommands()
    {
        return $this->api_commands;
    }

    /**
     * @param array $api_commands
     */
    public function setApiCommands($api_commands)
    {
        $this->api_commands = $api_commands;
    }

    /**
     * @return bool
     */
    public function getEnable()
    {
        return $this->enable;
    }

    /**
     * @param bool $enable
     */
    public function setEnable($enable)
    {
        $this->enable = $enable;
    }

    /**
     * @return array
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * @param array $config
     */
    public function setConfig($config)
    {
        $this->config = $config;
    }

    /**
     * @return string
     */
    public function get_provider_playlist_id()
    {
        return $this->playlist_id;
    }

    /**
     * @param string $playlist_id
     * @return void
     */
    public function set_provider_playlist_id($playlist_id)
    {
        $this->playlist_id = $playlist_id;
    }

    /**
     * Set default values if it present in provider config but not set in user credentials
     *
     * @return void
     */
    public function apply_config_defaults()
    {
        // Set default playlist settings for new provider
        $settings = array();
        $xmltv_picons = $this->getConfigValue(XMLTV_PICONS);
        if ($xmltv_picons) {
            $settings[PARAM_USE_PICONS] = COMBINED_PICONS;
        }

        $epg_preset = $this->getConfigValue(EPG_JSON_PRESETS);
        if (!empty($epg_preset)) {
            $settings[PARAM_EPG_CACHE_ENGINE] = ENGINE_JSON;
            $settings[PARAM_EPG_JSON_PRESET] = 0;
        }

        $detect_stream = $this->getConfigValue(PARAM_DUNE_FORCE_TS);
        if ($detect_stream) {
            $settings[PARAM_DUNE_FORCE_TS] = SwitchOnOff::to_def($detect_stream);
        }

        if (!empty($settings)) {
            $this->plugin->put_settings($this->playlist_id, $settings);
        }
    }

    /**
     * Check if values from config is present and set to valid value
     *
     * @return void
     */
    public function check_config_values()
    {
        static $config_items = array(
            CONFIG_STREAMS => MACRO_STREAM_ID,
            CONFIG_API_DOMAINS => MACRO_API_DOMAIN_ID,
            CONFIG_PL_DOMAINS => MACRO_PL_DOMAIN_ID,
            CONFIG_SERVERS => MACRO_SERVER_ID,
            CONFIG_DEVICES => MACRO_DEVICE_ID,
            CONFIG_QUALITIES => MACRO_QUALITY_ID,
        );

        foreach ($config_items as $name => $param) {
            $values = $this->getConfigValue($name);
            if (!empty($values)) {
                $idx = $this->GetProviderParameter($param);
                if (empty($idx) || !isset($values[$idx])) {
                    $this->SetProviderParameter($param, (string)key($values));
                }
            }
        }
    }

    /**
     * @param bool $force
     * @return void
     */
    public function request_provider_info($force = false)
    {
        hd_debug_print(null, true);
        hd_debug_print("force request_provider_info: " . var_export($force, true), true);

        if (!$this->hasApiCommand(API_COMMAND_ACCOUNT_INFO)) {
            $this->account_info = array();
        } else if (empty($this->account_info) || $force) {
            $account_info = $this->execApiCommandResponseNoOpt(API_COMMAND_ACCOUNT_INFO, Curl_Wrapper::RET_ARRAY);
            if ($account_info === false) {
                hd_debug_print("Failed to request_provider_info");
            } else if (isset($account_info['error'])) {
                hd_debug_print("request_provider_info: Server return error: {$account_info['error']}");
            } else {
                hd_debug_print("request_provider_info: " . json_format_unescaped($account_info), true);
                $this->account_info = $account_info;
            }
        }
    }

    /**
     * @param string $command
     * @return bool
     */
    public function hasApiCommand($command)
    {
        return isset($this->api_commands[$command]);
    }

    /**
     * @param string|null $command
     * @param array|null $params
     * @return array|null
     * @noinspection PhpUnusedParameterInspection
     */
    public function getCurlOpts($command = null, $params = null)
    {
        return null;
    }

    /**
     * @param string $command
     * @param object|bool $execResult
     * @param string|null $file
     * @param string|null $error_msg
     * @return bool|object
     */
    public function postExecAction($command, $execResult, $file = null, &$error_msg = null)
    {
        return $execResult;
    }

    /**
     * @param string $command
     * @param string $file
     * @param array $curl_opt
     * @param int $cache_opt
     * @return bool|object|array
     */
    public function execApiCommand($command, $file, $curl_opt, $cache_opt)
    {
        hd_debug_print(null, true);
        hd_debug_print("execApiCommand: $command", true);

        $command_url = $this->getApiCommand($command);
        if (empty($command_url)) {
            hd_debug_print("Command not found: $command or return empty value", true);
            return false;
        }

        if (isset($curl_opt[API_COMMAND_ADD_PARAMS])) {
            $command_url .= $curl_opt[API_COMMAND_ADD_PARAMS];
            $command_url = $this->replace_macros($command_url);
        }

        hd_debug_print("ApiCommandUrl: $command_url", true);
        $curl_wrapper = $this->plugin->setup_curl();

        $add_headers = $this->get_additional_headers($command);

        if (!empty($add_headers)) {
            if (empty($curl_opt[CURLOPT_HTTPHEADER])) {
                $curl_opt[CURLOPT_HTTPHEADER] = $add_headers;
            } else {
                $curl_opt[CURLOPT_HTTPHEADER] = array_merge($curl_opt[CURLOPT_HTTPHEADER], $add_headers);
            }
        }

        if (!empty($curl_opt[CURLOPT_HTTPHEADER])) {
            $curl_wrapper->set_send_headers($curl_opt[CURLOPT_HTTPHEADER]);
        }

        if (isset($curl_opt[CURLOPT_POST])) {
            $curl_wrapper->set_post($curl_opt[CURLOPT_POST]);
        }

        if (isset($curl_opt[CURLOPT_POSTFIELDS])) {
            $curl_wrapper->set_post_data($curl_opt[CURLOPT_POSTFIELDS]);
        }

        if (isset($curl_opt[CURLOPT_TIMEOUT])) {
            $curl_wrapper->set_download_timeout($curl_opt[CURLOPT_TIMEOUT]);
        }

        if (isset($curl_opt[CURLOPT_CONNECTTIMEOUT])) {
            $curl_wrapper->set_connect_timeout($curl_opt[CURLOPT_CONNECTTIMEOUT]);
        }

        if (is_null($file)) {
            $response = $curl_wrapper->download_content($command_url, $cache_opt);
        } else {
            $response = $curl_wrapper->download_file($command_url, $file);
        }

        if ($response === false) {
            hd_debug_print("Can't get response on request: " . $command_url);
            return false;
        }

        return is_null($file) ? $response : true;
    }

    /**
     * @param string $command
     * @param array $curl_opt
     * @param int $decode
     * @return array|bool|object
     */
    public function execApiCommandResponse($command, $curl_opt, $decode)
    {
        return $this->execApiCommand($command, null, $curl_opt, $decode);
    }

    /**
     * @param string $command
     * @param array $curl_opt
     * @param null $error_msg
     * @return bool|object
     */
    public function execApiCommandWithPostResponse($command, $curl_opt, &$error_msg = null)
    {
        return $this->postExecAction($command, $this->execApiCommandResponse($command, $curl_opt, 0), null,  $error_msg);
    }

    /**
     * @param string $command
     * @param int $decode
     * @return array|bool|object
     */
    public function execApiCommandResponseNoOpt($command, $decode)
    {
        return $this->execApiCommandResponse($command, array(), $decode);
    }

    /**
     * @param string $command
     * * @param string $file
     * * @param array $curl_opt
     * @return array|bool|object
     */
    public function execApiCommandFile($command, $file, $curl_opt = array())
    {
        return $this->execApiCommand($command, $file, $curl_opt, 0);
    }

    /**
     * @param string $command
     * @return string
     */
    public function getApiCommand($command)
    {
        hd_debug_print(null, true);

        return $this->replace_macros($this->getRawApiCommand($command));
    }

    /**
     * Recursive replace config macroses because they can contain other config macroses
     *
     * @param string $string
     * @return string
     */
    protected function replace_by_func(&$string)
    {
        static $config_macroses = array(
            MACRO_PLAYLIST_IPTV => 'GetPlaylistIptvUrl',
            MACRO_PLAYLIST_VOD => 'GetPlaylistVodUrl',
            MACRO_API => 'getApiUrl',
            MACRO_VOD_API => 'getVodApiUrl',
            MACRO_MIRROR => 'GetPlaylistMirror',
            MACRO_EPG_DOMAIN => 'GetEpgDomain',
            MACRO_SESSION_ID => 'GetSessionId',
        );

        foreach ($config_macroses as $macro => $function) {
            if (strpos($string, $macro) !== false) {
                $string = str_replace($macro, trim($this->$function()), $string);
                hd_debug_print("Replace $macro: $string", true);
                $this->replace_by_func($string);
            }
        }

        return $string;
    }

    /**
     * @param string $string
     * @return string
     */
    public function replace_macros($string)
    {
        hd_debug_print("template: $string", true);

        $string = $this->replace_by_func($string);

        static $params_macroses = array(
            MACRO_LOGIN => '',
            MACRO_PASSWORD => '',
            MACRO_STREAM_ID => '',
            MACRO_API_DOMAIN_ID => '',
            MACRO_PL_DOMAIN_ID => '',
            MACRO_DEVICE_ID => '',
            MACRO_SERVER_ID => '',
            MACRO_QUALITY_ID => '',
        );

        foreach ($params_macroses as $macro => $default) {
            if (strpos($string, $macro) !== false) {
                $value = trim($this->GetProviderParameter($macro, $default));
                hd_debug_print("Replace $macro with value '$value'", true);
                $string = str_replace($macro, $value, $string);
                hd_debug_print("Result: $string", true);
            }
        }

        $string = str_replace(MACRO_TOKEN, $this->plugin->get_cookie(PARAM_TOKEN), $string);

        hd_debug_print("result: $string", true);

        return $string;
    }

    ////////////////////////////////////////////////////////////////////////
    /// Methods

    /**
     * @param string $name
     * @param array|string $value
     * @return void
     */
    public function SetProviderParameter($name, $value)
    {
        if (!empty($this->playlist_id)) {
            $this->plugin->set_playlist_parameter($this->playlist_id, $name, $value);
        }
    }

    /**
     * @param string $name
     * @param string $default
     * @return string
     */
    public function GetProviderParameter($name, $default = '')
    {
        if (empty($this->playlist_id)) {
            return $default;
        }

        return $this->plugin->get_playlist_parameter($this->playlist_id, $name, $default);
    }

    /**
     * @param string $name
     */
    public function removeProviderParameter($name)
    {
        if (!empty($this->playlist_id)) {
            $this->plugin->remove_playlist_parameter($this->playlist_id, $name);
        }
    }

    /**
     * @return string
     */
    public function getApiUrl()
    {
        return $this->api_url;
    }
    /**
     * @param string $api_url
     */
    public function setApiUrl($api_url)
    {
        $this->api_url = $api_url;
    }

    /**
     * @return string
     */
    public function getVodApiUrl()
    {
        return $this->vod_api_url;
    }
    /**
     * @param string $vod_api_url
     */
    public function setVodApiUrl($vod_api_url)
    {
        $this->vod_api_url = $vod_api_url;
    }

    /**
     * @param string $command
     * @return string
     */
    public function getRawApiCommand($command)
    {
        hd_debug_print(null, true);

        return $this->hasApiCommand($command) ? $this->api_commands[$command] : '';
    }

    /**
     * @param string $val
     * @return string|array|null
     */
    public function getConfigValue($val)
    {
        return safe_get_value($this->config, $val);
    }

    /**
     * returns list of provider streams
     * @return array|null
     */
    public function GetStreams()
    {
        hd_debug_print(null, true);

        return $this->getConfigValue(CONFIG_STREAMS);
    }

    /**
     * returns list of provider domains
     * @return array|null
     */
    public function GetPlDomains()
    {
        hd_debug_print(null, true);

        return $this->getConfigValue(CONFIG_PL_DOMAINS);
    }

    /**
     * returns list of provider API domains
     * @return array|null
     */
    public function GetApiDomains()
    {
        hd_debug_print(null, true);

        return $this->getConfigValue(CONFIG_API_DOMAINS);
    }

    /**
     * returns list of provider servers
     * @return array|null
     */
    public function GetServers()
    {
        hd_debug_print(null, true);

        return $this->getConfigValue(CONFIG_SERVERS);
    }

    /**
     * returns list of provider devices
     * @return array|null
     */
    public function GetDevices()
    {
        hd_debug_print(null, true);

        return $this->getConfigValue(CONFIG_DEVICES);
    }

    /**
     * returns list of provider qualities
     * @return array|null
     */
    public function GetQualities()
    {
        hd_debug_print(null, true);

        return $this->getConfigValue(CONFIG_QUALITIES);
    }

    /**
     * returns account playlists
     *
     * @return array|null
     */
    public function GetPlaylistsIptv()
    {
        $config_playlist = $this->getConfigValue(CONFIG_PLAYLISTS_IPTV);
        if (!isset($config_playlist[DIRECT_PLAYLIST_ID])) {
            $config_playlist[DIRECT_PLAYLIST_ID][COLUMN_NAME] = TR::load('setup_native_url');
        }
        if (!isset($config_playlist[DIRECT_FILE_PLAYLIST_ID])) {
            $config_playlist[DIRECT_FILE_PLAYLIST_ID][COLUMN_NAME] = TR::load('setup_native_file');
        }

        return $config_playlist;
    }

    /**
     * returns iptv playlist url for current playlist id
     *
     * @return string
     */
    public function GetPlaylistIptvUrl()
    {
        $playlist_iptv_id = $this->GetPlaylistIptvId();
        hd_debug_print("Current playlist iptv id: $playlist_iptv_id", true);

        if ($playlist_iptv_id === DIRECT_PLAYLIST_ID) {
            $playlist = $this->GetProviderParameter(PARAM_CUSTOM_PLAYLIST_IPTV);
        } else if ($playlist_iptv_id === DIRECT_FILE_PLAYLIST_ID) {
            $playlist = $this->GetProviderParameter(PARAM_CUSTOM_FILE_PLAYLIST_IPTV);
        } else {
            $provider_playlists_iptv = $this->GetPlaylistsIptv();
            if (empty($provider_playlists_iptv[$playlist_iptv_id][COLUMN_URL])) {
                hd_debug_print("No iptv playlist url configured for: $playlist_iptv_id");
                $playlist = '';
            } else {
                $playlist = $provider_playlists_iptv[$playlist_iptv_id][COLUMN_URL];
            }
        }

        return $playlist;
    }

    /**
     * returns current iptv playlist id
     *
     * @return string
     */
    public function GetPlaylistIptvId()
    {
        $playlists = $this->GetPlaylistsIptv();
        $playlist_id = $this->GetProviderParameter(PARAM_PLAYLIST_IPTV_ID);
        if (empty($playlist_id) || !isset($playlists[$playlist_id])) {
            // playlist id is not set. Take first in list and remember it
            $playlist_id = (string)key($playlists);
            $this->SetProviderParameter(PARAM_PLAYLIST_IPTV_ID, $playlist_id);
        }

        return $playlist_id;
    }

    /**
     * returns account vod playlists
     *
     * @return array|null
     */
    public function GetPlaylistsVod()
    {
        return $this->getConfigValue(CONFIG_PLAYLISTS_VOD);
    }

    /**
     * returns vod playlist url for current playlist id
     *
     * @return string
     */
    public function GetPlaylistVodUrl()
    {
        $playlist_vod_id = $this->GetPlaylistVodId();
        hd_debug_print("Current playlist vod id: $playlist_vod_id", true);

        if ($playlist_vod_id === DIRECT_PLAYLIST_ID) {
            $playlist = $this->GetProviderParameter(PARAM_CUSTOM_PLAYLIST_VOD);
        } else {
            $provider_playlists_vod = $this->GetPlaylistsVod();
            if (empty($provider_playlists_vod[$playlist_vod_id][COLUMN_URL])) {
                hd_debug_print("No vod playlist url configured for: $playlist_vod_id");
                $playlist = '';
            } else {
                $playlist = $provider_playlists_vod[$playlist_vod_id][COLUMN_URL];
            }
        }

        return $playlist;
    }

    /**
     * returns vod playlist id for current playlist id
     *
     * @return string
     */
    public function GetPlaylistVodId()
    {
        $playlists = $this->GetPlaylistsVod();
        $playlist_id = $this->GetProviderParameter(PARAM_PLAYLIST_VOD_ID);
        if (empty($playlist_id) || !isset($playlists[$playlist_id])) {
            // playlist id is not set. Take first in list and remember it
            $playlist_id = (string)key($playlists);
            $this->SetProviderParameter(PARAM_PLAYLIST_VOD_ID, $playlist_id);
        }

        return $playlist_id;
    }

    /**
     * @return string
     */
    public function GetPlaylistMirror()
    {
        $mirror = '';
        $mirrors = $this->getConfigValue(CONFIG_PLAYLIST_MIRRORS);
        if (!empty($mirrors)) {
            $idx = $this->GetProviderParameter(PARAM_SELECTED_MIRROR);
            if (empty($idx) || !isset($mirrors[$idx])) {
                $idx = key($mirrors);
                $this->SetProviderParameter(PARAM_SELECTED_MIRROR, $idx);
            }
            $mirror = $mirrors[$idx];
        }

        return $mirror;
    }

    /**
     * @return string
     */
    public function GetEpgDomain()
    {
        return $this->GetProviderParameter(MACRO_EPG_DOMAIN);
    }

    /**
     * @return string
     */
    public function GetSessionId()
    {
        return $this->plugin->get_cookie(PARAM_SESSION_ID);
    }

    /**
     * set server
     *
     * @param string $server
     * @param string $error_msg
     * @return bool
     */
    public function SetServer($server, &$error_msg)
    {
        hd_debug_print(null, true);
        $this->SetProviderParameter(MACRO_SERVER_ID, $server);
        $error_msg = '';

        return true;
    }

    /**
     * set server
     *
     * @param string $devices
     * @return void
     */
    public function SetDevices($devices)
    {
        hd_debug_print(null, true);
        $this->SetProviderParameter(MACRO_DEVICE_ID, $devices);
    }

    /**
     * set stream
     * @param string $stream
     * @return void
     */
    public function SetStream($stream)
    {
        hd_debug_print(null, true);
        $this->SetProviderParameter(MACRO_STREAM_ID, $stream);
    }

    /**
     * set playlist domain
     * @param string $domain
     * @return void
     */
    public function SetPlDomain($domain)
    {
        hd_debug_print(null, true);
        $this->SetProviderParameter(MACRO_PL_DOMAIN_ID, $domain);
    }

    /**
     * set api domain
     * @param string $domain
     * @return void
     */
    public function SetApiDomain($domain)
    {
        hd_debug_print(null, true);
        $this->SetProviderParameter(MACRO_API_DOMAIN_ID, $domain);
    }

    /**
     * set quality
     * @param string $quality
     * @return void
     */
    public function SetQuality($quality)
    {
        hd_debug_print(null, true);
        $this->SetProviderParameter(MACRO_QUALITY_ID, $quality);
    }

    /**
     * @return array|false
     */
    public function GetSelectedPreset()
    {
        $preset_idx = $this->plugin->get_setting(PARAM_EPG_JSON_PRESET, 0);
        $provider_presets = $this->getConfigValue(EPG_JSON_PRESETS);
        if (!isset($provider_presets[$preset_idx])) {
            hd_debug_print("No preset for selected provider");
            return false;
        }

        $selected_preset = $provider_presets[$preset_idx];
        hd_debug_print("selected preset: {$selected_preset[EPG_JSON_PRESET_NAME]}", true);
        $preset = $this->plugin->get_epg_presets()->get($selected_preset[EPG_JSON_PRESET_NAME]);
        if (empty($preset)) {
            hd_debug_print("{$selected_preset[EPG_JSON_PRESET_NAME]} not exist in plugin configuration");
            return false;
        }

        if (isset($selected_preset[EPG_JSON_PRESET_ALIAS])) {
            $preset[EPG_JSON_PRESET_ALIAS] = $selected_preset[EPG_JSON_PRESET_ALIAS];
        }

        return $preset;
    }

    /**
     * @param array $matches
     * @param string $playlist_id
     * @return bool|array
     */
    public function fill_default_provider_info($matches, &$playlist_id)
    {
        $info[PARAM_TYPE] = PARAM_PROVIDER;
        $info[PARAM_NAME] = $this->getName();
        $info[PARAM_PROVIDER] = $matches[1];

        $vars = explode(':', $matches[2]);
        if (empty($vars)) {
            hd_debug_print("invalid provider_info: $matches[0]", true);
            return false;
        }

        hd_debug_print("parse imported provider_info: $vars[0]", true);

        switch ($this->getType()) {
            case PROVIDER_TYPE_PIN:
                hd_debug_print("set pin: $vars[0]", true);
                $info[MACRO_PASSWORD] = $vars[0];
                break;

            case PROVIDER_TYPE_LOGIN:
                hd_debug_print("set login: $vars[0]", true);
                $info[MACRO_LOGIN] = $vars[0];
                hd_debug_print("set password: $vars[1]", true);
                $info[MACRO_PASSWORD] = $vars[1];
                break;
            default:
        }

        $playlist_id = $this->get_hash($info);
        $this->plugin->set_playlist_parameters($playlist_id, $info);

        return $info;
    }

    /**
     * @param User_Input_Handler $handler
     * @return array|null
     */
    public function GetInfoUI($handler)
    {
        return null;
    }

    /**
     * @return array|null
     */
    public function GetPayUI()
    {
        return null;
    }

    /**
     * @param string $name
     * @param string $playlist_id
     * @param User_Input_Handler $handler
     * @return array|null
     */
    public function GetSetupUI($name, $playlist_id, $handler)
    {
        hd_debug_print(null, true);

        $is_new = empty($playlist_id);
        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        Control_Factory::add_text_field($defs, $handler, CONTROL_EDIT_NAME,
            TR::t('name'), $name, false,
            false, false, true, Control_Factory::DLG_CONTROLS_WIDTH);

        $type = $this->getType();
        if ($type !== PROVIDER_TYPE_PIN && $type !== PROVIDER_TYPE_LOGIN) {
            return null;
        }

        if ($type === PROVIDER_TYPE_PIN) {
            Control_Factory::add_text_field($defs, $handler, self::CONTROL_PASSWORD,
                TR::t('token'), $this->GetProviderParameter(MACRO_PASSWORD), false,
                false, false, true, Control_Factory::DLG_CONTROLS_WIDTH);
        }

        if ($type === PROVIDER_TYPE_LOGIN) {
            Control_Factory::add_text_field($defs, $handler, self::CONTROL_LOGIN,
                TR::t('login'), $this->GetProviderParameter(MACRO_LOGIN), false,
                false, false, true, Control_Factory::DLG_CONTROLS_WIDTH);
            Control_Factory::add_text_field($defs, $handler, self::CONTROL_PASSWORD,
                TR::t('password'), $this->GetProviderParameter(MACRO_PASSWORD), false,
                false, false, true, Control_Factory::DLG_CONTROLS_WIDTH);
        }

        if ($is_new) {
            $command_url = $this->getRawApiCommand(API_COMMAND_GET_PLAYLIST);
            $command_url = $this->replace_by_func($command_url);
            hd_debug_print("command_url: $command_url", true);

            $config_pl_domains = $this->getConfigValue(CONFIG_PL_DOMAINS);
            hd_debug_print("pl_domains: " . json_format_unescaped($config_pl_domains), true);
            if (!empty($config_pl_domains) && count($config_pl_domains) > 1 && strpos($command_url, MACRO_PL_DOMAIN_ID) !== false) {
                $idx = key($config_pl_domains);
                Control_Factory::add_combobox($defs, $handler, self::CONTROL_PL_DOMAIN, TR::t('pl_domain'),
                    $idx, $config_pl_domains, Control_Factory::DLG_CONTROLS_WIDTH);
            }

            $config_api_domains = $this->getConfigValue(CONFIG_API_DOMAINS);
            hd_debug_print("api_domains: " . json_format_unescaped($config_pl_domains), true);
            if (!empty($config_api_domains) && count($config_api_domains) > 1) {
                $idx = key($config_api_domains);
                Control_Factory::add_combobox($defs, $handler, self::CONTROL_API_DOMAIN, TR::t('api_domain'),
                    $idx, $config_api_domains, Control_Factory::DLG_CONTROLS_WIDTH);
            }

            $config_servers = $this->getConfigValue(CONFIG_SERVERS);
            hd_debug_print("servers: " . json_format_unescaped($config_servers), true);
            if (!empty($config_servers) && count($config_servers) > 1 && strpos($command_url, MACRO_SERVER_ID) !== false) {
                $idx = key($config_servers);
                Control_Factory::add_combobox($defs, $handler, self::CONTROL_SERVER, TR::t('server'),
                    $idx, $config_servers, Control_Factory::DLG_CONTROLS_WIDTH);
            }

            $config_qialities = $this->getConfigValue(CONFIG_QUALITIES);
            hd_debug_print("qualities: " . json_format_unescaped($config_qialities), true);
            if (!empty($config_qialities) && count($config_qialities) > 1 && strpos($command_url, MACRO_QUALITY_ID) !== false) {
                $idx = key($config_qialities);
                Control_Factory::add_combobox($defs, $handler, self::CONTROL_QUALITY, TR::t('quality'),
                    $idx, $config_qialities, Control_Factory::DLG_CONTROLS_WIDTH);
            }
        }

        Control_Factory::add_vgap($defs, 50);

        Control_Factory::add_close_dialog_and_apply_button($defs, $handler, ACTION_EDIT_PROVIDER_DLG_APPLY, TR::t('ok'),
            array(PARAM_PROVIDER => $this->getId(), CONTROL_EDIT_ITEM => $playlist_id)
        );

        Control_Factory::add_cancel_button($defs);
        Control_Factory::add_vgap($defs, 10);

        return $defs;
    }

    /**
     * @param object $user_input
     * @return array|string
     */
    public function ApplySetupUI($user_input)
    {
        hd_debug_print(null, true);

        $is_new = empty($user_input->{CONTROL_EDIT_ITEM});
        if ($is_new) {
            hd_debug_print("Create new provider info", true);
            $params[PARAM_TYPE] = PARAM_PROVIDER;
            $params[PARAM_PROVIDER] = $user_input->{PARAM_PROVIDER};
        } else {
            hd_debug_print("load info for existing playlist id: $this->playlist_id", true);
            $params = $this->plugin->get_playlist_parameters($this->playlist_id);
            hd_debug_print("provider info: " . json_format_unescaped($params), true);
        }

        if (safe_get_value($params, PARAM_NAME) !== $user_input->{CONTROL_EDIT_NAME}) {
            $params[PARAM_NAME] = $user_input->{CONTROL_EDIT_NAME};
        }

        switch ($this->getType()) {
            case PROVIDER_TYPE_PIN:
                if (empty($user_input->{self::CONTROL_PASSWORD})) {
                    return Action_Factory::show_error(false, TR::t('err_incorrect_access_data'));
                }

                $params[MACRO_PASSWORD] = $user_input->{self::CONTROL_PASSWORD};
                break;

            case PROVIDER_TYPE_LOGIN:
                if (empty($user_input->{self::CONTROL_LOGIN}) || empty($user_input->{self::CONTROL_PASSWORD})) {
                    return Action_Factory::show_error(false, TR::t('err_incorrect_access_data'));
                }

                $params[MACRO_LOGIN] = $user_input->{self::CONTROL_LOGIN};
                $params[MACRO_PASSWORD] = $user_input->{self::CONTROL_PASSWORD};
                break;

            default:
                return null;
        }

        if ($is_new) {
            $this->playlist_id = $this->get_hash($params);
            if (empty($this->playlist_id)) {
                return Action_Factory::show_error(false, TR::t('err_incorrect_access_data'));
            }
        }

        hd_debug_print("ApplySetupUI compiled account info for '$this->playlist_id': " . json_format_unescaped($params), true);
        $this->plugin->set_playlist_parameters($this->playlist_id, $params);

        // set config parameters if they not set in the playlist parameters
        hd_debug_print("Set default values for id: '$this->playlist_id'", true);
        if ($is_new) {
            $this->apply_config_defaults();
            $this->apply_user_input_parameter($user_input, self::CONTROL_SERVER, MACRO_SERVER_ID);
            $this->apply_user_input_parameter($user_input, self::CONTROL_PL_DOMAIN, MACRO_PL_DOMAIN_ID);
            $this->apply_user_input_parameter($user_input, self::CONTROL_API_DOMAIN, MACRO_API_DOMAIN_ID);
            $this->apply_user_input_parameter($user_input, self::CONTROL_QUALITY, MACRO_QUALITY_ID);
        }

        $this->check_config_values();

        $this->plugin->clear_playlist_cache($this->playlist_id);
        $this->plugin->remove_cookie(PARAM_TOKEN);
        $this->plugin->remove_cookie(PARAM_REFRESH_TOKEN);

        if (!$this->request_provider_token(true)) {
            hd_debug_print("Can't get provider token");
            return Action_Factory::show_error(false, TR::t('err_incorrect_access_data'), array(TR::t('err_cant_get_token')));
        }

        return $this->playlist_id;
    }

    /**
     * Set default values if it present in user account but not set in user credentials
     *
     * @param object $user_input
     * @param string $param
     * @param string $macro
     * @return void
     */
    protected function apply_user_input_parameter($user_input, $param, $macro)
    {
        $value = safe_get_member($user_input,  $param);
        if (!empty($value)) {
            $this->SetProviderParameter($macro, (string)$value);
        }
    }

    /**
     * Check if parameter is changed and save it
     * @param object $user_input
     * @param string $param
     * @param string $param_settings
     * @return bool
     */
    protected function IsParameterChanged($user_input, $param, $param_settings)
    {
        return isset($user_input->{$param}) && $user_input->{$param} != $this->GetProviderParameter($param_settings);
    }

    /**
     * @param array $info
     * @return string
     */
    public function get_hash($info)
    {
        $str = safe_get_value($info, MACRO_LOGIN, '');
        $str .= safe_get_value($info, MACRO_PASSWORD, '');

        if (empty($str)) {
            return '';
        }

        return $this->getId() . "_" . Hashed_Array::hash($info[PARAM_TYPE] . $info[PARAM_NAME] . $str);
    }

    /**
     * @param bool $force
     * @return bool
     */
    public function request_provider_token($force = false)
    {
        return true;
    }

    public function has_ext_params()
    {
        hd_debug_print(null, true);

        $has_ext_params = false;
        $streams = $this->GetStreams();
        if (!empty($streams) && count($streams) > 1) {
            $has_ext_params |= true;
        }

        $domains = $this->GetPlDomains();
        if (!empty($domains) && count($domains) > 1) {
            $has_ext_params |= true;
        }

        $servers = $this->GetServers();
        if (!empty($servers) && count($servers) > 1) {
            $has_ext_params |= true;
        }

        $devices = $this->GetDevices();
        if (!empty($devices) && count($devices) > 1) {
            $has_ext_params |= true;
        }

        $qualities = $this->GetQualities();
        if (!empty($qualities) && count($qualities) > 1) {
            $has_ext_params |= true;
        }

        $playlists = $this->GetPlaylistsIptv();
        if (!empty($playlists)) {
            $has_ext_params |= true;
        }

        $icon_replacements = $this->getConfigValue(CONFIG_ICON_REPLACE);
        if (!empty($icon_replacements)) {
            $has_ext_params |= true;
        }

        $playlist_mirrors = $this->getConfigValue(CONFIG_PLAYLIST_MIRRORS);
        if (!empty($playlist_mirrors)) {
            $has_ext_params |= true;
        }

        return $has_ext_params;
    }

    /**
     * Get additional curl headers
     * @param string $command command for wich can be added http headers
     * @return array
     */
    protected function get_additional_headers($command)
    {
        return array();
    }
}
