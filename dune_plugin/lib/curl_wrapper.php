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

    const RET_RAW = 1;
    const RET_ARRAY = 2;
    const RET_OBJECT = 4;
    const USE_ETAG = 8;
    const CACHE_RESPONSE = 16;

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
    private static $http_code;

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
    private static $error_no;

    /**
     * @var string
     */
    private static $error_desc;

    /**
     * @var array|null
     */
    private static $http_response_headers = null;

    /**
     * @var string
     */
    private $file_cache_time = 3600;

    /**
     * @var string
     */
    private $file_cache_path;

    /**
     * @param string $cache_subdir
     */
    protected function __construct($cache_subdir = 'common')
    {
        $this->file_cache_path = get_slash_trailed_path(get_data_path(CURL_CACHE_SUBDIR . '/' . $cache_subdir));
        create_path($this->file_cache_path);
        hd_debug_print("File cache path: $this->file_cache_path", true);
        $this->reset();
    }

    /**
     * @return void
     */
    public function reset()
    {
        hd_debug_print(null, true);
        self::$http_response_headers = null;
        self::$error_no = 0;
        self::$error_desc = '';
        self::$http_code = 0;
        $this->send_headers = array();
        $this->is_post = false;
        $this->post_data = null;
    }

    public static function getInstance($cache_subdir = 'common')
    {
        return new self($cache_subdir);
    }

    /**
     * download file to selected path
     *
     * @param string $url
     * @param string $save_file path to file
     * @param int $cache_opts caching parameters
     * @return bool result of operation
     */
    public function download_file($url, $save_file, $cache_opts = 0)
    {
        hd_debug_print(null, true);

        return $this->exec_php_curl($url, $save_file, $cache_opts);
    }

    /**
     * download and decode return contents
     *
     * @param string $url
     * @param int $opts options
     * @return bool|string|array|object content of the downloaded file or result of operation or decoded json response
     */
    public function download_content($url, $opts = self::RET_RAW)
    {
        hd_debug_print(null, true);

        $res = $this->exec_php_curl($url, null, $opts);

        if ($opts & self::RET_RAW) {
            hd_debug_print('Returns RAW response', true);
            return $res;
        }

        $assoc = ($opts & self::RET_ARRAY) === self::RET_ARRAY;
        $contents = json_decode($res, $assoc);
        if ($contents === false) {
            hd_debug_print("failed to decode json");
            hd_debug_print("doc: $res", true);
            return false;
        }

        return $contents;
    }

    /**
     * @return string
     */
    public static function get_response_header($header)
    {
        return safe_get_value(self::get_response_headers(), $header, '');
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
    public static function get_response_headers()
    {
        return empty(self::$http_response_headers) ? array() : self::$http_response_headers;
    }

    /**
     * @return string
     */
    public static function get_raw_response_headers()
    {
        return implode(PHP_EOL, self::get_response_headers());
    }

    /**
     * @param  int $timeout
     */
    public function set_connect_timeout($timeout)
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
     * @param int $time
     */
    public function set_file_cache_time($time)
    {
        $this->file_cache_time = $time;
    }

    /**
     * @return int
     */
    public static function get_http_code()
    {
        return self::$http_code;
    }

    /**
     * @return int
     */
    public static function get_error_no()
    {
        return self::$error_no;
    }

    /**
     * @return string
     */
    public static function get_error_desc()
    {
        return self::$error_desc;
    }


    /////////////////////////////////////////////////////////////
    /// static functions

    public function clear_cache($all = false)
    {
        if ($all) {
            $path = get_slash_trailed_path(get_data_path(CURL_CACHE_SUBDIR));
        } else {
            $path = $this->file_cache_path;
        }

        if (file_exists($path)) {
            clear_directory($path);
        }
    }

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
     * @param int $decode
     * @return mixed|false
     */
    public static function decodeJsonResponse($is_file, $source, $decode = Curl_Wrapper::RET_OBJECT)
    {
        if ($source === false) {
            return false;
        }

        if ($is_file) {
            $data = file_get_contents($source);
        } else {
            $data = $source;
        }

        if ($decode & Curl_Wrapper::RET_RAW) {
            return $data;
        }

        $contents = json_decode($data, $decode & Curl_Wrapper::RET_ARRAY);
        if ($contents !== null && $contents !== false) {
            return $contents;
        }

        hd_debug_print("failed to decode json");
        hd_debug_print("doc: $data", true);

        return false;
    }

    /**
     * @param string $url
     * @param bool $by_hash
     * @return string
     */
    public static function get_cached_etag($url, $by_hash = false)
    {
        $cache_db = self::load_cached_etags();
        $hash = $by_hash ? $url : self::get_url_hash($url);
        return empty($hash) ? '' : safe_get_value($cache_db, $hash, '');
    }

    /**
     * @param string $url
     * @param string $etag
     * @return void
     */
    public static function set_cached_etag($url, $etag)
    {
        if (!empty($url) && !empty($etag)) {
            $cache_db = self::load_cached_etags();
            $hash = self::get_url_hash($url);
            $cache_db[$hash] = $etag;
            self::save_cached_etags($cache_db);
        }
    }

    /**
     * @param string $url
     * @param bool $by_hash
     * @return void
     */
    public static function clear_cached_etag($url, $by_hash = false)
    {
        if (!empty($url)) {
            $cache_db = self::load_cached_etags();
            $hash = $by_hash ? $url : self::get_url_hash($url);
            unset($cache_db[$hash]);
            self::save_cached_etags($cache_db);
        }
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

    /**
     * @return array
     */
    protected static function load_cached_etags()
    {
        $etag_cache_file = get_data_path(self::CACHE_TAG_FILE);
        if (file_exists($etag_cache_file)) {
            $cache_db = json_decode(file_get_contents($etag_cache_file), true);
        }

        if (!isset($cache_db) ||$cache_db === false) {
            $cache_db = array();
        }

        return $cache_db;
    }

    /**
     * @param array $cache_db
     * @return void
     */
    protected static function save_cached_etags($cache_db)
    {
        file_put_contents(get_data_path(self::CACHE_TAG_FILE), json_encode($cache_db));
    }

    /**
     * @return string
     */
    protected function create_cache_path()
    {
        create_path($this->file_cache_path);
        return $this->file_cache_path;
    }

    /////////////////////////////////////////////////////////////
    /// private functions


    /**
     * if $save_file == null return content of request
     * if $save_file == false return only result of request i.e. make HEAD request
     *
     * @param string $url
     * @param string|null|bool $save_file
     * @param int $cache_opts
     * @return bool|string
     */
    private function exec_php_curl($url, $save_file, $cache_opts = 0)
    {
        hd_debug_print("exec_php_curl: url: '$url'", true);
        if ($save_file === false) {
            hd_debug_print("exec_php_curl: request only headers", true);
        }

        self::$http_code = 0;
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

        $opts[CURLOPT_HTTPHEADER][] = "Accept: */*";
        $opts[CURLOPT_HTTPHEADER][] = "Cache-Control: no-cache";
        $parsed_url = parse_url($url);
        if (isset($parsed_url['host'])) {
            $opts[CURLOPT_HTTPHEADER][] = "Host: {$parsed_url['host']}";
        }

        if ($cache_opts & self::USE_ETAG) {
            hd_debug_print("cache opts: Use ETag capability", true);
            $etag = self::get_cached_etag($url);
            if (!empty($etag)) {
                $opts[CURLOPT_HTTPHEADER][] = "If-None-Match: $etag";
            }
        }

        if (!empty($this->send_headers)) {
            $opts[CURLOPT_HTTPHEADER] = array_merge($opts[CURLOPT_HTTPHEADER], $this->send_headers);
        }

        if (!empty($this->post_data)) {
            if (in_array(CONTENT_TYPE_JSON, $opts[CURLOPT_HTTPHEADER])) {
                $opts[CURLOPT_POSTFIELDS] = json_format_unescaped($this->post_data);
            } else {
                $opts[CURLOPT_POSTFIELDS] = http_build_query($this->post_data);
            }
            $opts[CURLOPT_HTTPHEADER][] = "Content-Length: " . strlen($opts[CURLOPT_POSTFIELDS]);
        }

        if ($this->is_post) {
            $opts[CURLOPT_POST] = $this->is_post;
        } else if (empty($opts[CURLOPT_NOBODY]) && empty($opts[CURLOPT_PUT])) {
            $opts[CURLOPT_CUSTOMREQUEST] = "GET";
        }

        if (isset($opts[CURLOPT_POSTFIELDS])) {
            $hash = hash('md5', $url . $opts[CURLOPT_POSTFIELDS]);
        } else {
            $hash = hash('md5', $url);
        }

        if ($cache_opts & self::CACHE_RESPONSE) {
            hd_debug_print("cache opts: Use cache response. Cache time: {$this->file_cache_time}h", true);
            $path = $this->file_cache_path . $hash;
            if (file_exists($path)) {
                $now = time();
                $mtime = filemtime($path);
                $cache_expired_in = $mtime + $this->file_cache_time * 3600;
                hd_debug_print("Cache expiration time: " . format_datetime("Y-m-d H:i", $cache_expired_in), true);
                if ($now < $cache_expired_in) {
                    hd_debug_print("Response read from cache $path", true);
                    return file_get_contents($path);
                }
                hd_debug_print("Cache expired: $path", true);
                unlink($path);
            }
        }

        $ch = curl_init();

        foreach ($opts as $k => $v) {
            if (LogSeverity::$is_debug) {
                if (is_bool($v)) {
                    hd_debug_print(HD::curlopt_to_string($k) . " = " . var_export($v, true));
                } else if (is_array($v)) {
                    hd_debug_print(HD::curlopt_to_string($k) . " = " . json_format_unescaped($v));
                } else {
                    hd_debug_print(HD::curlopt_to_string($k) . " = $v");
                }
            }
            curl_setopt($ch, $k, $v);
        }

        $start_tm = microtime(true);
        $content = curl_exec($ch);
        $execution_tm = microtime(true) - $start_tm;
        self::$error_no = curl_errno($ch);
        self::$error_desc = curl_error($ch);
        self::$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (!is_null($fp)) {
            fclose($fp);
        }

        if (!empty(self::$http_response_headers) && LogSeverity::$is_debug) {
            hd_debug_print("---------  Response headers start ---------");
            foreach (self::$http_response_headers as $key => $header) {
                hd_debug_print("$key: $header");
            }
            hd_debug_print("---------   Response headers end  ---------");
        }

        if (self::$http_code < 200 || (self::$http_code >= 300 && self::$http_code != 301 && self::$http_code != 304)) {
            hd_debug_print("HTTP request failed (" . self::$http_code . ")");
            hd_debug_print("HTTP response " . $content);
            return false;
        }

        if (self::$error_no !== 0) {
            hd_debug_print(sprintf("CURL errno: %s (%s; HTTP error: %s;", self::$error_no, self::$error_desc, self::$http_code));
            return false;
        }

        if ($cache_opts & self::USE_ETAG) {
            $new_etag = self::get_response_header('etag');
            if (!isset($etag) || $etag !== $new_etag) {
                hd_debug_print("Save new ETag ($new_etag) for: $url", true);
                self::set_cached_etag($url, $new_etag);
            }
        }

        if ($cache_opts & self::CACHE_RESPONSE && $save_file === null && !empty($content)) {
            $cache_path = $this->create_cache_path();
            $path = $cache_path . $hash;
            hd_debug_print("Save response to $path", true);
            file_put_contents($path, $content);
        }

        if (empty($save_file)) {
            hd_debug_print(sprintf("HTTP OK (%d) in %.3fs", self::$http_code, $execution_tm), true);
        } else {
            hd_debug_print(sprintf("HTTP OK (%d, %d bytes) in %.3fs", self::$http_code, filesize($save_file), $execution_tm), true);
        }

        return $save_file === null ? $content : true;
    }
}
