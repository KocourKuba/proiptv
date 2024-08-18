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
     * @var array
     */
    protected $auth_info;

    /**
     * @var array
     */
    protected $cache;

    ///////////////////////////////////////////////////////////////////////////////

    /**
     * @param string $base_url
     * @param string $username
     * @param string $password
     * @return void
     */
    public function init($base_url, $username, $password)
    {
        $this->base_url = $base_url;
        $this->username = $username;
        $this->password = $password;
    }

    /**
     * Reset cache
     * @return void
     */
    public function reset_cache()
    {
        $this->auth_info = null;
        $this->cache = null;
    }

    /**
     * Get authentication info
     * @return false|mixed
     */
    public function get_auth()
    {
        if (is_null($this->auth_info)) {
            $this->auth_info = $this->get_cached_response($this->get_auth_url());
        }

        return $this->auth_info;
    }

    /**
     * Get response if it already requested return cached value
     * @param string $url
     * @return mixed|false
     */
    protected function get_cached_response($url)
    {
        $url_hash = hash('crc32', $url);
        if (!is_null($this->cache) && isset($this->cache[$url_hash])) {
            return $this->cache[$url_hash];
        }

        $tmp_file = get_temp_path("$url_hash.json");
        if (file_exists($tmp_file)) {
            $mtime = filemtime($tmp_file);
            $diff = time() - $mtime;
            if ($diff <= 3600) {
                $cached_data = HD::ReadContentFromFile($tmp_file, false);
                return $this->update_cache($url_hash, $cached_data);
            }

            hd_debug_print("xtream response cache expired " . ($diff - 3600) . " sec ago. Timestamp $mtime. Forcing reload");
            unlink($tmp_file);
        }

        $cached_data = Curl_Wrapper::decodeJsonResponse(false, Curl_Wrapper::simple_download_content($url));
        if ($cached_data !== false) {
            HD::StoreContentToFile($tmp_file, $cached_data);
        }

        return $this->update_cache($url_hash, $cached_data);
    }

    /**
     * Update cache
     * @param string $url_hash
     * @param mixed $cached_data
     * @return mixed
     */
    protected function update_cache($url_hash, $cached_data)
    {
        if ($cached_data !== false) {
            $this->cache[$url_hash] = $cached_data;
        }

        return $cached_data;
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
        return $this->get_cached_response($this->get_categories_url($stream_type));
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
     * @return mixed|false
     */
    public function get_streams($stream_type = self::VOD, $category_id = null)
    {
        return $this->get_cached_response($this->get_streams_url($stream_type, $category_id));
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
     * @return mixed|false
     */
    public function get_stream_info($id, $stream_type = self::VOD)
    {
        return $this->get_cached_response($this->get_stream_info_url($id, $stream_type));
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
}
