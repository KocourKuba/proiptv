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
    const DUNE_PARAMS_MAGIC = "|||dune_params|||";
    /**
     * @var bool
     */
    private static $with_rows_api;
    /**
     * @var string
     */
    private static $default_user_agent;
    /**
     * @var string
     */
    private static $plugin_user_agent;

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
     * @return string
     */
    public static function get_file_size($path)
    {
        $bytes = filesize($path);
        $si_prefix = array('B', 'KB', 'MB', 'GB', 'TB', 'EB', 'ZB', 'YB');
        $base = 1024;
        $class = min((int)log($bytes, $base), count($si_prefix) - 1);
        return sprintf('%1.2f', $bytes / pow($base, $class)) . ' ' . $si_prefix[$class];
    }

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

    ///////////////////////////////////////////////////////////////////////

    public static function http_local_port()
    {
        $port = getenv("HD_HTTP_LOCAL_PORT");
        return $port ? (int)$port : 80;
    }

    public static function get_default_user_agent()
    {
        if (empty(self::$default_user_agent))
            self::http_init();

        return self::$default_user_agent;
    }

    public static function http_init()
    {
        if (!empty(self::$default_user_agent))
            return;

        self::$default_user_agent = "DuneHD/1.0";

        $extra_useragent = "";
        $sysinfo = @file(getenv('FS_PREFIX') ."/tmp/sysinfo.txt", FILE_IGNORE_NEW_LINES);
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

    public static function set_dune_user_agent($user_agent)
    {
        self::$plugin_user_agent = $user_agent;
    }

    public static function send_log_to_developer($plugin, &$error = null)
    {
        $serial = get_serial_number();
        if (empty($serial)) {
            hd_debug_print("Unable to get DUNE serial.");
            $serial = 'XX-XX-XX-XX-XX';
        }
        $ver = $plugin->plugin_info['app_version'];
        $ver = str_replace('.', '_', $ver);
        $timestamp = format_datetime('Ymd_His', time());
        $model = get_product_id();
        $zip_file_name = "proiptv_{$ver}_{$model}_{$serial}_$timestamp.zip";
        hd_debug_print("Prepare archive $zip_file_name for send");
        $zip_file = get_temp_path($zip_file_name);
        $apk_subst = getenv('FS_PREFIX');
        $plugin_name = get_plugin_name();

        $paths = array(
            get_temp_path("*.txt"),
            get_temp_path("*.log"),
            get_temp_path("*.m3u8"),
            get_temp_path("*.m3u"),
            "$apk_subst/tmp/run/shell.log",
            "$apk_subst/tmp/run/shell.log.old",
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

        $plugin_backup = self::do_backup_settings($plugin, get_temp_path(), false);
        if ($plugin_backup === false) {
            $paths[] = get_data_path("*.settings");
        } else {
            $paths[] = $plugin_backup;
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
                self::http_put_document(base64_decode("aHR0cDovL2lwdHYuZXNhbGVjcm0ubmV0L3VwbG9hZC8", true) . $zip_file_name,
                    $handle, filesize($zip_file));
                hd_debug_print("Log file sent");
                $ret = true;
            }
        } catch (Exception $ex) {
            print_backtrace_exception($ex);
            $msg = ": Unable to upload log: " . $ex->getMessage();
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
     * @param Default_Dune_Plugin $plugin
     * @param string $folder_path
     * @return bool|string
     */
    public static function do_backup_settings($plugin, $folder_path, $complete = true)
    {
        $folder_path = get_paved_path($folder_path);

        hd_debug_print(ACTION_FOLDER_SELECTED . " $folder_path");
        if ($complete) {
            $timestamp = format_datetime('Y-m-d_H-i', time());
            $zip_file_name = "proiptv_backup_{$plugin->plugin_info['app_version']}_$timestamp.zip";
        } else {
            $zip_file_name = "proiptv_backup.zip";
        }
        $zip_file = get_temp_path($zip_file_name);

        try {
            $zip = new ZipArchive();
            if (!$zip->open($zip_file, ZipArchive::CREATE)) {
                throw new Exception(TR::t("err_create_zip__1", $zip_file));
            }

            $rootPath = get_data_path();
            foreach (array("common.db", "common.settings") as $name) {
                if (file_exists($rootPath . $name)) {
                    $zip->addFile($rootPath . $name, $name);
                }
            }
            foreach ($plugin->get_all_playlists_ids() as $key) {
                foreach (array(".settings", ".db") as $ext) {
                    $name = $key . $ext;
                    $path = $rootPath . $name;
                    if (file_exists($path)) {
                        $zip->addFile($path, $name);
                    }
                }

                foreach (array("_orders*\.db", "_orders*\.settings") as $ext) {
                    foreach (glob_dir($rootPath, "/$key$ext/i") as $full_path) {
                        if (file_exists($full_path)) {
                            $zip->addFile($full_path, basename($full_path));
                        }
                    }
                }
            }

            if ($complete) {
                $added_folders = array($rootPath . CACHED_IMAGE_SUBDIR);
                /** @var SplFileInfo[] $files */
                $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($rootPath),
                    RecursiveIteratorIterator::SELF_FIRST);

                foreach ($files as $file) {
                    if ($file->isDir()) continue;

                    $filePath = $file->getRealPath();
                    foreach ($added_folders as $folder) {
                        if (0 === strncmp($filePath, $folder, strlen($folder))) {
                            $relativePath = substr($filePath, strlen($rootPath));
                            $zip->addFile($filePath, $relativePath);
                        }
                    }
                }
            }

            if (!$zip->close()) {
                throw new Exception("Error create zip file: $zip_file " . $zip->getStatusString());
            }

            $backup_path = "$folder_path/$zip_file_name";
            if ($zip_file !== $backup_path && false === copy($zip_file, $backup_path)) {
                throw new Exception(TR::t('err_copy__2', $zip_file, $backup_path));
            }
        } catch (Exception $ex) {
            hd_debug_print(self::get_storage_size(get_temp_path()));
            print_backtrace_exception($ex);
            return false;
        }

        clearstatcache();
        if ($zip_file !== $backup_path) {
            hd_print("unlink $zip_file");
            unlink($zip_file);
        }

        return $backup_path;
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

    /**
     * @param string $url
     * @param array $opts
     * @param array $info
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

        if ($http_code >= 400) {
            $err_msg = "Fetch $url failed. HTTP request failed ($http_code): " . self::http_status_code_to_string($http_code);
            hd_debug_print($err_msg);
            throw new Exception($err_msg);
        }

        if ($http_code >= 300) {
            $err_msg = "Fetch $url completed, but ignored. HTTP request ($http_code): " . self::http_status_code_to_string($http_code);
            hd_debug_print($err_msg);
            $content = '';
        }

        curl_close($ch);

        return $content;
    }

    ///////////////////////////////////////////////////////////////////////

    public static function get_dune_user_agent()
    {
        if (empty(self::$default_user_agent))
            self::http_init();

        return (empty(self::$plugin_user_agent) || self::$default_user_agent === self::$plugin_user_agent) ? self::$default_user_agent : self::$plugin_user_agent;
    }

    /**
     * @param int $code
     * @return string
     */
    public static function http_status_code_to_string($code)
    {
        // Source: http://en.wikipedia.org/wiki/List_of_HTTP_status_codes

        switch ($code) {
            // 1xx Informational
            case 100:
                $string = 'Continue';
                break;
            case 101:
                $string = 'Switching Protocols';
                break;
            case 102:
                $string = 'Processing';
                break; // WebDAV
            case 122:
                $string = 'Request-URI too long';
                break; // Microsoft

            // 2xx Success
            case 200:
                $string = 'OK';
                break;
            case 201:
                $string = 'Created';
                break;
            case 202:
                $string = 'Accepted';
                break;
            case 203:
                $string = 'Non-Authoritative Information';
                break; // HTTP/1.1
            case 204:
                $string = 'No Content';
                break;
            case 205:
                $string = 'Reset Content';
                break;
            case 206:
                $string = 'Partial Content';
                break;
            case 207:
                $string = 'Multi-Status';
                break; // WebDAV

            // 3xx Redirection
            case 300:
                $string = 'Multiple Choices';
                break;
            case 301:
                $string = 'Moved Permanently';
                break;
            case 302:
                $string = 'Found';
                break;
            case 303:
                $string = 'See Other';
                break; //HTTP/1.1
            case 304:
                $string = 'Not Modified';
                break;
            case 305:
                $string = 'Use Proxy';
                break; // HTTP/1.1
            case 306:
                $string = 'Switch Proxy';
                break; // Depreciated
            case 307:
                $string = 'Temporary Redirect';
                break; // HTTP/1.1

            // 4xx Client Error
            case 400:
                $string = 'Bad Request';
                break;
            case 401:
                $string = 'Unauthorized';
                break;
            case 402:
                $string = 'Payment Required';
                break;
            case 403:
                $string = 'Forbidden';
                break;
            case 404:
                $string = 'Not Found';
                break;
            case 405:
                $string = 'Method Not Allowed';
                break;
            case 406:
                $string = 'Not Acceptable';
                break;
            case 407:
                $string = 'Proxy Authentication Required';
                break;
            case 408:
                $string = 'Request Timeout';
                break;
            case 409:
                $string = 'Conflict';
                break;
            case 410:
                $string = 'Gone';
                break;
            case 411:
                $string = 'Length Required';
                break;
            case 412:
                $string = 'Precondition Failed';
                break;
            case 413:
                $string = 'Request Entity Too Large';
                break;
            case 414:
                $string = 'Request-URI Too Long';
                break;
            case 415:
                $string = 'Unsupported Media Type';
                break;
            case 416:
                $string = 'Requested Range Not Satisfiable';
                break;
            case 417:
                $string = 'Expectation Failed';
                break;
            case 422:
                $string = 'Unprocessable Entity';
                break; // WebDAV
            case 423:
                $string = 'Locked';
                break; // WebDAV
            case 424:
                $string = 'Failed Dependency';
                break; // WebDAV
            case 425:
                $string = 'Unordered Collection';
                break; // WebDAV
            case 426:
                $string = 'Upgrade Required';
                break;
            case 449:
                $string = 'Retry With';
                break; // Microsoft
            case 450:
                $string = 'Blocked';
                break; // Microsoft

            // 5xx Server Error
            case 500:
                $string = 'Internal Server Error';
                break;
            case 501:
                $string = 'Not Implemented';
                break;
            case 502:
                $string = 'Bad Gateway';
                break;
            case 503:
                $string = 'Service Unavailable';
                break;
            case 504:
                $string = 'Gateway Timeout';
                break;
            case 505:
                $string = 'HTTP Version Not Supported';
                break;
            case 506:
                $string = 'Variant Also Negotiates';
                break;
            case 507:
                $string = 'Insufficient Storage';
                break; // WebDAV
            case 509:
                $string = 'Bandwidth Limit Exceeded';
                break; // Apache
            case 510:
                $string = 'Not Extended';
                break;

            // Unknown code:
            default:
                $string = 'Unknown';
                break;
        }
        return $string;
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
            '&#39;' => "'",
            '&gt;' => ">",
            '&lt;' => "<'>",
            '&apos;' => "'",
            '&quot;' => '"',
            '&amp;' => '&',
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
            '<br>' => PHP_EOL,
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
        if (file_put_contents($path, $json ? json_encode($items) : serialize($items)) === false) {
            hd_debug_print("Failed to save $path");
        }
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
            print_backtrace_exception($ex);
            return false;
        }

        return $contents;
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
     * @param string|array $needle
     * @param string|array $haystack
     * @return false|int|string
     */
    public static function array_search_i($needle, $haystack)
    {
        return array_search(strtolower($needle), array_map('strtolower', $haystack));
    }

    /**
     * @param string $source
     * @return string
     */
    public static function check_last_error($source)
    {
        $error_file = get_temp_path($source);
        $msg = '';
        if (file_exists($error_file)) {
            $msg = file_get_contents($error_file);
        }
        return $msg;
    }

    /**
     * @param string $source
     * @return string
     */
    public static function get_last_error($source)
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
     * @param string $source
     * @param string|null $error
     */
    public static function set_last_error($source, $error)
    {
        $error_file = get_temp_path($source);
        if (empty($error) && file_exists($error_file)) {
            unlink($error_file);
        } else {
            file_put_contents($error_file, $error);
        }
    }

    /**
     * @param string $sourcePath absoulute path where files will be searched
     * @param string $source_pattern regex pattern to match files
     * @param string $destPath absolute path to destination folder
     * @throws Exception
     */
    public static function copy_data($sourcePath, $source_pattern, $destPath)
    {
        if (empty($sourcePath) || empty($destPath)) {
            $msg = "One of is empty: sourceDir = $sourcePath | destDir = $destPath";
            hd_debug_print($msg);
            throw new Exception($msg);
        }

        if (!create_path($destPath)) {
            $msg = "Can't create destination folder: $destPath";
            hd_debug_print($msg);
            throw new Exception($msg);
        }

        foreach (glob_dir($sourcePath, $source_pattern) as $file) {
            $dest_file = get_slash_trailed_path($destPath) . basename($file);
            hd_debug_print("copy $file to $dest_file");
            if (!copy($file, $dest_file)) {
                throw new Exception(error_get_last());
            }
        }
    }

    public static function detect_encoding($string)
    {
        static $list = array("utf-8", "windows-1251", "windows-1252", "ASCII");

        foreach ($list as $item) {
            try {
                $sample = @iconv($item, $item, $string);
            } catch (Exception $ex) {
                print_backtrace_exception($ex);
                continue;
            }

            if (md5($sample) === md5($string)) {
                return $item;
            }
        }
        return null;
    }

    /**
     * Set cookie with expired time (timestamp).
     * If $persistent is true cookie stored to plugin data path
     *
     * @param string $filename file name without path
     * @param bool $persistent [optional] is stored in persistent file storage
     */
    public static function clear_cookie($filename, $persistent = false)
    {
        $file_path = $persistent ? get_data_path($filename) : get_temp_path($filename);
        if (file_exists($file_path)) {
            unlink($file_path);
        }
    }
}
