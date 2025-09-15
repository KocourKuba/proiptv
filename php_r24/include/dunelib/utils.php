<?php
///////////////////////////////////////////////////////////////////////////

class DuneCurlException extends Exception
{
    public $errno;
    public $error;
    public $http_code;

    public function __construct($errno, $error, $http_code, $message = null)
    {
        parent::__construct(
            isset($message) ? $message :
            ($errno != 0 ? "CURL errno: $errno ($error)" :
             ($http_code != 200 ? "HTTP error: $http_code" : "OK")),
            $errno);

        $this->errno = $errno;
        $this->error = $error;
        $this->http_code = $http_code;
    }
}

class HD
{
    const INT_MIN = -2147483648;
    const INT_MAX = 2147483647;

    const NBSP = "\xC2\xA0";

    const DUNE_AUTH_PATH = "/tmp/dune_auth.txt";

    const INTERNET_STATUS_PATH = "/tmp/run/internet_status.txt";
    const FORCE_CHECK_INTERNET_ON_ERROR_PATH =
        "/tmp/run/network_monitor/force_check_on_error";

    public static $DEFAULT_CURL_TIMEOUT = 60;
    public static $DEFAULT_CURL_CONNECT_TIMEOUT = 10;

    public static function get($map, $key, $def=null)
    {
        if (!isset($map) || !isset($key))
            return $def;
        if (is_object($map))
            return isset($map->$key) ? $map->$key : $def;
        return isset($map[$key]) ? $map[$key] : $def;
    }

    public static function arrget($arr, $key, $def=null)
    {
        return isset($arr[$key]) ? $arr[$key] : $def;
    }

    public static function assert($value, $text='')
    {
        if (!$value)
        {
            $msg = "HD::ASSERT($text)";
            hd_print($msg);
            debug_print_backtrace();
            throw new Exception($msg);
        }
    }

    public static function is_map($a)
    {
        return is_array($a) &&
            array_diff_key($a, array_keys(array_keys($a)));
    }

    public static function init($def_curl_timeout=20)
    {
        HD::$DEFAULT_CURL_TIMEOUT = $def_curl_timeout;
        mt_srand();
        self::http_init();
    }

    ///////////////////////////////////////////////////////////////////////

    public static function has_attribute($obj, $n)
    {
        $arr = (array) $obj;
        return isset($arr[$n]);
    }
    ///////////////////////////////////////////////////////////////////////

    public static function get_map_element($map, $key)
    {
        return isset($map[$key]) ? $map[$key] : null;
    }

    public static function filter_map($map, $key_arr)
    {
        $result = array();
        foreach ($key_arr as $key) {
            $v = HD::get($map, $key);
            if (isset($v))
                $result[$key] = $v;
        }
        return $result;
    }

    ///////////////////////////////////////////////////////////////////////

    public static function starts_with($str, $pattern)
    {
        return strpos($str, $pattern) === 0;
    }

    ///////////////////////////////////////////////////////////////////////

    public static function format_timestamp($ts, $fmt = null)
    {
        // NOTE: for some reason, explicit timezone is required for PHP
        // on Dune (no builtin timezone info?).

        if (is_null($fmt))
            $fmt = 'Y:m:d H:i:s';

        $dt = new DateTime('@' . $ts);
        return $dt->format($fmt);
    }

    ///////////////////////////////////////////////////////////////////////

    public static function format_duration($msecs)
    {
        $n = intval($msecs);

        if (strlen($msecs) <= 0 || $n <= 0)
            return "00:00";

        $n = $n / 1000;
        $hours = $n / 3600;
        $remainder = $n % 3600;
        $minutes = $remainder / 60;
        $seconds = $remainder % 60;

        if (intval($hours) > 0)
        {
            return sprintf("%d:%02d:%02d", $hours, $minutes, $seconds);
        }
        else
        {
            return sprintf("%02d:%02d", $minutes, $seconds);
        }
    }

    ///////////////////////////////////////////////////////////////////////

    public static function encode_user_data($a, $b = null)
    {
        $media_url = null;
        $user_data = null;

        if (is_array($a) && is_null($b))
        {
            $media_url = '';
            $user_data = $a;
        }
        else
        {
            $media_url = $a;
            $user_data = $b;
        }

        if (!is_null($user_data))
            $media_url .= '||' . json_encode($user_data);

        return $media_url;
    }

