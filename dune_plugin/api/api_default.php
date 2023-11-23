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
     * @return void
     */
    public function request_provider_token($force = false)
    {
        if (!$this->hasApiCommand(API_COMMAND_REQUEST_TOKEN)) {
            return;
        }

        $token = $this->getCredential(MACRO_TOKEN);
        if (!empty($token) && !$force) {
            return;
        }

        $response = $this->execApiCommand(API_COMMAND_REQUEST_TOKEN, false, true);
        if ($response === false) {
            return;
        }

        $token_name = $this->getConfigValue(CONFIG_TOKEN_RESPONSE);
        if (!empty($token_name) && isset($response[$token_name])) {
            $this->setCredential(MACRO_TOKEN, $response[$token_name]);
        }
    }

    /**
     * @param bool $force
     * @return bool
     */
    public function get_provider_info($force = false)
    {
        hd_debug_print(null, true);
        if (is_null($this->info) || $force) {
            $this->info = $this->execApiCommand(API_COMMAND_INFO);
            if ($this->info === false) {
                $this->info = array();
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
        if ($this->hasApiCommand($command))
            return $this->replace_macros($this->api_commands[$command]);

        return '';
    }

    /**
     * @param string $command
     * @param bool $binary
     * @param bool $assoc
     * @param string $params
     * @return mixed|false
     */
    public function execApiCommand($command, $binary = false, $assoc = false, $params = '')
    {
        hd_debug_print(null, true);
        $command_url = $this->getApiCommand($command);
        if (empty($command_url)) {
            return false;
        }
        $command_url .= $params;
        hd_debug_print("execApiCommand: $command_url", true);

        $curl_headers = null;
        $headers = $this->getConfigValue(CONFIG_HEADERS);
        if (!empty($headers)) {
            $curl_headers = array();
            foreach ($headers as $key => $header) {
                $curl_headers[CURLOPT_HTTPHEADER][] = "$key: " . $this->replace_macros($header);
            }
            hd_debug_print("headers: " . raw_json_encode($curl_headers), true);
        }

        if ($binary) {
            $data = HD::http_download_https_proxy($command_url);
        } else {
            $data = HD::DownloadJson($command_url, $assoc, $curl_headers);
        }

        return $data;
    }

    /**
     * @param $handler
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
     * returns list of provider servers
     * @return array|null
     */
    public function GetServers()
    {
        hd_debug_print(null, true);
        return $this->getConfigValue(CONFIG_SERVERS);
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
            if (strpos($string, $macro) === false) continue;
            $string = str_replace($macro, trim($this->getCredential($macro)), $string);
        }
        $string = str_replace(MACRO_API, $this->getApiUrl(), $string);
        hd_debug_print("result: $string", true);

        return $string;
    }
}
