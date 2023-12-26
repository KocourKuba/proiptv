<?php
###############################################################################
#
# Dune STB API
#
# This module is a collection of definitions and simplest functions for
# convenient interaction with the built-in Dune HD GUI Framework in php plugins.
# The module does not pretend to be a full-fledged API, although in terms of
# functionality and capabilities close to Dune JavaScript STB API.
#
# Using the module in your projects is not prohibited. Third party help in
# development is strongly encouraged. Send comments to brigadir@mydune.ru
#
# Author: Brigadir (forum.mydune.ru)
# Date: 23-07-2017
# Latest update: 04-10-2020
#
# PS: Certain parts of the functionality may not work on different firmware.
#     I hope this will be fixed with the release of new firmware.
#
###############################################################################

# Command execution status values
const COMMAND_STATUS_SUCCESS = 'ok';
const COMMAND_STATUS_FAIL = 'failed';
const COMMAND_STATUS_TIMEOUT = 'timeout';

# Adding GUI event key defs
const GUI_EVENT_KEY_DISCRETE_POWER_ON = 'key_power_on';
const GUI_EVENT_KEY_DISCRETE_POWER_OFF = 'key_power_off';

# Standby modes
const STANDBY_MODE_OFF = 0;
const STANDBY_MODE_ON = 1;

# Zoom presets
const VIDEO_ZOOM_NORMAL = 'normal';
const VIDEO_ZOOM_ENLARGE = 'enlarge';
const VIDEO_ZOOM_MAKE_WIDER = 'make_wider';
const VIDEO_ZOOM_NON_LINEAR_STRETCH = 'fill_screen';
const VIDEO_ZOOM_NON_LINEAR_STRETCH_TO_FULL_SCREEN = 'full_fill_screen';
const VIDEO_ZOOM_MAKE_TALLER = 'make_taller';
const VIDEO_ZOOM_CUT_EDGES = 'cut_edges';
const VIDEO_ZOOM_FULL_SCREEN = 'full_enlarge';
const VIDEO_ZOOM_STRETCH_TO_FULL_SCREEN = 'full_stretch';

# Player states
const PLAYER_STATE_STANDBY = 'standby';
const PLAYER_STATE_BLACK_SCREEN = 'black_screen';
const PLAYER_STATE_NAVIGATOR = 'navigator';
const PLAYER_STATE_FILE_PLAYBACK = 'file_playback';
const PLAYER_STATE_DVD_PLAYBACK = 'dvd_playback';
const PLAYER_STATE_BLURAY_PLAYBACK = 'bluray_playback';

# Playback events
const PLAYBACK_STOPPED = 'stopped';
const PLAYBACK_INITIALIZING = 'initializing';
const PLAYBACK_PLAYING = 'playing';
const PLAYBACK_PAUSED = 'paused';
const PLAYBACK_SEEKING = 'seeking';
const PLAYBACK_BUFFERING = 'buffering';
const PLAYBACK_FINISHED = 'finished';
const PLAYBACK_DEINITIALIZING = 'deinitializing';
const PLAYBACK_PCR_DISCONTINUITY = 'pcr_discontinuity';
const PLAYBACK_MEDIA_OPEN_FAILED = 'media_open_failed';

const CACHED_IMAGE_SUBDIR = 'cached_img';

# Hard-coded constants.
if (!defined('FONT_SIZE_LARGE')) define('FONT_SIZE_LARGE', 4);
if (!defined('ORIENTATION_VERTICAL')) define('ORIENTATION_VERTICAL', 0);
if (!defined('ORIENTATION_HORIZONTAL')) define('ORIENTATION_HORIZONTAL', 1);
if (!defined('CYCLE_MODE_GAP_AUTO')) define('CYCLE_MODE_GAP_AUTO', 0);
if (!defined('CYCLE_MODE_GAP_YES')) define('CYCLE_MODE_GAP_YES', 1);
if (!defined('CYCLE_MODE_GAP_NO')) define('CYCLE_MODE_GAP_NO', 2);

# Gui Event Keys
if (!defined('GUI_EVENT_KEY_FWD')) define('GUI_EVENT_KEY_FWD', 'key_fwd');
if (!defined('GUI_EVENT_KEY_REW')) define('GUI_EVENT_KEY_REW', 'key_rew');
if (!defined('GUI_EVENT_KEY_SLOW')) define('GUI_EVENT_KEY_SLOW', 'key_slow');
if (!defined('GUI_EVENT_KEY_STOP')) define('GUI_EVENT_KEY_STOP', 'key_stop');
if (!defined('GUI_EVENT_KEY_TOP_MENU')) define('GUI_EVENT_KEY_TOP_MENU', 'key_top_menu');
if (!defined('GUI_EVENT_KEY_POWER')) define('GUI_EVENT_KEY_POWER', 'key_power');
if (!defined('GUI_EVENT_KEY_EJECT')) define('GUI_EVENT_KEY_EJECT', 'key_eject');
if (!defined('GUI_EVENT_KEY_MODE')) define('GUI_EVENT_KEY_MODE', 'key_mode');
if (!defined('GUI_EVENT_KEY_VENDOR')) define('GUI_EVENT_KEY_VENDOR', 'key_vendor');
if (!defined('GUI_EVENT_KEY_SHUFFLE')) define('GUI_EVENT_KEY_SHUFFLE', 'key_shuffle');
if (!defined('GUI_EVENT_KEY_MUSIC')) define('GUI_EVENT_KEY_MUSIC', 'key_music');
if (!defined('GUI_EVENT_KEY_MUTE')) define('GUI_EVENT_KEY_MUTE', 'key_mute');
if (!defined('GUI_EVENT_KEY_V_PLUS')) define('GUI_EVENT_KEY_V_PLUS', 'key_v_plus');
if (!defined('GUI_EVENT_KEY_V_MINUS')) define('GUI_EVENT_KEY_V_MINUS', 'key_v_minus');
if (!defined('GUI_EVENT_KEY_SEARCH')) define('GUI_EVENT_KEY_SEARCH', 'key_search');
if (!defined('GUI_EVENT_KEY_ZOOM')) define('GUI_EVENT_KEY_ZOOM', 'key_zoom');
if (!defined('GUI_EVENT_KEY_SUBTITLE')) define('GUI_EVENT_KEY_SUBTITLE', 'key_subtitle');
if (!defined('GUI_EVENT_KEY_REPEAT')) define('GUI_EVENT_KEY_REPEAT', 'key_repeat');
if (!defined('GUI_EVENT_KEY_AUDIO')) define('GUI_EVENT_KEY_AUDIO', 'key_audio');
if (!defined('GUI_EVENT_KEY_REC')) define('GUI_EVENT_KEY_REC', 'key_rec');
if (!defined('GUI_EVENT_KEY_DUNE')) define('GUI_EVENT_KEY_DUNE', 'key_dune');
if (!defined('GUI_EVENT_KEY_URL')) define('GUI_EVENT_KEY_URL', 'key_url');
if (!defined('GUI_EVENT_KEY_KEYBRD')) define('GUI_EVENT_KEY_KEYBRD', 'key_keybrd');
if (!defined('GUI_EVENT_KEY_MOUSE')) define('GUI_EVENT_KEY_MOUSE', 'key_mouse');
if (!defined('GUI_EVENT_KEY_RECENT')) define('GUI_EVENT_KEY_RECENT', 'key_recent');
if (!defined('GUI_EVENT_KEY_TV')) define('GUI_EVENT_KEY_TV', 'key_tv');
if (!defined('GUI_EVENT_KEY_MOVIES')) define('GUI_EVENT_KEY_MOVIES', 'key_movies');
if (!defined('GUI_EVENT_KEY_MUSIC')) define('GUI_EVENT_KEY_MUSIC', 'key_music');
if (!defined('GUI_EVENT_KEY_ANGLE')) define('GUI_EVENT_KEY_ANGLE', 'key_angle');
# Discrete Power Control
if (!defined('GUI_EVENT_DISCRETE_POWER_ON')) define('GUI_EVENT_DISCRETE_POWER_ON', 'key_discrete_power_on');
if (!defined('GUI_EVENT_DISCRETE_POWER_OFF')) define('GUI_EVENT_DISCRETE_POWER_OFF', 'key_discrete_power_off');

