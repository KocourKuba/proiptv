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
     * @var string
     */
    protected $stream_type = self::VOD;

    /**
     * @var bool
     */
    protected $streams_by_category = false;

    /**
     * @var array
     */
    protected $auth_info;

    /**
     * @var array
     */
    protected $categories;

    /**
     * @var array
     */
    protected $streams;

    ///////////////////////////////////////////////////////////////////////////////

    /**
     * @param $base_url string
     * @param $username string
     * @param $password string
     * @param $stream_type string
     * @param $streams_by_category bool
     * @return void
     */
    public function init($base_url, $username, $password, $stream_type = self::VOD, $streams_by_category = false)
    {
        $this->base_url = $base_url;
        $this->username = $username;
        $this->password = $password;
        $this->stream_type = $stream_type;
        $this->streams_by_category = $streams_by_category;
    }

    public function reset_cache()
    {
        $this->auth_info = null;
        $this->categories = null;
        $this->streams = null;
    }

    /**
     * @return false|mixed
     */
    public function get_auth()
    {
        if (is_null($this->auth_info)) {
            $this->auth_info = self::DownloadJsonBigIntMod($this->get_auth_url(), false);
        }

        return $this->auth_info;
    }

    /**
     * @return array|false
     */
    public function get_categories()
    {
        if (is_null($this->categories)) {
            $this->categories = self::DownloadJsonBigIntMod($this->get_categories_url(), false);
        }

        return $this->categories;
    }

    /**
     * @param $category_id string|null
     * @return array|false
     */
    public function get_streams($category_id = null)
    {
        $load = false;
        if (!empty($category_id) && !isset($this->streams[$category_id])) {
            $load = true;
        } else if (empty($this->streams)) {
            $load = true;
        }

        if ($load) {
            $streams = self::DownloadJsonBigIntMod($this->get_streams_url($category_id), false);
            if ($streams === false) {
                return array();
            }

            if ($this->streams_by_category && !empty($category_id) ) {
                foreach ($streams as $stream) {
                    $this->streams[$category_id][$stream->stream_id] = $stream;
                }
            } else {
                foreach ($streams as $stream) {
                    $this->streams[$stream->category_id][$stream->stream_id] = $stream;
                }
            }
        }

        return empty($category_id) ? $this->streams : $this->streams[$category_id];
    }

    /**
     * @param $id string
     * @return mixed|false
     */
    public function get_stream_info($id)
    {
        return self::DownloadJsonBigIntMod($this->get_stream_info_url($id), false);
    }

    /**
     * @param $id string
     * @return string
     */
    public function get_stream_url($id)
    {
        $stream_type = ($this->stream_type === self::VOD) ? "movie" : $this->stream_type;
        $url = '';
        if (!empty($this->streams)) {
            foreach ($this->streams as $category) {
                if (isset($category[$id]->stream_id)) {
                    $stream = $category[$id];
                    $url = sprintf("%s/%s/%s/%s/$stream->stream_id",
                        $this->base_url,
                        $stream_type,
                        $this->username,
                        $this->password);
                    break;
                }
            }
        }

        return $url;
    }

    ///////////////////////////////////////////////////////////////////////////////

    /**
     * @return string
     */
    protected function get_auth_url()
    {
        return sprintf("%s/player_api.php?username=%s&password=%s", $this->base_url, $this->username, $this->password);
    }

    /**
     * @return string
     */
    protected function get_categories_url()
    {
        return sprintf("%s&action=get_%s_categories", $this->get_auth_url(), $this->stream_type);
    }

    /**
     * @return string|null
     */
    protected function get_streams_url($category_id = null)
    {
        $url = sprintf("%s&action=get_%s_streams", $this->get_auth_url(), $this->stream_type);

        if (!empty($category_id)) {
            $url .= "&category_id=$category_id";
        }

        return $url;
    }

    /**
     * @return string
     */
    protected function get_stream_info_url($id)
    {
        return sprintf("%s&action=get_%s_info&%s_id=%s", $this->get_auth_url(), $this->stream_type, $this->stream_type, $id);
    }

    /**
     * @param string $url
     * @param bool $to_array
     * @return false|mixed
     */
    public static function DownloadJsonBigIntMod($url, $to_array = true)
    {
        try {
            $doc = HD::http_get_document($url);
            $doc = preg_replace('/("\w+"):(\d{9,})/', '\\1:"\\2"', $doc);
            $contents = json_decode($doc, $to_array);
            if ($contents === null || $contents === false) {
                hd_debug_print("failed to decode json");
                hd_debug_print("doc: $doc");
                return false;
            }
        } catch (Exception $ex) {
            hd_debug_print("Unable to load url: " . $ex->getMessage());
            return false;
        }

        return $contents;
    }
}
