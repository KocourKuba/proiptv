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
      "id": "viplime",
      "name": "VipLime",
      "logo": "http://iptv.esalecrm.net/res/logo_viplime.png",
      "enable": true,
      "provider_type": "pin",
      "playlist_source": "http://cdntv.online/{QUALITY_ID}/{PASSWORD}/playlist.m3u8",
      "qualities": {
        "high": "High",
        "medium": "Medium",
        "low": "Medium",
        "variant": "Adaptive",
        "hls": "Optimal"
      },
      "id_map": "map",
      "id_parser": "tvg-id",
      "provider_info": false
  },
      "id": "1usd",
      "name": "1usd",
      "logo": "http://iptv.esalecrm.net/res/logo_1usd.png",
      "enable": true,
      "provider_type": "pin",
      "playlist_source": "http://1usd.tv/pl-{PASSWORD}-hls",
      "id_map": "parse",
      "id_parser": "^https?:\\/\\/.+\\/(?<id>.+)\\/.+\\.m3u8\\?.+$",
      "xmltv_sources": {
        "1": "http://epg.team/tvteam.xml.gz",
        "2": "http://epg.team/tvteam.7.7.xml"
      },
      "provider_info": false
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
    protected $logo = '';

    /**
     * @var bool
     */
    protected $enable = false;

    /**
     * @var string
     */
    protected $provider_type = '';

    /**
     * @var string
     */
    protected $playlist_source = '';

    /**
     * @var string
     */
    protected $playlist_catchup = '';

    /**
     * @var string
     */
    protected $id_map = '';

    /**
     * @var string
     */
    protected $id_parser = '';

    /**
     * @var string
     */
    protected $token_request_url = '';

    /**
     * @var string
     */
    protected $token_response = '';

    /**
     * @var string
     */
    protected $provider_info = '';

    /**
     * @var array
     */
    protected $provider_info_config;

    /**
     * @var bool
     */
    protected $vod_enabled = false;

    /**
     * @var array
     */
    protected $vod_config = array();

    /**
     * @var array
     */
    protected $devices = array();

    /**
     * @var array
     */
    protected $servers = array();

    /**
     * @var array
     */
    protected $qualities = array();

    /**
     * @var array
     */
    protected $xmltv_sources = array();

    /**
     * @var array
     */
    protected $credentials = array();

    /**
     * @var array
     */
    protected $provider_data = array();

    ////////////////////////////////////////////////////////////////////////
    /// non configurable vars
    /**
     * @var Named_Storage
     */
    private $parsed_info;

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
     * @return string
     */
    public function getProviderType()
    {
        return $this->provider_type;
    }

    /**
     * @param string $provider_type
     */
    public function setProviderType($provider_type)
    {
        $this->provider_type = $provider_type;
    }

    /**
     * @return string
     */
    public function getPlaylistSource()
    {
        return $this->playlist_source;
    }

    /**
     * @param string $playlist_source
     */
    public function setPlaylistSource($playlist_source)
    {
        $this->playlist_source = $playlist_source;
    }

    /**
     * @return string
     */
    public function getPlaylistCatchup()
    {
        return $this->playlist_catchup;
    }

    /**
     * @param string $playlist_catchup
     */
    public function setPlaylistCatchup($playlist_catchup)
    {
        $this->playlist_catchup = $playlist_catchup;
    }

    /**
     * @return string
     */
    public function getIdMap()
    {
        return $this->id_map;
    }

    /**
     * @param string $id_map
     */
    public function setIdMap($id_map)
    {
        $this->id_map = $id_map;
    }

    /**
     * @return string
     */
    public function getIdParser()
    {
        return $this->id_parser;
    }

    /**
     * @param string $id_parser
     */
    public function setIdParser($id_parser)
    {
        $this->id_parser = $id_parser;
    }

    /**
     * @return string
     */
    public function getTokenRequestUrl()
    {
        return $this->token_request_url;
    }

    /**
     * @param string $token_request_url
     */
    public function setTokenRequestUrl($token_request_url)
    {
        $this->token_request_url = $token_request_url;
    }

    /**
     * @return string
     */
    public function getTokenResponse()
    {
        return $this->token_response;
    }

    /**
     * @param string $token_response
     */
    public function setTokenResponse($token_response)
    {
        $this->token_response = $token_response;
    }

    /**
     * @return string
     */
    public function getProviderInfo()
    {
        return $this->provider_info;
    }

    /**
     * @param string $provider_info
     */
    public function setProviderInfo($provider_info)
    {
        $this->provider_info = $provider_info;
    }

    /**
     * @return array
     */
    public function getProviderInfoConfig()
    {
        return $this->provider_info_config;
    }

    /**
     * @param array $provider_info_config
     */
    public function setProviderInfoConfig($provider_info_config)
    {
        $this->provider_info_config = $provider_info_config;
    }

    /**
     * @param string $val
     * @return string|array|null
     */
    public function getProviderInfoConfigValue($val)
    {
        return isset($this->provider_info_config[$val]) ? $this->provider_info_config[$val] : null;
    }

    /**
     * @return bool
     */
    public function getVodEnabled()
    {
        return $this->vod_enabled;
    }

    /**
     * @param bool $vod_enabled
     */
    public function setVodEnabled($vod_enabled)
    {
        $this->vod_enabled = $vod_enabled;
    }

    /**
     * @return array
     */
    public function getVodConfig()
    {
        return $this->vod_config;
    }

    /**
     * @param array $vod_config
     */
    public function setVodConfig($vod_config)
    {
        $this->vod_config = $vod_config;
    }

    /**
     * @param string $value
     * @return string|array|null
     */
    public function getVodConfigValue($value)
    {
        return isset($this->vod_config[$value]) ? $this->vod_config[$value] : null;
    }

    /**
     * @return array
     */
    public function getDevices()
    {
        return $this->devices;
    }

    /**
     * @param array $devices
     */
    public function setDevices($devices)
    {
        $this->devices = $devices;
    }

    /**
     * @return array
     */
    public function getServers()
    {
        return $this->servers;
    }

    /**
     * @param array $servers
     */
    public function setServers($servers)
    {
        $this->servers = $servers;
    }

    /**
     * @return array
     */
    public function getQualities()
    {
        return $this->qualities;
    }

    /**
     * @param array $qualities
     */
    public function setQualities($qualities)
    {
        $this->qualities = $qualities;
    }

    /**
     * @return array
     */
    public function getXmltvSources()
    {
        return $this->xmltv_sources;
    }

    /**
     * @param array $xmltv_sources
     */
    public function setXmltvSources($xmltv_sources)
    {
        $this->xmltv_sources = $xmltv_sources;
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
     * @return array
     */
    public function getProviderData()
    {
        return $this->provider_data;
    }

    ////////////////////////////////////////////////////////////////////////
    /// Methods

    /**
     * @param Named_Storage $info
     * @return void
     */
    public function parse_provider_creds($info)
    {
        if (!is_null($this->parsed_info) && $this->parsed_info === $info) {
            return;
        }

        hd_debug_print("parse provider_info ({$this->getProviderType()}): $info", true);

        switch ($this->getProviderType()) {
            case PROVIDER_TYPE_PIN:
                $this->setCredential(MACRO_PASSWORD, $info->params[MACRO_PASSWORD]);
                break;

            case PROVIDER_TYPE_LOGIN:
            case PROVIDER_TYPE_LOGIN_TOKEN:
            case PROVIDER_TYPE_LOGIN_STOKEN:
                $this->setCredential(MACRO_LOGIN, $info->params[MACRO_LOGIN]);
                $this->setCredential(MACRO_PASSWORD, $info->params[MACRO_PASSWORD]);
                break;

            case PROVIDER_TYPE_EDEM:
                $this->setCredential(MACRO_SUBDOMAIN, $info->params[MACRO_SUBDOMAIN]);
                $this->setCredential(MACRO_OTTKEY, $info->params[MACRO_OTTKEY]);
                break;

            default:
                return;
        }

        if ($this->getProviderType() === PROVIDER_TYPE_LOGIN_TOKEN) {
            $this->setCredential(MACRO_TOKEN, md5(strtolower($info->params[MACRO_LOGIN]) . md5($info->params[MACRO_PASSWORD])));
        }

        $token_url = $this->getTokenRequestUrl();
        $token = $this->getCredential(MACRO_TOKEN);
        if (!empty($token_url) && empty($token)) {
            $response = HD::DownloadJson($this->replace_macros($token_url));
            $token_name = $this->getTokenResponse();
            if ($response !== false && isset($response[$token_name])) {
                $this->setCredential(MACRO_TOKEN, $response[$token_name]);
            }
        }

        if ($this->getProviderInfo()) {
            $curl_headers = null;
            $headers = $this->getProviderInfoConfigValue('headers');
            if (!empty($headers)) {
                $curl_headers = array();
                foreach ($headers as $key => $header) {
                    $curl_headers[CURLOPT_HTTPHEADER][] = "$key: " . $this->replace_macros($header);
                }
                hd_debug_print("headers: " . raw_json_encode($curl_headers));
            }

            $json = HD::DownloadJson($this->replace_macros($this->getProviderInfoConfigValue('url')), true, $curl_headers);

            $root = $this->getProviderInfoConfigValue('root');
            if ($json === false || (!is_null($root) && !isset($json[$root]))) {
                hd_debug_print("Can't get account status");
            } else {
                hd_debug_print("account: " . raw_json_encode($json), true);
                if (!is_null($root) && isset($json[$root])) {
                    foreach (explode(',', $root) as $key) {
                        $json = $json[$key];
                    }
                    hd_debug_print("root: " . raw_json_encode($json), true);
                }

                foreach ($json as $key => $value) {
                    $this->provider_data[$key] = $value;
                }
                hd_debug_print("info: " . raw_json_encode($this->provider_data), true);
            }
        }

        foreach($info->params as $key => $item) {
            switch($key) {
                case MACRO_SERVER:
                    $this->setCredential(MACRO_SERVER, $item);
                    break;
                case MACRO_DEVICE:
                    $this->setCredential(MACRO_DEVICE, $item);
                    break;
                case MACRO_QUALITY:
                    $this->setCredential(MACRO_QUALITY, $item);
                    break;
            }
        }
        $this->parsed_info = $info;
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
            MACRO_DEVICE,
            MACRO_SERVER,
            MACRO_QUALITY,
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
