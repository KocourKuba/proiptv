<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
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

require_once 'lib/hd.php';
require_once 'lib/curl_wrapper.php';

class xtream_codes_api
{
    const LIVE = "live";
    const VOD = "vod";
    const SERIES = "series";

    /**
     * @var string
     */
    protected $base_url;

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $password;

    /**
     * @var Default_Dune_Plugin
     */
    private $plugin;

    ///////////////////////////////////////////////////////////////////////////////

    /**
     * @param string $base_url
     * @param string $username
     * @param string $password
     * @return void
     */
    public function init($plugin, $base_url, $username, $password)
    {
        hd_debug_print(null, true);
        hd_debug_print("Base url: $base_url", true);
        $this->plugin = $plugin;
        $this->base_url = $base_url;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Get auth url
     * @return string
     */
    protected function get_auth_url()
    {
        return sprintf("%s/player_api.php?username=%s&password=%s", $this->base_url, $this->username, $this->password);
    }

    /**
     * Get categories
     * @return array|false
     */
    public function get_categories($stream_type = self::VOD)
    {
        return $this->json_request($this->get_categories_url($stream_type));
    }

    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Get categories url
     * @return string
     */
    protected function get_categories_url($stream_type = self::VOD)
    {
        return sprintf("%s&action=get_%s_categories", $this->get_auth_url(), $stream_type);
    }

    /**
     * Get streams
     * @param string|null $category_id
     * @return bool|array
     */
    public function get_streams($stream_type = self::VOD, $category_id = null)
    {
        return $this->json_request($this->get_streams_url($stream_type, $category_id));
    }

    /**
     * Get streams url
     * @return string|null
     */
    protected function get_streams_url($stream_type = self::VOD, $category_id = null)
    {
        if ($stream_type === self::SERIES) {
            $url = sprintf("%s&action=get_%s", $this->get_auth_url(), $stream_type);
        } else {
            $url = sprintf("%s&action=get_%s_streams", $this->get_auth_url(), $stream_type);
        }

        if (!empty($category_id)) {
            $url .= "&category_id=$category_id";
        }

        return $url;
    }

    /**
     * Get stream info
     * @param string $id
     * @return bool|string|array
     */
    public function get_stream_info($id, $stream_type = self::VOD)
    {
        $url = $this->get_stream_info_url($id, $stream_type);
        return $this->json_request($url);
    }

    /**
     * Get stream info url
     * @return string
     */
    protected function get_stream_info_url($id, $stream_type = self::VOD)
    {
        return sprintf("%s&action=get_%s_info&%s_id=%s", $this->get_auth_url(), $stream_type, $stream_type, $id);
    }

    /**
     * Get stream url
     * @param string $id
     * @return string
     */
    public function get_stream_url($id, $stream_type = self::VOD)
    {
        $stream_type = ($stream_type !== self::LIVE) ? "movie" : $stream_type;
        return sprintf("%s/%s/%s/%s/$id",
            $this->base_url,
            $stream_type,
            $this->username,
            $this->password);
    }

    protected function json_request($url)
    {
        return $this->plugin->setup_curl()->download_content($url, Curl_Wrapper::RET_ARRAY | Curl_Wrapper::CACHE_RESPONSE);
    }
}
