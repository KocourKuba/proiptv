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
require_once 'sql_wrapper.php';

class Curl_Wrapper
{
    const CACHE_TAG_FILE = "etags.db";

    const RET_RAW = 0;
    const RET_ARRAY = 1;
    const RET_OBJECT = 2;

    const USE_ETAG = 1;
    const CACHE_RESPONSE = 2;

    const UPLOAD = -1;
    const HEADERS_ONLY = 0;
    const GET_CONTENT = 1;
    const SAVE_FILE = 2;

    /**
     * @var int
     */
    private $connect_timeout = 30;

    /**
     * @var int
     */
    private $download_timeout = 90;

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
     * @var string
     */
    private $file_cache_time = 4;

    /**
     * @var string
     */
    private $file_cache_path;

    /**
     * @var int
     */
    private static $http_code;

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
     * @var Sql_Wrapper
     */
    private static $etag_db;

    /**
     * @param string $cache_subdir
     */
    protected function __construct($cache_subdir = 'common')
    {
        self::init_etag_db();
        $this->set_cache_path($cache_subdir);
        $this->reset();
    }

    public static function getInstance($cache_subdir = 'common')
    {
        return new self($cache_subdir);
    }

    public function set_cache_path($cache_subdir)
    {
        $this->file_cache_path = get_slash_trailed_path(get_data_path(CURL_CACHE_SUBDIR . '/' . $cache_subdir));
        create_path($this->file_cache_path);
    }

