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

require_once "lib/curl_wrapper.php";

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
     * @var
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
    protected $packages = array();

    /**
     * @var Curl_Wrapper
     */
    protected $curl_wrapper;

    public function __construct(DunePlugin $plugin)
    {
        $this->plugin = $plugin;
        $this->curl_wrapper = Curl_Wrapper::getInstance();
    }

    ////////////////////////////////////////////////////////////////////////
    /// non configurable vars

    public function __toString()
    {
        return (string)pretty_json_format(get_object_vars($this));
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
     * @return  Curl_Wrapper
     */
    public function getCurlWrapper()
    {
        return $this->curl_wrapper;
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
     * Set default values if it present in user account but not set in user credentials
     *
     * @return void
     */
    public function set_provider_defaults()
    {
    }

    /**
     * @param bool $force
     * @return bool|object
     */
    public function get_provider_info($force = false)
    {
        hd_debug_print(null, true);
        hd_debug_print("force get_provider_info: " . var_export($force, true), true);

        if (!$this->request_provider_token()) {
            hd_debug_print("Failed to get provider token", true);
            return null;
        }

        if (!$this->hasApiCommand(API_COMMAND_ACCOUNT_INFO)) {
            $this->account_info = array();
        } else if (empty($this->account_info) || $force) {
            $account_info = $this->execApiCommand(API_COMMAND_ACCOUNT_INFO);
            if ($account_info === false || isset($account_info->error)) {
                hd_debug_print("Failed to get provider info", true);
            } else {
                hd_debug_print("get_provider_info: " . pretty_json_format($account_info), true);
                $this->account_info = $account_info;
                $this->set_provider_defaults();
            }
        }

        return $this->account_info;
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
     * @param string $command
     * @param string $file
     * @param bool $decode
     * @param array $curl_opt
     * @return bool|object
     */
    public function execApiCommand($command, $file = null, $decode = true, $curl_opt = array())
    {
        hd_debug_print(null, true);
        hd_debug_print("execApiCommand: $command", true);
        hd_debug_print("curl options: " . pretty_json_format($curl_opt), true);

        $command_url = $this->getApiCommand($command);
        if (empty($command_url)) {
            return false;
        }

        if (isset($curl_opt[CURLOPT_CUSTOMREQUEST])) {
            $command_url .= $curl_opt[CURLOPT_CUSTOMREQUEST];
            unset($curl_opt[CURLOPT_CUSTOMREQUEST]);
        }

        $command_url = $this->replace_macros($command_url);
        hd_debug_print("ApiCommandUrl: $command_url", true);
        $this->curl_wrapper->reset();
        $this->plugin->set_curl_timeouts($this->curl_wrapper);

        $add_headers = $this->get_additional_headers($command);

        if (!empty($add_headers)) {
            if (empty($curl_opt[CURLOPT_HTTPHEADER])) {
                $curl_opt[CURLOPT_HTTPHEADER] = $add_headers;
            } else {
                $curl_opt[CURLOPT_HTTPHEADER] = array_merge($curl_opt[CURLOPT_HTTPHEADER], $add_headers);
            }
        }

        if (!empty($curl_opt[CURLOPT_HTTPHEADER])) {
            $this->curl_wrapper->set_send_headers($curl_opt[CURLOPT_HTTPHEADER]);
        }

        if (isset($curl_opt[CURLOPT_POST])) {
            $this->curl_wrapper->set_post($curl_opt[CURLOPT_POST]);
        }

        if (isset($curl_opt[CURLOPT_POSTFIELDS])) {
            $this->curl_wrapper->set_post_data($curl_opt[CURLOPT_POSTFIELDS]);
        }

        if (is_null($file)) {
            $response = $this->curl_wrapper->download_content($command_url);
        } else {
            $response = $this->curl_wrapper->download_file($command_url, $file, false);
        }

        if ($response === false) {
            hd_debug_print("Can't get response on request: " . $command_url);
            return false;
        }

        if (!is_null($file)) {
            return true;
        }

        if (!$decode) {
            return $response;
        }

        $data = Curl_Wrapper::decodeJsonResponse(false, $response);
        if ($data === false || $data === null) {
            hd_debug_print("Can't decode response on request: " . $command_url);
        }

        return $data;
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
     * @param string $string
     * @return string
     */
    public function replace_macros($string)
    {
        $macroses = array(
            MACRO_LOGIN => '',
            MACRO_PASSWORD => '',
            MACRO_STREAM_ID => '',
            MACRO_SESSION_ID => '',
            MACRO_DOMAIN_ID => '',
            MACRO_DEVICE_ID => '',
            MACRO_SERVER_ID => '',
            MACRO_QUALITY_ID => '',
            MACRO_PLAYLIST_ID => '',
            MACRO_PLAYLIST_VOD_ID => '',
        );

        hd_debug_print("template: $string", true);
        $string = str_replace(
            array(MACRO_API, MACRO_PLAYLIST_IPTV, MACRO_PLAYLIST_VOD, MACRO_EPG_DOMAIN),
            array($this->getApiUrl(),
                $this->GetParameter(MACRO_PLAYLIST_IPTV),
                $this->GetParameter(MACRO_PLAYLIST_VOD),
                $this->GetParameter(MACRO_EPG_DOMAIN)),
            $string);

        $mirrors = $this->getConfigValue(CONFIG_PLAYLIST_MIRRORS);
        if (!empty($mirrors)) {
            reset($mirrors);
            $selected = $this->GetParameter(PARAM_SELECTED_MIRROR, key($mirrors));
            $macroses[MACRO_MIRROR] = $mirrors[$selected];
        }

        foreach ($macroses as $macro => $default) {
            if (strpos($string, $macro) !== false) {
                $string = str_replace($macro, trim($this->GetParameter($macro, $default)), $string);
            }
        }
        hd_debug_print("result: $string", true);

        return $string;
    }

    ////////////////////////////////////////////////////////////////////////
    /// Methods

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
     * @param string $command
     * @return string
     */
    public function getRawApiCommand($command)
    {
        hd_debug_print(null, true);
        hd_debug_print(json_encode($this->api_commands), true);

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
    public function GetDomains()
    {
        hd_debug_print(null, true);

        return $this->getConfigValue(CONFIG_DOMAINS);
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
     * returns list of account playlists
     * @return array|null
     */
    public function GetPlaylists()
    {
        hd_debug_print(null, true);

        $config_playlist = $this->getConfigValue(CONFIG_PLAYLISTS_IPTV);
        if (!isset($config_playlist[DIRECT_PLAYLIST_ID])) {
            $config_playlist[DIRECT_PLAYLIST_ID][COLUMN_NAME] = TR::load('setup_native_url');
        }

        return $config_playlist;
    }

    /**
     * returns list of account playlists
     * @return array|null
     */
    public function GetPlaylistsVod()
    {
        hd_debug_print(null, true);

        return $this->getConfigValue(CONFIG_PLAYLISTS_VOD);
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
     * @param string $tmp_file
     * @return bool
     */
    public function load_playlist($tmp_file)
    {
        hd_debug_print(null, true);

        $playlists = $this->GetPlaylists();
        if (empty($playlists)) {
            return false;
        }

        $playlist_id = $this->GetParameter(MACRO_PLAYLIST_ID);
        if (empty($playlist_id)) {
            // playlist id not set. Take first in list and remember it
            $playlist_id = (string)key($playlists);
            $this->SetParameter(MACRO_PLAYLIST_ID, $playlist_id);
        }

        if ($playlist_id === DIRECT_PLAYLIST_ID) {
            $playlist = $this->GetParameter(MACRO_CUSTOM_PLAYLIST);
        } else if (!empty($playlists[$playlist_id][COLUMN_URL])) {
            $playlist = $playlists[$playlist_id][COLUMN_URL];
        }

        if (empty($playlist)) {
            return false;
        }

        if ($playlist !== $this->GetParameter(MACRO_PLAYLIST_IPTV)) {
            $this->SetParameter(MACRO_PLAYLIST_IPTV, $playlist);
        }

        return $this->execApiCommand(API_COMMAND_GET_PLAYLIST, $tmp_file);
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
        $defs = array();
        Control_Factory::add_vgap($defs, 20);

        Control_Factory::add_text_field($defs, $handler, null,
            CONTROL_EDIT_NAME, TR::t('name'), $name,
            false, false, false, true, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);

        $type = $this->getType();
        if ($type !== PROVIDER_TYPE_PIN && $type !== PROVIDER_TYPE_LOGIN) {
            return null;
        }

        if ($type === PROVIDER_TYPE_PIN) {
            Control_Factory::add_text_field($defs, $handler, null,
                self::CONTROL_PASSWORD, TR::t('token'), $this->GetParameter(MACRO_PASSWORD),
                false, false, false, true, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);
        }

        if ($type === PROVIDER_TYPE_LOGIN) {
            Control_Factory::add_text_field($defs, $handler, null,
                self::CONTROL_LOGIN, TR::t('login'), $this->GetParameter(MACRO_LOGIN),
                false, false, false, true, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);
            Control_Factory::add_text_field($defs, $handler, null,
                self::CONTROL_PASSWORD, TR::t('password'), $this->GetParameter(MACRO_PASSWORD),
                false, false, false, true, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);
        }

        Control_Factory::add_vgap($defs, 50);

        Control_Factory::add_close_dialog_and_apply_button($defs, $handler,
            ACTION_EDIT_PROVIDER_DLG_APPLY,
            TR::t('ok'),
            300,
            array(PARAM_PROVIDER => $this->getId(), CONTROL_EDIT_ITEM => $playlist_id)
        );

        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
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

        if (empty($this->playlist_id)) {
            $is_new = true;
            hd_debug_print("Create new provider info", true);
            $params[PARAM_TYPE] = PARAM_PROVIDER;
            $params[PARAM_PROVIDER] = $user_input->{PARAM_PROVIDER};
        } else {
            $is_new = false;
            hd_debug_print("load info for existing playlist id: $this->playlist_id", true);
            $params = $this->plugin->get_playlist_parameters($this->playlist_id);
            hd_debug_print("provider info: " . pretty_json_format($params), true);
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

        hd_debug_print("ApplySetupUI compiled account info for '$this->playlist_id': " . pretty_json_format($params), true);
        $this->plugin->set_playlist_parameters($this->playlist_id, $params);

        // set config parameters if they not set in the playlist parameters
        hd_debug_print("Set default values for id: '$this->playlist_id'", true);
        static $config_items = array(
            CONFIG_STREAMS => MACRO_STREAM_ID,
            CONFIG_DOMAINS => MACRO_DOMAIN_ID,
            CONFIG_SERVERS => MACRO_SERVER_ID,
            CONFIG_DEVICES => MACRO_DEVICE_ID,
            CONFIG_QUALITIES => MACRO_QUALITY_ID,
            CONFIG_PLAYLISTS_IPTV => MACRO_PLAYLIST_ID,
            CONFIG_PLAYLISTS_VOD => MACRO_PLAYLIST_VOD_ID,
        );

        foreach ($config_items as $name => $param) {
            $values = $this->getConfigValue($name);
            if (!empty($values)) {
                $idx = $this->GetParameter($param);
                if (empty($idx)) {
                    $this->SetParameter($param, (string)key($values));
                }
            }
        }

        // set provider parameters if they not set in the playlist parameters
        // parameters obtain from user account
        $this->set_provider_defaults();

        if ($is_new) {
            $this->apply_config_defaults();
        }

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
     * Check if parameter is changed and save it
     * @param object $user_input
     * @param string $param
     * @param string $param_settings
     * @return bool
     */
    protected function IsParameterChanged($user_input, $param, $param_settings)
    {
        return isset($user_input->{$param}) && $user_input->{$param} != $this->GetParameter($param_settings);
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

        $domains = $this->GetDomains();
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

        $playlists = $this->GetPlaylists();
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
     * set server
     *
     * @param string $server
     * @param string $error_msg
     * @return bool
     */
    public function SetServer($server, &$error_msg)
    {
        hd_debug_print(null, true);
        $this->SetParameter(MACRO_SERVER_ID, $server);
        $error_msg = '';

        return true;
    }

    /**
     * set playlist
     *
     * @param string $id
     * @return void
     */
    public function SetPlaylist($id)
    {
        hd_debug_print(null, true);
        $this->SetParameter(MACRO_PLAYLIST_ID, $id);
    }

    /**
     * set server
     *
     * @param string $device
     * @return void
     */
    public function SetDevice($device)
    {
        hd_debug_print(null, true);
        $this->SetParameter(MACRO_DEVICE_ID, $device);
    }

    /**
     * set stream
     * @param string $stream
     * @return void
     */
    public function SetStream($stream)
    {
        hd_debug_print(null, true);
        $this->SetParameter(MACRO_STREAM_ID, $stream);
    }

    /**
     * set domain
     * @param string $domain
     * @return void
     */
    public function SetDomain($domain)
    {
        hd_debug_print(null, true);
        $this->SetParameter(MACRO_DOMAIN_ID, $domain);
    }

    /**
     * set quality
     * @param string $quality
     * @return void
     */
    public function SetQuality($quality)
    {
        hd_debug_print(null, true);
        $this->SetParameter(MACRO_QUALITY_ID, $quality);
    }

    /**
     * @param string $name
     * @param array|string $value
     * @return void
     */
    public function SetParameter($name, $value)
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
    public function GetParameter($name, $default = '')
    {
        if (empty($this->playlist_id)) {
            return $default;
        }

        return $this->plugin->get_playlist_parameter($this->playlist_id, $name, $default);
    }

    /**
     * @param string $name
     */
    public function removeParameter($name)
    {
        if (!empty($this->playlist_id)) {
            $this->plugin->remove_playlist_parameter($this->playlist_id, $name);
        }
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
