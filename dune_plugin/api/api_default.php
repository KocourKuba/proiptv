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
     * @var array
     */
    protected $credentials = array();

    /**
     * @var
     */
    protected $info;

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
     * @param Named_Storage $info
     * @return api_default|null
     */
    public function init_provider($info)
    {
        hd_debug_print("provider info:" . json_encode($info));
        hd_debug_print("parse provider_info ({$this->getType()}): $info", true);

        switch ($this->getType()) {
            case PROVIDER_TYPE_PIN:
                $this->setCredential(MACRO_PASSWORD, isset($info->params[MACRO_PASSWORD]) ? $info->params[MACRO_PASSWORD] : '');
                break;

            case PROVIDER_TYPE_LOGIN_TOKEN:
                $this->setCredential(MACRO_LOGIN, isset($info->params[MACRO_LOGIN]) ? $info->params[MACRO_LOGIN] : '');
                $this->setCredential(MACRO_PASSWORD, isset($info->params[MACRO_PASSWORD]) ? $info->params[MACRO_PASSWORD] : '');
                $this->setCredential(MACRO_TOKEN,
                    md5(strtolower($this->getCredential(MACRO_LOGIN)) . md5($this->getCredential(MACRO_PASSWORD))));
                break;

            case PROVIDER_TYPE_LOGIN:
            case PROVIDER_TYPE_LOGIN_STOKEN:
                $this->setCredential(MACRO_LOGIN, isset($info->params[MACRO_LOGIN]) ? $info->params[MACRO_LOGIN] : '');
                $this->setCredential(MACRO_PASSWORD, isset($info->params[MACRO_PASSWORD]) ? $info->params[MACRO_PASSWORD] : '');
                break;

            default:
                return null;
        }

        $domains = $this->GetDomains();
        if (!empty($domains)) {
            $this->setCredential(MACRO_DOMAIN_ID, key($domains));
        }
        $servers = $this->GetServers();
        if (!empty($servers)) {
            $this->setCredential(MACRO_SERVER_ID, key($servers));
        }
        $devices = $this->GetDevices();
        if (!empty($devices)) {
            $this->setCredential(MACRO_DEVICE_ID, key($devices));
        }
        $qualities = $this->GetQualities();
        if (!empty($qualities)) {
            $this->setCredential(MACRO_QUALITY_ID, key($qualities));
        }
        $streams = $this->getStreams();
        if (!empty($streams)) {
            $this->setCredential(MACRO_STREAM_ID, key($streams));
        }

        foreach($info->params as $key => $item) {
            if ($key === MACRO_DOMAIN_ID || $key === MACRO_SERVER_ID || $key === MACRO_DEVICE_ID || $key === MACRO_QUALITY_ID || $key === MACRO_STREAM_ID) {
                $this->setCredential($key, $item);
            }
        }

        return $this;
    }

    /**
     * @param array $matches
     * @param Named_Storage $info
     * @return bool
     */
    public function set_info($matches, &$info)
    {
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
                hd_debug_print("set pin: $vars[0]");
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
                return false;
        }

        $domains = $this->GetDomains();
        if (!empty($domains)) {
            $info->params[CONFIG_DOMAINS] = key($domains);
        }

        $servers = $this->getConfigValue(CONFIG_SERVERS);
        if (!empty($servers)) {
            $info->params[MACRO_SERVER_ID] = key($servers);
        }

        $devices = $this->getConfigValue(CONFIG_DEVICES);
        if (!empty($devices)) {
            $info->params[MACRO_DEVICE_ID] = key($devices);
        }

        $qualities = $this->getConfigValue(CONFIG_QUALITIES);
        if (!empty($qualities)) {
            $info->params[MACRO_QUALITY_ID] = key($qualities);
        }

        $streams = $this->getConfigValue(CONFIG_STREAMS);
        if (!empty($streams)) {
            $info->params[MACRO_STREAM_ID] = key($streams);
        }

        return true;
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
     * @return string
     */
    public function getCredential($name)
    {
        return isset($this->credentials[$name]) ? $this->credentials[$name] : '';
    }

    /**
     * @param string $name
     * @param string $value
     * @return void
     */
    public function setCredential($name, $value)
    {
        $this->credentials[$name] = $value;
    }

    /**
     * @return string|null
     */
    public function get_vod_class()
    {
        if ($this->hasApiCommand(API_COMMAND_VOD)) {
            $vod_class = "vod_" . ($this->getConfigValue(CONFIG_VOD_CUSTOM) ? $this->getId() : "standard");
            hd_debug_print("Used VOD class: $vod_class");
            if (class_exists($vod_class)) {
                return $vod_class;
            }
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

        if (!$this->request_provider_token()) {
            return false;
        }

        return $this->execApiCommand(API_COMMAND_PLAYLIST, $tmp_file);
    }

    /**
     * @param bool $force
     * @return bool
     */
    public function get_provider_info($force = false)
    {
        hd_debug_print(null, true);
        if ((empty($this->info) || $force) && $this->hasApiCommand(API_COMMAND_INFO)) {
            $response = $this->execApiCommand(API_COMMAND_INFO);
            if ($response) {
                $this->info = HD::decodeResponse(false, $response);
            }
        }

        return $this->info;
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

        if (isset($curl_options['path'])) {
            $command_url .= $curl_options['path'];
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
            hd_debug_print("Can't get response on request: " . $command);
            return false;
        }

        if (!is_null($file)) {
            return true;
        }

        if (!$decode) {
            return $response;
        }

        $data = HD::decodeResponse(false, $response);
        if ($data === false) {
            hd_debug_print("Can't decode response on request: " . $command);
            return false;
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
     * @param $name string
     * @param $playlist_id string
     * @param $handler
     * @return array|null
     */
    public function GetSetupUI($name, $playlist_id, $handler)
    {
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

        $streams = $this->GetStreams();
        if (!empty($streams)) {
            $idx = $this->getCredential(MACRO_STREAM_ID);
            if (empty($idx)) {
                $idx = key($streams);
            }
            hd_debug_print("streams ($idx): " . json_encode($streams), true);

            Control_Factory::add_combobox($defs, $handler, null, CONTROL_STREAM,
                TR::t('stream'), $idx, $streams, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH, true);
        }

        $domains = $this->GetDomains();
        if (!empty($domains)) {
            $idx = $this->getCredential(MACRO_DOMAIN_ID);
            if (empty($idx)) {
                $idx = key($domains);
            }
            hd_debug_print("domains ($idx): " . json_encode($domains), true);

            Control_Factory::add_combobox($defs, $handler, null, CONTROL_DOMAIN,
                TR::t('domain'), $idx, $domains, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH, true);
        }

        $servers = $this->GetServers();
        if (!empty($servers)) {
            $idx = $this->getCredential(MACRO_SERVER_ID);
            if (empty($idx)) {
                $idx = key($servers);
            }
            hd_debug_print("servers ($idx): " . json_encode($servers), true);

            Control_Factory::add_combobox($defs, $handler, null, CONTROL_SERVER,
                TR::t('server'), $idx, $servers, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH, true);
        }

        $devices = $this->GetDevices();
        if (!empty($devices)) {
            $idx = $this->getCredential(MACRO_DEVICE_ID);
            if (empty($idx)) {
                $idx = key($devices);
            }
            hd_debug_print("devices ($idx): " . json_encode($devices), true);

            Control_Factory::add_combobox($defs, $handler, null, CONTROL_DEVICE,
                TR::t('device'), $idx, $devices, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH, true);
        }

        $qualities = $this->GetQualities();
        if (!empty($qualities)) {
            $idx = $this->getCredential(MACRO_QUALITY_ID);
            if (empty($idx)) {
                $idx = key($qualities);
            }
            hd_debug_print("qualities ($idx): " . json_encode($qualities), true);

            Control_Factory::add_combobox($defs, $handler, null, CONTROL_QUALITY,
                TR::t('quality'), $idx, $qualities, Abstract_Preloaded_Regular_Screen::DLG_CONTROLS_WIDTH, true);
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
     * @return array|null
     */
    public function GetPayUI()
    {
        return null;
    }

    /**
     * @param $user_input
     * @param $item Named_Storage
     * @return array|string|null
     */
    public function ApplySetupUI($user_input, &$item)
    {
        $id = $user_input->{CONTROL_EDIT_ITEM};

        switch ($this->getType()) {
            case PROVIDER_TYPE_PIN:
                $item->params[MACRO_PASSWORD] = $user_input->{CONTROL_PASSWORD};
                $id = empty($id) ? Hashed_Array::hash($item->type.$item->name.$item->params[MACRO_PASSWORD]) : $id;
                if (empty($item->params[MACRO_PASSWORD])) {
                    return null;
                }
                break;

            case PROVIDER_TYPE_LOGIN:
            case PROVIDER_TYPE_LOGIN_TOKEN:
            case PROVIDER_TYPE_LOGIN_STOKEN:
                $item->params[MACRO_LOGIN] = $user_input->{CONTROL_LOGIN};
                $item->params[MACRO_PASSWORD] = $user_input->{CONTROL_PASSWORD};
                $id = empty($id) ? Hashed_Array::hash($item->type.$item->name.$item->params[MACRO_LOGIN].$item->params[MACRO_PASSWORD]) : $id;
                if (empty($item->params[MACRO_LOGIN]) || empty($item->params[MACRO_PASSWORD])) {
                    return null;
                }

                if ($this->getType() === PROVIDER_TYPE_LOGIN_STOKEN) {
                    $this->setCredential(MACRO_TOKEN, '');
                }
                break;

            default:
                return null;
        }

        if (isset($user_input->{CONTROL_DOMAIN})) {
            $item->params[MACRO_DOMAIN_ID] = $user_input->{CONTROL_DOMAIN};
        }

        if (isset($user_input->{CONTROL_SERVER})) {
            $item->params[MACRO_SERVER_ID] = $user_input->{CONTROL_SERVER};
            $this->SetServer($user_input->{CONTROL_SERVER});
        }

        if (isset($user_input->{CONTROL_DEVICE})) {
            $item->params[MACRO_DEVICE_ID] = $user_input->{CONTROL_DEVICE};
        }

        if (isset($user_input->{CONTROL_QUALITY})) {
            $item->params[MACRO_QUALITY_ID] = $user_input->{CONTROL_QUALITY};
        }

        if (isset($user_input->{CONTROL_STREAM})) {
            $item->params[MACRO_STREAM_ID] = $user_input->{CONTROL_STREAM};
        }

        hd_debug_print("compiled provider info: $item->name, provider params: " . raw_json_encode($item->params), true);

        return $id;
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
     * @param $server
     * @return void
     */
    public function SetStreams($server)
    {
        hd_debug_print(null, true);
        $this->setCredential(CONFIG_STREAMS, $server);
    }

    /**
     * set server
     * @param $server
     * @return void
     */
    public function SetStream($server)
    {
        hd_debug_print(null, true);
        $this->setCredential(MACRO_STREAM_ID, $server);
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
     * @param $server
     * @return void
     */
    public function SetServer($server)
    {
        hd_debug_print(null, true);

        $this->setCredential(MACRO_SERVER_ID, $server);
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
     * returns list of provider servers
     * @return array|null
     */
    public function GetQualities()
    {
        hd_debug_print(null, true);
        return $this->getConfigValue(CONFIG_QUALITIES);
    }

    /**
     * @param string $string
     * @return string
     */
    public function replace_macros($string)
    {
        static $macroses = array(
            MACRO_STREAM_ID,
            MACRO_LOGIN,
            MACRO_PASSWORD,
            MACRO_SUBDOMAIN,
            MACRO_OTTKEY,
            MACRO_TOKEN,
            MACRO_DOMAIN_ID,
            MACRO_DEVICE_ID,
            MACRO_SERVER_ID,
            MACRO_QUALITY_ID,
            MACRO_VPORTAL,
        );

        hd_debug_print("template: $string", true);
        foreach ($macroses as $macro) {
            if (strpos($string, $macro) !== false) {
                $string = str_replace($macro, trim($this->getCredential($macro)), $string);
            }
        }
        $string = str_replace(MACRO_API, $this->getApiUrl(), $string);
        hd_debug_print("result: $string", true);

        return $string;
    }
}