# Dune colors const's.
# Common:
const DEF_LABEL_TEXT_COLOR_BLACK        = 0;  #0x000000	Black						IPTV plugin playback time and number of EPG item
const DEF_LABEL_TEXT_COLOR_BLUE         = 1;  #0x0000a0	Blue						unknown
const DEF_LABEL_TEXT_COLOR_PALEGREEN    = 2;  #0xc0e0c0	Light light grin			unknown
const DEF_LABEL_TEXT_COLOR_LIGHTBLUE    = 3;  #0xa0c0ff	Light blue					unknown
const DEF_LABEL_TEXT_COLOR_RED          = 4;  #0xff4040	Red							Symbol R Recorded Channel Kartina TV
const DEF_LABEL_TEXT_COLOR_LIMEGREEN    = 5;  #0xc0ff40	Light green					unknown
const DEF_LABEL_TEXT_COLOR_GOLD         = 6;  #0xffe040	Light yellow				unknown
const DEF_LABEL_TEXT_COLOR_SILVER       = 7;  #0xc0c0c0	Light grey					File browser (right sub description)
const DEF_LABEL_TEXT_COLOR_GRAY         = 8;  #0x808080	Grey						IPTV plugin playback, categories
const DEF_LABEL_TEXT_COLOR_VIOLET       = 9;  #0x4040c0	Violet						unknown
const DEF_LABEL_TEXT_COLOR_GREEN        = 10; #0x40ff40	Green						VOD description rating(IMDB..)
const DEF_LABEL_TEXT_COLOR_TURQUOISE    = 11; #0x40ffff	Cyan						unknown
const DEF_LABEL_TEXT_COLOR_ORANGE       = 12; #0xff8040	Orange						unknown
const DEF_LABEL_TEXT_COLOR_MAGENTA      = 13; #0xff40ff	Purple						unknown
const DEF_LABEL_TEXT_COLOR_LIGHTYELLOW  = 14; #0xffff40	Light yellow				Widget(time, temp), path (last item), messages, IPTV playback (channels number, )
const DEF_LABEL_TEXT_COLOR_WHITE        = 15; #0xffffe0	White						Main color, widget, combobox etc
# Extra:
const DEF_LABEL_TEXT_COLOR_DARKGRAY     = 16; #0x404040	Dark grey					Color buttons,
const DEF_LABEL_TEXT_COLOR_DIMGRAY      = 17; #0xaaaaa0	Grey						Some VOD description text
const DEF_LABEL_TEXT_COLOR_YELLOW       = 18; #0xffff00	Yellow						VOD descr
const DEF_LABEL_TEXT_COLOR_LIGHTGREEN   = 19; #0x50ff50	Green						VOD descr
const DEF_LABEL_TEXT_COLOR_SKYBLUE      = 20; #0x5080ff	Blue						VOD descr
const DEF_LABEL_TEXT_COLOR_CORAL        = 21; #0xff5030	Light red					VOD descr
const DEF_LABEL_TEXT_COLOR_DARKGRAY2    = 22; #0x404040	Dark grey					VOD descr
const DEF_LABEL_TEXT_COLOR_GAINSBORO    = 23; #0xe0e0e0	Light light light grey		P+ P-

const CMD_STATUS_GREP = '" /firmware/ext_command/cgi-bin/do | grep "command_status" | sed -n "s|^<param name=\"command_status\" value=\"(.*)\"/>|\1|p"';

///////////////////////////////////////////////////////////////////////////////

class LogSeverity
{
    /**
     * @var bool
     */
    public static $is_debug = false;
}

class KnownCatchupSourceTags
{
    const cu_unknown     = 'unknown';
    const cu_default     = 'default'; // only replace variables
    const cu_shift       = 'shift'; // ?utc=startUnix&lutc=nowUnix
    const cu_append      = 'append'; // appending value specified in catchup-source attribute to the base channel url
    const cu_archive     = 'archive'; // ?archive=startUnix&archive_end=toUnix
    const cu_timeshift   = 'timeshift'; // timeshift=startUnix&timenow=nowUnix
    const cu_flussonic   = 'flussonic';
    const cu_xstreamcode = 'xs'; // xstream codes

    public static $catchup_tags = array(
        self::cu_default => array('default'),
        self::cu_shift => array('shift', 'archive'),
        self::cu_append => array('append'),
        self::cu_archive => array('archive'),
        self::cu_timeshift => array('timeshift'),
        self::cu_flussonic => array('flussonic', 'flussonic-hls', 'fs', 'flussonic-ts'),
        self::cu_xstreamcode => array('xc'),
    );

    /**
     * @param $tag
     * @param mixed $value
     * @return bool
     */
    public static function is_tag($tag, $value)
    {
        if (!isset(self::$catchup_tags[$tag]))
            return false;

        if (is_array($value)) {
            foreach ($value as $item) {
                if (in_array($item, self::$catchup_tags[$tag])) {
                    return true;
                }
            }
            return false;
        }
        return in_array($value, self::$catchup_tags[$tag]);
    }
}

class SetupControlSwitchDefs
{
    const switch_on  = 'yes';
    const switch_off = 'no';

    public static $on_off_translated = array
    (
        self::switch_on => '%tr%yes',
        self::switch_off => '%tr%no',
    );

    public static $on_off_img = array
    (
        self::switch_on => 'on.png',
        self::switch_off => 'off.png',
    );
}

# Video zoom values for media_url string (|||dune_params|||zoom:value)
class DuneVideoZoomPresets
{
    const not_set = 'x';
    const normal = '0';
    const enlarge = '1';
    const make_wider = '2';
    const fill_screen = '3';
    const full_fill_screen = '4';
    const make_taller = '5';
    const cut_edges = '6';
    const full_enlarge = '8';
    const full_stretch = '9';

    public static $zoom_value = array(
        DuneVideoZoomPresets::normal => VIDEO_ZOOM_NORMAL,
        DuneVideoZoomPresets::enlarge => VIDEO_ZOOM_ENLARGE,
        DuneVideoZoomPresets::make_wider => VIDEO_ZOOM_MAKE_WIDER,
        DuneVideoZoomPresets::fill_screen => VIDEO_ZOOM_NON_LINEAR_STRETCH,
        DuneVideoZoomPresets::full_fill_screen => VIDEO_ZOOM_NON_LINEAR_STRETCH_TO_FULL_SCREEN,
        DuneVideoZoomPresets::make_taller => VIDEO_ZOOM_MAKE_TALLER,
        DuneVideoZoomPresets::cut_edges => VIDEO_ZOOM_CUT_EDGES,
        DuneVideoZoomPresets::full_enlarge => VIDEO_ZOOM_FULL_SCREEN,
        DuneVideoZoomPresets::full_stretch => VIDEO_ZOOM_STRETCH_TO_FULL_SCREEN
    );

    public static $zoom_ops = array(
        DuneVideoZoomPresets::not_set => 'tv_screen_zoom_not_set',
        DuneVideoZoomPresets::normal => 'tv_screen_zoom_normal',
        DuneVideoZoomPresets::enlarge => 'tv_screen_zoom_enlarge',
        DuneVideoZoomPresets::make_wider => 'tv_screen_zoom_make_wider',
        DuneVideoZoomPresets::fill_screen => 'tv_screen_zoom_fill_screen',
        DuneVideoZoomPresets::full_fill_screen => 'tv_screen_zoom_full_fill_screen',
        DuneVideoZoomPresets::make_taller => 'tv_screen_zoom_make_taller',
        DuneVideoZoomPresets::cut_edges => 'tv_screen_zoom_cut_edges',
        DuneVideoZoomPresets::full_enlarge => 'tv_screen_zoom_full_enlarge',
        DuneVideoZoomPresets::full_stretch => 'tv_screen_zoom_full_stretch'
    );
}

// Deinterlacing modes for media_url string (|||dune_params|||deint:value)
class DuneParamsDeintMode
{
    const    off = '1';
    const    bob = '0';
    const    adaptive = '4';
    const    adaptive_plus = '5';
}

