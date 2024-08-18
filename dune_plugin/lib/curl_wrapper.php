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
    const HTTP_HEADERS_LOG = "headers_%s.log";
    const HTTP_LOG = "response_%s.log";

    /**
     * @var string
     */
    private $url;

    /**
     * @var string
     */
    private $url_hash;

    /**
     * @var string
     */
    private $headers_path;

    /**
     * @var string
     */
    private $cache_path;

    /**
     * @var int
    */
    private $response_code;

    /**
     * @var array
     */
    private $send_headers;

    /**
     * @var array
     */
    private $response_headers;

    /**
     * @var string
     */
    private $post_data;

    /**
     * @var array
     */
    private $cache_db;

    /**
     * @var string
     */
    private $logfile;

    /**
     * @var string
     */
    private $config_file;

    /**
     * @var bool
     */
    private $is_post = false;

    public function __construct()
    {
        $path = get_data_path('curl_cache');
        create_path($path);
        $this->cache_path = $path . DIRECTORY_SEPARATOR . 'cache.dat';
        $this->config_file = get_temp_path("curl_config.txt");
        if (file_exists($this->cache_path)) {
            $this->cache_db = unserialize(file_get_contents($this->cache_path));
        } else {
            $this->cache_db = array();
        }
    }

    /**
     * @return int
     */
    public function get_response_code()
    {
        return $this->response_code;
    }

    /**
     * @param $value
     */
    public function set_post($value = true)
    {
        $this->is_post = $value;
    }

    /**
     * @param string $url
     */
    public function init($url)
    {
        $this->url = $url;
        $this->url_hash = hash('crc32', $url);
        $this->headers_path = get_temp_path(sprintf(self::HTTP_HEADERS_LOG, $this->url_hash));
        $this->logfile = get_temp_path(sprintf(self::HTTP_LOG, $this->url_hash));
        $this->send_headers = array();
        $this->response_headers = array();
        $this->is_post = false;
        $this->post_data = null;
        $this->response_code = 0;
    }

    /**
     * @return bool
     */
    public function is_cached()
    {
        return isset($this->cache_db[$this->url_hash]) && !empty($this->cache_db[$this->url_hash]);
    }

    /**
     * @return string
     */
    public function get_cached_etag()
    {
        return isset($this->cache_db[$this->url_hash]) ? $this->cache_db[$this->url_hash] : '';
    }

    /**
     * @return void
     */
    public function set_cached_etag($etag)
    {
        return $this->cache_db[$this->url_hash] = $etag;
    }

    /**
     * @return void
     */
    public function clear_cached_etag($url)
    {
        $hash = hash('crc32', $url);
        unset($this->cache_db[$hash]);
    }

    /**
     * @return void
     */
    public function clear_all_cache()
    {
        $this->cache_db = array();
        if (file_exists($this->cache_path)) {
            unlink($this->cache_path);
        }
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
        return $this->response_headers;
    }

    /**
     * @return string
     */
    public function get_response_headers_string()
    {
        $str = '';
        foreach ($this->response_headers as $key => $value) {
            $str .= "$key: $value" . PHP_EOL;
        }

        return $str;
    }

    /**
     * @return string
     */
    public function get_response_header($header)
    {
        return isset($this->response_headers[$header]) ? $this->response_headers[$header] : '';
    }

    /**
     * @return string
     */
    public function get_etag_header()
    {
        return isset($this->response_headers['ETag']) ? $this->response_headers['ETag'] : '';
    }

    /**
     * download file to selected path
     *
     * @param string $save_file path to file
     * @param bool $use_cache use ETag caching
     * @return bool result of operation
     */
    public function download_file($save_file, $use_cache)
    {
        hd_debug_print(null, true);

        $this->create_curl_config($save_file, $use_cache);
        if (!$this->exec_curl()) {
            hd_debug_print("Can't download to $save_file");
            return false;
        }

        if ($use_cache) {
            $etag = $this->get_etag_header();
            if (!empty($etag)) {
                $this->set_cached_etag($etag);
                file_put_contents($this->cache_path, serialize($this->cache_db));
            }
        }
        return true;
    }

    /**
     * download and return contents
     *
     * @return string|bool content of the downloaded file or result of operation
     */
    public function download_content()
    {
        hd_debug_print(null, true);

        if (file_exists($this->logfile)) {
            unlink($this->logfile);
        }

        $save_file = get_temp_path($this->url_hash);
        $this->create_curl_config($save_file, false);
        if ($this->exec_curl()) {
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
     * download file to selected path
     *
     * @param string $url url
     * @param string $save_file path to file
     * @param bool $use_cache use ETag caching
     * @return bool result of operation
     */
    public static function simple_download_file($url, $save_file, $use_cache)
    {
        hd_debug_print(null, true);
        $wrapper = new Curl_Wrapper();
        $wrapper->init($url);
        return $wrapper->download_file($save_file, $use_cache);
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
        $wrapper = new Curl_Wrapper();
        $wrapper->init($url);
        return $wrapper->download_content();
    }

    /**
     * Check if cached url is expired
     *
     * @return bool result of operation
     */
    public function check_is_expired()
    {
        hd_debug_print(null, true);

        $etag = $this->get_cached_etag();
        if (!empty($etag)) {
            $this->create_curl_config(null, true);
            if ($this->exec_curl()) {
                $code = $this->get_response_code();
                return !($code === 304 || ($code === 200 && $this->get_etag_header() === $this->get_cached_etag()));
            }
        }

        return true;
    }

    /**
     * Create curl config
     *
     * @param string $save_file
     * @param bool $use_cache
     */
    private function create_curl_config($save_file, $use_cache)
    {
        $config_data[] = "--insecure";
        $config_data[] = "--silent";
        $config_data[] = "--show-error";
        $config_data[] = "--dump-header " . $this->headers_path;
        $config_data[] = "--connect-timeout 30";
        $config_data[] = "--max-time 90";
        $config_data[] = "--location";
        $config_data[] = "--max-redirs 5";
        $config_data[] = "--compressed";
        $config_data[] = "--parallel";
        $config_data[] = "--write-out \"RESPONSE_CODE: %{response_code}\"";
        $config_data[] = "--user-agent \"" . HD::get_dune_user_agent() . "\"";
        $config_data[] = "--url \"$this->url\"";

        if (is_null($save_file)) {
            $config_data[] = "--head";
        } else {
            $config_data[] = "--output \"$save_file\"";
        }

        if (!$this->is_post) {
            $config_data[] = "--request GET";
        } else {
            $config_data[] = "--request POST";
            if (!empty($this->post_data)) {
                $config_data[] = "--data \"$this->post_data\"";
            }
        }

        foreach ($this->send_headers as $header) {
            $config_data[] = "--header \"$header\"";
        }

        if ($use_cache) {
            $etag = $this->get_cached_etag();
            if (!empty($etag)) {
                $header = "If-None-Match: " . str_replace('"', '\"', $etag);
                $config_data[] = "--header \"$header\"";
            }
        }

        if (LogSeverity::$is_debug) {
            hd_debug_print("Curl config:");
            foreach ($config_data as $line) {
                hd_debug_print($line);
            }
        }

        file_put_contents($this->config_file, implode(PHP_EOL, $config_data));
    }

    private function exec_curl()
    {
        $this->response_code = 0;

        if (file_exists($this->logfile)) {
            unlink($this->logfile);
        }

        $cmd = get_install_path('bin/https_proxy.sh') . " " . get_platform_curl() . " '$this->config_file' '$this->logfile'";
        hd_debug_print("Exec: $cmd", true);
        $result = shell_exec($cmd);
        if ($result === false) {
            hd_debug_print("Problem with exec https_proxy script");
            return false;
        }

        if (!file_exists($this->logfile)) {
            $log_content = "No http_proxy log! Exec result code: $result";
            hd_debug_print($log_content);
        } else {
            $log_content = file_get_contents($this->logfile);
            $pos = strpos($log_content, "RESPONSE_CODE:");
            if ($pos !== false) {
                $this->response_code = (int)trim(substr($log_content, $pos + strlen("RESPONSE_CODE:")));
                hd_debug_print("Response code: $this->response_code", true);
            }
        }

        if (file_exists($this->headers_path)) {
            $headers_content = file($this->headers_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($headers_content !== false) {
                if (LogSeverity::$is_debug) {
                    hd_debug_print("---------  Read response headers ---------");
                }

                $this->response_headers = array();
                foreach ($headers_content as $line) {
                    hd_debug_print($line, true);
                    if (preg_match("/^(.*):(.*)$/", $line, $m)) {
                        $this->response_headers[$m[1]] = trim($m[2]);
                    }
                }

                if (LogSeverity::$is_debug) {
                    hd_debug_print("---------     Read finished    ---------");
                }
            }
        }

        return true;
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
            return  false;
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
}
