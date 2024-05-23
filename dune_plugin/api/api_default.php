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
    protected $api_url = '';

    /**
     * @var string
     */
    protected $icons_template = '';
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

    public function __construct(DunePlugin $plugin)
    {
        $this->plugin = $plugin;
    }

    ////////////////////////////////////////////////////////////////////////
    /// non configurable vars

    public function __toString()
    {
        return (string)raw_json_encode(get_object_vars($this));
    }

    ////////////////////////////////////////////////////////////////////////
    /// Getters/Setters
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
    public function getIconstemplate()
    {
        return $this->icons_template;
    }

    /**
     * @param string $icons_template
     */
    public function setIconstemplate($icons_template)
    {
        $this->icons_template = $icons_template;
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

    ////////////////////////////////////////////////////////////////////////
    /// Methods

    /**
     * @param string $playlist_id
     */
    public function set_provider_playlist($playlist_id)
    {
        hd_debug_print(null, true);

        $this->playlist_id = $playlist_id;
        $playlist = $this->plugin->get_playlist($playlist_id);
        if ($playlist !== null && isset($playlist->params)) {
            $this->playlist_info = $playlist;
            hd_debug_print("provider info: ($playlist_id) " . json_encode($this->playlist_info), true);
        } else {
            hd_debug_print("incorrect provider info: $playlist_id");
        }

        // set credentials values if it not set
        $streams = $this->GetStreams();
        if (!empty($streams)) {
            $idx = $this->getCredential(MACRO_STREAM_ID);
            if (empty($idx)) {
                $this->setCredential(MACRO_STREAM_ID, key($streams));
            }
        }

        $domains = $this->GetDomains();
        if (!empty($domains)) {
            $idx = $this->getCredential(MACRO_DOMAIN_ID);
            if (empty($idx)) {
                $this->setCredential(MACRO_DOMAIN_ID, key($domains));
            }
        }

        $servers = $this->GetServers();
        if (!empty($servers)) {
            $idx = $this->getCredential(MACRO_SERVER_ID);
            if (empty($idx)) {
                $this->setCredential(MACRO_SERVER_ID, key($servers));
            }
        }

        $devices = $this->GetDevices();
        if (!empty($devices)) {
            $idx = $this->getCredential(MACRO_DEVICE_ID);
            if (empty($idx)) {
                $this->setCredential(MACRO_DEVICE_ID, key($devices));
            }
        }

        $qualities = $this->GetQualities();
        if (!empty($qualities)) {
            $idx = $this->getCredential(MACRO_QUALITY_ID);
            if (empty($idx)) {
                $this->setCredential(MACRO_QUALITY_ID, key($qualities));
            }
        }

        $playlists = $this->GetPlaylists();
        if (!empty($playlists)) {
            $idx = $this->getCredential(MACRO_PLAYLIST_ID);
            if ($idx === '') {
                hd_debug_print(MACRO_PLAYLIST_ID . " not set");
                $idx = (string)key($playlists);
                $this->setCredential(MACRO_PLAYLIST_ID, $idx);
            }
        }
    }

    public function get_provider_playlist()
    {
        return $this->playlist_info;
    }

    /**
     * @param array $matches
     * @param string $hash
     * @return bool|Named_Storage
     */
    public function fill_default_info($matches, &$hash)
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
            case PROVIDER_TYPE_LOGIN_TOKEN:
            case PROVIDER_TYPE_LOGIN_STOKEN:
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
     * @param string $val
     * @return string|array|null
     */
    public function getConfigValue($val)
    {
        return isset($this->config[$val]) ? $this->config[$val] : null;
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
     * @param string $name
     * @param array|string $value
     * @return void
     */
    public function setCredential($name, $value)
    {
        $this->playlist_info->params[$name] = $value;
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
            return  "vod_standard";
        }

        return null;
    }

    /**
     * @param bool $force
     * @return bool
     */
    public function request_provider_token($force = false)
    {
        hd_debug_print(null, true);

        if (!$this->hasApiCommand(API_COMMAND_REQUEST_TOKEN)) {
            return true;
        }

        $token = $this->getCredential(MACRO_TOKEN);
        if (!empty($token) && !$force) {
            return true;
        }

        $token_name = $this->getConfigValue(CONFIG_TOKEN_RESPONSE);
        $data = $this->execApiCommand(API_COMMAND_REQUEST_TOKEN);
        if (isset($data->{$token_name})) {
            $this->setCredential(MACRO_TOKEN, $data->{$token_name});
            $this->save_credentials();
            return true;
        }

        return false;
    }

    /**
     * @param $tmp_file string
     * @return bool
     */
    public function load_playlist($tmp_file)
    {
        hd_debug_print(null, true);

        $playlists = $this->GetPlaylists();
        if (!empty($playlists)) {
            $idx = $this->getCredential(MACRO_PLAYLIST_ID);
            if (isset($playlists[$idx]['url'])) {
                $this->setCredential(MACRO_PLAYLIST, $playlists[$idx]['url']);
            }
        }

        return $this->execApiCommand(API_COMMAND_GET_PLAYLIST, $tmp_file);
    }

    /**
     * @param bool $force
     * @return bool
     */
    public function get_provider_info($force = false)
    {
        hd_debug_print(null, true);

        $this->request_provider_token();

        if ((empty($this->account_info) || $force) && $this->hasApiCommand(API_COMMAND_ACCOUNT_INFO)) {
            $this->account_info = $this->execApiCommand(API_COMMAND_ACCOUNT_INFO);
        }

        hd_debug_print("get_provider_info: " . raw_json_encode($this->account_info), true);

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
     * @return string
     */
    public function getApiCommand($command)
    {
        hd_debug_print(null, true);

        return $this->replace_macros($this->getRawApiCommand($command));
    }

    /**
     * @param string $command
     * @return string
     */
    public function getRawApiCommand($command)
    {
        hd_debug_print(null, true);
        if ($this->hasApiCommand($command))
            return $this->api_commands[$command];

        return '';
    }

    /**
     * @param string $command
     * @param string $file
     * @param bool $decode
     * @param array $curl_options
     * @return bool|object
     */
    public function execApiCommand($command, $file = null, $decode = true, $curl_options = array())
    {
        hd_debug_print(null, true);
        hd_debug_print("execApiCommand: $command", true);
        $command_url = $this->getApiCommand($command);
        if (empty($command_url)) {
            return false;
        }

        if (isset($curl_options[vod_standard::VOD_GET_PARAM_PATH])) {
            $command_url .= $curl_options[vod_standard::VOD_GET_PARAM_PATH];
        }
        hd_debug_print("ApiCommandUrl: $command_url", true);

        $config_headers = $this->getConfigValue(CONFIG_HEADERS);
        if (!empty($config_headers)) {
            foreach ($config_headers as $key => $header) {
                $value = $this->replace_macros($header);
                if (!empty($value)) {
                    $curl_options[CURLOPT_HTTPHEADER][] = "$key: $value";
                }
            }
        }

        $response = HD::http_download_https_proxy($command_url, $file, $curl_options);
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

        $data = HD::decodeResponse(false, $response);
        if ($data === false || $data === null) {
            hd_debug_print("Can't decode response on request: " . $command_url);
        }

        return $data;
    }

    /**
     * @param $handler
     * @return array|null
     */
    public function GetInfoUI($handler)
    {
        hd_debug_print(null, true);
        $this->get_provider_info();
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
     * @param $name string
     * @param $playlist_id string
     * @param $handler
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
            case PROVIDER_TYPE_LOGIN_TOKEN:
            case PROVIDER_TYPE_LOGIN_STOKEN:
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
     * @param $user_input
     * @return bool
     */
    public function ApplySetupUI($user_input)
    {
        hd_debug_print(null, true);

        $id = $user_input->{CONTROL_EDIT_ITEM};

        switch ($this->getType()) {
            case PROVIDER_TYPE_PIN:
                $this->playlist_info->params[MACRO_PASSWORD] = $user_input->{CONTROL_PASSWORD};
                if (!empty($this->playlist_info->params[MACRO_PASSWORD])) {
                    break;
                }

                return false;

            case PROVIDER_TYPE_LOGIN:
            case PROVIDER_TYPE_LOGIN_TOKEN:
            case PROVIDER_TYPE_LOGIN_STOKEN:
                $this->playlist_info->params[MACRO_LOGIN] = $user_input->{CONTROL_LOGIN};
                $this->playlist_info->params[MACRO_PASSWORD] = $user_input->{CONTROL_PASSWORD};
                if (empty($this->playlist_info->params[MACRO_LOGIN]) || empty($this->playlist_info->params[MACRO_PASSWORD])) {
                    return false;
                }

                if ($this->getType() === PROVIDER_TYPE_LOGIN_STOKEN) {
                    $this->setCredential(MACRO_TOKEN, '');
                }
                break;

            default:
                return false;
        }

        $id = empty($id) ? $this->get_hash($this->playlist_info) : $id;

        hd_debug_print("ApplySetupUI compiled provider info: " . raw_json_encode($this->playlist_info), true);

        $this->set_default_settings($user_input, $id);

        return true;
    }

    /**
     * @param $handler
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
                TR::t('stream'), $idx, $streams, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH, true);
        }

        $domains = $this->GetDomains();
        if (!empty($domains) && count($domains) > 1) {
            $idx = $this->getCredential(MACRO_DOMAIN_ID);
            if (empty($idx)) {
                $idx = key($domains);
            }
            hd_debug_print("domains ($idx): " . json_encode($domains), true);

            Control_Factory::add_combobox($defs, $handler, null, CONTROL_DOMAIN,
                TR::t('domain'), $idx, $domains, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH, true);
        }

        $servers = $this->GetServers();
        if (!empty($servers) && count($servers) > 1) {
            $idx = $this->getCredential(MACRO_SERVER_ID);
            if (empty($idx)) {
                $idx = key($servers);
            }
            hd_debug_print("servers ($idx): " . json_encode($servers), true);

            Control_Factory::add_combobox($defs, $handler, null, CONTROL_SERVER,
                TR::t('server'), $idx, $servers, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH, true);
        }

        $devices = $this->GetDevices();
        if (!empty($devices) && count($devices) > 1) {
            $idx = $this->getCredential(MACRO_DEVICE_ID);
            if (empty($idx)) {
                $idx = key($devices);
            }
            hd_debug_print("devices ($idx): " . json_encode($devices), true);

            Control_Factory::add_combobox($defs, $handler, null, CONTROL_DEVICE,
                TR::t('device'), $idx, $devices, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH, true);
        }

        $qualities = $this->GetQualities();
        if (!empty($qualities) && count($qualities) > 1) {
            $idx = $this->getCredential(MACRO_QUALITY_ID);
            if (empty($idx)) {
                $idx = key($qualities);
            }
            hd_debug_print("qualities ($idx): " . json_encode($qualities), true);

            Control_Factory::add_combobox($defs, $handler, null, CONTROL_QUALITY,
                TR::t('quality'), $idx, $qualities, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH, true);
        }

        $playlists = $this->GetPlaylists();
        if (!empty($playlists) && count($playlists) > 1) {
            $pl_names = array();
            foreach ($playlists as $key => $pl) {
                $pl_names[$key] = $pl['name'];
            }
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
                Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH, true);
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
     * @param $user_input
     * @return bool
     */
    public function ApplyExtSetupUI($user_input)
    {
        hd_debug_print(null, true);

        $changed = false;
        if ($this->check_control_parameters($user_input, CONTROL_SERVER, MACRO_SERVER_ID)) {
            $this->SetServer($user_input->{CONTROL_SERVER});
            $changed = true;
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

        hd_debug_print("ApplyExtSetupUI compiled provider info: " . raw_json_encode($this->playlist_info), true);

        if (!$changed) {
            return false;
        }

        $playlist_id = $this->plugin->get_active_playlist_key();
        $this->plugin->get_playlists()->set($playlist_id, $this->playlist_info);
        $this->plugin->save_parameters(true);
        $this->plugin->clear_playlist_cache($playlist_id);

        return true;
    }

    /**
     * returns list of provider servers
     * @return array|null
     */
    public function GetStreams()
    {
        hd_debug_print(null, true);
        return $this->getConfigValue(CONFIG_STREAMS);
    }

    /**
     * set server
     * @param string $server
     * @return void
     */
    public function SetStreams($server)
    {
        hd_debug_print(null, true);
        $this->setCredential(CONFIG_STREAMS, $server);
    }

    /**
     * set server
     * @param string $stream
     * @return void
     */
    public function SetStream($stream)
    {
        hd_debug_print(null, true);
        $this->setCredential(MACRO_STREAM_ID, $stream);
    }

    /**
     * returns list of provider servers
     * @return array|null
     */
    public function GetServers()
    {
        hd_debug_print(null, true);
        $this->get_provider_info();
        return $this->getConfigValue(CONFIG_SERVERS);
    }

    /**
     * set server
     * @param string $server
     * @return void
     */
    public function SetServer($server)
    {
        hd_debug_print(null, true);

        $this->setCredential(MACRO_SERVER_ID, $server);
    }

    /**
     * returns list of account playlists
     * @return array|null
     */
    public function GetPlaylists()
    {
        hd_debug_print(null, true);
        $this->get_provider_info();
        return $this->getConfigValue(CONFIG_PLAYLISTS);
    }

    /**
     * set server
     * @param string $id
     * @return void
     */
    public function SetPlaylist($id)
    {
        hd_debug_print(null, true);
        hd_debug_print("SetPlaylist: $id");
        $this->setCredential(MACRO_PLAYLIST_ID, $id);
        $this->plugin->set_current_provider_playlist_id($id);
    }

    /**
     * returns list of provider servers
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
    public function GetDevices()
    {
        hd_debug_print(null, true);
        return $this->getConfigValue(CONFIG_DEVICES);
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
     * returns list of provider servers
     * @return array|null
     */
    public function GetQualities()
    {
        hd_debug_print(null, true);
        return $this->getConfigValue(CONFIG_QUALITIES);
    }

    /**
     * @param Named_Storage $info
     * @return string
     */
    protected function get_hash($info)
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

    protected function save_credentials()
    {
        $this->plugin->get_playlists()->set($this->playlist_id, $this->playlist_info);
        $this->plugin->save_parameters(true);
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
            MACRO_TOKEN,
            MACRO_DOMAIN_ID,
            MACRO_DEVICE_ID,
            MACRO_SERVER_ID,
            MACRO_QUALITY_ID,
            MACRO_PLAYLIST_ID,
            MACRO_VPORTAL,
        );

        hd_debug_print("template: $string", true);
        $string = str_replace(
            array(MACRO_API, MACRO_PLAYLIST),
            array($this->getApiUrl(), $this->getCredential(MACRO_PLAYLIST)),
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
     * @param $user_input
     * @param string $id
     * @return void
     */
    protected function set_default_settings($user_input, $id)
    {
        if (empty($user_input->{CONTROL_EDIT_ITEM})) {
            // new provider. Fill default values
            $settings = $this->plugin->get_settings($id);
            $xmltv_picons = $this->getConfigValue(XMLTV_PICONS);
            if ($xmltv_picons) {
                $settings[PARAM_USE_PICONS] = XMLTV_PICONS;
            }

            $epg_preset = $this->getConfigValue(EPG_JSON_PRESET);
            if (!empty($epg_preset)) {
                $settings[PARAM_EPG_CACHE_ENGINE] = ENGINE_JSON;
            }

            $detect_stream = $this->getConfigValue(PARAM_DUNE_FORCE_TS);
            if ($detect_stream) {
                $settings[PARAM_DUNE_FORCE_TS] = $detect_stream;
            }

            if (!empty($settings)) {
                $this->plugin->put_settings($id, $settings);
            }
        }

        $this->request_provider_token();

        $this->plugin->get_playlists()->set($id, $this->get_provider_playlist());
        $this->plugin->save_parameters(true);
        $this->plugin->clear_playlist_cache($id);

        if ($this->plugin->get_active_playlist_key() === $id) {
            $this->plugin->set_active_playlist_key($id);
        }
    }

    protected function check_control_parameters($user_input, $param, $param_settings)
    {
        return (isset($user_input->{$param})
            && (!isset($this->playlist_info->params[$param_settings])
                || $this->playlist_info->params[$param_settings] !== $user_input->{$param}));
    }
}