class DuneIrControl
{
    public static $key_codes = array(
        GUI_EVENT_KEY_VENDOR            => '0',
        GUI_EVENT_KEY_ENTER             => 'EB14BF00',
        GUI_EVENT_KEY_PLAY              => 'B748BF00',
        GUI_EVENT_KEY_A_RED             => 'BF40BF00',
        GUI_EVENT_KEY_B_GREEN           => 'E01FBF00',
        GUI_EVENT_KEY_C_YELLOW          => 'FF00BF00',
        GUI_EVENT_KEY_D_BLUE            => 'BE41BF00',
        GUI_EVENT_KEY_POPUP_MENU        => 'F807BF00',
        GUI_EVENT_KEY_INFO              => 'AF50BF00',
        GUI_EVENT_KEY_LEFT              => 'E817BF00',
        GUI_EVENT_KEY_RIGHT             => 'E718BF00',
        GUI_EVENT_KEY_UP                => 'EA15BF00',
        GUI_EVENT_KEY_DOWN              => 'E916BF00',
        GUI_EVENT_KEY_P_PLUS            => 'B44BBF00',
        GUI_EVENT_KEY_P_MINUS           => 'B34CBF00',
        GUI_EVENT_KEY_NEXT              => 'E21DBF00',
        GUI_EVENT_KEY_PREV              => 'B649BF00',
        GUI_EVENT_KEY_SETUP             => 'B14EBF00',
        GUI_EVENT_KEY_RETURN            => 'FB04BF00',
        GUI_EVENT_KEY_SELECT            => 'BD42BF00',
        GUI_EVENT_KEY_CLEAR             => 'FA05BF00',
        GUI_EVENT_KEY_PAUSE             => 'E11EBF00',
        GUI_EVENT_KEY_FWD               => 'E41BBF00',
        GUI_EVENT_KEY_REW               => 'E31CBF00',
        GUI_EVENT_KEY_SLOW              => 'E51ABF00',
        GUI_EVENT_KEY_STOP              => 'E619BF00',
        GUI_EVENT_KEY_TOP_MENU          => 'AE51BF00',
        GUI_EVENT_KEY_POWER             => 'BC43BF00',
        GUI_EVENT_KEY_EJECT             => 'EF10BF00',
        GUI_EVENT_KEY_MODE              => 'BA45BF00',
        GUI_EVENT_KEY_MUTE              => 'B946BF00',
        GUI_EVENT_KEY_V_PLUS            => 'AD52BF00',
        GUI_EVENT_KEY_V_MINUS           => 'AC53BF00',
        GUI_EVENT_KEY_SEARCH            => 'F906BF00',
        GUI_EVENT_KEY_ZOOM              => 'FD02BF00',
        GUI_EVENT_KEY_SUBTITLE          => 'AB54BF00',
        GUI_EVENT_KEY_REPEAT            => 'B24DBF00',
        GUI_EVENT_KEY_AUDIO             => 'BB44BF00',
        GUI_EVENT_KEY_REC               => '9F60BF00',
        GUI_EVENT_KEY_DUNE              => '9E61BF00',
        GUI_EVENT_KEY_URL               => '9D62BF00',
        GUI_EVENT_KEY_0                 => 'F50ABF00',
        GUI_EVENT_KEY_1                 => 'F40BBF00',
        GUI_EVENT_KEY_2                 => 'F30CBF00',
        GUI_EVENT_KEY_3                 => 'F20DBF00',
        GUI_EVENT_KEY_4                 => 'F10EBF00',
        GUI_EVENT_KEY_5                 => 'F00FBF00',
        GUI_EVENT_KEY_6                 => 'FE01BF00',
        GUI_EVENT_KEY_7                 => 'EE11BF00',
        GUI_EVENT_KEY_8                 => 'ED12BF00',
        GUI_EVENT_KEY_9                 => 'EC13BF00',
        GUI_EVENT_KEY_SHUFFLE           => 'B847BF00',
        GUI_EVENT_KEY_KEYBRD            => 'FC03BF00',
        GUI_EVENT_KEY_MOUSE             => 'B04FBF00',
        GUI_EVENT_KEY_RECENT            => '9E61BF00',
        GUI_EVENT_KEY_TV                => '9C63BF00',
        GUI_EVENT_KEY_MOVIES            => 'B847BF00',
        GUI_EVENT_KEY_MUSIC             => 'A758BF00',
        GUI_EVENT_KEY_ANGLE             => 'B24DBF00',
        GUI_EVENT_DISCRETE_POWER_ON     => 'A05FBF00',
        GUI_EVENT_DISCRETE_POWER_OFF    => 'A15EBF00');
}


###############################################################################
# System functions
###############################################################################

function print_backtrace()
{
    hd_print("Back trace:");
    foreach (debug_backtrace() as $f) {
        hd_print("  - {$f['function']} at {$f['file']}:{$f['line']}");
    }
}

/**
 * @param mixed $val
 * @param bool $is_debug
 * @return void
 */
function hd_debug_print($val = null, $is_debug = false)
{
    if ($is_debug && !LogSeverity::$is_debug)
        return;

    $bt = debug_backtrace();
    $caller = array_shift($bt);
    $caller_name = array_shift($bt);
    $prefix = "(" . str_pad($caller['line'], 4) . ") ";
    if (isset($caller_name['class'])) {
        if (!is_null($val)) {
            $val = str_replace(array('"{', '}"', '\"'), array('{', '}', '"'), (string)raw_json_encode($val));
        }
        $prefix .= "{$caller_name['class']}::";
    }
    $prefix .= "{$caller_name['function']}(): ";

    if ($val === null) {
        $val = '';
        $parent_caller = array_shift($bt);
        if (!isset($caller_name['line'])) {
            $prefix .= "unknown line: $caller ";
            print_backtrace();
        } else {
            $prefix .= "called from: (". str_pad($caller_name['line'], 4) . ") ";
        }
        if (isset($parent_caller['class'])) {
            $prefix .= "{$parent_caller['class']}:";
        }

        $prefix .= "{$parent_caller['function']}(): ";
    }
    else if (is_bool($val)) {
        $val = $val ? 'true' : 'false';
    }

    hd_print($prefix . $val);
}

function hd_debug_print_separator()
{
    hd_print(str_repeat("-", 80));
}

/**
 * return is shell is APK.
 * @return bool
 */
function is_apk(){
    return (bool) getenv("HD_APK");
}

/**
 * return type of platform: android, apk, 8670, etc.
 * @return array
 */
function get_platform_info()
{
    static $platform = null;

    if (is_null($platform)){
        if (getenv("HD_APK")) {
            $platform['platform'] = 'android';
            $platform['type'] = 'apk';
        } else {
            $ini_arr = parse_ini_file('/tmp/run/versions.txt');
            if (isset($ini_arr['platform_kind'])) {
                if ($ini_arr['platform_kind'] === 'android') {
                    $platform['platform'] = $ini_arr['platform_kind'];
                    if (isset($ini_arr['android_platform'])) {
                        $platform['type'] = $ini_arr['android_platform'];
                    } else {
                        $platform['type'] = "not android";
                    }
                } else {
                    $platform['platform'] = 'sigma';
                    $platform['type'] = $ini_arr['platform_kind'];
                }
            } else {
                $platform['platform'] = 'unknown';
                $platform['type'] = 'unknown';
            }
        }
    }
    return $platform;
}

function get_bug_platform_kind()
{
    static $bug_platform_kind = null;

    if (is_null($bug_platform_kind)) {
        $v = get_platform_info();
        if ($v['platform'] !== 'android') {
            $bug_platform_kind = ($v['type'] === '8672' || $v['type'] === '8673' || $v['type'] === '8758');
        }
    }
    return $bug_platform_kind;
}

/**
 * return product id
 * @return string
 */
function get_product_id()
{
    static $result = null;

    if (is_null($result)) {
        $result = trim(shell_exec('grep "product_id:" /tmp/sysinfo.txt | sed "s/^.*: *//"'));
    }

    return $result;
}

/**
 * return firmware version
 * @return string
 */
function get_raw_firmware_version()
{
    static $result = null;

    if (is_null($result)) {
        $result = trim(shell_exec('grep "firmware_version:" /tmp/sysinfo.txt | sed "s/^.*: *//"'));
    }

    return $result;
}

/**
 * @param bool $is_debug
 * @return void
 */
function set_debug_log($is_debug)
{
    hd_print("Set debug logging: " . var_export($is_debug, true));
    LogSeverity::$is_debug = $is_debug;
}

/**
 * return firmware version
 * @return array
 */
function get_parsed_firmware_ver()
{
    //   (
    //     'string' => '150729_0139_r10_js_stb_opera33',
    //     'build_date' => '150729',
    //     'build_number' => '0139',
    //     'rev_literal => 'r' or 'b',
    //     'rev_number' => '10',
    //     'features' => 'js_stb_opera33',
    //	)

    static $result = null;

    if (is_null($result)) {
        preg_match_all('/^(\d*)_(\d*)_(\D*)(\d*)(.*)?$/', get_raw_firmware_version(), $matches, PREG_SET_ORDER);
        $matches[0][5] = ltrim($matches[0][5], '_');
        $result = array_combine(array('string', 'build_date', 'build_number', 'rev_literal', 'rev_number', 'features'), $matches[0]);
    }

    return is_array($result) ? $result : array();
}

/**
 * return serial number
 * @return string
 */
function get_serial_number()
{
    static $result = null;

    if (is_null($result)) {
        $result = trim(shell_exec('grep "^serial_number:" /tmp/sysinfo.txt | sed "s/^.*: *//"'));
    }

    return $result;
}

/**
 * @return string
 */
function get_ip_address()
{
    $v = get_platform_info();
    if ($v['type'] === '8670') {
        $active_network_connection = parse_ini_file('/tmp/run/active_network_connection.txt', 0, INI_SCANNER_RAW);
        $ip = isset($active_network_connection['ip']) ? trim($active_network_connection['ip']) : '';
    } else {
        $ip = trim(shell_exec('ifconfig eth0 2>/dev/null | head -2 | tail -1 | sed "s/^.*inet addr:\([^ ]*\).*$/\1/"'));
        if (!is_numeric(preg_replace('/\s|\./', '', $ip))) {
            $ip = trim(shell_exec('ifconfig wlan0 2>/dev/null | head -2 | tail -1 | sed "s/^.*inet addr:\([^ ]*\).*$/\1/"'));
            if (!is_numeric(preg_replace('/\s|\./', '', $ip))) {
                $ip = '';
            }
        }
    }

    return $ip;
}

/**
 * @return string
 */
function get_dns_address()
{
    $platform = get_platform_info();
    if ($platform['platform'] === 'android') {
        $dns = explode(PHP_EOL, shell_exec('getprop | grep "net.dns"'));
    } else {
        $dns = explode(PHP_EOL, shell_exec('cat /etc/resolv.conf | grep "nameserver"'));
    }

    $addr = '';
    foreach ($dns as $key => $server) {
        if (preg_match("|(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})|", $server, $m)) {
            $addr .= "nameserver" . ($key + 1) . ": " . $m[1] . ", ";
        }
    }

    return $addr;
}