    ///////////////////////////////////////////////////////////////////////

    public static function decode_user_data($media_url_str, &$media_url, &$user_data)
    {
        $idx = strpos($media_url_str, '||');

        if ($idx === false)
        {
            $media_url = $media_url_str;
            $user_data = null;
            return;
        }

        $media_url = substr($media_url_str, 0, $idx);
        $user_data = json_decode(substr($media_url_str, $idx + 2));
    }

    ///////////////////////////////////////////////////////////////////////

    public static function create_regular_folder_range($items,
        $from_ndx = 0, $total = -1, $more_items_available = false)
    {
        if ($total === -1)
            $total = $from_ndx + count($items);

        if ($from_ndx >= $total)
        {
            $from_ndx = $total;
            $items = array();
        }
        else if ($from_ndx + count($items) > $total)
        {
            array_splice($items, $total - $from_ndx);
        }

        return array
        (
            PluginRegularFolderRange::total => intval($total),
            PluginRegularFolderRange::more_items_available => $more_items_available,
            PluginRegularFolderRange::from_ndx => intval($from_ndx),
            PluginRegularFolderRange::count => count($items),
            PluginRegularFolderRange::items => $items
        );
    }

    ///////////////////////////////////////////////////////////////////////

    public static function get_internet_status()
    {
        return is_file(self::INTERNET_STATUS_PATH) ?
            (int) file_get_contents(self::INTERNET_STATUS_PATH) : -1;
    }

    public static function force_check_internet_on_error()
    {
        $tmp_path = self::FORCE_CHECK_INTERNET_ON_ERROR_PATH . ".tmp";
        file_put_contents($tmp_path, "1");
        rename($tmp_path, self::FORCE_CHECK_INTERNET_ON_ERROR_PATH);
    }

    ///////////////////////////////////////////////////////////////////////

    private static $user_agent = null;
    private static $serial_number = null;

    public static function http_init()
    {
        if (isset(self::$user_agent))
            return;

        $extra_ua = "";
        $sn = "";

        $sysinfo = file("/tmp/sysinfo.txt", FILE_IGNORE_NEW_LINES);
        if (isset($sysinfo))
        {
            foreach ($sysinfo as $line)
            {
                $pos = strpos($line, ':');
                if (!$pos)
                    continue;

                $key = trim(substr($line, 0, $pos));
                $value = trim(substr($line, $pos + 1));

                if ($key == 'serial_number')
                    $sn = $value;
                else if ($key == 'product_id' || $key == 'firmware_version')
                {
                    $extra_ua = $extra_ua ? $extra_ua . "; " : " (";
                    $extra_ua .= $line;
                }
            }

            if ($extra_ua)
                $extra_ua .= ")";
        }

        self::$serial_number = $sn;
        self::$user_agent = "DuneHD/1.0" . $extra_ua;

        hd_print("HTTP X-Dune-Serial-Number: " . self::$serial_number);
        hd_print("HTTP UserAgent: " . self::$user_agent);
    }

    public static function get_serial_number()
    {
        self::http_init();

        return self::$serial_number;
    }

    public static function get_dune_user_agent()
    {
        return self::$user_agent;
    }

    public static function get_addr_from_http_url($url)
    {
        if (0 === strpos($url, 'https://'))
            $len = 8;
        else if (0 === strpos($url, 'http://'))
            $len = 7;
        else
            return null;

        $pos = strpos($url, '/', $len);
        return $pos === false ?
            substr($url, $len) :
            substr($url, $len, $pos - $len);
    }

    public static function unset_http_params($params)
    {
        if ($params && isset($params->eh))
        {
            curl_close($params->eh);
            unset($params->eh);
        }
    }

    public static function get_dune_opts($add_opts=null)
    {
        $opts = $add_opts ? (array) $add_opts : array();
        $opts[CURLOPT_USERAGENT] = self::get_dune_user_agent();
        return $opts;
    }

    private static $http_response_headers = null;

    public static function http_headerfunction($curl, $header)
    {
        $len = strlen($header);
        $header = explode(':', $header, 2);
        if (count($header) == 2)
        {
            $key = strtolower(trim($header[0]));
            self::$http_response_headers->$key = trim($header[1]);
        }
        return $len;
    }

