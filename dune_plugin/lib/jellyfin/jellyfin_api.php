<?php
require_once "lib/curl_wrapper.php";

class jellyfin_api
{
    const MOVIES = "Movie";
    const SERIES = "Series";
    const TVSHOWS_TYPE = "tvshows";
    const MOVIES_TYPE = "movies";

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
     * @var Curl_Wrapper
     */
    private $curl_wrapper;

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
        $this->curl_wrapper = $plugin->setup_curl();
    }

    /**
     * Authentication
     *
     * @param array $access_info
     * @return bool
     */
    public function login($access_info)
    {
        $username = safe_get_value($access_info, MACRO_LOGIN);
        $password = safe_get_value($access_info, MACRO_PASSWORD);
        $this->accessToken = safe_get_value($access_info, PARAM_TOKEN);
        $this->userId = safe_get_value($access_info, PARAM_USER_ID);

        if (!empty($this->accessToken) && !empty($this->userId)) {
            $response = $this->systemInfo();
            if ($response !== false) {
                return true;
            }
        }

        hd_debug_print("Performing login to '$this->baseUrl'");
        $headers = $this->buildHeaders(false);
        $headers[] = CONTENT_TYPE_JSON;

        $this->plugin->reset_curl($this->curl_wrapper);
        $this->curl_wrapper->set_post_data(array('Username' => $username, 'Pw' => $password));
        $this->curl_wrapper->set_send_headers($headers);

        $command_url = $this->baseUrl . '/Users/AuthenticateByName';
        $response = $this->curl_wrapper->download_content($command_url, Curl_Wrapper::RET_ARRAY);
        if ($response === false) {
            hd_debug_print("Can't get response on request: $command_url");
            return false;
        }

        $this->userId = safe_get_value($response, array('SessionInfo', 'UserId'));
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
        $this->plugin->reset_curl($this->curl_wrapper);
        $this->curl_wrapper->set_post();
        $this->curl_wrapper->set_send_headers($this->buildHeaders(true));
        $this->curl_wrapper->download_content($this->baseUrl . '/Sessions/Logout');
    }

    /**
     * Get server Info
     * @return array|bool
     */
    public function systemInfo()
    {
        $this->plugin->reset_curl($this->curl_wrapper);
        $this->curl_wrapper->set_send_headers($this->buildHeaders(true));
        $response = $this->curl_wrapper->download_content($this->baseUrl . '/System/Info', Curl_Wrapper::RET_ARRAY);
        if (empty($response)) {
            hd_debug_print("Unauthorized");
            return false;
        }

        hd_debug_print("Auth response: " . json_encode($response), true);
        return $response;
    }

    /**
     * @param string $id
     * @param array $query
     * @return array|false
     */
    public function getItemPlaybackInfo($id, $query)
    {
        $post_data['UserId'] = $this->userId;
        $post_data['MediaSourceId'] = $query['MediaSourceId'];
        $post_data['AudioStreamIndex'] = $query['AudioStreamIndex'];
        $post_data['EnableTranscoding'] = false;

        $headers = $this->buildHeaders(true);
        $headers[] = CONTENT_TYPE_JSON;

        $this->plugin->reset_curl($this->curl_wrapper);
        $this->curl_wrapper->set_post_data($post_data);
        $this->curl_wrapper->set_send_headers($headers);

        $command_url = $this->baseUrl . "/Items/$id/PlaybackInfo";
        return $this->curl_wrapper->download_content($command_url, Curl_Wrapper::RET_ARRAY);
    }

    /**
     * @return string|null
     */
    public function get_user_id()
    {
        return $this->userId;
    }

    /**
     * @return string|null
     */
    public function get_access_token()
    {
        return $this->accessToken;
    }

    /**
     * Get User View
     *
     * @return array
     */
    public function getUserViews($param = null)
    {
        $path = 'UserViews';
        if (!is_null($param)) {
            $path .= "/$param";
        }

        $query = array();
        if (!empty($this->userId)) {
            $query['userId'] = $this->userId;
        }
        return $this->get($path, $query);
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
        $query['userId'] = $this->userId;

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
     * get master.m3u8 HLS manifest
     *
     * @param string $itemId
     * @param array $query
     * @return string
     */
    public function getPlayUrlMaster($itemId, $query = array())
    {
        $this->updateQuery($query);
        return $this->baseUrl . '/Videos/' . urlencode($itemId) . '/master.m3u8?' . http_build_query($query);
    }

    /**
     * get main.m3u8 play url (contains media segments )
     *
     * @param string $itemId
     * @param array $query
     * @return string
     */
    public function getPlayUrlMain($itemId, $query = array())
    {
        $this->updateQuery($query);
        return $this->baseUrl . '/Videos/' . urlencode($itemId) . '/main.m3u8?' . http_build_query($query);
    }

    /**
     * get download url (streams entire file)
     *
     * @param string $itemId
     * @return string
     */
    public function getDownloadUrl($itemId)
    {
        $this->updateQuery($query);
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
        $this->updateQuery($query);
        return $this->get('Items/Filters', $query);
    }

    /**
     * Update query for necessary items
     *
     * @param array $query
     * @return void
     */
    private function updateQuery(&$query)
    {
        $query['apiKey'] = $this->accessToken;
        $query['DeviceId'] = $this->deviceId;
        $query['userId'] = $this->userId;
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

        $headers = $this->buildHeaders(true);
        $headers[] = CONTENT_TYPE_JSON;

        $this->plugin->reset_curl($this->curl_wrapper);
        $this->curl_wrapper->set_send_headers($headers);

        $command_url = $this->baseUrl . '/' . ltrim($path, '/');
        if (!empty($query)) {
            $command_url .= '?' . http_build_query($query);
        }

        $response = $this->curl_wrapper->download_content($command_url, Curl_Wrapper::RET_ARRAY | Curl_Wrapper::CACHE_RESPONSE);
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