/**
 * @return string|null
 */
function get_mac_address()
{
    static $mac_addr = null;

    if (is_null($mac_addr)) {
        if (is_apk()) {
            $mac_addr = file_exists('/tmp/run/dune_mac.txt') ? strtoupper(trim(shell_exec('cat $FS_PREFIX/tmp/run/dune_mac.txt'))) : '';
        } else {
            $mac_addr = trim(shell_exec('ifconfig eth0 | head -1 | sed "s/^.*HWaddr //"'));
        }
    }

    return $mac_addr;
}

/**
 * get timezone string from /etc/TZ, for example GMT-01
 * @return string
 */
function get_local_tz()
{
    exec((file_exists('/etc/TZ') ? 'TZ=`cat /etc/TZ` ' : '') . 'date +%z', $tz, $rc);
    return (($rc !== 0) || (count($tz) !== 1) || !is_numeric($tz[0])) ? '' : $tz[0];
}

/**
 * @return int|string
 */
function get_local_time_zone_offset()
{
    // return integer of offset in seconds
    $local_tz = get_local_tz();

    if ($local_tz !== '') {
        $sign_ch = $local_tz[0];
        if ($sign_ch === '-') {
            $sign = -1;
        } else if ($sign_ch === '+') {
            $sign = +1;
        } else {
            return '';
        }

        $tz_hh = (int)substr($local_tz, 1, 2);
        $tz_mm = (int)substr($local_tz, 3, 2);

        return $sign * ($tz_hh * 60 + $tz_mm) * 60;
    }

    return 0;
}

/**
 * Get timezone or UTC +???? offset name
 * @return string
 */
function getTimeZone()
{
    $local_tz = get_local_tz();

    $tz = date('e');
    if ($tz !== 'UTC') {
        return "$tz (UTC$local_tz)";
    }

    if ($local_tz !== '') {
        $sign_ch = $local_tz[0];
        $tz_hh = substr($local_tz, 1, 2);
        $tz_mm = substr($local_tz, 3, 2);
        return "UTC $sign_ch$tz_hh$tz_mm";
    }
    return "UTC";
}

/**
 * @return bool
 */
function is_android()
{
    return is_file("/system/dunehd/init");
}

/**
 * @param string $format
 * @return string
 * @throws Exception
 */
function getAndroidTime ($format)
{
    $airDate = exec('date');
    $date = new DateTime($airDate);
    if ($format === 'timestamp') {
        return strtotime($date->format('Y-m-d H:i:s'));
    }

    return $date->format($format);
}

/**
 * format timestamp
 * @param int $ts
 * @param string|null $fmt
 * @return string
 * @throws Exception
 */
function format_timestamp($ts, $fmt = 'Y:m:d H:i:s')
{
    // NOTE: for some reason, explicit timezone is required for PHP
    // on Dune (no builtin timezone info?).

    $dt = new DateTime('@' . $ts);
    return $dt->format($fmt);
}

/**
 * Format time using current timezone offset
 * @param string $fmt
 * @param int $ts
 * @return string
 */
function format_datetime($fmt, $ts)
{
    $tz_str = date_default_timezone_get();
    if ($tz_str === 'UTC') {
        $ts += get_local_time_zone_offset();
    }

    return date($fmt, $ts);
}

/**
 * @param string $ticks
 * @return string
 */
function format_duration($ticks, $point = false)
{
    $n = (int)$ticks;
    if ($n <= 0 || strlen($ticks) <= 0) {
        return "--:--";
    }

    $hours = (int)($n / 3600000);
    $remainder = $n % 3600000;
    $minutes = (int)($remainder / 60000);
    $seconds = (int)(($remainder % 60000) / 1000);
    if ($point) {
        $msecond = $remainder % 1000;
    }

    if ($hours > 0) {
        return sprintf("%d:%02d:%02d", $hours, $minutes, $seconds);
    }

    if ($point) {
        return sprintf("%02d:%02d.%03d", $minutes, $seconds, $msecond);
    }

    return sprintf("%02d:%02d", $minutes, $seconds);
}

/**
 * @param string $secs
 * @return string
 */
function format_duration_seconds($secs)
{
    $n = (int)$secs;

    if ($n <= 0 || strlen($secs) <= 0) {
        return "--:--";
    }

    $hours = $n / 3600;
    $remainder = $n % 3600;
    $minutes = $remainder / 60;
    $seconds = $remainder % 60;

    if ($hours > 0) {
        return sprintf("%d:%02d:%02d", $hours, $minutes, $seconds);
    }

    return sprintf("%02d:%02d", $minutes, $seconds);
}

/**
 * @throws Exception
 */
function is_need_daylight_fix()
{
    $utc = new DateTimeZone('Europe/Moscow');
    $date = new DateTime('now', new DateTimeZone('UTC'));
    $diff = $utc->getOffset($date);
    return (date('I') === '1' && $diff !== 10800);
}

/**
 * @param string $cmd
 * @return string
 */
function get_shell_exec($cmd)
{
    return rtrim(shell_exec($cmd), "\n");
}

/**
 * @param $key
 * @return false|string|null
 */
function send_ir_code($key)
{
    if (isset(DuneIrControl::$key_codes[$key])) {
        return shell_exec('echo ' . DuneIrControl::$key_codes[$key] . ' > /proc/ir/button');
    }

    hd_debug_print("Error in class " . get_class($this) . "::" . __FUNCTION__ . "! Code of key '$key' not found in base!");
    return '0';
}

/**
 * @param $key
 * @return string
 */
function send_ir_code_return_status($key)
{
    # return string (command execution status)

    if (isset(DuneIrControl::$key_codes[$key])) {
        $cmd = 'env REQUEST_METHOD="GET" QUERY_STRING="cmd=ir_code&ir_code=' . DuneIrControl::$key_codes[$key] . CMD_STATUS_GREP;
        return get_shell_exec($cmd);
    }

    hd_debug_print("Error in class " . get_class($this) . "::" . __FUNCTION__ . "! Code of key '$key' not found in base!");
    return '0';
}

/**
 * @return string
 */
function get_player_state()
{
    # return string (PLAYER_STATE_STANDBY | PLAYER_STATE_BLACK_SCREEN | PLAYER_STATE_NAVIGATOR | PLAYER_STATE_FILE_PLAYBACK | PLAYER_STATE_DVD_PLAYBACK | PLAYER_STATE_BLURAY_PLAYBACK)

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "player_state" | sed -n "s/^.*player_state = /\1/p"';
    return get_shell_exec($cmd);
}

/**
 * @return array|false
 */
function get_player_state_assoc()
{
    # return array

    return parse_ini_file('/tmp/run/ext_command.state', 0, INI_SCANNER_RAW);
}

/**
 * @return int
 */
function get_standby_mode()
{
    $cmd = 'cat /tmp/run/ext_command.state | grep -w "player_state" | sed -n "s/^.*player_state = /\1/p"';
    return (int)(get_shell_exec($cmd) === PLAYER_STATE_STANDBY);
}

/**
 * @param $mode
 * @return string
 */
function set_standby_mode($mode)
{
    # return string (command execution status)
    # argument values (STANDBY_MODE_OFF | STANDBY_MODE_ON)

    $cmd = 'env REQUEST_METHOD="GET" QUERY_STRING="cmd=ir_code&ir_code=' . (($mode === STANDBY_MODE_OFF) ? GUI_EVENT_DISCRETE_POWER_ON : GUI_EVENT_DISCRETE_POWER_OFF) . CMD_STATUS_GREP;
    return get_shell_exec($cmd);
}

/**
 * @param string $setting
 * @return string|null
 */
function get_shell_settings($setting)
{
    $settings = array();
    if (file_exists('/config/settings.properties')) {
        $settings = parse_ini_file('/config/settings.properties', 0, INI_SCANNER_RAW);
    }

    return isset($settings[$setting]) ? $settings[$setting] : '';
}

###############################################################################
# Playback controls
###############################################################################

/**
 * @return string
 */
function get_playback_state()
{
    # return string (PLAYBACK_STOPPED | PLAYBACK_INITIALIZING | PLAYBACK_PLAYING | PLAYBACK_PAUSED | PLAYBACK_SEEKING | PLAYBACK_BUFFERING | PLAYBACK_FINISHED | PLAYBACK_DEINITIALIZING)

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "playback_state" | sed -n "s/^.*playback_state = /\1/p"';
    return get_shell_exec($cmd);
}

/**
 * @return string
 */
function stop()
{
    # return string (command execution status)

    $cmd = 'env REQUEST_METHOD="GET" QUERY_STRING="cmd=light_stop"' . CMD_STATUS_GREP;
    return get_shell_exec($cmd);
}

/**
 * @return bool
 */
function can_pause()
{
    $cmd = 'cat /tmp/run/ext_command.state | grep -w "pause_is_available" | sed -n "s/^.*pause_is_available = /\1/p"';
    return get_shell_exec($cmd) === '1';
}

/**
 * @return string
 */
function pause()
{
    # return string (command execution status)

    return set_speed(0);
}

/**
 * @return string
 */
function resume()
{
    # return string (command execution status)
    return set_speed(256);
}

/**
 * @return string
 */
