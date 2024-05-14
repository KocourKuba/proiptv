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

require_once 'dune_stb_api.php';
require_once 'dune_plugin_constants.php';

class HD
{
    /**
     * @var bool
     */
    private static $with_rows_api;

    /**
     * @var string
     */
    private static $default_user_agent;

    private static $plugin_user_agent;

    private static $token = '05ba6358d39c4f298f43024b654b7387';
    const DUNE_PARAMS_MAGIC = "|||dune_params|||";

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param int $size
     * @return string
     */
    public static function get_filesize_str($size)
    {
        if ($size < 1024) {
            $size_num = $size;
            $size_suf = "B";
        } else if ($size < 1048576) { // 1M
            $size_num = round($size / 1024, 2);
            $size_suf = "KiB";
        } else if ($size < 1073741824) { // 1G
            $size_num = round($size / 1048576, 2);
            $size_suf = "MiB";
        } else {
            $size_num = round($size / 1073741824, 2);
            $size_suf = "GiB";
        }
        return "$size_num $size_suf";
    }

    /**
     * @return bool
     */
    public static function rows_api_support()
    {
        if (!isset(self::$with_rows_api))
            self::$with_rows_api = class_exists("PluginRowsFolderView");

        return self::$with_rows_api;
    }

    /**
     * @param string $path
     * @param array|null $arg
     * @return array|string
     */
    public static function get_storage_size($path, $arg = null)
    {
        $d[0] = disk_free_space($path);
        $d[1] = disk_total_space($path);
        foreach ($d as $bytes) {
            $si_prefix = array('B', 'KB', 'MB', 'GB', 'TB', 'EB', 'ZB', 'YB');
            $base = 1024;
            $class = min((int)log($bytes, $base), count($si_prefix) - 1);
            $size[] = sprintf('%1.2f', $bytes / pow($base, $class)) . ' ' . $si_prefix[$class];
        }

        if ($arg !== null) {
            $arr['str'] = $size[0] . '/' . $size[1];
            $arr['free_space'] = ($arg < $d[0]);
            return $arr;
        }
        return $size[0] . ' (' . $size[1] . ')';
    }

    ///////////////////////////////////////////////////////////////////////

    public static function print_array($opts, $ident = 0)
    {
        if (is_array($opts)) {
            foreach ($opts as $k => $v) {
                if (is_array($v)) {
                    hd_debug_print(str_repeat(' ', $ident) . "$k : array");
                    self::print_array($v, $ident + 4);
                } else {
                    hd_debug_print(str_repeat(' ', $ident) . "$k : $v");
                }
            }
        } else {
            hd_debug_print(str_repeat(' ', $ident) . $opts);
        }
    }

    public static function http_local_port()
    {
        $port = getenv("HD_HTTP_LOCAL_PORT");
        return $port ? (int) $port : 80;
    }

    public static function http_init()
    {
        if (!empty(self::$default_user_agent))
            return;

        self::$default_user_agent = "DuneHD/1.0";

        $extra_useragent = "";
        $sysinfo = file("/tmp/sysinfo.txt", FILE_IGNORE_NEW_LINES);
        if ($sysinfo !== false) {
            foreach ($sysinfo as $line) {
                if (preg_match("/product_id:/", $line) ||
                    preg_match("/firmware_version:/", $line)) {
                    $line = trim($line);

                    if (empty($extra_useragent))
                        $extra_useragent = " (";
                    else
                        $extra_useragent .= "; ";

                    $extra_useragent .= $line;
                }
            }

            if (!empty($extra_useragent))
                $extra_useragent .= ")";
        }

        self::$default_user_agent .= $extra_useragent;

        hd_debug_print("HTTP UserAgent: " . self::$default_user_agent);
    }

    public static function get_default_user_agent()
    {
        if (empty(self::$default_user_agent))
            self::http_init();

        return self::$default_user_agent;
    }

