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
    private $appVersion;

    /**
     * @var string
     */
    private $deviceName = 'dunehd';

    /**
     * @var string
     */
    private $clientName = 'ProIPTV';

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
     * @param Curl_Wrapper $curl_wrapper
     */
    private $curl_wrapper;

    /**
     * @param $plugin
     * @param $baseUrl
     * @param $appVersion
     */
    public function __construct($plugin, $baseUrl, $appVersion = '1.0.0')
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->appVersion = $appVersion;

        $this->base_auth_string = sprintf('MediaBrowser Client="%s", Device="%s", DeviceId="%s", Version="%s"',
            $this->clientName, $this->deviceName, $this->deviceName, $this->appVersion);

        $this->curl_wrapper = Curl_Wrapper::getInstance();
        if ($plugin) {
            $plugin->set_curl_timeouts($this->curl_wrapper);
        }
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
        $this->curl_wrapper->reset();
        $this->curl_wrapper->set_post();
        $this->curl_wrapper->set_post_data(array('Username' => $username, 'Pw' => $password));
        $headers = $this->buildHeaders(false);
        $headers[] = CONTENT_TYPE_JSON;
        $this->curl_wrapper->set_send_headers($headers);

        $command_url = $this->baseUrl . '/Users/AuthenticateByName';
        $response = $this->curl_wrapper->download_content($command_url);
        if ($response === false) {
            hd_debug_print("Can't get response on request: $command_url");
            return false;
        }

        $data = json_decode($response, true);
        if (!isset($data['AccessToken']) || !isset($data['User']['Id'])) {
            hd_debug_print('Login failed.');
            return false;
        }

        $this->accessToken = $data['AccessToken'];
        $this->userId      = $data['User']['Id'];
        return true;
    }

    public function logout()
    {
        $this->curl_wrapper->reset();
        $this->curl_wrapper->set_post();
        $this->curl_wrapper->set_send_headers($this->buildHeaders(true));
        $this->curl_wrapper->download_content($this->baseUrl . '/Sessions/Logout');
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
     * @return mixed
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
        $query['DeviceId'] = $this->deviceName;
        $query['apiKey'] = $this->accessToken;
        $query['MediaSourceId'] = isset($media_source['Id']) ? $media_source['Id'] : $itemId;
        if ($audioIndex !== -1) {
            $query['AudioStreamIndex'] = $audioIndex;
        }
        return $this->baseUrl . '/Videos/' . urlencode($itemId) . '/master.m3u8?' . http_build_query($query);
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

        $this->curl_wrapper->reset();
        $headers = $this->buildHeaders(true);
        $headers[] = CONTENT_TYPE_JSON;
        $this->curl_wrapper->set_send_headers($headers);

        $command_url = $this->baseUrl . '/' . ltrim($path, '/');
        if (!empty($query)) {
            $command_url .= '?' . http_build_query($query);
        }

        $response = $this->curl_wrapper->download_content($command_url, true);
        if ($response === false) {
            hd_debug_print("Can't get response on request: $command_url");
            return array();
        }

        return json_decode($response, true);
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