function get_speed()
{
    # return string			(-8192|-4096|-2048|-1024|-512|-256| -128|  -64|  -32|   -16|    -8|    0|    8|   16|   32|  64| 128|  256|  512| 1024| 2048|  4096|  8192)
    # corresponds speed		( -32x| -16x|  -8x|  -4x| -2x| -1x|-1/2x|-1/4x|-1/8x|-1/16x|-1/32x|pause|1/32x|1/16x| 1/8x|1/4x|1/2x|   1x|   2x|   4x|   8x|   16x|   32x)

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "playback_speed" | sed -n "s/^.*playback_speed = /\1/p"';
    return get_shell_exec($cmd);
}

/**
 * @param $value
 * @return string
 */
function set_speed($value)
{
    # return string (command execution status)
    # argument values (-8192|-4096|-2048|-1024|-512|-256|-128|-64|-32|-16|-8|0|8|16|32|64|128|256|512|1024|2048|4096|8192)

    $cmd = 'env REQUEST_METHOD="GET" QUERY_STRING="cmd=set_playback_state&speed=' . $value . CMD_STATUS_GREP;
    return get_shell_exec($cmd);
}

/**
 * @return string
 */
function get_length_seconds()
{
    # return string

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "playback_duration" | sed -n "s/^.*playback_duration = /\1/p"';
    return get_shell_exec($cmd);
}

/**
 * @return bool
 */
function has_length()
{
    # return boolean (true if length of media is known)

    return get_length_seconds() > 0;
}

/**
 * @return string
 */
function get_position_seconds()
{
    # return string
    $cmd = 'cat /tmp/run/ext_command.state | grep -w "playback_position" | sed -n "s/^.*playback_position = /\1/p"';
    return get_shell_exec($cmd);
}

/**
 * @param $seconds
 * @return string
 */
function set_position_seconds($seconds)
{
    # return string (command execution status)
    $cmd = 'env REQUEST_METHOD="GET" QUERY_STRING="cmd=' . (empty($seconds) ? 'status' : 'set_playback_state&position=' . $seconds) . CMD_STATUS_GREP;
    return get_shell_exec($cmd);
}

/**
 * @param $speed
 * @param $seconds
 * @return string
 */
function set_speed_and_position_seconds($speed, $seconds)
{
    # return string (command execution status)

    $cmd = 'env REQUEST_METHOD="GET" QUERY_STRING="cmd=' . ((empty($speed) || empty($seconds)) ? 'status' : 'set_playback_state&speed=' . $speed . '&position=' . $seconds) . CMD_STATUS_GREP;
    return get_shell_exec($cmd);
}

/**
 * @return bool
 */
function is_scrambling_detected()
{
    # return boolean

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "scrambling_detected" | sed -n "s/^.*scrambling_detected = /\1/p"';
    return get_shell_exec($cmd) === '1';
}

/**
 * @return string
 */
function get_segment_length_seconds()
{
    # return string (length of one media segment for segment-based media, for HLS returns the value of X-EXT-TARGETDURATION)

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "segment_length" | sed -n "s/^.*segment_length = /\1/p"';
    return get_shell_exec($cmd);
}

/**
 * @return string
 */
function get_playback_url()
{
    # return string

    $url = rtrim(shell_exec('cat /tmp/run/ext_command.state | grep -w "playback_url" | sed -n "s/^.*playback_url = /\1/p"'), "\n");

    if (preg_match_all('/\/\/(127\.0\.0\.1|localhost).*((htt|rt|rtc|rts|ud)p:\/\/.*$)/i', $url, $matches) && !empty($matches[2][0])) {
        return $matches[2][0];
    }

    return $url;
}


###############################################################################
# Audio controls
###############################################################################

function get_volume()
{
    # return string (value 0..100 - current volume in percents)

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "playback_volume" | sed -n "s/^.*playback_volume = /\1/p"';
    return get_shell_exec($cmd);
}

function set_volume($percents /*0-100*/)
{
    # return string (command execution status)

    $cmd = 'env REQUEST_METHOD="GET" QUERY_STRING="cmd=set_playback_state&volume=' . $percents . CMD_STATUS_GREP;
    return get_shell_exec($cmd);
}

function is_mute_enabled()
{
    # return boolean

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "playback_mute" | sed -n "s/^.*playback_mute = /\1/p"';
    return get_shell_exec($cmd) === '1';
}

function toggle_mute($mute = 1)
{
    # return string (command execution status)

    $cmd = 'env REQUEST_METHOD="GET" QUERY_STRING="cmd=set_playback_state&mute=' . $mute . CMD_STATUS_GREP;
    return get_shell_exec($cmd);
}

function get_audio_tracks_description()
{
    # return array(
    #   [first_track_index] =>
    #       array(
    #          'lang' => [value],
    #          'pid' => [value],
    #          'codec' => [value],
    #          'type' => [value]),
    #   [next_track_index] =>
    #       array (
    #          'lang' => [value],
    #          'pid' => [value],
    #          'codec' => [value],
    #          'type' => [value]),...)

    preg_match_all('/audio_track\.(\d)\.(.*)\s=\s(.*$)/mx', file_get_contents('/tmp/run/ext_command.state'), $matches);

    $result = array();

    foreach ($matches[1] as $key => $value) {
        $result[$value][$matches[2][$key]] = $matches[3][$key];
    }

    return $result;
}

function get_audio_track()
{
    # Return: 0..N - current audio track index

    return
        rtrim(shell_exec('cat /tmp/run/ext_command.state | grep -w "audio_track" | sed -n "s/^.*audio_track = /\1/p"'), "\n");
}

function set_audio_track($track)
{
    # Argument: 0..N - audio track index
    # Return: command execution status

    $cmd = 'env REQUEST_METHOD="GET" QUERY_STRING="cmd=set_playback_state&audio_track=' . $track . CMD_STATUS_GREP;
    return get_shell_exec($cmd);
}


###############################################################################
# Teletext controls (playback in TV mode)
###############################################################################

function is_teletext_available()
{
    # Return: boolean value of teletext available in the current stream

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "teletext_available" | sed -n "s/^.*teletext_available = /\1/p"';
    return get_shell_exec($cmd);
}

function is_teletext_enabled()
{
    # Return: boolean value of teletext mode is turned

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "teletext_enabled" | sed -n "s/^.*teletext_enabled = /\1/p"';
    return get_shell_exec($cmd);
}

function toggle_teletext($enable = 1)
{
    # Return: command execution status

    $cmd = 'env REQUEST_METHOD="GET" QUERY_STRING="cmd=set_playback_state&teletext_enabled=' . $enable . CMD_STATUS_GREP;
    return get_shell_exec($cmd);
}

function get_teletext_page_number()
{
    # Return: string value of current teletext page number

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "teletext_page_number" | sed -n "s/^.*teletext_page_number = /\1/p"';
    return get_shell_exec($cmd);
}

function set_teletext_page_number($value)
{
    # Argument: 100..899 - page number
    # Return: command execution status

    $cmd = 'env REQUEST_METHOD="GET" QUERY_STRING="cmd=set_playback_state&teletext_page_number=' . $value . CMD_STATUS_GREP;
    return get_shell_exec($cmd);
}

function is_teletext_mix_mode_enabled()
{
    # Return: boolean value of teletext mix mode is turned

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "teletext_mix_mode" | sed -n "s/^.*teletext_enabled = /\1/p"';
    return get_shell_exec($cmd) === '1';
}

function set_teletext_mix_mode($mode = 1)
{
    # Return: command execution status

    $cmd = 'env REQUEST_METHOD="GET" QUERY_STRING="cmd=set_playback_state&teletext_mix_mode=' . $mode . CMD_STATUS_GREP;
    return get_shell_exec($cmd);
}

###############################################################################
# Video controls
###############################################################################

function is_video_enabled()
{
    # Returns true if primary video showing during primary video playback is enabled.
    # When primary video playback is performed and primary video showing is disabled,
    # primary video playback internally runs in the usual way, but primary video is
    # hidden. By default, primary video showing is enabled.

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "video_enabled" | sed -n "s/^.*video_enabled = /\1/p"';
    return get_shell_exec($cmd) === '1';
}

function toggle_video($enable = 1)
{
    # Return: command execution status

    $cmd = 'env REQUEST_METHOD="GET" QUERY_STRING="cmd=set_playback_state&video_enabled=' . $enable . CMD_STATUS_GREP;
    return get_shell_exec($cmd);
}

function get_video_zorder()
{
    # Return: string value of current Z-order of primary video

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "video_zorder" | sed -n "s/^.*video_zorder = /\1/p"';
    return get_shell_exec($cmd);
}

function set_video_zorder($value)
{
    # Sets Z-order of primary video. If primary video playback is not running or primary
    # video showing is disabled, primary video layer is absent and this setting has no
    # effect. Z-ordering of primary video, PiP video and OSD layers works in the following
    # way. Layers with greater Z-order are put above layers with lesser Z-order. Default
    # Z-order values for primary video, PiP video, OSD are: 200, 400, 500. If some of the
    # layers (primary video, PiP video, OSD) have the same Z-order, the following ordering
    # rules are used: OSD is above PiP video, PiP video is above primary video.
    # Argument: 0..1000 - Z-order
    # Return: command execution status

    $cmd = 'env REQUEST_METHOD="GET" QUERY_STRING="cmd=set_playback_state&video_zorder=' . $value . CMD_STATUS_GREP;
    return get_shell_exec($cmd);
}