    public static function get_dune_user_agent()
    {
        if (empty(self::$default_user_agent))
            self::http_init();

        return (empty(self::$plugin_user_agent) || self::$default_user_agent === self::$plugin_user_agent) ? self::$default_user_agent : self::$plugin_user_agent;
    }

    public static function set_dune_user_agent($user_agent)
    {
        self::$plugin_user_agent = $user_agent;
    }

    /**
     * @param $url string
     * @param $opts array
     * @param $info array
     * @return bool|string
     * @throws Exception
     */
    public static function http_get_document($url, $opts = null, &$info = array())
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FILETIME, true);
        curl_setopt($ch, CURLOPT_USERAGENT, self::get_dune_user_agent());
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
        curl_setopt($ch, CURLOPT_URL, $url);

        if (isset($opts)) {
            //self::dump_curl_opts($opts);
            foreach ($opts as $k => $v) {
                curl_setopt($ch, $k, $v);
            }
        }

        hd_debug_print("HTTP fetching '$url'");

        $content = curl_exec($ch);
        $info = curl_getinfo($ch);
        $http_code = $info['http_code'];

        if ($content === false) {
            $err_msg = "Fetch $url failed. HTTP error: $http_code (" . curl_error($ch) . ')';
            hd_debug_print($err_msg);
            throw new Exception($err_msg);
        }

        if ($http_code >= 300) {
            $err_msg = "Fetch $url failed. HTTP request failed ($http_code): " . self::http_status_code_to_string($http_code);
            hd_debug_print($err_msg);
            throw new Exception($err_msg);
        }

        curl_close($ch);

        return $content;
    }

    /**
     * @param $url string
     * @param $file_name string
     * @param $opts array
     * @return array
     * @throws Exception
     */
    public static function http_save_document($url, $file_name, $opts = null, &$info = array())
    {
        $timestamp = file_exists($file_name) ? filemtime($file_name) : -1;

        $tmp_file = get_temp_path(Hashed_Array::hash($file_name));
        $fp = fopen($tmp_file, 'wb');

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        curl_setopt($ch, CURLOPT_USERAGENT, self::get_dune_user_agent());
        curl_setopt($ch, CURLOPT_FILETIME, true);
        curl_setopt($ch, CURLOPT_TIMECONDITION, CURL_TIMECOND_IFMODSINCE);
        curl_setopt($ch, CURLOPT_TIMEVALUE, $timestamp);
        curl_setopt($ch, CURLOPT_FILE, $fp);

        if (isset($opts)) {
            //self::dump_curl_opts($opts);
            foreach ($opts as $k => $v) {
                curl_setopt($ch, $k, $v);
            }
        }

        hd_debug_print("HTTP fetching '$url'");

        try {
            $result = curl_exec($ch);
            if ($result === false) {
                throw new Exception($url . PHP_EOL . "curl_exec error: " . curl_error($ch));
            }

            $info = curl_getinfo($ch);
            $http_code = $info['http_code'];

            if ($http_code !== 200 && $http_code !== 304) {
                throw new Exception($url . PHP_EOL . "HTTP request failed ({$info['http_code']}): " . self::http_status_code_to_string($info['http_code']));
            }

            fclose($fp);
            curl_close($ch);

            if ($http_code === 304) {
                hd_debug_print("file not changed", true);
            } else {
                copy($tmp_file, $file_name);
                touch($file_name, $info['filetime']);
            }
            unlink($tmp_file);
        } catch (Exception $ex) {
            fclose($fp);
            curl_close($ch);
            unlink($tmp_file);
            flush();
            throw $ex;
        }

        return $info;
    }

    /**
     * download and return contents
     * if $save_file is null return content
     * otherwise save content to $save_file and return true or false
     *
     * @param string $url
     * @param string|null $save_file
     * @param array|null $curl_headers
     * @return string|bool content of the downloaded file or result of operation
     */
    public static function http_download_https_proxy($url, $save_file = null, $curl_headers = null)
    {
        $ret_content = empty($save_file);

        $logfile = get_temp_path("https_proxy.log");
        if (file_exists($logfile)) {
            unlink($logfile);
        }

        $config_file = get_temp_path('curl_confg.txt');
        if (file_exists($config_file)) {
            unlink($config_file);
        }

        if (empty($save_file)) {
            $save_file = get_temp_path(Hashed_Array::hash($url));
        }

        $config_data  = "--insecure" . PHP_EOL;
        $config_data .= "--silent" . PHP_EOL;
        $config_data .= "--dump-header -" . PHP_EOL;
        $config_data .= "--connect-timeout 30" . PHP_EOL;
        $config_data .= "--max-time 30" . PHP_EOL;
        //$config_data .= "--retry 1" . PHP_EOL;
        $config_data .= "--location" . PHP_EOL;
        $config_data .= "--max-redirs 4" . PHP_EOL;
        $config_data .= '--user-agent "' . self::get_dune_user_agent() .'"' . PHP_EOL;
        $config_data .= '--url "' . $url . '"' . PHP_EOL;
        $config_data .= '--output "' . $save_file . '"' . PHP_EOL;
        if (!empty($curl_headers)) {
            foreach ($curl_headers as $header) {
                $config_data .= '--header "' . $header . '"' . PHP_EOL;
            }
        }
        file_put_contents($config_file, $config_data);

        $cmd = get_install_path('bin/https_proxy.sh') . " " . get_platform_curl() . " '$config_file' '$logfile'";
        hd_debug_print("Exec: $cmd", true);
        shell_exec($cmd);
        if (!file_exists($logfile)) {
            hd_debug_print("No http_proxy log!");
        } else {
            $log_content = @file_get_contents($logfile);
            if (LogSeverity::$is_debug && $log_content !== false) {
                hd_debug_print("Read http_proxy log...");
                foreach (explode("\n", $log_content) as $l) {
                    hd_debug_print(rtrim($l));
                }
                hd_debug_print("Read finished");
            }
        }

        if (!file_exists($save_file)) {
            hd_debug_print("Can't download to $save_file");
            return false;
        }

        if ($ret_content) {
            $content = file_get_contents($save_file);
            unlink($save_file);
            return $content;
        }

        return file_exists($save_file);
    }

    /**
     * @param bool $is_file
     * @param string $source contains data or file name
     * @param bool $assoc
     * @return mixed|false
     */
    public static function decodeResponse($is_file, $source, $assoc = false)
    {
        if ($is_file) {
            $data = file_get_contents($source);
        } else {
            $data = $source;
        }

        hd_debug_print("decode json");
        $contents = json_decode($data, $assoc);
        if ($contents !== null && $contents !== false) {
            return $contents;
        }

        hd_debug_print("failed to decode json");
        hd_debug_print("doc: $data", true);

        return false;
    }

    /**
     * @param int $code
     * @return string
     */
    public static function http_status_code_to_string($code)
    {
        // Source: http://en.wikipedia.org/wiki/List_of_HTTP_status_codes

        switch( $code ){
            // 1xx Informational
            case 100: $string = 'Continue'; break;
            case 101: $string = 'Switching Protocols'; break;
            case 102: $string = 'Processing'; break; // WebDAV
            case 122: $string = 'Request-URI too long'; break; // Microsoft

            // 2xx Success
            case 200: $string = 'OK'; break;
            case 201: $string = 'Created'; break;
            case 202: $string = 'Accepted'; break;
            case 203: $string = 'Non-Authoritative Information'; break; // HTTP/1.1
            case 204: $string = 'No Content'; break;
            case 205: $string = 'Reset Content'; break;
            case 206: $string = 'Partial Content'; break;
            case 207: $string = 'Multi-Status'; break; // WebDAV

            // 3xx Redirection
            case 300: $string = 'Multiple Choices'; break;
            case 301: $string = 'Moved Permanently'; break;
            case 302: $string = 'Found'; break;
            case 303: $string = 'See Other'; break; //HTTP/1.1
            case 304: $string = 'Not Modified'; break;
            case 305: $string = 'Use Proxy'; break; // HTTP/1.1
            case 306: $string = 'Switch Proxy'; break; // Depreciated
            case 307: $string = 'Temporary Redirect'; break; // HTTP/1.1

            // 4xx Client Error
            case 400: $string = 'Bad Request'; break;
            case 401: $string = 'Unauthorized'; break;
            case 402: $string = 'Payment Required'; break;
            case 403: $string = 'Forbidden'; break;
            case 404: $string = 'Not Found'; break;
            case 405: $string = 'Method Not Allowed'; break;
            case 406: $string = 'Not Acceptable'; break;
            case 407: $string = 'Proxy Authentication Required'; break;
            case 408: $string = 'Request Timeout'; break;
            case 409: $string = 'Conflict'; break;
            case 410: $string = 'Gone'; break;
            case 411: $string = 'Length Required'; break;
            case 412: $string = 'Precondition Failed'; break;
            case 413: $string = 'Request Entity Too Large'; break;
            case 414: $string = 'Request-URI Too Long'; break;
            case 415: $string = 'Unsupported Media Type'; break;
            case 416: $string = 'Requested Range Not Satisfiable'; break;
            case 417: $string = 'Expectation Failed'; break;
            case 422: $string = 'Unprocessable Entity'; break; // WebDAV
            case 423: $string = 'Locked'; break; // WebDAV
            case 424: $string = 'Failed Dependency'; break; // WebDAV
            case 425: $string = 'Unordered Collection'; break; // WebDAV
            case 426: $string = 'Upgrade Required'; break;
            case 449: $string = 'Retry With'; break; // Microsoft
            case 450: $string = 'Blocked'; break; // Microsoft

            // 5xx Server Error
            case 500: $string = 'Internal Server Error'; break;
            case 501: $string = 'Not Implemented'; break;
            case 502: $string = 'Bad Gateway'; break;
            case 503: $string = 'Service Unavailable'; break;
            case 504: $string = 'Gateway Timeout'; break;
            case 505: $string = 'HTTP Version Not Supported'; break;
            case 506: $string = 'Variant Also Negotiates'; break;
            case 507: $string = 'Insufficient Storage'; break; // WebDAV
            case 509: $string = 'Bandwidth Limit Exceeded'; break; // Apache
            case 510: $string = 'Not Extended'; break;

            // Unknown code:
            default: $string = 'Unknown'; break;
        }
        return $string;
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $url
     * @param resource $in_file
     * @param integer $in_file_size
     * @return bool|string
     * @throws Exception
     */
    public static function http_put_document($url, $in_file, $in_file_size)
    {
        return self::http_get_document($url,
            array(
                CURLOPT_PUT => true,
                CURLOPT_CUSTOMREQUEST => "PUT",
                CURLOPT_INFILE => $in_file,
                CURLOPT_INFILESIZE => $in_file_size,
                CURLOPT_HTTPHEADER => array("accept: */*", "Expect: 100-continue", "Content-Type: application/zip"),
            ));
    }

    ///////////////////////////////////////////////////////////////////////

    public static function send_log_to_developer($ver, &$error = null)
    {
        $serial = get_serial_number();
        if (empty($serial)) {
            hd_debug_print("Unable to get DUNE serial.");
            $serial = 'XX-XX-XX-XX-XX';
        }
        $ver = str_replace('.', '_', $ver);
        $timestamp = format_datetime('Ymd_His', time());
        $model = get_product_id();
        $zip_file_name = "proiptv_{$ver}_{$model}_{$serial}_$timestamp.zip";
        hd_debug_print("Prepare archive $zip_file_name for send");
        $zip_file = get_temp_path($zip_file_name);
        $apk_subst = getenv('FS_PREFIX');
        $plugin_name = get_plugin_name();

        $paths = array(
            get_data_path("*.settings"),
            get_temp_path("*.log"),
            get_temp_path("*.m3u8"),
            "$apk_subst/tmp/run/shell.*",
        );

        if (file_exists("$apk_subst/D/dune_plugin_logs/$plugin_name.log")) {
            $paths[] = "$apk_subst/D/dune_plugin_logs/$plugin_name.*";
        }
        if (file_exists("$apk_subst/tmp/mnt/D/dune_plugin_logs/$plugin_name.log")) {
            $paths[] = "$apk_subst/tmp/mnt/D/dune_plugin_logs/$plugin_name.*";
        }
        if (file_exists("$apk_subst/tmp/run/$plugin_name.log")) {
            $paths[] = "$apk_subst/tmp/run/$plugin_name.*";
        }

        $files = array();
        foreach ($paths as $path) {
            foreach (glob($path) as $file) {
                if (is_file($file) && filesize($file) > 0) {
                    $files[] = $file;
                }
            }
        }

        $handle = false;
        $ret = false;
        try {
            $zip = new ZipArchive();
            $zip->open($zip_file, ZipArchive::CREATE);
            foreach ($files as $key => $file) {
                $zip->addFile($file, "/$key." . basename($file));
            }
            $zip->close();

            $handle = fopen($zip_file, 'rb');
            if (is_resource($handle)) {
                self::http_put_document(base64_decode("aHR0cDovL2lwdHYuZXNhbGVjcm0ubmV0L3VwbG9hZC8", true) . $zip_file_name, $handle, filesize($zip_file));
                hd_debug_print("Log file sent");
                $ret = true;
            }
        } catch (Exception $ex) {
            $msg = ": Unable to upload log: " . $ex->getMessage();
            hd_debug_print($msg);
            if ($error !== null) {
                $error = $msg;
            }
        }

        if (is_resource($handle)) {
            @fclose($handle);
        }
        @unlink($zip_file);

        return $ret;
    }

    /**
     * @param string $doc
     * @return SimpleXMLElement
     * @throws Exception
     */
    public static function parse_xml_document($doc)
    {
        $xml = simplexml_load_string($doc);

        if ($xml === false) {
            hd_debug_print("Error: can not parse XML document.");
            hd_debug_print("XML-text: $doc.");
            throw new Exception('Illegal XML document');
        }

        return $xml;
    }

    /**
     * @param string $path
     * @return SimpleXMLElement
     * @throws Exception
     */
    public static function parse_xml_file($path)
    {
        $xml = simplexml_load_string(file_get_contents($path));

        if ($xml === false) {
            hd_debug_print("Error: can't parse XML document.");
            hd_debug_print("path to XML: $path");
            throw new Exception('Illegal XML document');
        }

        return $xml;
    }

    /**
     * @param string $path
     * @param bool $to_array
     * @return mixed
     */
    public static function parse_json_file($path, $to_array = false)
    {
        return json_decode(file_get_contents(
            $path,
            FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES),
            $to_array);
    }

    ///////////////////////////////////////////////////////////////////////

    /**
     * @param string $op_name
     * @param string $params
     * @return array
     */
    public static function make_json_rpc_request($op_name, $params)
    {
        static $request_id = 0;

        return array
        (
            'jsonrpc' => '2.0',
            'id' => ++$request_id,
            'method' => $op_name,
            'params' => $params
        );
    }

    public static function compress_file($source, $dest)
    {
        $data = file_get_contents($source);
        $gz_data = gzencode($data, -1);
        return file_put_contents($dest, $gz_data);
    }

    ///////////////////////////////////////////////////////////////////////////

    /**
     * @param string $raw_string
     * @return array|string|string[]
     */
    public static function unescape_entity_string($raw_string)
    {
        $replace = array(
            "&nbsp;" => ' ',
            '&#39;'  => "'",
            '&gt;'   => ">",
            '&lt;'   => "<'>",
            '&apos;' => "'",
            '&quot;' => '"',
            '&amp;'  => '&',
            '&#196;' => 'Г„',
            '&#228;' => 'Г¤',
            '&#214;' => 'Г–',
            '&#220;' => 'Гњ',
            '&#223;' => 'Гџ',
            '&#246;' => 'Г¶',
            '&#252;' => 'Гј',
            '&#257;' => 'ā',
            '&#258;' => 'Ă',
            '&#268;' => 'Č',
            '&#326;' => 'ņ',
            '&#327;' => 'Ň',
            '&#363;' => 'ū',
            '&#362;' => 'Ū',
            '&#352;' => 'Š',
            '&#353;' => 'š',
            '&#382;' => 'ž',
            '&#275;' => 'ē',
            '&#276;' => 'Ĕ',
            '&#298;' => 'Ī',
            '&#299;' => 'ī',
            '&#291;' => 'ģ',
            '&#311;' => 'ķ',
            '&#316;' => 'ļ',
            '<br>'   => PHP_EOL,
        );

        return str_replace(array_keys($replace), $replace, $raw_string);
    }

    /**
     * @param array $arrayItems
     * @return string
     */
    public static function ArrayToStr($arrayItems)
    {
        $array = array();
        foreach ($arrayItems as $item) {
            if (!empty($item)) {
                $array[] = $item;
            }
        }

        return implode(", ", $array);
    }

    /**
     * @param string $path
     * @param boolean $preserve_keys
     * @return array|mixed
     */
    public static function get_data_items($path, $preserve_keys = true, $json = true)
    {
        return self::get_items(get_data_path($path), $preserve_keys, $json);
    }

    /**
     * @param string $path
     * @param boolean $preserve_keys
     * @return array|mixed
     */
    public static function get_items($path, $preserve_keys = true, $json = true)
    {
        if (file_exists($path)) {
            $contents = file_get_contents($path);
            $items = $json ? json_decode($contents, true) : unserialize($contents);
            $items = is_null($items) ? array() : $items;
        } else {
            //hd_debug_print("$path not exist");
            $items = array();
        }

        return $preserve_keys ? $items : array_values($items);
    }

    /**
     * @param string $path
     * @param mixed $items
     */
    public static function put_data_items($path, $items, $json = true)
    {
        self::put_items(get_data_path($path), $items, $json);
    }

    /**
     * @param string $path
     * @param mixed $items
     */
    public static function put_items($path, $items, $json = true)
    {
        file_put_contents($path, $json ? json_encode($items) : serialize($items));
    }

    /**
     * @param string $path
     */
    public static function erase_data_items($path)
    {
        self::erase_items(get_data_path($path));
    }

    /**
     * @param string $path
     */
    public static function erase_items($path)
    {
        if (file_exists($path)) {
            hd_debug_print("$path deleted");
            unlink($path);
        } else {
            hd_debug_print("$path not exist");
        }
    }

    /**
     * @param string $path
     * @return false|string
     */
    public static function get_data_item($path)
    {
        $full_path = get_data_path($path);
        return file_exists($full_path) ? file_get_contents($full_path) : '';
    }

    /**
     * @param string $path
     * @param mixed $item
     */
    public static function put_data_item($path, $item)
    {
        file_put_contents(get_data_path($path), $item);
    }

    /**
     * @param string $url
     * @return string
     */
    public static function make_ts($url, $force = false)
    {
        if (!preg_match("|^https?://ts://|", $url)) {
            if (preg_match("/\.mp4(?=\?|$)/i", $url)) {
                $url = preg_replace(TS_REPL_PATTERN, "$1" . "mp4://$2", $url);
            } else if ($force || preg_match("/\.ts|\.mpeg|mpegts(?=\?|$)/i", $url)) {
                $url = preg_replace(TS_REPL_PATTERN, "$1ts://$2", $url);
            }
        }

        return $url;
    }

    public static function fix_double_scheme_url($url)
    {
        $pos = strpos($url, self::DUNE_PARAMS_MAGIC);
        if ($pos !== false && $pos > 0)
            $url = substr($url, 0, $pos);

        return preg_replace("#(https?://)((mp4|ts)://)#", '\1', $url);
    }

    /**
     * @param string $url
     * @param bool $to_array
     * @param array|null $opts
     * @return false|mixed
     */
    public static function DownloadJson($url, $to_array = true, $opts = null)
    {
        try {
            $doc = self::http_get_document($url, $opts);
            $contents = json_decode($doc, $to_array);
            if ($contents === null || $contents === false) {
                hd_debug_print("failed to decode json");
                hd_debug_print("doc: $doc", true);
                return false;
            }
        } catch (Exception $ex) {
            hd_debug_print("Unable to load url: " . $ex->getMessage());
            return false;
        }

        return $contents;
    }

    /**
     * @param $path string
     * @param $content mixed
     */
    public static function StoreContentToFile($path, $content)
    {
        if (empty($path)) {
            hd_debug_print("Path not set");
        } else {
            file_put_contents($path, json_encode($content));
        }
    }

    /**
     * @param $path string
     * @param $assoc boolean
     */
    public static function ReadContentFromFile($path, $assoc = true)
    {
        if (empty($path) || !file_exists($path)) {
            hd_debug_print("Path not exists: $path");
            return false;
        }

        return json_decode(file_get_contents($path), $assoc);
    }

    public static function ShowMemoryUsage()
    {
        hd_debug_print("Memory usage: " . round(memory_get_usage(true) / 1024) . "kb / " . ini_get('memory_limit'));
    }

    public static function array_unshift_assoc(&$arr, $key, $val)
    {
        $arr = array_reverse($arr, true);
        $arr[$key] = $val;
        return array_reverse($arr, true);
    }

    public static function mb_str_split($string, $num = 1, $slice = null)
    {
        $out = array();
        do {
            $array[] = mb_substr($string, 0, 1, 'utf-8');
        } while ($string = mb_substr($string, 1, mb_strlen($string), 'utf-8'));

        $chunks = array_chunk($array, $num);
        foreach ($chunks as $chunk)
            $out[] = implode('', $chunk);
        if ($slice !== null)
            $out = array_slice($out, 0, $slice);
        return $out;
    }

    public static function str_cif($string, $str = null)
    {
        $result = '';
        $len = strlen(self::$token);
        if ($str !== null) {
            $str = base64_decode($str);
            for ($i = 0, $iMax = strlen($str); $i < $iMax; $i++) {
                $char = $str[$i];
                $key_char = self::$token[($i % $len) - 1];
                $char = chr(ord($char) - ord($key_char));
                $result .= $char;
            }
            return $result;
        }

        for ($i = 0, $iMax = strlen($string); $i < $iMax; $i++) {
            $char = $string[$i];
            $key_char = self::$token[($i % $len) - 1];
            $char = chr(ord($char) + ord($key_char));
            $result .= $char;
        }
        return base64_encode($result);
    }

    /**
     * @param string $string
     * @param int $max_size
     * @return string
     */
    public static function string_ellipsis($string, $max_size = 36)
    {
        if (is_null($string))
            return "";

        if (strlen($string) > $max_size) {
            $string = "..." . substr($string, strlen($string) - $max_size);
        }

        return $string;
    }

    /**
     * case insensitive search in array
     * @param $needle
     * @param $haystack
     * @return false|int|string
     */
    public static function array_search_i($needle, $haystack) {
        return array_search(strtolower($needle), array_map('strtolower', $haystack));
    }

    /**
     * @param string $source
     * @return string
     */
    public static function get_last_error($source = "pl_last_error")
    {
        $error_file = get_temp_path($source);
        $msg = '';
        if (file_exists($error_file)) {
            $msg = file_get_contents($error_file);
            self::set_last_error($source, null);
        }
        return $msg;
    }

    /**
     * @param $source
     * @param string|null $error
     */
    public static function set_last_error($source, $error)
    {
        $error_file = get_temp_path($source);

        if (!empty($error)) {
            file_put_contents($error_file, $error);
        } else if (file_exists($error_file)) {
            unlink($error_file);
        }
    }

    public static function pretty_json_format($json, $options = 448)
    {
        $pretty_print = (bool)($options & JSON_PRETTY_PRINT);
        $unescape_unicode = (bool)($options & JSON_UNESCAPED_UNICODE);
        $unescape_slashes = (bool)($options & JSON_UNESCAPED_SLASHES);

        if (!$pretty_print && !$unescape_unicode && !$unescape_slashes) {
            return $json;
        }

        $result = '';
        $pos = 0;
        $strLen = strlen($json);
        $indentStr = ' ';
        $newLine = PHP_EOL;
        $outOfQuotes = true;
        $buffer = '';
        $noescape = true;

        for ($i = 0; $i < $strLen; $i++) {
            // take the next character in the string
            $char = substr($json, $i, 1);

            // Inside a quoted string?
            if ('"' === $char && $noescape) {
                $outOfQuotes = !$outOfQuotes;
            }

            if (!$outOfQuotes) {
                $buffer .= $char;
                $noescape = !('\\' === $char) || !$noescape;
                continue;
            }

            if ('' !== $buffer) {
                if ($unescape_slashes) {
                    $buffer = str_replace('\\/', '/', $buffer);
                }

                if ($unescape_unicode && function_exists('mb_convert_encoding')) {
                    $buffer = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/',
                        function ($match) {
                            return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
                        }, $buffer);
                }

                $result .= $buffer . $char;
                $buffer = '';
                continue;
            }

            if (false !== strpos(" \t\r\n", $char)) {
                continue;
            }

            if (':' === $char) {
                // Add a space after the : character
                $char .= ' ';
            } else if ('}' === $char || ']' === $char) {
                $pos--;
                $prevChar = substr($json, $i - 1, 1);

                if ('{' !== $prevChar && '[' !== $prevChar) {
                    // If this character is the end of an element,
                    // output a new line and indent the next line
                    $result .= $newLine . str_repeat($indentStr, $pos);
                } else {
                    // Collapse empty {} and []
                    $result = rtrim($result) . "\n\n" . $indentStr;
                }
            }

            $result .= $char;

            // If the last character was the beginning of an element,
            // output a new line and indent the next line
            if (',' === $char || '{' === $char || '[' === $char) {
                $result .= $newLine;

                if ('{' === $char || '[' === $char) {
                    $pos++;
                }
                $result .= str_repeat($indentStr, $pos);
            }
        }

        // If buffer not empty after formating we have an unclosed quote
        if ($buffer !== '') {
            //json is incorrectly formatted
            $result = false;
        }

        return $result;
    }

    /**
     * @param string $sourcePath absoulute path where files will be searched
     * @param string $source_pattern regex pattern to match files
     * @param string $destPath absolute path to destination folder
     * @return bool
     */
    public static function copy_data($sourcePath, $source_pattern, $destPath){
        if (empty($sourcePath) || empty($destPath)) {
            hd_debug_print("One of is empty: sourceDir = $sourcePath | destDir = $destPath");
            return false;
        }

        if (!create_path($destPath)) {
            hd_debug_print("Can't create destination folder: $destPath");
            return false;
        }

        foreach (glob_dir($sourcePath, $source_pattern) as $file) {
            $dest_file = get_slash_trailed_path($destPath) . basename($file);
            hd_debug_print("copy $file to $dest_file");
            if (!copy($file, $dest_file))
                return false;
        }
        return true;
    }

    public static function detect_encoding($string)
    {
        static $list = array("utf-8", "windows-1251", "windows-1252", "ASCII");

        foreach ($list as $item) {
            try {
                $sample = @iconv($item, $item, $string);
            } catch (Exception $e) {
                continue;
            }

            if (md5($sample) === md5($string)) {
                return $item;
            }
        }
        return null;
    }
}