    public static function http_get_document(
        $url, $opts = null, $silent=false, $params=null)
    {
        if ($params && isset($params->eh))
        {
            hd_print("DEBUG reusing curl handle");
            $ch = $params->eh;
            $ch_occupied = false;
        }
        else
        {
            $ch = curl_init();
            $ch_occupied = true;
        }

        if (!isset($opts))
            $opts = array();

        if (self::$serial_number)
        {
            $hh = HD::get($opts, CURLOPT_HTTPHEADER, array());
            $hh[] = "X-Dune-Serial-Number: " . self::$serial_number;
            $opts[CURLOPT_HTTPHEADER] = $hh;
        }

        if (!isset($opts[CURLOPT_POST]))
            $opts[CURLOPT_POST] = false;

        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,    false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,    self::$DEFAULT_CURL_CONNECT_TIMEOUT);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION,    1);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,    true);
        curl_setopt($ch, CURLOPT_TIMEOUT,           self::$DEFAULT_CURL_TIMEOUT);
        curl_setopt($ch, CURLOPT_USERAGENT,         self::$user_agent);
        curl_setopt($ch, CURLOPT_ENCODING,          1);
        curl_setopt($ch, CURLOPT_URL,               $url);

        if (HD::get($opts, CURLOPT_SSL_VERIFYPEER) &&
            0 === strpos($url, 'https://'))
        {
            $opts[CURLOPT_CAINFO] = "/firmware/certs/ca-bundle.crt";
        }

        $resp_hh = HD::get($params, 'response_headers');
        if ($resp_hh)
        {
            self::$http_response_headers = $resp_hh;
            $opts[CURLOPT_HEADERFUNCTION] = 'HD::http_headerfunction';
        }

        foreach ($opts as $k => $v)
            curl_setopt($ch, $k, $v);

        if (!$silent)
            hd_print("HTTP fetching '$url'...");

        if (!$silent)
        {
            if (isset($opts) && isset($opts[CURLOPT_POSTFIELDS]))
                hd_print("HTTP POST fields '" . $opts[CURLOPT_POSTFIELDS] . "'");
        }

        $start_tm = microtime(true);
        $content = curl_exec($ch);
        $execution_tm = microtime(true) - $start_tm;
        $curl_errno = curl_errno($ch);
        $curl_error = curl_error($ch);

        $len = strlen($content);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        // TODO: revise
        if ($content === false || $curl_errno != 0)
            HD::force_check_internet_on_error();

        if ($content === false)
        {
            self::unset_http_params($params);

            $err_msg =
                "CURL errno: $curl_errno ($curl_error); ".
                "HTTP error: $http_code; ";
            if (!$silent)
                hd_print($err_msg);
            throw new DuneCurlException(
                $curl_errno, $curl_error, $http_code, $err_msg);
        }

        if ($http_code != 200)
        {
            $is_redirect = $http_code == 301 || $http_code == 302;
            $is_unchanged = $http_code == 304;
            if (!$is_redirect && !$is_unchanged)
                self::unset_http_params($params);

            $err_msg = "HTTP request failed ($http_code)";
            if (!$silent && !$is_unchanged)
                hd_print($err_msg);
            throw new DuneCurlException(
                $curl_errno, $curl_error, $http_code, $err_msg);
        }

        if (!$silent)
            hd_print("HTTP OK ($http_code, $len bytes) in ".sprintf("%.3fs", $execution_tm));

        if ($ch_occupied)
            curl_close($ch);

        return $content;
    }

    ///////////////////////////////////////////////////////////////////////

    public static function http_post_document($url, $post_data,
        $opts = null, $silent=false, $params=null)
    {
        $post_opts = array
        (
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $post_data
        );

        if (!is_null($opts))
        {
            foreach ($opts as $k => $v)
                $post_opts[$k] = $v;
        }

        return self::http_get_document($url, $post_opts, $silent, $params);
    }

    ///////////////////////////////////////////////////////////////////////

    public static function http_request_document($url,
        $post_data=null, $opts = null, $silent=false)
    {
        return isset($post_data) ?
            self::http_post_document($url, $post_data, $opts, $silent) :
            self::http_get_document($url, $opts, $silent);
    }

    ///////////////////////////////////////////////////////////////////////

    public static function http_server_responding($url, $timeout)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER,    false);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT,    $timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT,           $timeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION,    0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,    true);
        curl_setopt($ch, CURLOPT_NOBODY,            true);
        curl_setopt($ch, CURLOPT_USERAGENT,         self::get_dune_user_agent());
        curl_setopt($ch, CURLOPT_URL,               $url);

        hd_print("HTTP checking $url of ${timeout}s");
        $content = curl_exec($ch);
        $curl_errno = curl_errno($ch);
        hd_print("HTTP check status: $curl_errno");
        return $curl_errno == CURLE_OK;
    }

    ///////////////////////////////////////////////////////////////////////

    public static function append_dune_auth($url)
    {
        $sep = FALSE === strpos($url, '?') ? '?' : '&';
        $res = file_get_contents(self::DUNE_AUTH_PATH);
        $data = $res === false ? '' : $res;
        return "$url${sep}$data";
    }

    ///////////////////////////////////////////////////////////////////////

    public static function write_to_file_using_tmp($path, $str)
    {
        $tmp_path = "$path.tmp";
        if (false === file_put_contents($tmp_path, $str))
        {
            hd_print("Error writing to '$tmp_path'");
            return false;
        }

        if (false === rename($tmp_path, $path))
        {
            hd_print("Error renaming '$tmp_path' to $path");
            unlink($tmp_path);
            return false;
        }

        return true;
    }

    ///////////////////////////////////////////////////////////////////////

    public static function write_to_file_using_tmp_if_changed($path, $str)
    {
        if (is_file($path))
        {
            $old_str = file_get_contents($path);
            if ($str == $old_str)
                return 0;
        }

        $tmp_path = "$path.tmp";
        if (false === file_put_contents($tmp_path, $str))
        {
            hd_print("Error writing to '$tmp_path'");
            return -1;
        }

        if (false === rename($tmp_path, $path))
        {
            hd_print("Error renaming '$tmp_path' to $path");
            unlink($tmp_path);
            return -1;
        }

        return 1;
    }

    public static function safe_unlink($path)
    {
        if (!is_file($path))
            return 0;
        $ok = unlink($path);
        return $ok ? 1 : -1;
    }

    ///////////////////////////////////////////////////////////////////////

    public static function parse_xml_document($doc)
    {
        $xml = simplexml_load_string($doc);

        if ($xml === false)
        {
            hd_print("Error: can not parse XML document.");
            hd_print("XML-text: $doc.");
            throw new Exception('Illegal XML document');
        }

        return $xml;
    }

    public static function parse_props($doc)
    {
        $props = array();

        $tok = strtok($doc, "\n");
        while ($tok !== false) {
            $pos = strpos($tok, '=');
            if ($pos)
            {
                $key = trim(substr($tok, 0, $pos));
                $val = trim(substr($tok, $pos + 1));
                $props[$key] = $val;
            }
            $tok = strtok("\n");
        }

        return $props;
    }

    public static function read_props_file($path, $def=null)
    {
        if (!$path || !is_file($path))
            return $def;

        $doc = file_get_contents($path);
        if ($doc === false)
            return $def;

        return HD::parse_props($doc);
    }

    public static function encode_props($props)
    {
        $str = '';
        foreach ($props as $key => $val) {
            $str .= "$key = $val\n";
        }
        return $str;
    }

    ///////////////////////////////////////////////////////////////////////

    public static function make_json_rpc_request($op_name, $params)
    {
        static $request_id = 0;

        $request = array
        (
            'jsonrpc' => '2.0',
            'id' => ++$request_id,
            'method' => $op_name,
            'params' => $params
        );

        return $request;
    }

    ///////////////////////////////////////////////////////////////////////////

    public static function get_mac_addr()
    {
        static $mac_addr = null;

        if (is_null($mac_addr))
        {
            if (HD::is_apk()) {
                $mac_addr = strtoupper(
                    trim(file_get_contents("/tmp/run/dune_mac.txt")));
            } else {
                $mac_addr = shell_exec(
                    'ifconfig  eth0 | head -1 | sed "s/^.*HWaddr //"');
                $mac_addr = trim($mac_addr);
            }

            hd_print("MAC Address: '$mac_addr'");
        }

        return $mac_addr;
    }

    ///////////////////////////////////////////////////////////////////////////

    public static function get_product_id()
    {
        static $product_id = null;
        if (!isset($product_id))
        {
            $product_id = trim(
                file_get_contents("/firmware/config/product_id.txt"));
        }
        return $product_id;
    }

    ///////////////////////////////////////////////////////////////////////////

    public static function is_android() {
        return true;
    }

    public static function is_apk() {
        return (bool) getenv("HD_APK");
    }

    public static function is_fw_apk() {
        return (bool) getenv("HD_FW_APK");
    }

    public static function is_limited_apk() {
        return self::is_apk() && !self::is_fw_apk();
    }

    public static function with_network_manager() {
        return !self::is_limited_apk();
    }

    public static function get_apk_package_name() {
        return getenv("APK_PACKAGE_NAME");
    }

    public static function get_fs_prefix() {
        $fs_prefix = getenv("FS_PREFIX");
        return $fs_prefix ? (string) $fs_prefix : "";
    }

    public static function with_epfs()
    {
        static $value = -1;
        if ($value == -1)
            $value = is_dir("/flashdata/plugins_epfs/shell_ext") ? 1 : 0;
        return $value;
    }

    ///////////////////////////////////////////////////////////////////////////

    private static function check_is_custom_fw()
    {
        $base_path = "/firmware/config/base_firmware_info.txt";
        if (!is_file($base_path))
            return false;

        $base_product_id = null;
        foreach (file($base_path) as $line)
        {
            if (preg_match('|^product_id: (\S*)\s*$|', $line, $m))
            {
                $base_product_id = $m[1];
                break;
            }
        }
        if (!$base_product_id)
        {
            // NOTE: invalid base_firmware_info.txt file => treat as custom.
            return true;
        }

        $product_id = HD::get_product_id();
        if (!$product_id)
        {
            // NOTE: invalid product_id.txt file => treat as custom.
            return true;
        }

        return $product_id != $base_product_id;
    }

    public static function is_custom_fw()
    {
        static $value = null;
        if (!isset($value))
        {
            $value = self::check_is_custom_fw();
            hd_print("Custom firmware: ".($value ? "YES" : "NO"));
        }
        return $value;
    }

    ///////////////////////////////////////////////////////////////////////////

    public static function enable_caching_for_image_url(&$image_url)
    {
        if (!$image_url ||
            (substr($image_url, 0, 4) != 'http' &&
             substr($image_url, 0, 12) != 'cached_image'))
        {
            return;
        }

        $sep = FALSE === strpos($image_url, '?') ? '?' : '&';
        $image_url .= $sep . "dune_image_cache=1";
    }

    public static function escape_xml_string($str)
    {
        $str = strval($str);
        $len = strlen($str);
        $out = '';
        for ($i = 0; $i < $len; $i++)
        {
            if ($str[$i] == '&')
                $out .= '&amp;';
            else if ($str[$i] == '<')
                $out .= '&lt;';
            else if ($str[$i] == '>')
                $out .= '&gt;';
            else if ($str[$i] == '"')
                $out .= '&quot;';
            else
                $out .= $str[$i];
        }
        return $out;
    }

    private static $MONTHS = array(
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December',
    );

    private static $last_lt_tm = 0;
    private static $last_lt_result;
    public static function localtime_TZ($tm)
    {
        if ($tm == self::$last_lt_tm)
            return self::$last_lt_result;

        $time_str = exec("date +'%S|%M|%H|%d|%m|%Y|%w|%j' -d @$tm");
        $result = explode('|', $time_str);
        $result[4] -= 1;
        $result[5] -= 1900;
        $result[7] -= 1;
        $result[8] = 0;

        self::$last_lt_tm = $tm;
        self::$last_lt_result = $result;
        return $result;
    }

    private static function get_timezone_diff()
    {
        if (!is_file('/etc/TZ'))
            return 0;

        $timezone = trim(file_get_contents('/etc/TZ'));
        $time_part = substr($timezone, 3);
        if ($time_part == "")
            return 0;

        $is_negative = false;
        if ($time_part[0] == '-') {
            $is_negative = true;
            $time_part = substr($time_part, 1);
        } else if ($time_part[0] == '+') {
            $time_part = substr($time_part, 1);
        }

        $hour_part = '';
        $min_part = '';
        $pos = strpos($time_part, ':');
        if ($pos !== false) {
            $hour_part = substr($time_part, 0, $pos);
            $min_part = substr($time_part, $pos + 1);
        } else {
            $hour_part = $time_part;
        }

        $tz_diff = $hour_part * 60 + $min_part;
        $tz_diff *= 60;
        if (!$is_negative)
            $tz_diff = -$tz_diff;

        //hd_print("Timezone: $timezone, tz_diff: $tz_diff, time_part: $time_part");
        return $tz_diff;
    }

    public static function MY_localtime($tm, $use_tz)
    {
        if ($use_tz)
        {
            // TODO: find out and implement more effective approach
            return self::localtime_TZ($tm);
        }

        return localtime($tm);
    }

    public static function format_date_time_date($tm, $use_tz = false)
    {
        $lt = self::MY_localtime($tm, $use_tz);
        $mon = self::$MONTHS[$lt[4]];
        return sprintf("%02d %s %04d", $lt[3], $mon, $lt[5] + 1900);
    }

    private static function get_month_key($n)
    {
        return "formatting_month_$n";
    }

    public static function format_date_time_date_ext($tm, $use_tz = false)
    {
        $lt = self::MY_localtime($tm, $use_tz);
        $mon_key = self::get_month_key($lt[4]);
        return sprintf("%02d <key_global>%s</key_global> %04d",
            $lt[3], $mon_key, $lt[5] + 1900);
    }

    private static function get_24h_ampm(&$hour)
    {
        if ($hour <= 11) {
            if ($hour == 0)
                $hour = 12;
            return " AM";
        }
        if ($hour > 12)
            $hour -= 12;
        return " PM";
    }

    public static function convert_daytime_to_12h($str)
    {
        if (strlen($str) <= 3 || $str[2] != ':')
            return $str;

        $hour = (int) substr($str, 0, 2);
        $ampm = self::get_24h_ampm($hour);
        return $hour . substr($str, 2) . $ampm;
    }

    public static function format_date_time_time($tm,
        $with_sec = false, $fmt="24", $use_tz = false)
    {
        $lt = self::MY_localtime($tm, $use_tz);
        $str = sprintf("%02d:%02d", $lt[2], $lt[1]);
        if ($with_sec)
            $str .= sprintf(":%02d", $lt[0]);
        return $fmt == "12" ? self::convert_daytime_to_12h($str) : $str;
    }

    public static function readlines($path)
    {
        if (!is_file($path))
            return array();
        return file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    }

    public static function print_backtrace()
    {
        hd_print('Back trace:');
        foreach (debug_backtrace() as $f)
        {
            hd_print(
                '  - ' . $f['function'] . 
                ' at ' . $f['file'] . ':' . $f['line']);
        }
    }
    
    public static function escape_shell_arg($str)
    {
        return "'".str_replace("'", "'\\''", $str)."'";
    }

    public static function encode_json_str($str)
    {
        $escapers = array("\\", "\"", "\n", "\r", "\t", "\x08", "\x0c");
        $replacements = array("\\\\", "\\\"", "\\n", "\\r", "\\t", "\\f", "\\b");
        return str_replace($escapers, $replacements, $str);
    }

    public static function path_basename($str)
    {
        return preg_replace('/^.*\//', '', $str);
    }

    public static function path_dirname($str)
    {
        $str = rtrim($str, '/');
        $ndx = strrpos($str, '/');
        if ($ndx === false)
            return '';
        if ($ndx == 0)
            return '/';
        return substr($str, 0, $ndx);
    }

    public static function ensure_plugin_name(&$action)
    {
        if (!isset($action['plugin_name']))
            $action['plugin_name'] = DuneSystem::$properties['plugin_name'];
    }

    public static function http_status_code_to_string($code)
    {
        // Source: http://en.wikipedia.org/wiki/List_of_HTTP_status_codes

        switch( $code )
        {
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

    public static function get_item($path,$dummy=null) {
        return false;
    }
}

///////////////////////////////////////////////////////////////////////////
?>