function get_osd_zorder()
{
    # Return: string value of current Z-order of OSD

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "osd_zorder" | sed -n "s/^.*osd_zorder = /\1/p"';
    return get_shell_exec($cmd);
}

function set_osd_zorder($value)
{
    # Argument: 0..1000 - Z-order
    # Return: command execution status

    $cmd = 'env REQUEST_METHOD="GET" QUERY_STRING="cmd=set_playback_state&osd_zorder=' . $value . CMD_STATUS_GREP;
    return get_shell_exec($cmd);
}

function is_video_on_top()
{
    # Return: true if primary video has Z-order greater than Z-order of OSD

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "video_on_top" | sed -n "s/^.*video_on_top = /\1/p"';
    return get_shell_exec($cmd);
}

function toggle_video_on_top($enable = 1)
{
    # Puts primary video above OSD. The function is equivalent to setVideoZOrder(900) and
    # setOSDZOrder(500). NOTE: This function is intended to be used by applications which
    # do not use PiP video; if PiP video is used, it is recommended not to use this function
    # and instead use set{Video,PIP,OSD}ZOrder functions directly.
    # Return: command execution status

    $cmd = 'env REQUEST_METHOD="GET" QUERY_STRING="cmd=set_playback_state&video_on_top=' . $enable . CMD_STATUS_GREP;
    return get_shell_exec($cmd);
}

function is_window_full_screen()
{
    # Return: boolean value of window full screen mode enabled

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "playback_window_fullscreen" | sed -n "s/^.*playback_window_fullscreen = /\1/p"';
    return get_shell_exec($cmd) === '1';
}

function enable_window_full_screen()
{
    # Return: command execution status

    $cmd = 'env REQUEST_METHOD="GET" QUERY_STRING="cmd=set_playback_state&window_fullscreen=1"' . CMD_STATUS_GREP;
    return get_shell_exec($cmd);
}

function get_window_rect_x()
{
    # Return: string value of window rect x

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "playback_window_rect_x" | sed -n "s/^.*playback_window_rect_x = /\1/p"';
    return get_shell_exec($cmd);
}

function get_window_rect_y()
{
    # Return: string value of window rect y

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "playback_window_rect_y" | sed -n "s/^.*playback_window_rect_y = /\1/p"';
    return get_shell_exec($cmd);
}

function get_window_rect_width()
{
    # Return: string value of window rect width

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "playback_window_rect_width" | sed -n "s/^.*playback_window_rect_width = /\1/p"';
    return get_shell_exec($cmd);
}

function get_window_rect_height()
{
    # Return: string value of window rect height

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "playback_window_rect_height" | sed -n "s/^.*playback_window_rect_height = /\1/p"';
    return get_shell_exec($cmd);
}

function set_window_rect($x, $y, $width, $height)
{
    # Return: command execution status

    $cmd = 'env REQUEST_METHOD="GET" QUERY_STRING="cmd=set_playback_state&window_fullscreen=0&window_rect_x=' . $x . '&window_rect_y=' . $y . '&window_rect_width=' . $width . '&window_rect_height=' . $height . CMD_STATUS_GREP;
    return get_shell_exec($cmd);
}

function get_clip_rect_x()
{
    # Return: string value of clip rect x

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "playback_clip_rect_x" | sed -n "s/^.*playback_clip_rect_x = /\1/p"';
    return get_shell_exec($cmd);
}

function get_clip_rect_y()
{
    # Return: string value of clip rect y

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "playback_clip_rect_y" | sed -n "s/^.*playback_clip_rect_y = /\1/p"';
    return get_shell_exec($cmd);
}

function get_clip_rect_width()
{
    # Return: string value of clip rect width

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "playback_clip_rect_width" | sed -n "s/^.*playback_clip_rect_width = /\1/p"';
    return get_shell_exec($cmd);
}

function get_clip_rect_height()
{
    # Return: string value of clip rect height

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "playback_clip_rect_height" | sed -n "s/^.*playback_clip_rect_height = /\1/p"';
    return get_shell_exec($cmd);
}

function set_clip_rect($x, $y, $width, $height)
{
    # Return: command execution status

    $cmd = 'env REQUEST_METHOD="GET" QUERY_STRING="cmd=set_playback_state&clip_rect_x=' . $x . '&clip_rect_y=' . $y . '&clip_rect_width=' . $width . '&clip_rect_height=' . $height . CMD_STATUS_GREP;
    return get_shell_exec($cmd);
}

function get_video_source_rect_x()
{
    # Return: string value of video source rect x

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "playback_video_source_rect_x" | sed -n "s/^.*playback_video_source_rect_x = /\1/p"';
    return get_shell_exec($cmd);
}

function get_video_source_rect_y()
{
    # Return: string value of video source rect y

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "playback_video_source_rect_y" | sed -n "s/^.*playback_video_source_rect_y = /\1/p"';
    return get_shell_exec($cmd);
}

function get_video_source_rect_width()
{
    # Return: string value of video source rect width

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "playback_video_source_rect_width" | sed -n "s/^.*playback_video_source_rect_width = /\1/p"';
    return get_shell_exec($cmd);
}

function get_video_source_rect_height()
{
    # Return: string value of video source rect height

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "playback_video_source_rect_height" | sed -n "s/^.*playback_video_source_rect_height = /\1/p"';
    return get_shell_exec($cmd);
}

function set_video_source_rect($x, $y, $width, $height)
{
    # Sets coordinates of a portion of the original video for displaying in window or on full
    # screen as specified via set_window_rect(). The specification of the portion coordinates is
    # in 0-4096 scale for all arguments instead of OSD size scale in set_window_rect() and
    # set_clip_rect() thus set_video_source_rect(0, 0, 4096, 4096) specifies complete original video.
    # The coordinates are specified relative to the original video dimensions with no dependence
    # on display/window aspect ratio so if there is, say, 4:3 video that should be displayed in a
    # 3:1 window and set_video_source_rect(0, 0, 1024, 4096) is called then the resulting video will
    # have 1:3 aspect ratio with appropriate black bars added in the window if normal zoom mode is
    # specified. The video source rectangle specification is also compatible with
    # VIDEO_ZOOM_STRETCH_TO_FULL_SCREEN preset, in this case the portion of the original video is
    # stretched to full screen or window as expected. Other zoom presets are not compatible, just
    # like with set_clip_rect() call - set_video_source_rect() fails if an incompatible zoom preset is
    # set and set_video_zoom() fails with an incompatible zoom preset if a partial video source
    # rectangle is already in effect. Note that both video source rectangle and clip rectangle may
    # be specified with set_video_source_rect() and set_clip_rect() respectively but if both are in effect
    # then the clip rectangle setting is ignored until set_video_source_rect() is set to complete
    # original video so programmer should take care to avoid such situations.
    # Return: command execution status

    $cmd = 'env REQUEST_METHOD="GET" QUERY_STRING="cmd=set_playback_state&video_source_rect_x=' . $x . '&video_source_rect_y=' . $y . '&video_source_rect_width=' . $width . '&video_source_rect_height=' . $height . CMD_STATUS_GREP;
    return get_shell_exec($cmd);
}

function get_video_width()
{
    # Return: string value of current video width

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "playback_video_width" | sed -n "s/^.*playback_video_width = /\1/p"';
    return get_shell_exec($cmd);
}

function get_video_height()
{
    # Return: string value of current video height

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "playback_video_height" | sed -n "s/^.*playback_video_height = /\1/p"';
    return get_shell_exec($cmd);
}

function get_video_zoom()
{
    # Return: string value of current video zoom

    $cmd = 'cat /tmp/run/ext_command.state | grep -w "video_zoom" | sed -n "s/^.*video_zoom = /\1/p"';
    return get_shell_exec($cmd);
}

/**
 * @param $value string
 * @return string
 */
function set_video_zoom($value)
{
    # Argument: VIDEO_ZOOM_NORMAL | VIDEO_ZOOM_ENLARGE | VIDEO_ZOOM_MAKE_WIDER | VIDEO_ZOOM_NON_LINEAR_STRETCH |
    #           VIDEO_ZOOM_NON_LINEAR_STRETCH_TO_FULL_SCREEN | VIDEO_ZOOM_MAKE_TALLER | VIDEO_ZOOM_CUT_EDGES |
    #           VIDEO_ZOOM_FULL_SCREEN | VIDEO_ZOOM_STRETCH_TO_FULL_SCREEN
    # Return: command execution status

    $cmd = 'env REQUEST_METHOD="GET" QUERY_STRING="cmd=set_playback_state&video_zoom=' . $value . CMD_STATUS_GREP;
    return get_shell_exec($cmd);
}

/**
 * @param $preset DuneVideoZoomPresets::const
 * @return string
 */
