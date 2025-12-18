<?php
/**
 * The MIT License (MIT)
 *
 * @Author: sharky72 (https://github.com/KocourKuba)
 * Some code imported from various authors of dune plugins
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

require_once 'hd.php';

class Curl_Wrapper
{
    const CACHE_TAG_FILE = "etag_cache.dat";

    /**
     * @var int
     */
    private $connect_timeout = 30;

    /**
     * @var int
     */
    private $download_timeout = 90;

    /**
     * @var int
     */
    private $http_code;

    /**
     * @var array
     */
    private $send_headers;

    /**
     * @var array
     */
    private $options;

    /**
     * @var array
     */
    private $post_data;

    /**
     * @var bool
     */
    private $is_post = false;

    /**
     * @var int
     */
    private $error_no;

    /**
     * @var string
     */
    private $error_desc;

    /**
     * @var array|null
     */
    private static $http_response_headers = null;

    public function __construct()
    {
        $this->reset();
    }

    public function reset()
    {
        hd_debug_print(null, true);
        self::$http_response_headers = null;
        $this->send_headers = array();
        $this->is_post = false;
        $this->post_data = null;
        $this->http_code = 0;
        $this->error_no = 0;
        $this->error_desc = '';
    }

    public static function getInstance()
    {
        return new self();
    }

    /**
     * download file to selected path
     *
     * @param string $url
     * @param string $save_file path to file
     * @param bool $use_cache use ETag caching
     * @return bool result of operation
     */
    public function download_file($url, $save_file, $use_cache = false)
    {
        hd_debug_print(null, true);

        return $this->exec_php_curl($url, $save_file, $use_cache);
    }

    /**
     * download and return contents
     *
     * @param string $url
     * @param bool $use_cache use ETag caching
     * @return string|bool content of the downloaded file or result of operation
     */
    public function download_content($url, $use_cache = false)
    {
        hd_debug_print(null, true);

        return $this->exec_php_curl($url, null, $use_cache);
    }

    /**
     * @return string
     */
    public function get_etag_header()
    {
        return $this->get_response_header('etag');
    }

    /**
     * @return string
     */
    public function get_response_header($header)
    {
        return safe_get_value($this->get_response_headers(), $header, '');
    }

    /**
     * @param $value
     */
    public function set_post($value = true)
    {
        $this->is_post = $value;
    }

    /**
     * @param array $headers
     */
    public function set_send_headers($headers)
    {
        $this->send_headers = $headers;
    }

    /**
     * @param array $opts
     */
    public function set_options($opts)
    {
        $this->options = $opts;
    }

    /**
     * @param array $data
     */
    public function set_post_data($data)
    {
        $this->post_data = $data;
    }

    /**
     * @return array
     */
    public function get_response_headers()
    {
        return empty(self::$http_response_headers) ? array() : self::$http_response_headers;
    }

    /**
     * @return string
     */
    public function get_raw_response_headers()
    {
        return implode(PHP_EOL, $this->get_response_headers());
    }

    /**
     * @param  int $timeout
     */
    public function set_connection_timeout($timeout)
    {
        $this->connect_timeout = $timeout;
    }

    /**
     * @param int $timeout
     */
    public function set_download_timeout($timeout)
    {
        $this->download_timeout = $timeout;
    }

    /**
     * Check if cached url is expired
     *
     * @param string $url
     * @return bool result of operation
     */
    public function check_is_expired($url)
    {
        hd_debug_print(null, true);

        $etag = self::get_cached_etag($url);
        if (empty($etag)) {
            hd_debug_print("No ETag value");
        } else {
            if ($this->exec_php_curl($url, false, true)) {
                $code = $this->get_http_code();
                hd_debug_print("http code: $code", true);
                return !($code === 304 || ($code === 200 && $this->get_etag_header() === $etag));
            }
        }

        return true;
    }

    /**
     * @return int
     */
    public function get_http_code()
    {
        return $this->http_code;
    }

    /**
     * @return int
     */
    public function get_error_no()
    {
        return $this->error_no;
    }

    /**
     * @return string
     */
    public function get_error_desc()
    {
        return $this->error_desc;
    }


    /////////////////////////////////////////////////////////////
    /// static functions

    /**
     * @param string $url
     */
    public static function get_url_hash($url)
    {
        return hash('crc32', $url);
    }

    /**
     * @param bool $is_file
     * @param string $source contains data or file name
     * @param bool $assoc
     * @return mixed|false
     */
    public static function decodeJsonResponse($is_file, $source, $assoc = false)
    {
        if ($source === false) {
            return false;
        }

        if ($is_file) {
            $data = file_get_contents($source);
        } else {
            $data = $source;
        }

        $contents = json_decode($data, $assoc);
        if ($contents !== null && $contents !== false) {
            return $contents;
        }

        hd_debug_print("failed to decode json");
        hd_debug_print("doc: $data", true);

        return false;
    }

    /**
     * @param string $url
     * @return string
     */
    public static function get_cached_etag($url)
    {
        return empty($url) ? '' : safe_get_value(self::load_cached_etags(), self::get_url_hash($url), '');
    }

    /**
     * @param string $url
     * @param string $etag
     * @return void
     */
    public static function set_cached_etag($url, $etag)
    {
        hd_debug_print(null, true);
        if (!empty($url) && !empty($etag)) {
            $cache_db = self::load_cached_etags();
            $cache_db[self::get_url_hash($url)] = $etag;
            self::save_cached_etag($cache_db);
        }
    }

    /**
     * @param string $url
     * @return bool
     */
    public static function is_cached_etag($url)
    {
        $etag = self::get_cached_etag(self::get_url_hash($url));
        return !empty($etag);
    }

    /**
     * @param string $url
     * @return void
     */
    public static function clear_cached_etag($url)
    {
        $cache_db = self::load_cached_etags();
        unset($cache_db[self::get_url_hash($url)]);
        self::save_cached_etag($cache_db);
    }

    /**
     * @param string $hash
     * @return void
     */
    public static function clear_cached_etag_by_hash($hash)
    {
        if (empty($hash)) {
            $cache_db = array();
        } else {
            $cache_db = self::load_cached_etags();
            unset($cache_db[$hash]);
        }
        self::save_cached_etag($cache_db);
    }

    /**
     * @return array
     */
    protected static function load_cached_etags()
    {
        $cache_path = get_data_path(self::CACHE_TAG_FILE);
        if (file_exists($cache_path)) {
            $cache_db = json_decode(file_get_contents($cache_path), true);
        } else {
            $cache_db = array();
        }

        return $cache_db;
    }

    /**
     * @param array $cache_db
     * @return void
     */
    protected static function save_cached_etag($cache_db)
    {
        file_put_contents(get_data_path(self::CACHE_TAG_FILE), json_encode($cache_db));
    }

    /** @noinspection PhpUnusedParameterInspection */
    public static function http_header_function($curl, $header)
    {
        $len = strlen($header);
        $header = explode(':', $header, 2);
        if (count($header) == 2) {
            $key = strtolower(trim($header[0]));
            self::$http_response_headers[$key] = trim($header[1]);
        }
        return $len;
    }

    /////////////////////////////////////////////////////////////
    /// private functions


    /**
     * if $save_file == null return content of request
     * if $save_file == false return only result of request
     *
     * @param string $url
     * @param string|null|bool $save_file
     * @param bool $use_cache
     * @return bool|string
     */
    private function exec_php_curl($url, $save_file, $use_cache = false)
    {
        $this->http_code = 0;
        self::$http_response_headers = null;

        $opts[CURLOPT_URL] = $url;
        $opts[CURLOPT_SSL_VERIFYPEER] = 0;
        $opts[CURLOPT_SSL_VERIFYHOST] = 0;
        $opts[CURLOPT_CONNECTTIMEOUT] = $this->connect_timeout;
        $opts[CURLOPT_TIMEOUT] = $this->download_timeout;
        $opts[CURLOPT_RETURNTRANSFER] = 1;
        $opts[CURLOPT_FOLLOWLOCATION] = 1;
        $opts[CURLOPT_MAXREDIRS] = 5;
        $opts[CURLOPT_FILETIME] = 1;
        $opts[CURLOPT_USERAGENT] = HD::get_dune_user_agent();
        $opts[CURLOPT_HEADERFUNCTION] = 'Curl_Wrapper::http_header_function';
        $opts[CURLOPT_ENCODING] = "";

        if (!empty($this->options)) {
            $opts = safe_merge_array($opts, $this->options);
        }

        $fp = null;
        if (isset($opts[CURLOPT_INFILE]) || isset($opts[CURLOPT_INFILESIZE])) {
            $opts[CURLOPT_PUT] = 1;
        } else if ($save_file === false) {
            $opts[CURLOPT_NOBODY] = 1;
        } else if ($save_file !== null){
            hd_debug_print("Save to file: '$save_file'", true);
            $fp = fopen($save_file, "w+");
            $opts[CURLOPT_FILE] = $fp;
        }

        if ($use_cache) {
            $etag = self::get_cached_etag($url);
            if (!empty($etag)) {
                $this->send_headers[] = "If-None-Match: $etag";
            }
        } else {
            self::clear_cached_etag($url);
        }

        if (!empty($this->send_headers)) {
            $opts[CURLOPT_HTTPHEADER] = $this->send_headers;
        }

        if ($this->is_post) {
            $opts[CURLOPT_POST] = $this->is_post;
        }

        if (!empty($this->post_data)) {
            if (in_array(CONTENT_TYPE_JSON, $opts[CURLOPT_HTTPHEADER])) {
                $opts[CURLOPT_POSTFIELDS] = json_encode($this->post_data);
            } else {
                $data = '';
                foreach($this->post_data as $key => $value) {
                    if (!empty($data)) {
                        $data .= "&";
                    }
                    $data .= $key . "=" . urlencode($value);
                }
                $opts[CURLOPT_POSTFIELDS] = $data;
            }
        }

        $ch = curl_init();

        foreach ($opts as $k => $v) {
            if (LogSeverity::$is_debug) {
                if (is_bool($v)) {
                    hd_debug_print(HD::curlopt_to_string($k) . " ($k) = " . var_export($v, true));
                } else if (is_array($v)) {
                    hd_debug_print(HD::curlopt_to_string($k) . " ($k) = " . json_encode($v));
                } else {
                    hd_debug_print(HD::curlopt_to_string($k) . " ($k) = $v");
                }
            }
            curl_setopt($ch, $k, $v);
        }

        $start_tm = microtime(true);
        $content = curl_exec($ch);
        $execution_tm = microtime(true) - $start_tm;
        $this->error_no = curl_errno($ch);
        $this->error_desc = curl_error($ch);
        $this->http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (!is_null($fp)) {
            fclose($fp);
        }

        if ($this->error_no !== 0) {
            hd_debug_print("CURL errno: $this->error_no ($this->error_desc); HTTP error: $this->http_code;");
            return false;
        }

        if ($this->http_code < 200 || ($this->http_code >= 300 && $this->http_code != 304)) {
            hd_debug_print("HTTP request failed ($this->http_code)");
            return false;
        }

        if ($use_cache) {
            self::set_cached_etag($url, $this->get_etag_header());
        }

        if (empty($save_file)) {
            hd_debug_print(sprintf("HTTP OK (%d) in %.3fs", $this->http_code, $execution_tm), true);
        } else {
            hd_debug_print(sprintf("HTTP OK (%d, %d bytes) in %.3fs", $this->http_code, filesize($save_file), $execution_tm), true);
        }

        if (!empty(self::$http_response_headers) && LogSeverity::$is_debug) {
            hd_debug_print("---------  Response headers start ---------");
            foreach (self::$http_response_headers as $key => $header) {
                hd_debug_print("$key: $header");
            }
            hd_debug_print("---------   Response headers end  ---------");
        }

        return $save_file === null ? $content : true;
    }
}
