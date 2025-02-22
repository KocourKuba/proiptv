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
    /**
     * @var string
     */
    protected $id = '';

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
     * @var Named_Storage
     */
    protected $playlist_info;

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
        $this->curl_wrapper = new Curl_Wrapper();
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
        hd_debug_print(null, true);
        hd_debug_print("playlist id: $playlist_id", true);

        if (empty($playlist_id)) {
            return;
        }

        $this->playlist_id = $playlist_id;
        $item = $this->plugin->get_playlists()->get($playlist_id);
        if ($item !== null && isset($item->params)) {
            $this->playlist_info = $item;
            hd_debug_print("provider info: ($playlist_id) " . pretty_json_format($this->playlist_info), true);
        } else {
            hd_debug_print("incorrect provider info: $playlist_id");
        }

        $this->set_config_defaults();

        if ($this->get_provider_info() !== false) {
            $this->set_provider_defaults();
        } else {
            hd_debug_print("Can't get provider info!");
        }
    }

    /**
     * Set default values if it present in provider config but not set in user credentials
     *
     * @return void
     */
    public function set_config_defaults()
    {
        hd_debug_print(null, true);

        static $config_items = array(
            CONFIG_STREAMS => MACRO_STREAM_ID,
            CONFIG_DOMAINS => MACRO_DOMAIN_ID,
            CONFIG_SERVERS => MACRO_SERVER_ID,
            CONFIG_DEVICES => MACRO_DEVICE_ID,
            CONFIG_QUALITIES => MACRO_QUALITY_ID,
            CONFIG_PLAYLISTS => MACRO_PLAYLIST_ID,
        );

        foreach ($config_items as $name => $param) {
            $values = $this->getConfigValue($name);
            if (!empty($values)) {
                $idx = $this->getCredential($param);
                if (empty($idx)) {
                    $this->setCredential($param, (string)key($values));
                }
            }
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

        if ((empty($this->account_info) || $force) && $this->hasApiCommand(API_COMMAND_ACCOUNT_INFO)) {
            $account_info = $this->execApiCommand(API_COMMAND_ACCOUNT_INFO);
            if ($account_info === false || isset($account_info->error)) {
                hd_debug_print("Failed to get provider token", true);
            } else {
                hd_debug_print("get_provider_info: " . pretty_json_format($account_info), true);
                $this->account_info = $account_info;
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

        hd_debug_print("ApiCommandUrl: $command_url", true);
        $this->curl_wrapper->set_url($command_url);

        $add_headers = $this->get_additional_headers($command);

        if (empty($curl_opt[CURLOPT_HTTPHEADER]) && !empty($add_headers)) {
            $curl_opt[CURLOPT_HTTPHEADER] = $add_headers;
        } else if (!empty($curl_opt[CURLOPT_HTTPHEADER]) && !empty($add_headers)) {
            $curl_opt[CURLOPT_HTTPHEADER] = array_merge($curl_opt[CURLOPT_HTTPHEADER], $add_headers);
        }

        if (!empty($curl_opt[CURLOPT_HTTPHEADER])) {
            $this->curl_wrapper->set_send_headers($curl_opt[CURLOPT_HTTPHEADER]);
        }

        if (isset($curl_opt[CURLOPT_POST])) {
            hd_debug_print("CURLOPT_POST: " . var_export($curl_opt[CURLOPT_POST], true), true);
            $this->curl_wrapper->set_post($curl_opt[CURLOPT_POST]);
        }

        if (isset($curl_opt[CURLOPT_POSTFIELDS])) {
            hd_debug_print("CURLOPT_POSTFIELDS: {$curl_opt[CURLOPT_POSTFIELDS]}", true);
            $this->curl_wrapper->set_post_data($curl_opt[CURLOPT_POSTFIELDS]);
        }

        if (is_null($file)) {
            $response = $this->curl_wrapper->download_content();
        } else {
            $response = $this->curl_wrapper->download_file($file);
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
        static $macroses = array(
            MACRO_LOGIN,
            MACRO_PASSWORD,
            MACRO_STREAM_ID,
            MACRO_SUBDOMAIN,
            MACRO_OTTKEY,
            MACRO_SESSION_ID,
            MACRO_DOMAIN_ID,
            MACRO_DEVICE_ID,
            MACRO_SERVER_ID,
            MACRO_QUALITY_ID,
            MACRO_PLAYLIST_ID,
            MACRO_VPORTAL,
        );

        hd_debug_print("template: $string", true);
        $string = str_replace(
            array(MACRO_API, MACRO_PLAYLIST, MACRO_EPG_DOMAIN),
            array($this->getApiUrl(), $this->getCredential(MACRO_PLAYLIST), $this->getCredential(MACRO_EPG_DOMAIN)),
            $string);

        foreach ($macroses as $macro) {
            if (strpos($string, $macro) !== false) {
                $string = str_replace($macro, trim($this->getCredential($macro)), $string);
            }
        }
        hd_debug_print("result: $string", true);

        return $string;
    }

    /**
     * @return string
     */
    public function getApiUrl()
    {
        return $this->api_url;
    }

    ////////////////////////////////////////////////////////////////////////
    /// Methods

    /**
     * @param string $api_url
     */
    public function setApiUrl($api_url)
    {
        $this->api_url = $api_url;
    }

    /**
     * @param string $name
     * @param string $default
     * @return string
     */
    public function getCredential($name, $default = '')
    {
        return isset($this->playlist_info->params[$name]) ? $this->playlist_info->params[$name] : $default;
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
        return isset($this->config[$val]) ? $this->config[$val] : null;
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
     * @param string $name
     * @param array|string $value
     * @return void
     */
    public function setCredential($name, $value)
    {
        $this->playlist_info->params[$name] = $value;
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

        return $this->getConfigValue(CONFIG_PLAYLISTS);
    }

    /**
     * @param array $matches
     * @param string $hash
     * @return bool|Named_Storage
     */
    public function fill_default_provider_info($matches, &$hash)
    {
        $info = new Named_Storage();
        $info->type = PARAM_PROVIDER;
        $info->params[PARAM_PROVIDER] = $matches[1];
        $info->name = $this->getName();

        $vars = explode(':', $matches[2]);
        if (empty($vars)) {
            hd_debug_print("invalid provider_info: $matches[0]", true);
            return false;
        }

        hd_debug_print("parse imported provider_info: $vars[0]", true);

        switch ($this->getType()) {
            case PROVIDER_TYPE_PIN:
                hd_debug_print("set pin: $vars[0]", true);
                $info->params[MACRO_PASSWORD] = $vars[0];
                break;

            case PROVIDER_TYPE_LOGIN:
                hd_debug_print("set login: $vars[0]", true);
                $info->params[MACRO_LOGIN] = $vars[0];
                hd_debug_print("set password: $vars[1]", true);
                $info->params[MACRO_PASSWORD] = $vars[1];
                break;
            default:
        }

        $hash = Hashed_Array::hash($info->type . $info->name . $info->params[MACRO_LOGIN] . $info->params[MACRO_PASSWORD]);

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
     * @param string $name
     * @return bool
     */
    public function removeCredential($name)
    {
        if (isset($this->playlist_info->params[$name])) {
            unset($this->playlist_info->params[$name]);
            return true;
        }

        return false;
    }

    /**
     * @return string|null
     */
    public function get_vod_class()
    {
        if ($this->hasApiCommand(API_COMMAND_GET_VOD)) {
            $vod_class = "vod_" . $this->getId();
            if (class_exists($vod_class)) {
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
     * @param string $tmp_file
     * @return bool
     */
    public function load_playlist($tmp_file)
    {
        hd_debug_print(null, true);

        $playlists = $this->GetPlaylists();
        if (!empty($playlists)) {
            $idx = $this->getCredential(MACRO_PLAYLIST_ID);
            $playlist = '';
            if ($idx === CUSTOM_PLAYLIST_ID) {
                $playlist = $this->getCredential(MACRO_CUSTOM_PLAYLIST);
            } else if (!empty($playlists[$idx]['url'])) {
                $playlist = $playlists[$idx]['url'];
            }

            $this->setCredential(MACRO_PLAYLIST, $playlist);
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

        Control_Factory::add_text_field($defs, $handler, null,
            CONTROL_EDIT_NAME, TR::t('name'), $name,
            false, false, false, true, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);

        switch ($this->getType()) {
            case PROVIDER_TYPE_PIN:
                Control_Factory::add_text_field($defs, $handler, null,
                    CONTROL_PASSWORD, TR::t('token'), $this->getCredential(MACRO_PASSWORD),
                    false, false, false, true, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);
                break;

            case PROVIDER_TYPE_LOGIN:
                Control_Factory::add_text_field($defs, $handler, null,
                    CONTROL_LOGIN, TR::t('login'), $this->getCredential(MACRO_LOGIN),
                    false, false, false, true, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);
                Control_Factory::add_text_field($defs, $handler, null,
                    CONTROL_PASSWORD, TR::t('password'), $this->getCredential(MACRO_PASSWORD),
                    false, false, false, true, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);
                break;

            default:
                return null;
        }

        Control_Factory::add_vgap($defs, 50);

        Control_Factory::add_close_dialog_and_apply_button($defs, $handler,
            array(PARAM_PROVIDER => $this->getId(), CONTROL_EDIT_ITEM => $playlist_id),
            ACTION_EDIT_PROVIDER_DLG_APPLY,
            TR::t('ok'), 300);

        Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
        Control_Factory::add_vgap($defs, 10);

        return $defs;
    }

    /**
     * @param Object $user_input
     * @return bool|array|string
     */
    public function ApplySetupUI($user_input)
    {
        hd_debug_print(null, true);

        $id = empty($user_input->{CONTROL_EDIT_ITEM}) ? '' : $user_input->{CONTROL_EDIT_ITEM};

        if (!empty($id)) {
            hd_debug_print("load info for existing playlist id: $id", true);
            $this->playlist_info = $this->plugin->get_playlists()->get($id);
            hd_debug_print("provider info: " . pretty_json_format($this->playlist_info), true);
        }

        if (is_null($this->playlist_info)) {
            hd_debug_print("Create new provider info", true);
            $this->playlist_info = new Named_Storage();
            $this->playlist_info->type = PARAM_PROVIDER;
            $this->playlist_info->name = $user_input->{CONTROL_EDIT_NAME};
            $this->setCredential(PARAM_PROVIDER, $user_input->{PARAM_PROVIDER});
        }

        if ($this->playlist_info->name !== $user_input->{CONTROL_EDIT_NAME}) {
            $this->playlist_info->name = $user_input->{CONTROL_EDIT_NAME};
        }

        switch ($this->getType()) {
            case PROVIDER_TYPE_PIN:
                if (empty($user_input->{CONTROL_PASSWORD})) {
                    return Action_Factory::show_error(false, TR::t('err_incorrect_access_data'));
                }

                $this->setCredential(MACRO_PASSWORD, $user_input->{CONTROL_PASSWORD});
                break;

            case PROVIDER_TYPE_LOGIN:
                if (empty($user_input->{CONTROL_LOGIN}) || empty($user_input->{CONTROL_PASSWORD})) {
                    return Action_Factory::show_error(false, TR::t('err_incorrect_access_data'));
                }

                $this->setCredential(MACRO_LOGIN, $user_input->{CONTROL_LOGIN});
                $this->setCredential(MACRO_PASSWORD, $user_input->{CONTROL_PASSWORD});
                break;

            default:
                return null;
        }

        $is_new = empty($id);
        $id = $is_new ? $this->get_hash($this->playlist_info) : $id;
        if (empty($id)) {
            return Action_Factory::show_error(false, TR::t('err_incorrect_access_data'));
        }

        hd_debug_print("ApplySetupUI compiled provider ($id) info: " . pretty_json_format($this->playlist_info), true);

        if ($is_new) {
            hd_debug_print("Set default values for id: $id", true);
            $this->set_default_settings($user_input, $id);
            $this->set_config_defaults();
        }

        $this->plugin->get_playlists()->set($id, $this->playlist_info);
        $this->plugin->save_parameters(true);
        $this->plugin->clear_playlist_cache($id);

        $this->clear_session_info();

        if (!$this->request_provider_token(true)) {
            hd_debug_print("Can't get provider token");
            return Action_Factory::show_error(false, TR::t('err_incorrect_access_data'), array(TR::t('err_cant_get_token')));
        }

        $this->set_provider_defaults();
        return $id;
    }

    protected function check_control_parameters($user_input, $param, $param_settings)
    {
        return (isset($user_input->{$param})
            && (!isset($this->playlist_info->params[$param_settings])
                || $this->playlist_info->params[$param_settings] !== $user_input->{$param}));
    }

    /**
     * @param Named_Storage $info
     * @return string
     */
    public function get_hash($info)
    {
        $str = '';
        if (isset($info->params[MACRO_LOGIN])) {
            $str .= $info->params[MACRO_LOGIN];
        }

        if (isset($info->params[MACRO_PASSWORD])) {
            $str .= $info->params[MACRO_PASSWORD];
        }

        if (empty($str)) {
            return '';
        }

        return $this->getId() . "_" . Hashed_Array::hash($info->type . $info->name . $str);
    }

    /**
     * @param Object $user_input
     * @param string $id
     * @return void
     */
    protected function set_default_settings($user_input, $id)
    {
        if (empty($user_input->{CONTROL_EDIT_ITEM})) {
            // new provider. Fill default values
            $settings = dune_plugin_settings::get_settings($id);
            $xmltv_picons = $this->getConfigValue(XMLTV_PICONS);
            if ($xmltv_picons) {
                $settings[PARAM_USE_PICONS] = XMLTV_PICONS;
            }

            $epg_preset = $this->getConfigValue(EPG_JSON_PRESETS);
            if (!empty($epg_preset)) {
                $settings[PARAM_EPG_CACHE_ENGINE] = ENGINE_JSON;
                $settings[PARAM_EPG_JSON_PRESET] = 0;
            }

            $detect_stream = $this->getConfigValue(PARAM_DUNE_FORCE_TS);
            if ($detect_stream) {
                $settings[PARAM_DUNE_FORCE_TS] = $detect_stream;
            }

            if (!empty($settings)) {
                dune_plugin_settings::put_settings($id, $settings);
            }
        }

        $this->plugin->get_playlists()->set($id, $this->playlist_info);

        if ($this->plugin->get_active_playlist_key() === $id) {
            $this->plugin->set_active_playlist_key($id);
        }
    }

    /**
     * @param bool $force
     * @return bool
     */
    public function request_provider_token($force = false)
    {
        return true;
    }

    /**
     * @return void
     */
    public function clear_session_info()
    {
    }

    /**
     * @param User_Input_Handler $handler
     * @return array|null
     */
    public function GetExtSetupUI($handler)
    {
        hd_debug_print(null, true);

        $defs = array();

        $streams = $this->GetStreams();
        if (!empty($streams) && count($streams) > 1) {
            $idx = $this->getCredential(MACRO_STREAM_ID);
            if (empty($idx)) {
                $idx = key($streams);
            }
            hd_debug_print("streams ($idx): " . json_encode($streams), true);

            Control_Factory::add_combobox($defs, $handler, null, CONTROL_STREAM,
                TR::t('stream'), $idx, $streams, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);
        }

        $domains = $this->GetDomains();
        if (!empty($domains) && count($domains) > 1) {
            $idx = $this->getCredential(MACRO_DOMAIN_ID);
            if (empty($idx)) {
                $idx = key($domains);
            }
            hd_debug_print("domains ($idx): " . json_encode($domains), true);

            Control_Factory::add_combobox($defs, $handler, null, CONTROL_DOMAIN,
                TR::t('domain'), $idx, $domains, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);
        }

        $servers = $this->GetServers();
        if (!empty($servers) && count($servers) > 1) {
            $idx = $this->getCredential(MACRO_SERVER_ID);
            if (empty($idx)) {
                $idx = key($servers);
            }
            hd_debug_print("servers ($idx): " . json_encode($servers), true);

            Control_Factory::add_combobox($defs, $handler, null, CONTROL_SERVER,
                TR::t('server'), $idx, $servers, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);
        }

        $devices = $this->GetDevices();
        if (!empty($devices) && count($devices) > 1) {
            $idx = $this->getCredential(MACRO_DEVICE_ID);
            if (empty($idx)) {
                $idx = key($devices);
            }
            hd_debug_print("devices ($idx): " . json_encode($devices), true);

            Control_Factory::add_combobox($defs, $handler, null, CONTROL_DEVICE,
                TR::t('device'), $idx, $devices, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);
        }

        $qualities = $this->GetQualities();
        if (!empty($qualities) && count($qualities) > 1) {
            $idx = $this->getCredential(MACRO_QUALITY_ID);
            if (empty($idx)) {
                $idx = key($qualities);
            }
            hd_debug_print("qualities ($idx): " . json_encode($qualities), true);

            Control_Factory::add_combobox($defs, $handler, null, CONTROL_QUALITY,
                TR::t('quality'), $idx, $qualities, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);
        }

        $playlists = $this->GetPlaylists();
        if (!empty($playlists) && count($playlists) > 1) {
            $pl_names = array_map(function ($pl) { return $pl['name']; }, $playlists);
            $idx = $this->getCredential(MACRO_PLAYLIST_ID);
            if (empty($idx)) {
                $idx = key($pl_names);
                $this->setCredential(MACRO_PLAYLIST_ID, $idx);
            }

            hd_debug_print("playlist ($idx): " . json_encode($playlists), true);

            Control_Factory::add_combobox($defs, $handler, null, CONTROL_PLAYLIST,
                TR::t('playlist'), $idx, $pl_names, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH, true);
        }

        $icon_replacements = $this->getConfigValue(CONFIG_ICON_REPLACE);
        if (!empty($icon_replacements)) {
            $val = $this->getCredential(PARAM_REPLACE_ICON, SetupControlSwitchDefs::switch_on);
            Control_Factory::add_combobox($defs, $handler, null, CONTROL_REPLACE_ICONS,
                TR::t('setup_channels_square_icons'), $val, SetupControlSwitchDefs::$on_off_translated,
                Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH);
        }

        if (!empty($defs)) {
            Control_Factory::add_vgap($defs, 50);

            Control_Factory::add_close_dialog_and_apply_button($defs, $handler,
                array(PARAM_PROVIDER => $this->getId()),
                ACTION_EDIT_PROVIDER_EXT_DLG_APPLY,
                TR::t('ok'), 300);

            Control_Factory::add_close_dialog_button($defs, TR::t('cancel'), 300);
            Control_Factory::add_vgap($defs, 10);
        }

        return $defs;
    }

    /**
     * @param Object $user_input
     * @return bool|array
     */
    public function ApplyExtSetupUI($user_input)
    {
        hd_debug_print(null, true);

        $changed = false;
        $error_message = '';
        if ($this->check_control_parameters($user_input, CONTROL_SERVER, MACRO_SERVER_ID)) {
            $changed = $this->SetServer($user_input->{CONTROL_SERVER}, $error_message);
        }

        if ($this->check_control_parameters($user_input, CONTROL_PLAYLIST, MACRO_PLAYLIST_ID)) {
            $this->SetPlaylist($user_input->{CONTROL_PLAYLIST});
            $changed = true;
        }

        if ($this->check_control_parameters($user_input, CONTROL_DEVICE, MACRO_DEVICE_ID)) {
            $this->SetDevice($user_input->{CONTROL_DEVICE});
            $changed = true;
        }

        if ($this->check_control_parameters($user_input, CONTROL_DOMAIN, MACRO_DOMAIN_ID)) {
            $this->setCredential(MACRO_DOMAIN_ID, $user_input->{CONTROL_DOMAIN});
            $changed = true;
        }

        if ($this->check_control_parameters($user_input, CONTROL_QUALITY, MACRO_QUALITY_ID)) {
            $this->setCredential(MACRO_QUALITY_ID, $user_input->{CONTROL_QUALITY});
            $changed = true;
        }

        if ($this->check_control_parameters($user_input, CONTROL_STREAM, MACRO_STREAM_ID)) {
            $this->setCredential(MACRO_STREAM_ID, $user_input->{CONTROL_STREAM});
            $changed = true;
        }

        if ($this->check_control_parameters($user_input, CONTROL_REPLACE_ICONS, PARAM_REPLACE_ICON)) {
            $this->setCredential(PARAM_REPLACE_ICON, $user_input->{CONTROL_REPLACE_ICONS});
            $changed = true;
        }

        hd_debug_print("ApplyExtSetupUI compiled provider info: " . pretty_json_format($this->playlist_info), true);

        if ($changed) {
            $playlist_id = $this->plugin->get_active_playlist_key();
            $this->plugin->get_playlists()->set($playlist_id, $this->playlist_info);
            $this->plugin->save_parameters(true);
            $this->plugin->clear_playlist_cache($playlist_id);
        }

        if (!empty($error_message)) {
            return Action_Factory::show_title_dialog(TR::t('err_error'), null, $error_message);
        }

        return true;
    }

    /**
     * set server
     * @param string $server
     * @param string $error_msg
     * @return bool
     */
    public function SetServer($server, &$error_msg)
    {
        hd_debug_print(null, true);

        $this->setCredential(MACRO_SERVER_ID, $server);
        $error_msg = '';

        return true;
    }

    /**
     * set playlist
     * @param string $id
     * @return void
     */
    public function SetPlaylist($id)
    {
        hd_debug_print(null, true);
        hd_debug_print("SetPlaylist: $id");

        $this->setCredential(MACRO_PLAYLIST_ID, $id);
    }

    /**
     * set server
     * @param string $device
     * @return void
     */
    public function SetDevice($device)
    {
        hd_debug_print(null, true);

        $this->setCredential(MACRO_DEVICE_ID, $device);
    }

    /**
     * set stream
     * @param string $stream
     * @return void
     */
    public function SetStream($stream)
    {
        hd_debug_print(null, true);
        $this->setCredential(MACRO_STREAM_ID, $stream);
    }

    /**
     * Save credentials
     */
    protected function save_credentials()
    {
        if (!empty($this->playlist_id)) {
            $this->plugin->get_playlists()->set($this->playlist_id, $this->playlist_info);
            $this->plugin->save_parameters(true);
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