function get_zoom_value($preset)
{
    return isset(DuneVideoZoomPresets::$zoom_value[$preset])
        ? DuneVideoZoomPresets::$zoom_value[$preset]
        : DuneVideoZoomPresets::$zoom_value[DuneVideoZoomPresets::normal];
}

###############################################################################
# Storage access
###############################################################################

function get_temp_path($path = '')
{
    return DuneSystem::$properties['tmp_dir_path'] . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
}

function get_data_path($path = '')
{
    return DuneSystem::$properties['data_dir_path'] . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
}

function get_install_path($path = '')
{
    return DuneSystem::$properties['install_dir_path'] . DIRECTORY_SEPARATOR . ltrim($path, DIRECTORY_SEPARATOR);
}

function get_plugin_cgi_url($path = '')
{
    return DuneSystem::$properties['plugin_cgi_url'] . ltrim($path, DIRECTORY_SEPARATOR);
}

function get_plugin_www_url($path = '')
{
    return DuneSystem::$properties['plugin_www_url'] . ltrim($path, DIRECTORY_SEPARATOR);
}

function get_plugin_name()
{
    return DuneSystem::$properties['plugin_name'];
}

/**
 * @param string $image
 * @return string
 */
function get_image_path($image = '')
{
    return get_install_path("img" . DIRECTORY_SEPARATOR . ltrim($image, DIRECTORY_SEPARATOR));
}

/**
 * @param string $image
 * @return string
 */
function get_cached_image_path($image = '')
{
    $cache_image_path = get_data_path(CACHED_IMAGE_SUBDIR);
    create_path($cache_image_path);
    return $cache_image_path . DIRECTORY_SEPARATOR . ltrim($image, DIRECTORY_SEPARATOR);
}

function get_plugin_manifest_info()
{
    $result = array();
    try {
        $manifest_path = get_install_path("dune_plugin.xml");
        if (!file_exists($manifest_path)) {
            throw new Exception("Plugin manifest not found!");
        }

        $xml = HD::parse_xml_file($manifest_path);
        if ($xml === null) {
            throw new Exception("Empty plugin manifest!");
        }

        $result['app_name'] = (string)$xml->name;
        $result['app_caption'] = (string)$xml->caption;
        $result['app_class_name'] = (string)$xml->class_name;
        $result['app_version'] = (string)$xml->version;
        $ver = explode('.', $result['app_version']);
        $result['app_base_version'] = "$ver[0].$ver[1]";
        $result['app_version_idx'] = isset($xml->version_index) ? (string)$xml->version_index : '0';
        $result['app_release_date'] = (string)$xml->release_date;
        $result['app_background'] = (string)$xml->background;
        $result['app_manifest_path'] = $manifest_path;

        foreach(func_get_args() as $node_name) {
            $result[$node_name] = json_decode(json_encode($xml->xpath("//$node_name")), true);
        }
    } catch (Exception $ex) {
        hd_debug_print($ex->getMessage());
    }

    return $result;
}

/**
 * @return array array of local storages
 */
function get_local_storages_list($path)
{
    $i = 0;
    $result = array();

    foreach (scandir($path) as $item) {
        if (($item === '.') || ($item === '..') || !is_dir($path . DIRECTORY_SEPARATOR . $item)) {
            continue;
        }

        $disk_name = '';

        foreach (explode('_', $item) as $chunk) {
            $disk_name .= is_numeric('0x' . $chunk) ? '' : $chunk;
        }

        $alias = (($disk_name === 'DuneHDD') ? 'D:' : 'usb' . $i++ . ':');
        $result['list'][$item] = $alias;
        $result['names'][] = $item;
        $result['aliases'][] = $alias;
        $result['labels'][] = (($disk_name === 'usbstorage') ? '' : $disk_name);
    }

    return $result;
}

###############################################################################
# Miscellaneous
###############################################################################

function is_newer_versions()
{
    $versions = get_parsed_firmware_ver();

    return (isset($versions['rev_number']) && $versions['rev_number'] > 10);
}


function get_active_skin_path()
{
    # Returns the path to the directory of the active skin (no trailing slash)

    if (file_exists('/tmp/dune_skin_dir.txt')) {
        return rtrim(trim(preg_replace('/^.*=/', '', file_get_contents('/tmp/dune_skin_dir.txt'))), DIRECTORY_SEPARATOR);
    }

    hd_debug_print("Error in class " . __METHOD__ . " ! Can not determine the path to the active skin.");
    return '';
}

# Returns the specified path (no trailing slash), creating directories along the way
function get_paved_path($path, $dir_mode = 0777)
{
    if (!create_path($path, $dir_mode)) {
        hd_debug_print("Directory '$path' was not created");
    }

    return rtrim($path, DIRECTORY_SEPARATOR);
}

function get_slash_trailed_path($path)
{
    if (!empty($path) && substr($path, -1) !== DIRECTORY_SEPARATOR) {
        $path .= DIRECTORY_SEPARATOR;
    }

    return $path;
}

function get_filename($path)
{
    $ar = explode(DIRECTORY_SEPARATOR, $path);
    return (count($ar) === 1) ? $path : end($ar);
}

# creating directories along the way
function create_path($path, $dir_mode = 0777)
{
    if (!file_exists($path) && !@mkdir($path, $dir_mode, true) && !is_dir($path)) {
        hd_debug_print("Directory '$path' was not created");
        return false;
    }

    return true;
}

/** @noinspection PhpUnusedParameterInspection */
function json_encode_unicode($data, $flags = 0)
{
    # Analog of json_encode() with the JSON_UNESCAPED_UNICODE option available in PHP 5.4.0 and higher

    array_walk_recursive(
        $data,
        function (&$item, $key) {
            if (is_string($item)) {
                $item = mb_encode_numericentity($item, array(0x80, 0xffff, 0, 0xffff), 'UTF-8');
            }
        }
    );

    return mb_decode_numericentity(json_encode($data), array(0x80, 0xffff, 0, 0xffff), 'UTF-8');
}

function print_sysinfo()
{
    hd_debug_print_separator();
    $platform = get_platform_info();
    $dns = get_dns_address();
    $values = curl_version();
    $table = array(
        'Dune Product' => get_product_id(),
        'Dune Model' => get_dune_model(),
        'Dune FW' => get_raw_firmware_version(),
        'Dune Serial' => get_serial_number(),
        'Dune Platform' => "{$platform['platform']} ({$platform['type']})",
        'Dune MAC Addr' => get_mac_address(),
        'Dune IP Addr' => get_ip_address(),
        'Dune DNS servers' => $dns,
        'PHP Version' => PHP_VERSION,
        'libCURL Version' => "{$values['version']} ({$values['ssl_version']})",
    );

    if (class_exists("SQLite3")) {
        $sqlite_ver = SQLite3::version();
        $table['SQLite3 Version'] = $sqlite_ver['versionString'];
    }

    $table = array_merge($table, DuneSystem::$properties);

    $max = 0;
    foreach (array_keys($table) as $key) {
        $max = max(strlen($key), $max);
    }
    foreach ($table as $key => $value) {
        hd_print(str_pad($key, $max + 2) . $value);
    }
}

/**
 * @return string Model name
 */
function get_dune_model() {
    static $models = array(
        // android models
        'boxy_apk' => 'Boxy',
        'dune_apk' => 'Homatics Box',
        // android models
        'tv173b' => 'Neo 4K (revision tv173b)',
        'tv174a' => 'Neo 4K T2',
        'tv174b' => 'Neo 4K T2 Plus (revision tv174b)',
        'tv174c' => 'Neo 4K T2 Plus (revsion tv174c)',
        'tv175e' => 'Neo 4K (revision tv175e)',
        'tv175f' => 'Neo 4K Plus',
        'tv175h' => 'Pro 4K II',
        'tv175j' => 'Pro 4K Plus II',
        'tv175l' => 'SmartBox 4K',
        'tv175n' => 'SmartBox 4K Plus',
        'tv175o' => 'SmartBox 4K Plus II',
        'tv175p' => 'Traveler',
        'tv175q' => 'Magic 4K',
        'tv175r' => 'Magic 4K Plus',
        'tv175u' => 'Real Vision 4K',
        'tv175v' => 'Pro Vision 4K Solo',
        'tv175x' => 'RealBox 4K',
        'tv175y' => 'Real Vision 4K Plus',
        'tv274a' => 'Sky 4K Plus',
        'tv288b' => 'Pro One 8K Plus',
        'tv292a' => 'Pro 4K',
        'tv292b' => 'Pro 4K',
        'tv393a' => 'Pro 4K Plus',
        'tv494b' => 'Real Vision 4K Duo',
        'tv793a' => 'Max 4K',
        'tv794a' => 'Max 4K Vision',
        'tv993a' => 'Ultra 4K',
        'tv994a' => 'Ultra 4K Vision',

        // sigma chipsets r11
        // SMP8672
        'tv303d' => 'TV 303D',
        'tv303d2' => 'TV 303D v2',
        'base3d' => 'Base3D',
        'base3d2' => 'Base3D v2',
        // SMP8674
        'connect' => 'Connect',
        'tv102' => 'TV 102',
        'tv102v2' => 'TV 102 v2',
        // SMP8756
        'tv203' => 'TV 203',
        'tv204' => 'TV 204',
        // SMP8758
        'tv205' => 'TV 205',
        'tv206' => 'TV 206',
        'solo4k' => 'Solo 4K',
        'sololite' => 'Solo Lite',
        'duo4k' => 'Duo 4K',
        'duobase4k' => 'Duo Base 4K',
        //
        'hdduo' => 'HD Duo',
        'hdmax' => 'HD Max',
        'hdsmart_b1' => 'HD Smart B1',
        'hdsmart_d1' => 'HD Smart D1',
        'hdsmart_h1' => 'HD Smart H1',
        'hdbase3' => 'HD Base 3.0',
        'bdprime3' => 'HD Prime 3.0',

        // sigma chipsets < r11 (not supported)
        // SMP8670
        'hdtv_301' => 'HD TV 301',
        'hdtv_102p' => 'HD TV 102p',
        'hdtv_101' => 'HD TV 101',
        'hdlite_53d' => 'HD Lite 53D',
        'hdbase2' => 'HD Base 2.0',
        'hdcenter_sony' => 'HD Center',
        'bdprime_sony' => 'BD Prime',
        'hdmini' => 'HD Mini',
        'hdultra' => 'HD Ultra',
    );

    $product_code = get_product_id();
    return isset($models[$product_code]) ? $models[$product_code] : "Unknown model";
}

