<?php
require_once "lib/curl_wrapper.php";

class jellyfin_api
{
    const MOVIES = "Movie";
    const SERIES = "Series";
    const TVSHOWS = "tvshows";

    /**
     * @var string
     */
    private $baseUrl;

    /**
     * @var string
     */
    private $deviceId;

    /**
     * @var string
     */
    private $accessToken = null;

    /**
     * @var string
     */
    private $userId = null;

    /**
     * @var string
     */
    private $base_auth_string;

    /**
     * @var Default_Dune_Plugin
     */
    private $plugin;

    /**
     * @param Default_Dune_Plugin $plugin
     * @param string $baseUrl
     * @param string $appVersion
     */
    public function init($plugin, $baseUrl, $appVersion = '1.0.0')
    {
        $this->plugin = $plugin;
        $this->deviceId = get_serial_number();
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->base_auth_string = sprintf('MediaBrowser Client="ProIPTV", Device="dunehd", DeviceId="%s", Version="%s"', $this->deviceId, $appVersion);
    }

    /**
     * Authentication
     *
     * @param string $username
     * @param string $password
     * @return bool
     */
    public function login($username, $password)
    {
        $curl_wrapper = $this->plugin->setup_curl();
        $curl_wrapper->set_post();
        $curl_wrapper->set_post_data(array('Username' => $username, 'Pw' => $password));
        $headers = $this->buildHeaders(false);
        $headers[] = CONTENT_TYPE_JSON;
        $curl_wrapper->set_send_headers($headers);

        $command_url = $this->baseUrl . '/Users/AuthenticateByName';
        $response = $curl_wrapper->download_content($command_url, Curl_Wrapper::RET_ARRAY);
        if ($response === false) {
            hd_debug_print("Can't get response on request: $command_url");
            return false;
        }

        $this->userId = safe_get_value($response, array('User', 'Id'));
        $this->accessToken = safe_get_value($response, 'AccessToken');
        if (!empty($this->accessToken) && !empty($this->userId)) {
            return true;
        }

        hd_debug_print("Login failed.");
        return false;
    }

    /**
     * @return void
     */
    public function logout()
    {
        $curl_wrapper = $this->plugin->setup_curl();
        $curl_wrapper->set_post();
        $curl_wrapper->set_send_headers($this->buildHeaders(true));
        $curl_wrapper->download_content($this->baseUrl . '/Sessions/Logout');
    }

    /**
     * Get User View
     *
     * @return array
     */
    public function getUserViews($param = null)
    {
        $query = 'UserViews';
        if (!is_null($param)) {
            $query .= "/$param";
        }
        return $this->get($query, array('userId' => $this->userId));
    }

    /**
     * Get items under ParentId
     *
     * @param array $query
     * @return array
     */
    public function getItems($query = array())
    {
        if (isset($query['ParentId'])) {
            $query['ParentId'] = urlencode($query['ParentId']);
        }

        return $this->get('Items', $query);
    }

    /**
     * @param string $id
     * @return array
     */
    public function getItemInfo($id)
    {
        return $this->get('Items/' . urlencode($id));
    }

    /**
     * @param string $seriesId
     * @return array
     */
    public function getSeasons($seriesId)
    {
        return $this->get('Shows/' . urlencode($seriesId) . '/Seasons');
    }

    /**
     * @param string $seriesId
     * @param string $seasonId
     * @return array
     */
    public function getEpisodes($seriesId, $seasonId)
    {
        $query['SeasonId'] = urlencode($seasonId);
        $query['sortBy'] = 'IndexNumber';
        return $this->get('Shows/' . urlencode($seriesId) . '/Episodes', $query);
    }

    // ---------------- Images ----------------

    /**
     * @param string $itemId
     * @param string $imageType
     * @param int $maxWidth
     * @param int $maxHeight
     * @param string $format
     * @return string
     */
    public function getItemImageUrl($itemId, $imageType = 'Primary', $maxWidth = 400, $maxHeight = 0, $format = 'Png')
    {
        $query['format'] = $format;
        if ($maxWidth > 0) {
            $query['maxWidth'] = $maxWidth;
        }
        if ($maxHeight > 0) {
            $query['maxHeight'] = $maxHeight;
        }
        $qs = empty($query) ? '' : ('?' . http_build_query($query));
        return $this->baseUrl . '/Items/' . urlencode($itemId) . '/Images/' . $imageType . $qs;
    }

    // ---------------- Playback helpers ----------------

    /**
     * get play url
     *
     * @param string $itemId
     * @param array $media_source
     * @param int $audioIndex
     * @return string
     */
    public function getPlayUrl($itemId, $media_source = array(), $audioIndex = -1)
    {
        $query['DeviceId'] = $this->deviceId;
        $query['apiKey'] = $this->accessToken;
        $query['MediaSourceId'] = isset($media_source['Id']) ? $media_source['Id'] : $itemId;
        if ($audioIndex !== -1) {
            $query['AudioStreamIndex'] = $audioIndex;
        }
        return $this->baseUrl . '/Videos/' . urlencode($itemId) . '/master.m3u8?' . http_build_query($query);
    }

    /**
     * get play url
     *
     * @param string $itemId
     * @return string
     */
    public function getDownloadUrl($itemId)
    {
        $query['apiKey'] = $this->accessToken;
        return $this->baseUrl . '/Items/' . urlencode($itemId) . '/Download?' . http_build_query($query);
    }

    // ---------------- Internal ----------------

    /**
     * Get all distinct genres in the library
     *
     * @param array $query
     * @return array
     */
    public function getFilters($query = array())
    {
        return $this->get('Items/Filters', $query);
    }

    /**
     * @param string $path
     * @param array $query
     * @return array
     */
    private function get($path, $query = array())
    {
        if (empty($this->accessToken)) {
            return array();
        }

        $curl_wrapper = $this->plugin->setup_curl();
        $headers = $this->buildHeaders(true);
        $headers[] = CONTENT_TYPE_JSON;
        $curl_wrapper->set_send_headers($headers);

        $command_url = $this->baseUrl . '/' . ltrim($path, '/');
        if (!empty($query)) {
            $command_url .= '?' . http_build_query($query);
        }

        $response = $curl_wrapper->download_content($command_url, Curl_Wrapper::RET_ARRAY | Curl_Wrapper::CACHE_RESPONSE);
        if ($response !== false) {
            return $response;
        }

        print_backtrace();
        hd_debug_print("Can't get response on request: $command_url");
        return array();
    }

    /**
     * @param bool $auth
     * @return array
     */
    private function buildHeaders($auth)
    {
        $authorization = $this->base_auth_string;
        if ($auth && $this->accessToken) {
            $authorization .= ", Token=\"$this->accessToken\"";
        }

        return array(
            "Authorization: $authorization",
            ACCEPT_JSON,
        );
    }
}