    /**
     * @return void
     */
    public function reset()
    {
        self::$http_response_headers = null;
        self::$error_no = 0;
        self::$error_desc = '';
        self::$http_code = 0;
        $this->send_headers = array();
        $this->is_post = false;
        $this->post_data = null;
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
     * @param int $decode_opt
     * @param int $cache_opts options
     * @return bool|string|array|object content of the downloaded file or result of operation or decoded json response
     */
    public function download_content($url, $decode_opt = self::RET_RAW, $cache_opts = 0)
    {
        hd_debug_print(null, true);

        $res = $this->exec_php_curl($url, null, $cache_opts);

        if ($decode_opt === self::RET_RAW) {
            hd_debug_print('Returns RAW response', true);
            return $res;
        }

        $contents = json_decode($res, $decode_opt === self::RET_ARRAY);
        if ($contents === false) {
            hd_debug_print('failed to decode json');
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
        return safe_get_value(self::$http_response_headers, $header, '');
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
    public function set_post_data($data, $is_post = true)
    {
        $this->is_post = $is_post;
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
        $headers = array();
        foreach (self::$http_response_headers as $key => $header) {
            $headers[] = "$key: $header";
        }

        return empty($headers) ? '' : implode(PHP_EOL, $headers);
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
     * @param int $time in hours
     */
    public function set_file_cache_time($time)
    {
        $this->file_cache_time = $time;
    }

    /**
     * @return  int $time in hours
     */
    public function get_file_cache_time()
    {
        return $this->file_cache_time;
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
    public function clear_cache($all = false)
    {
        if ($all) {
            $path = get_slash_trailed_path(get_data_path(CURL_CACHE_SUBDIR));
            delete_directory($path);
            create_path($path);
        } else {
            $path = $this->file_cache_path;
            delete_directory($path);
        }

        hd_debug_print("Clear query cache: $path");
    }

    /////////////////////////////////////////////////////////////
    /// static functions

    public static function init_etag_db()
    {
        if (self::$etag_db === null) {
            create_path(get_data_path(CURL_CACHE_SUBDIR));
            self::$etag_db = new Sql_Wrapper(get_data_path(CURL_CACHE_SUBDIR . '/' . self::CACHE_TAG_FILE));
            self::$etag_db->exec('CREATE TABLE IF NOT EXISTS etags (hash TEXT PRIMARY KEY, etag TEXT);');
            $old_etags = get_data_path('etag_cache.dat');
            if (file_exists($old_etags)) {
                unlink($old_etags);
            }
        }
    }
    /**
     * @param string $url
     */
    public static function get_url_hash($url)
    {
        return hash('md5', $url);
    }

    /**
     * @param string $url
     * @param bool $by_hash
     * @return string
     */
    public static function get_cached_etag($url, $by_hash = false)
    {
        self::init_etag_db();
        $hash = $by_hash ? $url : self::get_url_hash($url);
        if (empty($hash)) {
            return '';
        }
        return self::$etag_db->query_value(sprintf('SELECT etag from etags WHERE hash=%s;', Sql_Wrapper::sql_quote($hash)));
    }

    /**
     * @param string $url
     * @param string $etag
     * @return void
     */
    public static function set_cached_etag($url, $etag)
    {
        self::init_etag_db();
        if (!empty($url) && !empty($etag)) {
            $query = sprintf('INSERT OR REPLACE INTO etags (hash, etag) VALUES(%s, %s);',
                Sql_Wrapper::sql_quote(self::get_url_hash($url)), Sql_Wrapper::sql_quote($etag));
            self::$etag_db->exec($query);
        }
    }

    /**
     * @param string $url
     * @param bool $by_hash
     * @return void
     */
    public static function clear_cached_etag($url, $by_hash = false)
    {
        self::init_etag_db();
        if (!empty($url)) {
            $hash = $by_hash ? $url : self::get_url_hash($url);
            $query = sprintf('DELETE FROM etags WHERE hash=%s;', Sql_Wrapper::sql_quote($hash));
            self::$etag_db->exec($query);
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

    /////////////////////////////////////////////////////////////
    /// private functions

    /**
     * if $save_file == null return content of request
     * if $save_file == false return only result of request i.e. make HEAD request
     * return false in case of error
     *
     * @param string $url
     * @param string|null|bool $save_file
     * @param int $cache_opts
     * @return bool|string
     */
    private function exec_php_curl($url, $save_file, $cache_opts = 0)
    {
        if ($save_file === false) {
            hd_debug_print('exec_php_curl: request only headers', true);
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

        if (isset($opts[CURLOPT_INFILE]) || isset($opts[CURLOPT_INFILESIZE])) {
            $opts[CURLOPT_PUT] = 1;
            $mode = self::UPLOAD;
        } else if ($save_file === false) {
            $opts[CURLOPT_NOBODY] = 1;
            $mode = self::HEADERS_ONLY;
        } else if ($save_file !== null){
            hd_debug_print("Save to file: '$save_file'", true);
            $mode = self::SAVE_FILE;
        } else {
            $mode = self::GET_CONTENT;
        }

        $opts[CURLOPT_HTTPHEADER][] = "Accept: */*";
        $opts[CURLOPT_HTTPHEADER][] = "Pragma: no-cache";
        $parsed_url = parse_url($url);
        if (isset($parsed_url['host'])) {
            $opts[CURLOPT_HTTPHEADER][] = "Host: {$parsed_url['host']}";
        }

        if (!empty($this->send_headers)) {
            $opts[CURLOPT_HTTPHEADER] = array_values(safe_merge_array($opts[CURLOPT_HTTPHEADER], $this->send_headers));
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

        $cached_path = $this->file_cache_path . $hash;
        create_path($cached_path);
        if ($cache_opts & self::CACHE_RESPONSE) {
            hd_debug_print("cache opts: Use cache response. Cache time: {$this->file_cache_time}h", true);
            if (file_exists($cached_path)) {
                $now = time();
                $mtime = filemtime($cached_path);
                $cache_expired_in = $mtime + $this->file_cache_time * 3600;
                hd_debug_print('Cache expiration time: ' . format_datetime('Y-m-d H:i', $cache_expired_in), true);
                if ($now < $cache_expired_in) {
                    hd_debug_print("Response read from cache $cached_path", true);
                    return file_get_contents($cached_path);
                }
                hd_debug_print("Cache expired: $cached_path", true);
                if (!($cache_opts & self::USE_ETAG)) {
                    // if etag not used - remove cached copy
                    unlink($cached_path);
                }
            }
        }

        $etag = '';
        if ($cache_opts & self::USE_ETAG) {
            hd_debug_print('cache opts: Use ETag capability', true);
            if (!file_exists($cached_path)) {
                hd_debug_print('Cached copy not exist!', true);
            } else {
                $etag = self::get_cached_etag($url);
                if (!empty($etag)) {
                    $opts[CURLOPT_HTTPHEADER][] = "If-None-Match: $etag";
                }
            }
        }

        if ($mode === self::SAVE_FILE) {
            $tmp_file = tempnam(pathinfo($save_file, PATHINFO_DIRNAME), 'curl_');
            $fp = fopen($tmp_file, "w+");
            if (is_null($fp)) {
                hd_debug_print("Unable to open temp file: $tmp_file!");
                return false;
            }
            $opts[CURLOPT_FILE] = $fp;
        } else {
            $fp = null;
            $tmp_file = '';
        }

        $ch = curl_init();

        foreach ($opts as $k => $v) {
            if (LogSeverity::$is_debug) {
                if (is_bool($v)) {
                    hd_debug_print(curlopt_to_string($k) . " = " . var_export($v, true));
                } else if (is_array($v)) {
                    hd_debug_print(curlopt_to_string($k) . " = " . json_format_unescaped($v));
                } else {
                    hd_debug_print(curlopt_to_string($k) . " = $v");
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

        if ($mode === self::SAVE_FILE) {
            fclose($fp);
            if (file_exists($tmp_file)) {
                if (filesize($tmp_file) > 0) {
                    if (file_exists($save_file)) {
                        unlink($save_file);
                    }
                    rename($tmp_file, $save_file);
                } else {
                    unlink($tmp_file);
                }
                clearstatcache();
            }
        }

        if (!empty(self::$http_response_headers) && LogSeverity::$is_debug) {
            hd_debug_print('---------  Response headers start ---------');
            foreach (self::$http_response_headers as $key => $header) {
                hd_debug_print("$key: $header");
            }
            hd_debug_print('---------   Response headers end  ---------');
        }

        if (self::$http_code < 200 || (self::$http_code >= 300 && self::$http_code != 301 && self::$http_code != 304)) {
            hd_debug_print('HTTP request failed (' . self::$http_code . ')');
            hd_debug_print('HTTP response: ' . $content);
            return false;
        }

        if (self::$error_no !== 0) {
            hd_debug_print(sprintf('CURL errno: %s (%s); HTTP error: %s', self::$error_no, self::$error_desc, self::$http_code));
            return false;
        }

        hd_debug_print(sprintf('HTTP code: %d time: %.3fs', self::$http_code, $execution_tm), true);

        if ($cache_opts & self::USE_ETAG) {
            $new_etag = self::get_response_header('etag');
            if (!empty($new_etag)) {
                if ($etag !== $new_etag) {
                    hd_debug_print("Save new ETag ($new_etag) for: $url", true);
                    self::set_cached_etag($url, $new_etag);
                } else {
                    $content = file_get_contents($cached_path);
                }
            }
        }

        if (($cache_opts & self::CACHE_RESPONSE) && $save_file === null && !empty($content)) {
            hd_debug_print("Save response to $cached_path", true);
            file_put_contents($cached_path, $content);
        }

        if ($mode === self::HEADERS_ONLY) {
            return true;
        }

        if ($mode === self::GET_CONTENT) {
            hd_debug_print(sprintf('Content size: %d', strlen($content)), true);
            return $content;
        }

        if ($mode === self::SAVE_FILE) {
            if (!file_exists($save_file)) {
                hd_debug_print("Download file '$save_file' not exist!");
                return false;
            }

            hd_debug_print(sprintf('File size: %d bytes', filesize($save_file)), true);
        }

        if ($mode === self::UPLOAD) {
            hd_debug_print('Upload done', true);
        }

        return true;
    }
}