function get_canonize_string($str, $encoding = 'UTF-8')
{
    # Returns canonized string in lowercase

    return str_replace(
        array('а', 'в', 'е', 'к', 'м', 'н', 'о', 'р', 'с', 'т', 'у', 'х'),
        array('a', 'b', 'e', 'k', 'm', 'h', 'o', 'p', 'c', 't', 'y', 'x'),
        mb_strtolower(trim($str), $encoding));
}

function get_system_language_string_value($string_key)
{
    # Returns a string constant in the system language by key

    if ($sys_settings = parse_ini_file('/config/settings.properties', false, INI_SCANNER_RAW)) {
        $sys_lang = file_exists('/firmware/translations/dune_language_' . $sys_settings['interface_language'] . '.txt') ? $sys_settings['interface_language'] : 'english';
        if (($lang_txt = file_get_contents("/firmware/translations/dune_language_$sys_lang.txt")) &&
            preg_match("/^$string_key\\s*=(.*)$/m", $lang_txt, $m)) {
            return trim($m[1]);
        }
    }

    hd_debug_print("Error in class " . __METHOD__ . " ! Not found value for key '$string_key'!");
    return '';
}

function debug_print(/*mixed $var1, $var2...*/)
{
    if (!is_null($backtrace = debug_backtrace(false))) {
        $var = $chain = '';

        foreach (func_get_args() as $value) {
            $var .= "\n" . trim(var_export($value, true), "'");
        }

        for ($i = count($backtrace) - 4; $i > 1; $i--) {
            if ($backtrace[$i - 1]['class'] !== 'User_Input_Handler_Registry') {
                $chain .= $backtrace[$i - 1]['class'] . '::' . $backtrace[$i - 1]['function'] . '()->';
            }
        }

        hd_debug_print("Debug alert! " . rtrim($chain, '->') . (empty($var) ? '' : ' >> ') . ltrim($var, "\n"));
    }
}

/**
 * @param Object $user_input
 * @return void
 */
function dump_input_handler($user_input)
{
    if (!LogSeverity::$is_debug)
        return;

    hd_debug_print(null, true);

    foreach ($user_input as $key => $value) {
        $decoded_value = html_entity_decode(preg_replace("/(\\\u([0-9A-Fa-f]{4}))/", "&#x\\2;", $value), ENT_NOQUOTES, 'UTF-8');
        hd_print("  $key => $decoded_value");
    }
}

/**
 * Replace for glob (not works with non ansi symbols in path)
 *
 * @param string $path
 * @param string $pattern regex pattern
 * @param bool $exclude_dir
 * @return array
 */
function glob_dir($path, $pattern = null, $exclude_dir = true)
{
    $list = array();
    $path = rtrim($path, DIRECTORY_SEPARATOR);
    if (is_dir($path)) {
        $files = array_diff(scandir($path), array('.', '..'));
        if ($pattern !== null) {
            $files = preg_grep($pattern, $files);
        }

        if ($files !== false) {
            foreach ($files as $file) {
                $full_path = $path . DIRECTORY_SEPARATOR . $file;
                if ($exclude_dir && !is_file($full_path)) continue;

                $list[] = $full_path;
            }
        }
    }
    return $list;
}

function delete_directory($dir)
{
    if (!file_exists($dir)) {
        return true;
    }

    if (!is_dir($dir)) {
        return unlink($dir);
    }

    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        if (!delete_directory($dir . DIRECTORY_SEPARATOR . $item)) {
            return false;
        }
    }

    return rmdir($dir);
}

/**
 * This is more efficient then merge_array in the loops
 *
 * @param array $ar1
 * @param array|null $ar2
 * @return array
 */
function safe_merge_array($ar1, $ar2)
{
    if (is_array($ar2)) {
        foreach ($ar2 as $key => $itm) {
            $ar1[$key] = $itm;
        }
    }

    return $ar1;
}

function raw_json_encode($arr)
{
    $pattern = "/\\\\u([0-9a-fA-F]{4})/";
    $callback = function ($m) {
        return html_entity_decode("&#x$m[1];", ENT_QUOTES, 'UTF-8');
    };

    return str_replace('\\/', '/', preg_replace_callback($pattern, $callback, json_encode($arr)));
}

/**
 * return wrapped string
 *
 * @param $long_string
 * @param $max_chars
 * @return string
 */
function wrap_string_to_lines($long_string, $max_chars)
{
    $lines = array_slice(
        explode(PHP_EOL,
            iconv('Windows-1251', 'UTF-8',
                wordwrap(iconv('UTF-8', 'Windows-1251',
                    trim(preg_replace('/([!?])\.+\s*$/Uu', '$1', $long_string))),
                    $max_chars, PHP_EOL, true))
        ),
        0, 2
    );

    return implode(PHP_EOL, $lines);
}

function is_assoc_array($array){
    $keys = array_keys($array);
    return $keys !== array_keys($keys);
}

function register_all_known_events($handler, &$actions)
{
    $all_events = array(
        GUI_EVENT_KEY_NEXT,
        GUI_EVENT_KEY_PREV,
        GUI_EVENT_KEY_FIP_NEXT,
        GUI_EVENT_KEY_FIP_PREV,
        GUI_EVENT_KEY_SETUP,
        GUI_EVENT_KEY_RETURN,
        GUI_EVENT_KEY_SELECT,
        GUI_EVENT_KEY_CLEAR,
        GUI_EVENT_KEY_PAUSE,
        GUI_EVENT_KEY_FWD,
        GUI_EVENT_KEY_REW,
        GUI_EVENT_KEY_SLOW,
        GUI_EVENT_KEY_STOP,
        GUI_EVENT_KEY_TOP_MENU,
        GUI_EVENT_KEY_POWER,
        GUI_EVENT_KEY_EJECT,
        GUI_EVENT_KEY_MODE,
        GUI_EVENT_KEY_VENDOR,
        GUI_EVENT_KEY_SHUFFLE,
        GUI_EVENT_KEY_MUSIC,
        GUI_EVENT_KEY_MUTE,
        GUI_EVENT_KEY_SEARCH,
        GUI_EVENT_KEY_ZOOM,
        GUI_EVENT_KEY_SUBTITLE,
        GUI_EVENT_KEY_REPEAT,
        GUI_EVENT_KEY_AUDIO,
        GUI_EVENT_KEY_REC,
        GUI_EVENT_KEY_DUNE,
        GUI_EVENT_KEY_URL,
        GUI_EVENT_UI_ACTION,
        GUI_EVENT_TIMER,
        GUI_EVENT_PLUGIN_ROWS_INFO_UPDATE,
        GUI_EVENT_FOLDER_LEAVE,
        GUI_EVENT_FOLDER_ENTER,
        GUI_EVENT_FOLDER_RETURN_BACK,
        GUI_EVENT_PLAYBACK_USER_ACTION,
        GUI_EVENT_PLAYBACK_STATE_CHANGED,
        GUI_EVENT_MENU_PLAYBACK_OSD_CLOSED,
        GUI_EVENT_MENU_PLAYBACK_OSD_GOING_TO_OPEN,
        GUI_EVENT_MENU_PLAYBACK_FINISH,
        GUI_EVENT_MENU_EXT_APP_FINISH,
        GUI_EVENT_WEB_BROWSER_FINISH,
        GUI_EVENT_GOING_TO_UPDATE,
        GUI_EVENT_GOING_TO_STOP,
        GUI_EVENT_TOPMENU_POPUP_MENU,
        GUI_EVENT_GOING_TO_RELOAD_ALL_FOLDERS,
        GUI_EVENT_FAVORITES_UPDATED,
        GUI_EVENT_SHUTDOWN,
    );

    foreach ($all_events as $action) {
        if (!isset($actions[$action])) {
            hd_debug_print("register event: $action");
            $actions[$action] = User_Input_Handler_Registry::create_action($handler, $action);
        }
    }
}
