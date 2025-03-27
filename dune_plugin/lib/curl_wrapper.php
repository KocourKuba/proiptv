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
    const HTTP_HEADERS_LOG = "%s_headers%s.log";
    const HTTP_LOG = "%s_response%s.log";
    const CURL_CONFIG = "%s_curl_config%s.txt";
    const CACHE_TAG_FILE = "etag_cache.dat";

    /**
     * @var int
     */
    private $connect_timeout = 60;

    /**
     * @var int
     */
    private $download_timeout = 90;

    /**
     * @var int
     */
    private $response_code;

    /**
     * @var array
     */
    private $send_headers;

    /**
     * @var string
     */
    private $raw_response_headers;

    /**
     * @var string
     */
    private $post_data;

    /**
     * @var bool
     */
    private $is_post = false;

    public function __construct()
    {
        $this->init();
    }

    public function init()
    {
        hd_debug_print(null, true);
        $this->send_headers = array();
        $this->is_post = false;
        $this->post_data = null;
        $this->response_code = 0;
    }

    /**
     * download file to selected path
     *
     * @param string $url
     * @param string $save_file path to file
     * @param bool $use_cache use ETag caching
     * @return bool result of operation
     */
    public function download_file($url, $save_file, $use_cache)
    {
        hd_debug_print(null, true);

        if (!$this->exec_curl($url, $save_file, $use_cache)) {
            hd_debug_print("Can't download to $save_file");
            return false;
        }

        if ($use_cache) {
            $etag = $this->get_etag_header();
            if (!empty($etag)) {
                self::set_cached_etag($url, $etag);
            }
        }
        return true;
    }

    /**
     * download and return contents
     *
     * @param string $url
     * @return string|bool content of the downloaded file or result of operation
     */
    public function download_content($url)
    {
        hd_debug_print(null, true);

        $save_file = get_temp_path(self::get_url_hash($url));
        if ($this->exec_curl($url, $save_file)) {
            if (!file_exists($save_file)) {
                hd_debug_print("Can't download to $save_file");
                return false;
            }

            $content = file_get_contents($save_file);
            unlink($save_file);
            return $content;
        }

        return false;
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
        $headers = explode("\r\n", $this->raw_response_headers);
        $response_headers = array();
        foreach ($headers as $line) {
            if (empty($line)) continue;

            hd_debug_print($line, true);
            if (preg_match("/^(.*):(.*)$/", $line, $m)) {
                $response_headers[strtolower($m[1])] = trim($m[2]);
            }
        }

        return $response_headers;
    }

    /**
     * @return string
     */
    public function get_raw_response_headers()
    {
        return $this->raw_response_headers;
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
            if ($this->exec_curl($url, null, true)) {
                $code = $this->get_response_code();
                hd_debug_print("http code: $code", true);
                return !($code === 304 || ($code === 200 && $this->get_etag_header() === $etag));
            }
        }

        return true;
    }

    /**
     * @return int
     */
    public function get_response_code()
    {
        return $this->response_code;
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
     * download file to selected path
     *
     * @param string $url url
     * @param string $save_file path to file
     * @param bool $use_cache use ETag caching
     * @return array result of operation (first bool, second string)
     */
    public static function simple_download_file($url, $save_file, $use_cache = false)
    {
        hd_debug_print(null, true);
        $wrapper = new self();
        return array($wrapper->download_file($url, $save_file, $use_cache), $wrapper->get_raw_response_headers());
    }

    /**
     * download and return contents
     *
     * @param string $url url
     * @return string|bool content of the downloaded file or result of operation
     */
    public static function simple_download_content($url)
    {
        hd_debug_print(null, true);

        $wrapper = new self();
        return $wrapper->download_content($url);
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
        return safe_get_value(self::load_cached_etags(), self::get_url_hash($url), '');
    }

    /**
     * @param string $url
     * @param string $etag
     * @return void
     */
    public static function set_cached_etag($url, $etag)
    {
        hd_debug_print(null, true);
        $cache_db = self::load_cached_etags();
        $cache_db[self::get_url_hash($url)] = $etag;
        self::save_cached_etag($cache_db);
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

    /////////////////////////////////////////////////////////////
    /// private functions

    /**
     * @param string $url
     * @param string $save_file
     * @param bool $use_cache
     * @return bool
     */
    private function exec_curl($url, $save_file, $use_cache = false)
    {
        $this->response_code = 0;

        $url_hash = self::get_url_hash($url);
        $headers_path = get_temp_path(sprintf(self::HTTP_HEADERS_LOG, $url_hash, ''));

        $config_data[] = "--insecure";
        $config_data[] = "--silent";
        $config_data[] = "--show-error";
        $config_data[] = "--fail";
        $config_data[] = "--dump-header " . $headers_path;
        $config_data[] = "--connect-timeout $this->connect_timeout";
        $config_data[] = "--max-time $this->download_timeout";
        $config_data[] = "--location";
        $config_data[] = "--max-redirs 5";
        $config_data[] = "--compressed";
        $config_data[] = "--parallel";
        $config_data[] = "--write-out \"RESPONSE_CODE: %{response_code}\"";
        $config_data[] = "--user-agent \"" . HD::get_dune_user_agent() . "\"";
        $config_data[] = "--url \"$url\"";

        if ($use_cache) {
            $etag = self::get_cached_etag($url);
            if (!empty($etag)) {
                $header = "If-None-Match: " . str_replace('"', '\"', $etag);
                $config_data[] = "--header \"$header\"";
            }
        }

        if (is_null($save_file)) {
            $config_data[] = "--output /dev/null";
        } else {
            $config_data[] = "--output \"$save_file\"";
        }

        foreach ($this->send_headers as $header) {
            $config_data[] = "--header \"$header\"";
        }

        if ($this->is_post) {
            $config_data[] = "--request POST";
        } else {
            $config_data[] = "--request GET";
        }

        if (!empty($this->post_data)) {
            $config_data[] = "--data \"$this->post_data\"";
        }

        if (LogSeverity::$is_debug) {
            hd_debug_print("Curl config:");
            foreach ($config_data as $line) {
                hd_debug_print($line);
            }
        }

        $config_file = get_temp_path(sprintf(self::CURL_CONFIG, $url_hash, ''));
        file_put_contents($config_file, implode(PHP_EOL, $config_data));

        $url_hash = self::get_url_hash($url);
        $log_file = get_temp_path(sprintf(self::HTTP_LOG, $url_hash, ''));

        if (file_exists($log_file)) {
            unlink($log_file);
        }

        $cmd = get_platform_curl() . " --config $config_file >>$log_file";
        hd_debug_print("Exec: $cmd", true);
        $result = shell_exec($cmd);
        if ($result === false) {
            hd_debug_print("Problem with exec curl");
            return false;
        }

        if (file_exists($log_file)) {
            $log_content = file_get_contents($log_file);
            $pos = strrpos($log_content, "RESPONSE_CODE:");
            if ($pos !== false) {
                $this->response_code = (int)trim(substr($log_content, $pos + strlen("RESPONSE_CODE:")));
                hd_debug_print("Response code: $this->response_code from $log_file", true);
            }
            if (!LogSeverity::$is_debug) {
                unlink($log_file);
            }
        } else {
            $log_content = "No http_proxy log! Exec result code: $result";
            hd_debug_print($log_content);
        }

        if (!LogSeverity::$is_debug) {
            unlink($config_file);
        }

        if (file_exists($headers_path)) {
            $this->raw_response_headers = file_get_contents($headers_path);
            if (!empty($this->raw_response_headers)) {
                if (LogSeverity::$is_debug) {
                    hd_debug_print("---------  Read response headers ---------");
                }
                if (LogSeverity::$is_debug) {
                    hd_debug_print("---------     Read finished    ---------");
                }
            }
            if (!LogSeverity::$is_debug) {
                unlink($headers_path);
            }
        }

        return true;
    }
}
