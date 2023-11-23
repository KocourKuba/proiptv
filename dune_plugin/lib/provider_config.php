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

class Provider_Config
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
    protected $api = '';

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
    protected $provider_config;

    /**
     * @var array
     */
    protected $credentials = array();

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
    public function getApi()
    {
        return $this->api;
    }

    /**
     * @param string $api
     */
    public function setApi($api)
    {
        $this->api = $api;
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
     * @param string $command
     * @return string
     */
    public function getApiCommand($command)
    {
        if (!isset($this->api_commands[$command]))
            return '';

        return str_replace(MACRO_API, $this->getApi(), $this->api_commands[$command]);
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
     * @param array $provider_config
     */
    public function setProviderConfig($provider_config)
    {
        $this->provider_config = $provider_config;
    }

    /**
     * @param string $val
     * @return string|array|null
     */
    public function getProviderConfigValue($val)
    {
        return isset($this->provider_config[$val]) ? $this->provider_config[$val] : null;
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

    ////////////////////////////////////////////////////////////////////////
    /// Methods

    /**
     * @param bool $force
     * @return void
     */
    public function request_provider_token($force = false)
    {
        $token = $this->getCredential(MACRO_TOKEN);
        if (!empty($token) && !$force) {
            return;
        }

        $token_url = $this->getProviderConfigValue(CONFIG_TOKEN_REQUEST_URL);
        if (empty($token_url)) {
            return;
        }

        $response = HD::DownloadJson($this->replace_macros($token_url));
        $token_name = $this->getProviderConfigValue(CONFIG_TOKEN_RESPONSE);
        if (!empty($token_name) && $response !== false && isset($response[$token_name])) {
            $this->setCredential(MACRO_TOKEN, $response[$token_name]);
        }
    }

    /**
     * @return array
     */
    public function request_provider_info()
    {
        $url = $this->getApiCommand(API_COMMAND_INFO);
        if (empty($url)) {
            return array();
        }

        $curl_headers = null;
        $headers = $this->getProviderConfigValue(CONFIG_HEADERS);
        if (!empty($headers)) {
            $curl_headers = array();
            foreach ($headers as $key => $header) {
                $curl_headers[CURLOPT_HTTPHEADER][] = "$key: " . $this->replace_macros($header);
            }
            hd_debug_print("headers: " . raw_json_encode($curl_headers), true);
        }

        $provider_data = HD::DownloadJson($this->replace_macros($url), true, $curl_headers);
        hd_debug_print("info: " . raw_json_encode($provider_data), true);

        return $provider_data;
    }

    /**
     * @param string $url
     * @return string
     */
    public function replace_macros($url)
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

        hd_debug_print("playlist template $url", true);
        foreach ($macroses as $macro) {
            if (strpos($url, $macro) === false) continue;
            $url = str_replace($macro, trim($this->getCredential($macro)), $url);
        }
        hd_debug_print("playlist url $url", true);

        return $url;
    }
}
