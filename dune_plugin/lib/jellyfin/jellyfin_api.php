<?php
require_once "lib/curl_wrapper.php";

class jellyfin_api
{
    const VOD = "Movie";
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
    public function getUserViews()
    {
        return $this->get('UserViews', array('userId' => $this->userId));
    }

    /**
     * Get categories
     *
     * @return array
     */
    public function getCategories()
    {
        return $this->get('UserViews/GroupingOptions', array('userId' => $this->userId));
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
     * Extract data for selected index
     *
     * @param array $items
     * @param string $index
     * @return array
     */
    public static function stripIndex($items, $index = 'Items')
    {
        return isset($items[$index]) ? $items[$index] : $items;
    }

    // ---------------- Movies ----------------

    /**
     * @param array $query
     * @return array
     */
    public function getMovies($query = array())
    {
        $query['IncludeItemTypes'] = 'Movie';
        return self::stripIndex($this->getItems($query));
    }

    // ---------------- Series ----------------

    /**
     * @param array $query
     * @return array
     */
    public function getSeries($query = array())
    {
        $query['IncludeItemTypes'] = 'Series';
        return self::stripIndex($this->getItems($query));
    }

    /**
     * @param string $seriesId
     * @return array
     */
    public function getSeasons($seriesId)
    {
        $items = $this->get('Shows/' . urlencode($seriesId) . '/Seasons');
        return self::stripIndex($items);
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
        $items = $this->get('Shows/' . urlencode($seriesId) . '/Episodes', $query);
        return self::stripIndex($items);
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

    /**
     * @param string $itemId
     * @return array
     */
    public function getAvailableImages($itemId)
    {
        $info  = $this->get('Items/' . urlencode($itemId));
        $types = array();
        if (isset($info['ImageTags'])) {
            foreach ($info['ImageTags'] as $type => $tag) {
                $types[] = $type;
            }
        }
        if (!empty($info['BackdropImageTags'])) {
            $types[] = 'Backdrop';
        }
        if (!empty($info['ScreenshotImageTags'])) {
            $types[] = 'Screenshot';
        }
        return $types;
    }

    // ---------------- Playback helpers ----------------

    /**
     * Query playback info (server determines direct vs transcode possibilities)
     *
     * @param string $itemId
     * @param array $options
     * @return array
     */
    public function getPlaybackInfo($itemId, $options = array())
    {
        $query['UserId'] = $this->userId;

        if (isset($options['MaxStreamingBitrate'])) {
            $query['MaxStreamingBitrate'] = $options['MaxStreamingBitrate'];
        }

        if (isset($options['StartTimeTicks'])) {
            $query['StartTimeTicks'] = $options['StartTimeTicks'];
        }

        if (isset($options['Profile'])) {
            // device profile if you have one
            $query['Profile'] = $options['Profile'];
        }

        return $this->get('Items/' . urlencode($itemId) . '/PlaybackInfo', $query);
    }

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
        // http://jeleyka.balelbrus.com/Items/2749bdccd02b6853f544af497b4bc4fc/Download?api_key=c50bd0f08d3947dea02b7751ce5987ce
        // http://jeleyka.balelbrus.com/Items/2be5791959d2026fae72704697f0215b/Download?api_key=c50bd0f08d3947dea02b7751ce5987ce

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
    public function getGenres($query = array())
    {
        return $this->get('Genres', $query);
    }

    /**
     * Get all distinct production years for Movies/Series
     *
     * @param array $options
     * @return array
     */
    public function getYears($options = array())
    {
        $query = array(
            'IncludeItemTypes' => isset($options['types']) ? $options['types'] : 'Movie,Series',
            'Fields'           => 'ProductionYear',
            'Recursive'        => 'true',
        );

        if (isset($options['limit'])) {
            $query['Limit'] = $options['Limit'];
        }

        $items = $this->getItems($query);
        $years = array();

        if (isset($items['Items'])) {
            foreach ($items['Items'] as $item) {
                if (isset($item['ProductionYear'])) {
                    $years[$item['ProductionYear']] = true;
                }
            }
        }

        $yearList = array_keys($years);
        sort($yearList);
        return $yearList;
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
