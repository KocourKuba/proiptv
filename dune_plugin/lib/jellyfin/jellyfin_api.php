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
     * credentials kept to log in again when the token is revoked during the session
     * @var string
     */
    private $username = '';

    /**
     * @var string
     */
    private $password = '';

    /**
     * @param Default_Dune_Plugin $plugin
     * @param string $baseUrl
     * @param string $appVersion
     * @return void
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
        $this->username = safe_get_value($access_info, MACRO_LOGIN, '');
        $this->password = safe_get_value($access_info, MACRO_PASSWORD, '');
        $this->accessToken = safe_get_value($access_info, PARAM_TOKEN);
        $this->userId = safe_get_value($access_info, PARAM_USER_ID);

        // saved token is still valid if the server knows its user
        if (!empty($this->accessToken)) {
            $user = $this->getCurrentUser();
            if (!empty($user['Id'])) {
                $this->userId = $user['Id'];
                return true;
            }
        }

        return $this->authenticate();
    }

    /**
     * Log in by user name and password
     *
     * @return bool
     */
    private function authenticate()
    {
        hd_debug_print("Performing login to '$this->baseUrl'");
        $this->accessToken = null;
        $this->userId = null;

        $headers = $this->buildHeaders(false);
        $headers[] = CONTENT_TYPE_JSON;

        $this->plugin->reset_curl($this->curl_wrapper);
        $this->curl_wrapper->set_post_data(array('Username' => $this->username, 'Pw' => $this->password));
        $this->curl_wrapper->set_send_headers($headers);

        $command_url = $this->baseUrl . '/Users/AuthenticateByName';
        $response = $this->curl_wrapper->download_content($command_url, Curl_Wrapper::RET_ARRAY);
        if ($response === false) {
            hd_debug_print("Login failed (" . Curl_Wrapper::get_http_code() . ") on request: $command_url");
            return false;
        }

        $this->accessToken = safe_get_value($response, 'AccessToken');
        $this->userId = safe_get_value($response, array('User', 'Id'));
        if (empty($this->userId)) {
            $this->userId = safe_get_value($response, array('SessionInfo', 'UserId'));
        }

        if (!empty($this->accessToken) && !empty($this->userId)) {
            return true;
        }

        hd_debug_print('Login failed.');
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
     * Get user of the current token.
     * Unlike System/Info it is allowed for every user, not only for the ones who ignore parental control.
     *
     * @return array|bool
     */
    public function getCurrentUser()
    {
        $this->plugin->reset_curl($this->curl_wrapper);
        $this->curl_wrapper->set_send_headers($this->buildHeaders(true));
        $response = $this->curl_wrapper->download_content($this->baseUrl . '/Users/Me', Curl_Wrapper::RET_ARRAY);
        if (empty($response)) {
            hd_debug_print('Unauthorized (' . Curl_Wrapper::get_http_code() . ')');
            return false;
        }

        return $response;
    }

    /**
     * @param string $id
     * @param array $query
     * @return array|false
     */
    public function getItemPlaybackInfo($id, $query)
    {
        // EnableDirectPlay/EnableDirectStream/EnableTranscoding are applied by the server only together with
        // a DeviceProfile, without it they have no effect. The request is used to get media sources and PlaySessionId.
        $post_data['UserId'] = $this->userId;
        // both are optional, without them the server picks the default source and audio track
        foreach (array('MediaSourceId', 'AudioStreamIndex') as $key) {
            if (isset($query[$key])) {
                $post_data[$key] = $query[$key];
            }
        }

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
     * @param string|null $param
     * @return array
     */
    public function getUserViews($param = null)
    {
        $path = 'UserViews';
        if (!is_null($param)) {
            $path .= "/$param";
        }

        return $this->get($path, array('userId' => $this->userId));
    }

    /**
     * Get items under ParentId
     *
     * @param array $query
     * @return array
     */
    public function getItems($query = array())
    {
        $query['userId'] = $this->userId;

        return $this->get('Items', $query);
    }

    /**
     * @param string $id
     * @return array
     */
    public function getItemInfo($id)
    {
        return $this->get('Items/' . urlencode($id), array('userId' => $this->userId));
    }

    /**
     * @param string $seriesId
     * @return array
     */
    public function getSeasons($seriesId)
    {
        return $this->get('Shows/' . urlencode($seriesId) . '/Seasons', array('userId' => $this->userId));
    }

    /**
     * Episodes of the season, with media sources: no need to request every episode separately
     *
     * @param string $seriesId
     * @param string $seasonId
     * @return array
     */
    public function getEpisodes($seriesId, $seasonId)
    {
        $query['userId'] = $this->userId;
        $query['seasonId'] = $seasonId;
        $query['fields'] = 'MediaSources,Overview';
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
     * mediaSourceId is mandatory, the default media source has the same id as the item
     *
     * @param string $itemId
     * @param array $query
     * @return string
     */
    public function getPlayUrlMaster($itemId, $query = array())
    {
        if (empty($query['MediaSourceId'])) {
            $query['MediaSourceId'] = $itemId;
        }
        $this->updateQuery($query);
        return $this->baseUrl . '/Videos/' . urlencode($itemId) . '/master.m3u8?' . http_build_query($query);
    }

    /**
     * get url of the original file of the media source (direct stream, no remux or transcoding)
     *
     * @param string $itemId
     * @param array $query
     * @param string $extension file extension of the container (mp4, mkv, mov), empty if unknown
     * @return string
     */
    public function getStreamUrl($itemId, $query = array(), $extension = '')
    {
        $query['static'] = 'true';
        if (empty($query['MediaSourceId'])) {
            $query['MediaSourceId'] = $itemId;
        }
        $this->updateQuery($query);
        $path = empty($extension) ? 'stream' : "stream.$extension";
        return $this->baseUrl . '/Videos/' . urlencode($itemId) . "/$path?" . http_build_query($query);
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
        $query = array();
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
        $query['userId'] = $this->userId;
        return $this->get('Items/Filters', $query);
    }

    /**
     * Add authorization to the url played by the player, it can't send the Authorization header.
     * ApiKey replaces the api_key parameter deprecated since 10.11
     *
     * @param array $query
     * @return void
     */
    private function updateQuery(&$query)
    {
        $query['ApiKey'] = $this->accessToken;
        $query['DeviceId'] = $this->deviceId;
    }

    /**
     * @param string $path
     * @param array $query
     * @param bool $retry log in again and repeat request on 401
     * @return array
     */
    private function get($path, $query = array(), $retry = true)
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

        $response = $this->curl_wrapper->download_content($command_url, Curl_Wrapper::RET_ARRAY, Curl_Wrapper::CACHE_RESPONSE);
        if ($response !== false) {
            return $response;
        }

        // token revoked or expired during the session, log in again and repeat once
        if ($retry && Curl_Wrapper::get_http_code() === 401 && $this->authenticate()) {
            return $this->get($path, $query, false);
        }

        hd_debug_print("Can't get response (" . Curl_Wrapper::get_http_code() . ") on request: $command_url");
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
