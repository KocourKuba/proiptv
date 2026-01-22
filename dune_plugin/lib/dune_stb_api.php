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
const PLAYER_STATE = 'player_state';
const PLAYBACK_STATE = 'playback_state';
const PLAYBACK_POSITION = 'playback_position';
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
const LAST_PLAYBACK_EVENT = 'last_playback_event';

const CACHED_IMAGE_SUBDIR = 'cached_img';

const ACCEPT_JSON = 'Accept: application/json';
const CONTENT_TYPE_JSON = 'Content-Type: application/json; charset=utf-8';
const CONTENT_TYPE_WWW_FORM_URLENCODED = 'Content-Type: application/x-www-form-urlencoded';

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
if (!defined('GUI_EVENT_KEY_FAVORITES')) define('GUI_EVENT_KEY_FAVORITES', 'key_favorites');
# Discrete Power Control
if (!defined('GUI_EVENT_DISCRETE_POWER_ON')) define('GUI_EVENT_DISCRETE_POWER_ON', 'key_discrete_power_on');
if (!defined('GUI_EVENT_DISCRETE_POWER_OFF')) define('GUI_EVENT_DISCRETE_POWER_OFF', 'key_discrete_power_off');

# Google IR special controls
if (!defined('GUI_EVENT_KEY_ACCOUNT')) define('GUI_EVENT_KEY_ACCOUNT', 'key_account');
if (!defined('GUI_EVENT_KEY_BOOKMARK')) define('GUI_EVENT_KEY_BOOKMARK', 'key_bookmark');
if (!defined('GUI_EVENT_KEY_YOUTUBE')) define('GUI_EVENT_KEY_YOUTUBE', 'key_youtube');
if (!defined('GUI_EVENT_KEY_NETFLIX')) define('GUI_EVENT_KEY_NETFLIX', 'key_netflix');
if (!defined('GUI_EVENT_KEY_PRIME')) define('GUI_EVENT_KEY_PRIME', 'key_prime');
if (!defined('GUI_EVENT_KEY_APP_CUSTOM')) define('GUI_EVENT_KEY_APP_CUSTOM', 'key_app_custom');

if (!defined('PHP_INT_MIN')) define('PHP_INT_MIN', ~PHP_INT_MAX);

if (!defined('JSON_UNESCAPED_SLASHES')) define("JSON_UNESCAPED_SLASHES", 64);
if (!defined('JSON_PRETTY_PRINT')) define('JSON_PRETTY_PRINT', 128);
if (!defined('JSON_UNESCAPED_UNICODE')) define('JSON_UNESCAPED_UNICODE', 256);

# Dune colors const's.
# Common:                               idx     default palette                 Silver      (new)
const DEF_LABEL_TEXT_COLOR_BLACK        = 0;  # '000000'	Black				'000000'	IPTV plugin playback time and number of EPG item
const DEF_LABEL_TEXT_COLOR_BLUE         = 1;  # '0000a0'	Blue				'0000a0'	unknown
const DEF_LABEL_TEXT_COLOR_PALEGREEN    = 2;  # 'c0e0c0'	Light light green  	'797979'	unknown
const DEF_LABEL_TEXT_COLOR_LIGHTBLUE    = 3;  # 'a0c0ff'	Light blue			'797979'	unknown
const DEF_LABEL_TEXT_COLOR_RED          = 4;  # 'ff4040'	Red					'8b0000'	Symbol R Recorded Channel Kartina TV
const DEF_LABEL_TEXT_COLOR_LIMEGREEN    = 5;  # 'c0ff40'	Light green			'797979'	unknown
const DEF_LABEL_TEXT_COLOR_GOLD         = 6;  # 'ffe040'	Gold (Light yellow)	'797979'	unknown
const DEF_LABEL_TEXT_COLOR_SILVER       = 7;  # 'c0c0c0'	Silver  			'bfbfbf'	File browser (right sub description)
const DEF_LABEL_TEXT_COLOR_GRAY         = 8;  # '808080'	Grey				'797979'	IPTV plugin playback, categories
const DEF_LABEL_TEXT_COLOR_VIOLET       = 9;  # '4040c0'	Violet				'797979'	unknown
const DEF_LABEL_TEXT_COLOR_GREEN        = 10; # '40ff40'	Green				'797979'	VOD description rating(IMDB..)
const DEF_LABEL_TEXT_COLOR_TURQUOISE    = 11; # '40ffff'	Turquoise (Cyan)	'797979'	unknown
const DEF_LABEL_TEXT_COLOR_ORANGE       = 12; # 'ff8040'	Orange				'797979'	unknown
const DEF_LABEL_TEXT_COLOR_MAGENTA      = 13; # 'ff40ff'	Purple				'797979'	unknown
const DEF_LABEL_TEXT_COLOR_LIGHTYELLOW  = 14; # 'ffff40'	Light yellow		'fffdf3'	Widget(time, temp), path, messages, IPTV playback (channels number)
const DEF_LABEL_TEXT_COLOR_WHITE        = 15; # 'ffffe0'	White				'a9a9a9'	Main color, widget, combobox etc
# Extra:
const DEF_LABEL_TEXT_COLOR_DARKGRAY     = 16; # '404040'	Dark grey			'797979'	Color buttons,
const DEF_LABEL_TEXT_COLOR_DIMGRAY      = 17; # 'aaaaa0'	Grey				'797979'	Some VOD description text
const DEF_LABEL_TEXT_COLOR_YELLOW       = 18; # 'ffff00'	Yellow				'797979'	VOD descr
const DEF_LABEL_TEXT_COLOR_LIGHTGREEN   = 19; # '50ff50'	Green				'797979'	VOD descr
const DEF_LABEL_TEXT_COLOR_SKYBLUE      = 20; # '5080ff'	Blue				'797979'	VOD descr
const DEF_LABEL_TEXT_COLOR_CORAL        = 21; # 'ff5030'	Coral (Light red)	'797979'	VOD descr
const DEF_LABEL_TEXT_COLOR_DARKGRAY2    = 22; # '404040'	Dark grey 2			'797979'	VOD descr
const DEF_LABEL_TEXT_COLOR_GAINSBORO    = 23; # 'e0e0e0'	Light light grey	'797979'	P+ P-

const CMD_STATUS_GREP = '" /firmware/ext_command/cgi-bin/do | grep "command_status" | sed -n "s|^<param name=\"command_status\" value=\"(.*)\"/>|\1|p"';

static $dune_default_colors_values = array(
//  DEF_LABEL_TEXT_COLOR_BLACK 			=> '000000',
//  DEF_LABEL_TEXT_COLOR_BLUE 			=> '0000a0',
    DEF_LABEL_TEXT_COLOR_PALEGREEN 		=> 'c0e0c0',
    DEF_LABEL_TEXT_COLOR_LIGHTBLUE 		=> 'a0c0ff',
//  DEF_LABEL_TEXT_COLOR_RED            => 'c0c0c0',
    DEF_LABEL_TEXT_COLOR_LIMEGREEN 		=> 'c0ff40',
    DEF_LABEL_TEXT_COLOR_GOLD 			=> 'ffe040',
//  DEF_LABEL_TEXT_COLOR_SILVER         => 'c0c0c0',
    DEF_LABEL_TEXT_COLOR_GRAY           => '808080',
    DEF_LABEL_TEXT_COLOR_VIOLET 		=> '4040c0',
    DEF_LABEL_TEXT_COLOR_GREEN   		=> '40ff40',
    DEF_LABEL_TEXT_COLOR_TURQUOISE 		=> '40ffff',
    DEF_LABEL_TEXT_COLOR_ORANGE 		=> 'ff8040',
    DEF_LABEL_TEXT_COLOR_MAGENTA 		=> 'ff40ff',
//    DEF_LABEL_TEXT_COLOR_LIGHTYELLOW    => 'ffff40',
    DEF_LABEL_TEXT_COLOR_WHITE			=> 'ffffe0',
    DEF_LABEL_TEXT_COLOR_DARKGRAY       => '404040',
    DEF_LABEL_TEXT_COLOR_DIMGRAY        => 'aaaaa0',
    DEF_LABEL_TEXT_COLOR_YELLOW 		=> 'ffff00',
    DEF_LABEL_TEXT_COLOR_LIGHTGREEN 	=> '50ff50',
    DEF_LABEL_TEXT_COLOR_SKYBLUE 		=> '5080ff',
    DEF_LABEL_TEXT_COLOR_CORAL 			=> 'ff5030',
    DEF_LABEL_TEXT_COLOR_DARKGRAY2 		=> '404040',
    DEF_LABEL_TEXT_COLOR_GAINSBORO 		=> 'e0e0e0',
);

static $dune_air_colors_values = array(
    DEF_LABEL_TEXT_COLOR_BLACK 			=> '000000',
    DEF_LABEL_TEXT_COLOR_BLUE 			=> '0000a0',
    DEF_LABEL_TEXT_COLOR_PALEGREEN 		=> '797979',
    DEF_LABEL_TEXT_COLOR_LIGHTBLUE 		=> '797979',
    DEF_LABEL_TEXT_COLOR_RED            => '8b0000',
    DEF_LABEL_TEXT_COLOR_LIMEGREEN 		=> '797979',
    DEF_LABEL_TEXT_COLOR_GOLD 			=> '797979',
    DEF_LABEL_TEXT_COLOR_SILVER         => 'bfbfbf',
    DEF_LABEL_TEXT_COLOR_GRAY           => '797979',
    DEF_LABEL_TEXT_COLOR_VIOLET 		=> '797979',
    DEF_LABEL_TEXT_COLOR_GREEN   		=> '797979',
    DEF_LABEL_TEXT_COLOR_TURQUOISE 		=> '797979',
    DEF_LABEL_TEXT_COLOR_ORANGE 		=> '797979',
    DEF_LABEL_TEXT_COLOR_MAGENTA 		=> '797979',
    DEF_LABEL_TEXT_COLOR_LIGHTYELLOW    => 'fffdf3',
    DEF_LABEL_TEXT_COLOR_WHITE			=> 'a9a9a9',
    DEF_LABEL_TEXT_COLOR_DARKGRAY       => '797979',
    DEF_LABEL_TEXT_COLOR_DIMGRAY        => '797979',
    DEF_LABEL_TEXT_COLOR_YELLOW 		=> '797979',
    DEF_LABEL_TEXT_COLOR_LIGHTGREEN 	=> '797979',
    DEF_LABEL_TEXT_COLOR_SKYBLUE 		=> '797979',
    DEF_LABEL_TEXT_COLOR_CORAL 			=> '797979',
    DEF_LABEL_TEXT_COLOR_DARKGRAY2 		=> '797979',
    DEF_LABEL_TEXT_COLOR_GAINSBORO 		=> '797979',
);

///////////////////////////////////////////////////////////////////////////////

class LogSeverity
{
    /**
     * @var bool
     */
    public static $is_debug = false;
}

class SwitchOnOff
{
    const on = 'yes';
    const off = 'no';

    public static $translated = array(
        self::on => '%tr%yes',
        self::off => '%tr%no',
    );

    public static $image = array(
        self::on => 'on.png',
        self::off => 'off.png',
    );

    public static function to_def($val)
    {
        return $val ? self::on : self::off;
    }

    public static function to_bool($val)
    {
        return $val === self::on;
    }

    public static function to_image($val)
    {
        return get_image_path(safe_get_value(self::$image, $val, self::$image[self::off]));
    }

    public static function translate($val)
    {
        return safe_get_value(self::$translated, $val, self::$translated[self::off]);
    }

    public static function translate_from($translated, $val)
    {
        return safe_get_value($translated, $val, $translated[self::off]);
    }

    public static function toggle($val)
    {
        if ($val === self::on) {
            return self::off;
        }
        return self::on;
    }
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
        GUI_EVENT_KEY_AUDIO             => 'BB44BF00',
        GUI_EVENT_KEY_REC               => '9F60BF00',
        GUI_EVENT_KEY_URL               => '9D62BF00', // not works when sent to /proc/ir/button
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
        GUI_EVENT_KEY_MOUSE             => 'B04FBF00',
        GUI_EVENT_KEY_KEYBRD            => 'FC03BF00', // not works when sent to /proc/ir/button
        GUI_EVENT_KEY_DUNE              => '9E61BF00', //FAV1
        GUI_EVENT_KEY_RECENT            => '9E61BF00', //FAV1
        GUI_EVENT_KEY_FAVORITES         => '8B74BF00', //FAV2 not implemented yet in firmware
        GUI_EVENT_KEY_TV                => '9C63BF00',
        GUI_EVENT_KEY_MUSIC             => 'A758BF00',
        GUI_EVENT_KEY_MOVIES            => 'B847BF00',
        GUI_EVENT_KEY_SHUFFLE           => 'B847BF00',
        GUI_EVENT_KEY_REPEAT            => 'B24DBF00', // Reload plugin when called from main screen
        GUI_EVENT_KEY_ANGLE             => 'B24DBF00',
        GUI_EVENT_DISCRETE_POWER_ON     => 'A05FBF00',
        GUI_EVENT_DISCRETE_POWER_OFF    => 'A15EBF00'
    );

    public static $new_key_codes = array(
        GUI_EVENT_KEY_VENDOR            => '0',
        GUI_EVENT_KEY_ENTER             => 'EB14CFCF',
        GUI_EVENT_KEY_PLAY              => 'B748CFCF',
        GUI_EVENT_KEY_A_RED             => 'BF40CFCF',
        GUI_EVENT_KEY_B_GREEN           => 'E01FCFCF',
        GUI_EVENT_KEY_C_YELLOW          => 'FF00CFCF',
        GUI_EVENT_KEY_D_BLUE            => 'BE41CFCF',
        GUI_EVENT_KEY_POPUP_MENU        => 'F807CFCF',
        GUI_EVENT_KEY_INFO              => 'AF50CFCF',
        GUI_EVENT_KEY_LEFT              => 'E817CFCF',
        GUI_EVENT_KEY_RIGHT             => 'E718CFCF',
        GUI_EVENT_KEY_UP                => 'EA15CFCF',
        GUI_EVENT_KEY_DOWN              => 'E916CFCF',
        GUI_EVENT_KEY_P_PLUS            => 'B44BCFCF',
        GUI_EVENT_KEY_P_MINUS           => 'B34CCFCF',
        GUI_EVENT_KEY_NEXT              => 'E21DCFCF',
        GUI_EVENT_KEY_PREV              => 'B649CFCF',
        GUI_EVENT_KEY_SETUP             => 'B14ECFCF',
        GUI_EVENT_KEY_RETURN            => 'FB04CFCF',
        GUI_EVENT_KEY_SELECT            => 'BD42CFCF',
        GUI_EVENT_KEY_CLEAR             => 'FA05CFCF',
        GUI_EVENT_KEY_PAUSE             => 'E11ECFCF',
        GUI_EVENT_KEY_FWD               => 'E41BCFCF',
        GUI_EVENT_KEY_REW               => 'E31CCFCF',
        GUI_EVENT_KEY_SLOW              => 'E51ACFCF',
        GUI_EVENT_KEY_STOP              => 'E619CFCF',
        GUI_EVENT_KEY_TOP_MENU          => 'AE51CFCF',
        GUI_EVENT_KEY_POWER             => 'BC43CFCF',
        GUI_EVENT_KEY_EJECT             => 'EF10CFCF',
        GUI_EVENT_KEY_MODE              => 'BA45CFCF',
        GUI_EVENT_KEY_MUTE              => 'B946CFCF',
        GUI_EVENT_KEY_V_PLUS            => 'AD52CFCF',
        GUI_EVENT_KEY_V_MINUS           => 'AC53CFCF',
        GUI_EVENT_KEY_SEARCH            => 'F906CFCF',
        GUI_EVENT_KEY_ZOOM              => 'FD02CFCF',
        GUI_EVENT_KEY_SUBTITLE          => 'AB54CFCF',
        GUI_EVENT_KEY_AUDIO             => 'BB44CFCF',
        GUI_EVENT_KEY_REC               => '9F60CFCF',
        GUI_EVENT_KEY_URL               => '9D62CFCF', // not works when sent to /proc/ir/button
        GUI_EVENT_KEY_0                 => 'F50ACFCF',
        GUI_EVENT_KEY_1                 => 'F40BCFCF',
        GUI_EVENT_KEY_2                 => 'F30CCFCF',
        GUI_EVENT_KEY_3                 => 'F20DCFCF',
        GUI_EVENT_KEY_4                 => 'F10ECFCF',
        GUI_EVENT_KEY_5                 => 'F00FCFCF',
        GUI_EVENT_KEY_6                 => 'FE01CFCF',
        GUI_EVENT_KEY_7                 => 'EE11CFCF',
        GUI_EVENT_KEY_8                 => 'ED12CFCF',
        GUI_EVENT_KEY_9                 => 'EC13CFCF',
        GUI_EVENT_KEY_MOUSE             => 'B04FCFCF',
        GUI_EVENT_KEY_KEYBRD            => 'FC03CFCF', // not works when sent to /proc/ir/button
        GUI_EVENT_KEY_DUNE              => '9E61CFCF', //FAV1
        GUI_EVENT_KEY_RECENT            => '9E61CFCF', //FAV1
        GUI_EVENT_KEY_FAVORITES         => '8B74CFCF', //FAV2 not implemented yet in firmware
        GUI_EVENT_KEY_TV                => '9C63CFCF',
        GUI_EVENT_KEY_MUSIC             => 'A758CFCF',
        GUI_EVENT_KEY_MOVIES            => 'B847CFCF',
        GUI_EVENT_KEY_SHUFFLE           => 'B847CFCF',
        GUI_EVENT_KEY_REPEAT            => 'B24DCFCF', // Reload plugin when called from main screen
        GUI_EVENT_KEY_ANGLE             => 'B24DCFCF',
        GUI_EVENT_DISCRETE_POWER_ON     => 'A05FCFCF',
        GUI_EVENT_DISCRETE_POWER_OFF    => 'A15ECFCF'
    );

    public static $google_key_codes = array(
        GUI_EVENT_KEY_POWER             => 'DE217788',
        GUI_EVENT_KEY_SETUP             => 'F00F7788',
        GUI_EVENT_KEY_UP                => 'EA157788',
        GUI_EVENT_KEY_LEFT              => 'E8177788',
        GUI_EVENT_KEY_RIGHT             => 'E7187788',
        GUI_EVENT_KEY_DOWN              => 'E9167788',
        GUI_EVENT_KEY_ENTER             => 'E6197788',
        GUI_EVENT_KEY_RETURN            => 'B7487788',
        GUI_EVENT_KEY_TOP_MENU          => 'B8477788',
        GUI_EVENT_KEY_TV                => 'CD327788',
        GUI_EVENT_KEY_MUTE              => 'DA257788',
        GUI_EVENT_KEY_V_PLUS            => 'DC237788',
        GUI_EVENT_KEY_V_MINUS           => 'DB247788',
        GUI_EVENT_KEY_P_PLUS            => 'CC337788',
        GUI_EVENT_KEY_P_MINUS           => 'CB347788',
        GUI_EVENT_KEY_0                 => 'F50A7788',
        GUI_EVENT_KEY_1                 => 'FE017788',
        GUI_EVENT_KEY_2                 => 'FD027788',
        GUI_EVENT_KEY_3                 => 'FC037788',
        GUI_EVENT_KEY_4                 => 'FB047788',
        GUI_EVENT_KEY_5                 => 'FA057788',
        GUI_EVENT_KEY_6                 => 'F9067788',
        GUI_EVENT_KEY_7                 => 'F8077788',
        GUI_EVENT_KEY_8                 => 'F7087788',
        GUI_EVENT_KEY_9                 => 'F6097788',
        GUI_EVENT_KEY_SUBTITLE          => 'A7587788',
        GUI_EVENT_KEY_INFO              => 'D6297788',
        GUI_EVENT_KEY_A_RED             => 'B44B7788',
        GUI_EVENT_KEY_B_GREEN           => 'B54A7788',
        GUI_EVENT_KEY_C_YELLOW          => 'B6497788',
        GUI_EVENT_KEY_D_BLUE            => 'B34C7788',

        GUI_EVENT_KEY_ACCOUNT           => 'A6597788',
        GUI_EVENT_KEY_BOOKMARK          => '8B747788',
        GUI_EVENT_KEY_YOUTUBE           => '9B647788',
        GUI_EVENT_KEY_NETFLIX           => '9C637788',
        GUI_EVENT_KEY_PRIME             => '98677788',
        GUI_EVENT_KEY_APP_CUSTOM        => '97687788',
    );
}


###############################################################################
# System functions
###############################################################################

function print_backtrace()
{
    hd_print("Back trace:");
    foreach (debug_backtrace() as $f) {
        if (!isset($f['file'], $f['line'])) {
            hd_print($f['function'] . ": " . json_encode($f));
            hd_print("  - " . json_encode($f));
            continue;
        }

        $func = safe_get_value($f, 'function', "unknown function");
        hd_print("  - $func at {$f['file']}:{$f['line']}");
    }
}

/**
 * provide a Java style exception trace
 *
 * @param Exception|Throwable $ex
 * @param bool $as_string
 * @param array|null $seen - array passed to recursive calls to accumulate trace lines already seen
 *                leave as NULL when calling this function
 * @return array|string of strings, one entry per trace line
 */
function backtrace_exception($ex, $as_string = false, $seen = null)
{
    $starter = $seen ? 'Caused by: ' : '';
    $result = array();
    if (!$seen) {
        $seen = array();
    }

    $trace = $ex->getTrace();
    $prev = $ex->getPrevious();
    $result[] = sprintf('%s%s: %s', $starter, get_class($ex), $ex->getMessage());
    $file = $ex->getFile();
    $line = $ex->getLine();
    while (true) {
        $current = "$file:$line";
        if (is_array($seen) && in_array($current, $seen)) {
            $result[] = sprintf(' ... %d more', count($trace) + 1);
            break;
        }
        $result[] = sprintf(' at %s%s%s(%s%s%s)',
            count($trace) && array_key_exists('class', $trace[0]) ? str_replace('\\', '.', $trace[0]['class']) : '',
            count($trace) && array_key_exists('class', $trace[0]) && array_key_exists('function', $trace[0]) ? '.' : '',
            count($trace) && array_key_exists('function', $trace[0]) ? str_replace('\\', '.', $trace[0]['function']) : '(main)',
            $line === null ? $file : basename($file),
            $line === null ? '' : ':',
            $line === null ? '' : $line);
        if (is_array($seen))
            $seen[] = "$file:$line";
        if (!count($trace))
            break;
        $file = array_key_exists('file', $trace[0]) ? $trace[0]['file'] : 'Unknown Source';
        $line = array_key_exists('file', $trace[0]) && array_key_exists('line', $trace[0]) && $trace[0]['line'] ? $trace[0]['line'] : null;
        array_shift($trace);
    }

    if ($as_string) {
        $result = implode(PHP_EOL, $result);

        if ($prev) {
            $result .= PHP_EOL . backtrace_exception($prev, $as_string, $seen);
        }
    } else if ($prev) {
        $result = array_merge($result, backtrace_exception($prev, $as_string, $seen));
    }

    return $result;
}

/**
 * print exception backtrace to log
 *
 * @param Exception|Throwable $ex
 * @param bool $as_string
 */
function print_backtrace_exception($ex, $as_string = false)
{
    if ($as_string) {
        hd_debug_print(backtrace_exception($ex, $as_string));
    } else {
        foreach (backtrace_exception($ex, $as_string) as $line) {
            hd_debug_print($line);
        }
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
            $prefix .= "called from: (" . str_pad($caller_name['line'], 4) . ") ";
        }
        if (isset($parent_caller['class'])) {
            $prefix .= "{$parent_caller['class']}:";
        }

        $prefix .= "{$parent_caller['function']}(): ";
    } else if ($val instanceof Json_Serializer) {
        $val = $val->__toString();
    } else if (is_array($val)) {
        if (empty($val)) {
            $val = '{}';
        } else {
            $val = str_replace(array('"{', '}"', '\"'), array('{', '}', '"'), (string)json_format_unescaped($val));
        }
    } else if (is_bool($val)) {
        $val = var_export($val, true);
    }

    hd_print($prefix . $val);
}

function hd_print_separator()
{
    hd_print(str_repeat("-", 80));
}

function hd_debug_print_separator()
{
    if (!LogSeverity::$is_debug)
        return;

    hd_print_separator();
}

/**
 * return is shell is APK.
 * @return bool
 */
function is_apk()
{
    return (bool)getenv("HD_APK");
}

/**
 * return is shell is FW APK.
 * @return bool
 */
function is_fw_apk()
{
    return (bool)getenv("HD_FW_APK");
}

/**
 * return is shell is FW APK.
 * @return bool
 */
function is_limited_apk()
{
    return is_apk() && !is_fw_apk();
}

/**
 * return is runned on dune (andriod) otherwise it runned on windows (debug)
 * @return bool
 */
function is_dune()
{
    return !getenv('windir');
}

/**
 * return type of platform: android, apk, windows
 * @return array
 */
function get_platform_info()
{
    static $platform = null;

    if (is_null($platform)) {
        if (is_apk()) {
            $platform['platform'] = 'android';
            if (is_fw_apk()) {
                $platform['type'] = 'fw_apk';
            } else {
                $platform['type'] = 'apk';
            }
        } else if (!is_dune()) {
            $platform['platform'] = 'windows';
            $platform['type'] = 'test';
        } else {
            $ini_arr = @parse_ini_file(getenv('FS_PREFIX') . '/tmp/run/versions.txt');
            if ($ini_arr === false || (isset($ini_arr['platform_kind']) && $ini_arr['platform_kind'] !== 'android')) {
                $platform['platform'] = 'unsupported';
                $platform['type'] = 'unsupported';
            } else  {
                $platform['platform'] = $ini_arr['platform_kind'];
                $platform['type'] = safe_get_value($ini_arr, 'android_platform', "not android");
            }
        }
    }
    return $platform;
}

function get_platform_curl()
{
    static $curl = null;
    if (is_null($curl)) {
        $v = get_platform_info();
        hd_debug_print("platform: {$v['platform']}", true);
        hd_debug_print("type:     {$v['type']}", true);
        if ($v['platform'] == 'android') {
            $curl = getenv('FS_PREFIX') . "/firmware/bin/curl";
        } else {
            // run curl using path
            $curl = "curl";
        }
        hd_debug_print("used curl: $curl", true);
    }

    return $curl;
}

function get_platform_php()
{
    static $php = null;
    if (is_null($php)) {
        $v = get_platform_info();
        if ($v['platform'] == 'android') {
            $php = '$FS_PREFIX/firmware_ext/php/php-cgi';
        } else {
            $php = getenv('PHP_EXTERNAL');
            if (empty($php)) {
                hd_debug_print("Please define PHP_EXTERNAL environment variable that point to system PHP interpreter!");
            }
        }
        hd_debug_print("used php interpreter: $php", true);
    }

    return $php;
}

/**
 * return product id
 * @return string
 */
function get_product_id()
{
    static $result = null;

    if (is_null($result)) {
        /** @var array $m */
        if (preg_match("/^product_id:(.*)/m", file_get_contents(getenv('FS_PREFIX') . "/tmp/sysinfo.txt"), $m) > 0) {
            $result = trim($m[1]);
        } else {
            $result = "Not detected";
        }
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
        /** @var array $m */
        if (preg_match("/^firmware_version:(.*)/m", file_get_contents(getenv('FS_PREFIX') . "/tmp/sysinfo.txt"), $m) > 0) {
            $result = trim($m[1]);
        } else {
            $result = "Not detected";
        }
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
        /** @var array $m */
        preg_match_all('/^(\d*)_(\d*)_(\D*)(\d*)(.*)?$/', get_raw_firmware_version(), $m, PREG_SET_ORDER);
        $m[0][5] = ltrim($m[0][5], '_');
        $result = array_combine(array('string', 'build_date', 'build_number', 'rev_literal', 'rev_number', 'features'), $m[0]);
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

    /** @var array $m */
    if (is_null($result)
        && preg_match("/^serial_number:(.*)/m", file_get_contents(getenv('FS_PREFIX') . "/tmp/sysinfo.txt"), $m) > 0) {
        $result = trim($m[1]);
    }

    return $result;
}

/**
 * @return string
 */
function get_ip_address()
{
    $ip = '';
    if (is_dune()) {
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
    $addr = '';
    if (is_dune()) {
        $dns = explode(PHP_EOL, shell_exec('getprop | grep "net.dns"'));
        foreach ($dns as $key => $server) {
            /** @var array $m */
            if (preg_match("|(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})|", $server, $m)) {
                $addr .= "nameserver" . ($key + 1) . ": " . $m[1] . ", ";
            }
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
            $mac_addr = @file_get_contents(getenv('FS_PREFIX') . '/tmp/run/dune_mac.txt');
            $mac_addr = strtoupper($mac_addr);
        } else if (is_dune()) {
            $mac_addr = trim(shell_exec('ifconfig eth0 | head -1 | sed "s/^.*HWaddr //"'));
        } else {
            $mac_addr = '';
        }
    }

    return $mac_addr;
}

/**
 * get timezone string system date, for example +0200
 * @return string
 */
function get_local_tz()
{
    $cmd = file_exists('/etc/TZ') ? 'TZ=`cat /etc/TZ` date +%z' : 'date +%z';
    /** @var array $tz */
    /** @var int $rc */
    exec($cmd, $tz, $rc);
    return (($rc !== 0) || (count($tz) !== 1) || !is_numeric($tz[0])) ? '' : $tz[0];
}

/**
 * Return integer of offset in seconds
 * @return int
 */
function get_local_time_zone_offset()
{
    static $offset = null;

    if (is_null($offset)) {
        $local_tz = get_local_tz();
        if ($local_tz === '') {
            $offset = 0;
        } else {
            $sign_ch = $local_tz[0];
            if ($sign_ch === '-') {
                $sign = -1;
            } else {
                $sign = 1;
            }

            $tz_hh = (int)substr($local_tz, 1, 2);
            $tz_mm = (int)substr($local_tz, 3, 2);

            $offset = $sign * ($tz_hh * 60 + $tz_mm) * 60;
        }
    }

    return $offset;
}

/**
 * Adjust timestamp from local time TZ to UTC
 *
 * @param int $time
 * @return int
 */
function from_local_time_zone_offset($time)
{
    return $time - get_local_time_zone_offset();
}

/**
 * Adjust timestamp from UTC to local time TZ
 *
 * @param int $time
 * @return int
 */
function to_local_time_zone_offset($time)
{
    return $time + get_local_time_zone_offset();
}

/**
 * Get timezone or UTC +???? offset name
 * @return string
 */
function getTimeZone()
{
    $local_tz = get_local_tz();
    if ($local_tz === '') {
        $local_tz = "Unknown";
    }

    if (is_android()) {
        /** @var array $tz */
        /** @var int $rc */
        exec('getprop persist.sys.timezone', $tz, $rc);
        $tz_name = (($rc !== 0) || (count($tz) !== 1)) ? '' : $tz[0];
    }

    if (empty($tz_name)) {
        $tz_name = date('e');
    }

    return "$tz_name ($local_tz)";
}

/**
 * @return bool
 */
function is_android()
{
    return is_file("/system/dunehd/init");
}

/**
 * Format time using current timezone offset
 *
 * @param string $fmt
 * @param int $ts
 * @return string
 */
function format_datetime($fmt, $ts)
{
    return gmdate($fmt, to_local_time_zone_offset($ts));
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

    $hours = (int)($n / 3600);
    $remainder = $n % 3600;
    $minutes = $remainder / 60;
    $seconds = $remainder % 60;

    if ($hours > 0) {
        return sprintf("%d:%02d:%02d", $hours, $minutes, $seconds);
    }

    return sprintf("%02d:%02d", $minutes, $seconds);
}

/**
 * @param string $secs
 * @param bool $show_sign
 * @return string
 */
function format_duration_minutes($secs, $show_sign = true)
{
    if ($show_sign) {
        $n = (int)$secs;
    } else {
        $n = (int)abs($secs);
    }

    if (!$show_sign && ($n <= 0 || strlen($secs) <= 0)) {
        return "00:00";
    }

    $hours = $n / 3600;
    $remainder = $n % 3600;
    $minutes = abs($remainder / 60);

    return sprintf(($show_sign ? "%+d:%02d" : "%d:%02d"), $hours, $minutes);
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
 * @param string $key
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
 * @param string $key
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
    # return string (PLAYER_STATE_STANDBY | PLAYER_STATE_BLACK_SCREEN | PLAYER_STATE_NAVIGATOR
    # | PLAYER_STATE_FILE_PLAYBACK | PLAYER_STATE_DVD_PLAYBACK | PLAYER_STATE_BLURAY_PLAYBACK)

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "player_state" | sed -n "s/^.*player_state = /\1/p"';
    return get_shell_exec($cmd);
}

/**
 * @return array|false
 */
function get_player_state_assoc()
{
    return parse_ini_file(getenv('FS_PREFIX') . '/tmp/run/ext_command.state', 0, INI_SCANNER_RAW);
}

/**
 * @return array|false
 */
function get_resume_state_assoc()
{
    return parse_ini_file('/config/resume_state.properties', 0, INI_SCANNER_RAW);
}

/**
 * @return int
 */
function get_standby_mode()
{
    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "player_state" | sed -n "s/^.*player_state = /\1/p"';
    return (int)(get_shell_exec($cmd) === PLAYER_STATE_STANDBY);
}

/**
 * @param int $mode
 * @return string
 */
function set_standby_mode($mode)
{
    # return string (command execution status)
    # argument values (STANDBY_MODE_OFF | STANDBY_MODE_ON)

    $cmd = 'env REQUEST_METHOD="GET" QUERY_STRING="cmd=ir_code&ir_code='
        . (($mode === STANDBY_MODE_OFF) ? GUI_EVENT_DISCRETE_POWER_ON : GUI_EVENT_DISCRETE_POWER_OFF) . CMD_STATUS_GREP;
    return get_shell_exec($cmd);
}

/**
 * @return array
 */
function get_shell_settings()
{
    if (file_exists('/config/settings.properties')) {
        $settings = parse_ini_file('/config/settings.properties', 0, INI_SCANNER_RAW);
        if ($settings !== false) {
            return $settings;
        }
    }
    return array();
}

/**
 * @param string $setting
 * @return string
 */
function get_shell_setting($setting)
{
    $settings = get_shell_settings();
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
    # return string (PLAYBACK_STOPPED | PLAYBACK_INITIALIZING | PLAYBACK_PLAYING | PLAYBACK_PAUSED
    # | PLAYBACK_SEEKING | PLAYBACK_BUFFERING | PLAYBACK_FINISHED | PLAYBACK_DEINITIALIZING)

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "playback_state" | sed -n "s/^.*playback_state = /\1/p"';
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
    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "pause_is_available" | sed -n "s/^.*pause_is_available = /\1/p"';
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

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "playback_speed" | sed -n "s/^.*playback_speed = /\1/p"';
    return get_shell_exec($cmd);
}

/**
 * @param int $value
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

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "playback_duration" | sed -n "s/^.*playback_duration = /\1/p"';
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
    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "playback_position" | sed -n "s/^.*playback_position = /\1/p"';
    return get_shell_exec($cmd);
}

/**
 * @param int $seconds
 * @return string
 */
function set_position_seconds($seconds)
{
    # return string (command execution status)
    $cmd = 'env REQUEST_METHOD="GET" QUERY_STRING="cmd=' . (empty($seconds) ? 'status' : "set_playback_state&position=$seconds") . CMD_STATUS_GREP;
    return get_shell_exec($cmd);
}

/**
 * @param string $speed
 * @param int $seconds
 * @return string
 */
function set_speed_and_position_seconds($speed, $seconds)
{
    # return string (command execution status)

    $cmd = 'env REQUEST_METHOD="GET" QUERY_STRING="cmd='
        . ((empty($speed) || empty($seconds)) ? 'status' : "set_playback_state&speed=$speed&position=$seconds")
        . CMD_STATUS_GREP;
    return get_shell_exec($cmd);
}

/**
 * @return bool
 */
function is_scrambling_detected()
{
    # return boolean

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "scrambling_detected" | sed -n "s/^.*scrambling_detected = /\1/p"';
    return get_shell_exec($cmd) === '1';
}

/**
 * @return string
 */
function get_segment_length_seconds()
{
    # return string (length of one media segment for segment-based media, for HLS returns the value of X-EXT-TARGETDURATION)

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "segment_length" | sed -n "s/^.*segment_length = /\1/p"';
    return get_shell_exec($cmd);
}

/**
 * @return string
 */
function get_playback_url()
{
    # return string

    $url = rtrim(shell_exec('cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "playback_url" | sed -n "s/^.*playback_url = /\1/p"'), "\n");

    /** @var array $m */
    if (preg_match_all('/\/\/(127\.0\.0\.1|localhost).*((htt|rt|rtc|rts|ud)p:\/\/.*$)/i', $url, $m) && !empty($m[2][0])) {
        return $m[2][0];
    }

    return $url;
}


###############################################################################
# Audio controls
###############################################################################

function get_volume()
{
    # return string (value 0..100 - current volume in percents)

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "playback_volume" | sed -n "s/^.*playback_volume = /\1/p"';
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

    /** @var array $m */
    preg_match_all('/audio_track\.(\d)\.(.*)\s=\s(.*$)/mx', file_get_contents('/tmp/run/ext_command.state'), $m);

    $result = array();

    foreach ($m[1] as $key => $value) {
        $result[$value][$m[2][$key]] = $m[3][$key];
    }

    return $result;
}

function get_audio_track()
{
    # Return: 0..N - current audio track index

    return
        rtrim(shell_exec('cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "audio_track" | sed -n "s/^.*audio_track = /\1/p"'), "\n");
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

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "teletext_available" | sed -n "s/^.*teletext_available = /\1/p"';
    return get_shell_exec($cmd);
}

function is_teletext_enabled()
{
    # Return: boolean value of teletext mode is turned

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "teletext_enabled" | sed -n "s/^.*teletext_enabled = /\1/p"';
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

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "teletext_page_number" | sed -n "s/^.*teletext_page_number = /\1/p"';
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

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "teletext_mix_mode" | sed -n "s/^.*teletext_enabled = /\1/p"';
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

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "video_enabled" | sed -n "s/^.*video_enabled = /\1/p"';
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

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "video_zorder" | sed -n "s/^.*video_zorder = /\1/p"';
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

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "osd_zorder" | sed -n "s/^.*osd_zorder = /\1/p"';
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

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "video_on_top" | sed -n "s/^.*video_on_top = /\1/p"';
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

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "playback_window_fullscreen" | sed -n "s/^.*playback_window_fullscreen = /\1/p"';
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

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "playback_window_rect_x" | sed -n "s/^.*playback_window_rect_x = /\1/p"';
    return get_shell_exec($cmd);
}

function get_window_rect_y()
{
    # Return: string value of window rect y

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "playback_window_rect_y" | sed -n "s/^.*playback_window_rect_y = /\1/p"';
    return get_shell_exec($cmd);
}

function get_window_rect_width()
{
    # Return: string value of window rect width

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "playback_window_rect_width" | sed -n "s/^.*playback_window_rect_width = /\1/p"';
    return get_shell_exec($cmd);
}

function get_window_rect_height()
{
    # Return: string value of window rect height

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "playback_window_rect_height" | sed -n "s/^.*playback_window_rect_height = /\1/p"';
    return get_shell_exec($cmd);
}

function set_window_rect($x, $y, $width, $height)
{
    # Return: command execution status

    $cmd = 'env REQUEST_METHOD="GET" QUERY_STRING="cmd='
        . "set_playback_state&window_fullscreen=0&window_rect_x=$x&window_rect_y=$y&window_rect_width=$width&window_rect_height=$height"
        . CMD_STATUS_GREP;
    return get_shell_exec($cmd);
}

function get_clip_rect_x()
{
    # Return: string value of clip rect x

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "playback_clip_rect_x" | sed -n "s/^.*playback_clip_rect_x = /\1/p"';
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

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "playback_clip_rect_width" | sed -n "s/^.*playback_clip_rect_width = /\1/p"';
    return get_shell_exec($cmd);
}

function get_clip_rect_height()
{
    # Return: string value of clip rect height

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "playback_clip_rect_height" | sed -n "s/^.*playback_clip_rect_height = /\1/p"';
    return get_shell_exec($cmd);
}

function set_clip_rect($x, $y, $width, $height)
{
    # Return: command execution status

    $cmd = 'env REQUEST_METHOD="GET" QUERY_STRING="cmd='
        . "set_playback_state&clip_rect_x=$x&clip_rect_y=$y&clip_rect_width=$width&clip_rect_height=$height"
        . CMD_STATUS_GREP;
    return get_shell_exec($cmd);
}

function get_video_source_rect_x()
{
    # Return: string value of video source rect x

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "playback_video_source_rect_x" | sed -n "s/^.*playback_video_source_rect_x = /\1/p"';
    return get_shell_exec($cmd);
}

function get_video_source_rect_y()
{
    # Return: string value of video source rect y

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "playback_video_source_rect_y" | sed -n "s/^.*playback_video_source_rect_y = /\1/p"';
    return get_shell_exec($cmd);
}

function get_video_source_rect_width()
{
    # Return: string value of video source rect width

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "playback_video_source_rect_width" | sed -n "s/^.*playback_video_source_rect_width = /\1/p"';
    return get_shell_exec($cmd);
}

function get_video_source_rect_height()
{
    # Return: string value of video source rect height

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "playback_video_source_rect_height" | sed -n "s/^.*playback_video_source_rect_height = /\1/p"';
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

    $cmd = 'env REQUEST_METHOD="GET" QUERY_STRING="cmd='
        . "set_playback_state&video_source_rect_x=$x&video_source_rect_y=$y&video_source_rect_width=$width&video_source_rect_height=$height"
        . CMD_STATUS_GREP;
    return get_shell_exec($cmd);
}

function get_video_width()
{
    # Return: string value of current video width

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "playback_video_width" | sed -n "s/^.*playback_video_width = /\1/p"';
    return get_shell_exec($cmd);
}

function get_video_height()
{
    # Return: string value of current video height

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "playback_video_height" | sed -n "s/^.*playback_video_height = /\1/p"';
    return get_shell_exec($cmd);
}

function get_video_zoom()
{
    # Return: string value of current video zoom

    $cmd = 'cat $FS_PREFIX/tmp/run/ext_command.state | grep -w "video_zoom" | sed -n "s/^.*video_zoom = /\1/p"';
    return get_shell_exec($cmd);
}

/**
 * @param string $value
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
 * @param DuneVideoZoomPresets::const $preset
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
    return DuneSystem::$properties['tmp_dir_path'] . '/' . ltrim($path, "/");
}

function get_data_path($path = '')
{
    return DuneSystem::$properties['data_dir_path'] . '/' . ltrim($path, "/");
}

function get_install_path($path = '')
{
    return DuneSystem::$properties['install_dir_path'] . '/' . ltrim($path, "/");
}

function get_plugin_cgi_url($path = '')
{
    return DuneSystem::$properties['plugin_cgi_url'] . ltrim($path, "/");
}

function get_plugin_www_url($path = '')
{
    return DuneSystem::$properties['plugin_www_url'] . ltrim($path, "/");
}

function get_plugin_name()
{
    return DuneSystem::$properties['plugin_name'];
}

function export_DuneSystem()
{
    putenv("PLUGIN_NAME=" . DuneSystem::$properties['plugin_name']);
    putenv("PLUGIN_INSTALL_DIR_PATH=" . DuneSystem::$properties['install_dir_path']);
    putenv("PLUGIN_DATA_DIR_PATH=" . DuneSystem::$properties['data_dir_path']);
    putenv("PLUGIN_TMP_DIR_PATH=" . DuneSystem::$properties['tmp_dir_path']);
    putenv("PLUGIN_WWW_URL=" . DuneSystem::$properties['plugin_www_url']);
    putenv("PLUGIN_CGI_URL=" . DuneSystem::$properties['plugin_cgi_url']);
}

/**
 * @param string $image
 * @return string
 */
function get_image_path($image = '')
{
    return get_install_path("img/" . ltrim($image, "/"));
}

/**
 * @param string $image
 * @return string
 */
function get_cached_image_path($image = '')
{
    $cache_image_path = get_data_path(CACHED_IMAGE_SUBDIR);
    create_path($cache_image_path);
    return $cache_image_path . '/' . ltrim($image, "/");
}

function get_cached_image($image)
{
    if (strpos($image, "plugin_file://") === false && file_exists(get_cached_image_path($image))) {
        $image = get_cached_image_path($image);
    }

    return $image;
}

function get_plugin_manifest_info()
{
    $result = array();
    try {
        $manifest_path = get_install_path("dune_plugin.xml");
        if (!file_exists($manifest_path)) {
            throw new Exception("Plugin manifest not found!");
        }

        $xml = parse_xml_file($manifest_path);
        if ($xml === null) {
            throw new Exception("Empty plugin manifest!");
        }

        $result['app_name'] = (string)$xml->name;
        $result['app_caption'] = (string)$xml->caption;
        $result['app_class_name'] = (string)$xml->class_name;
        $result['app_version'] = (string)$xml->version;
        $ver = explode('.', $result['app_version']);
        $result['app_base_version'] = "$ver[0].$ver[1]";
        $result['app_version_idx'] = (string)safe_get_value($xml, 'version_index', '0');
        $result['app_release_date'] = (string)$xml->release_date;
        $result['app_background'] = (string)$xml->background;
        $result['app_manifest_path'] = $manifest_path;

        foreach (func_get_args() as $node_name) {
            $result[$node_name] = json_decode(json_encode($xml->xpath("//$node_name")), true);
        }
    } catch (Exception $ex) {
        print_backtrace_exception($ex);
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
        if (($item === '.') || ($item === '..') || !is_dir($path . '/' . $item)) {
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

function is_r22_or_higher()
{
    return safe_get_value(get_parsed_firmware_ver(), 'rev_number', 0) > 21;
}

function is_r24_or_higher()
{
    return safe_get_value(get_parsed_firmware_ver(), 'rev_number', 0) > 22;
}

function is_ext_epg_supported()
{
    $apk_subst = getenv('FS_PREFIX');
    return (defined('PluginTvInfo::ext_epg_channel_ids_url') && is_file( "$apk_subst/firmware_ext/plugins/ext_epg/dune_plugin.xml"));
}

function normalizePath($path) {
    return str_replace(array('\\', '/'), DIRECTORY_SEPARATOR, $path);
}

/**
 * Returns the path to the directory of the active skin (no trailing slash)
 *
 * @return string
 */
function get_active_skin_path()
{
    $skin_path = getenv('FS_PREFIX') . '/tmp/dune_skin_dir.txt';
    if (file_exists($skin_path)) {
        return getenv('FS_PREFIX') . rtrim(trim(preg_replace('/^.*=/', '', file_get_contents($skin_path))), '/');
    }

    hd_debug_print("Error in class " . __METHOD__ . " ! Can not determine the path to the active skin. $skin_path");
    return '';
}

/**
 * Returns the path to the skin configuration
 *
 * @return string
 */
function get_skin_config_path()
{
    return getenv('FS_PREFIX') . "/flashdata/dune_skin/dune_skin_config.xml";
}

/**
 * Returns the specified path (no trailing slash), creating directories along the way
 * @param string $path
 * @param int $dir_mode in octal
 * @return string
 */
function get_paved_path($path, $dir_mode = 0777)
{
    if (!create_path($path, $dir_mode)) {
        hd_debug_print("Directory '$path' was not created");
    }

    return rtrim($path, '/');
}

function get_slash_trailed_path($path)
{
    if (!empty($path) && substr($path, -1) !== '/') {
        $path .= '/';
    }

    return $path;
}

function get_noslash_trailed_path($path)
{
    return rtrim($path, '/');
}

function get_filename($path)
{
    $ar = explode('/', $path);
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

function safe_unlink($path)
{
    if (!empty($path) && file_exists($path) && !is_dir($path)) {
        unlink($path);
    }
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
    hd_print_separator();
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
        'Plugin Memory' => ini_get('memory_limit'),
        'libCURL Version' => "{$values['version']} ({$values['host']}) {$values['ssl_version']} zlib/{$values['libz_version']})",
    );

    if (class_exists("SQLite3")) {
        $sqlite_ver = SQLite3::version();
        $table['SQLite3 Version'] = $sqlite_ver['versionString'];
    }

    $table = safe_merge_array($table, DuneSystem::$properties);

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
function get_dune_model()
{
    static $models = array(
        // android models
        'boxy_apk' => 'Homatics Boxy',
        'dune_apk' => 'Homatics Models',
        'dune_whale_apk' => 'Dune HD TV',
        // android models
        'tv173b' => 'Neo 4K (revision tv173b)',
        'tv174a' => 'Neo 4K T2',
        'tv174b' => 'Neo 4K T2 Plus (revision tv174b)',
        'tv174c' => 'Neo 4K T2 Plus (revision tv174c)',
        'tv175a' => 'Neo 4K (revision tv175a)',
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
        'tv182a' => 'AV1 4K',
        'tv182b' => 'AV1 4K Plus',
        'tv184a' => 'Pro Vision 4K',
        'tv188b' => 'Pro 8K Plus',
        'tv274a' => 'Sky 4K Plus',
        'tv288b' => 'Pro One 8K Plus',
        'tv292a' => 'Pro 4K',
        'tv292b' => 'Pro 4K',
        'tv388a' => 'Solo 8K',
        'tv393a' => 'Pro 4K Plus',
        'tv494b' => 'Real Vision 4K Duo',
        'tv689a' => 'Duo Cinema 8K',
        'tv788a' => 'Max 8K',
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
        $sys_lang = file_exists("/firmware/translations/dune_language_{$sys_settings['interface_language']}.txt")
            ? $sys_settings['interface_language']
            : 'english';

        /** @var array $m */
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
 * @param object $user_input
 * @param bool $force
 * @return void
 */
function dump_input_handler($user_input, $force = false)
{
    if (!LogSeverity::$is_debug && !$force) {
        return;
    }

    hd_debug_print();

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
    $path = rtrim($path, '/');
    if (is_dir($path)) {
        $files = array_diff(scandir($path), array('.', '..'));
        if ($pattern !== null) {
            $files = preg_grep($pattern, $files);
        }

        if ($files !== false) {
            foreach ($files as $file) {
                $full_path = $path . '/' . $file;
                if ($exclude_dir && !is_file($full_path)) continue;

                $list[] = $full_path;
            }
        }
    }
    return $list;
}

/**
 * @param string $dir
 * @return bool
 */
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
        if (!delete_directory($dir . '/' . $item)) {
            return false;
        }
    }

    return rmdir($dir);
}

/**
 * @param string $dir
 */
function clear_directory($dir)
{
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..') continue;
        delete_directory($dir . '/' . $item);
    }
}

/**
 * Check if url has http?:// scheme
 *
 * @param string $url
 * @return bool
 */
function is_proto_http($url)
{
    return strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0;
}

/**
 * Check if url has udp:// scheme
 *
 * @param string $url
 * @return bool
 */
function is_proto_udp($url)
{
    return strpos($url, 'udp://') === 0;
}

/**
 * Check if url has file:// scheme
 *
 * @param string $url
 * @return bool
 */
function is_proto_file($url)
{
    return strpos($url, 'file://') === 0;
}

/**
 * Check if url has rtsp:// scheme
 *
 * @param string $url
 * @return bool
 */
function is_proto_rtsp($url)
{
    return strpos($url, 'rtsp://') === 0;
}

function is_supported_proto($url)
{
    return is_proto_http($url) || is_proto_udp($url) || is_proto_file($url) || is_proto_rtsp($url);
}

/**
 * Replace https scheme to http
 *
 * @param string $url
 * @return string
 */
function replace_https($url)
{
    return str_replace('https://', 'http://', $url);
}

/**
 * This is more efficient then merge_array in the loops
 * Do not use with indexed arrays!
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

/**
 * Safe get value from array or object by key or the keys chain
 *
 * @param array|object $src
 * @param string|array $param
 * @param mixed $default
 * @return mixed
 */
function safe_get_value($src, $param, $default = null)
{
    // No key to resolve. Null key or empty string is not allowed
    if (is_null($param) || $param === '') {
        return $default;
    }

    // Base case: single key
    if (!is_array($param)) {
        if (is_array($src)) {
            return isset($src[$param]) ? $src[$param] : $default;
        }

        if (is_object($src)) {
            return isset($src->{$param}) ? $src->{$param} : $default;
        }

        return $default;
    }

    // Recursive case: key path

    // empty array also is not allowed
    if (empty($param)) {
        return $default;
    }

    $key = array_shift($param);

    if (is_array($src)) {
        if (!isset($src[$key])) {
            return $default;
        }
        return safe_get_value($src[$key], $param, $default);
    }

    if (is_object($src)) {
        if (!isset($src->{$key})) {
            return $default;
        }
        return safe_get_value($src->{$key}, $param, $default);
    }

    return $default;
}

function extract_column($rows, $column)
{
    return array_map(function ($row) use ($column) { return $row[$column]; }, $rows);
}

/**
 * @param object $plugin_cookies
 * @param string $param
 * @param bool $default
 * @return mixed
 */
function get_cookie_bool_param($plugin_cookies, $param, $default = true)
{
    if (!isset($plugin_cookies->{$param})) {
        $plugin_cookies->{$param} = SwitchOnOff::to_def($default);
    }

    return $plugin_cookies->{$param};
}

/**
 * @param object $plugin_cookies
 * @param string $param
 * @return string
 */
function toggle_cookie_param($plugin_cookies, $param)
{
    $old = safe_get_value($plugin_cookies, $param, SwitchOnOff::off);
    $new = SwitchOnOff::toggle($old);
    $plugin_cookies->{$param} = $new;
    hd_debug_print("toggle new cookie param $param: $old -> $new", true);
    return $new;
}

/**
 * @param string $doc
 * @return SimpleXMLElement
 * @throws Exception
 */
function parse_xml_document($doc)
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
function parse_xml_file($path)
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
 * @param mixed $content
 */
function store_to_json_file($path, $content)
{
    if (empty($path)) {
        hd_debug_print("Path not set");
    } else {
        file_put_contents($path, json_encode($content));
    }
}

/**
 * @param string $path
 * @param bool $assoc
 */
function parse_json_file($path, $assoc = true)
{
    if (empty($path) || !file_exists($path)) {
        hd_debug_print("Path not exists: $path");
        return false;
    }

    return json_decode(file_get_contents($path), $assoc);
}

function json_format_readable($content)
{
    return json_format($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function json_format_unescaped($content)
{
    return json_format($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function json_format($content, $options = 0)
{
    $pretty_print = (bool)($options & JSON_PRETTY_PRINT);
    $unescape_unicode = (bool)($options & JSON_UNESCAPED_UNICODE);
    $unescape_slashes = (bool)($options & JSON_UNESCAPED_SLASHES);

    if ($content instanceof Json_Serializer) {
        $json_str = $content->__toString();
    } else {
        $json_str = json_encode($content);
    }
    if (!$json_str || (!$pretty_print && !$unescape_unicode && !$unescape_slashes)) {
        return $json_str;
    }

    $result = '';
    $pos = 0;
    $strLen = strlen($json_str);
    $indentStr = $pretty_print ? ' ': '';
    $newLine = $pretty_print ? PHP_EOL : '';
    $outOfQuotes = true;
    $buffer = '';
    $noescape = true;

    for ($i = 0; $i < $strLen; $i++) {
        // take the next character in the string
        $char = $json_str[$i];

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

            if ($unescape_unicode) {
                $pattern = "/\\\\u([0-9a-fA-F]{4})/";
                if (function_exists('mb_convert_encoding')) {
                    $callback = function ($match) {
                        return mb_convert_encoding(pack('H*', $match[1]), 'UTF-8', 'UCS-2BE');
                    };
                } else {
                    $callback = function ($match) {
                        return html_entity_decode("&#x$match[1];", ENT_QUOTES, 'UTF-8');
                    };
                }
                $buffer = preg_replace_callback($pattern, $callback, $buffer);
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
            $char .= $indentStr;
        } else if ('}' === $char || ']' === $char) {
            $pos--;
            $prevChar = $json_str[$i - 1];

            if ('{' !== $prevChar && '[' !== $prevChar) {
                // If this character is the end of an element,
                // output a new line and indent the next line
                $result .= $newLine . str_repeat($indentStr, $pos);
            } else {
                // Collapse empty {} and []
                $result = rtrim($result) . $newLine . $newLine . $indentStr;
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
 * return wrapped string
 *
 * @param string $long_string
 * @param int $max_chars
 * @param string $separator
 * @return string
 */
function wrap_string_to_lines($long_string, $max_chars, $separator = PHP_EOL)
{
    $lines = array_slice(
        explode(PHP_EOL,
            iconv('Windows-1251', 'UTF-8',
                wordwrap(iconv('UTF-8', 'Windows-1251',
                    trim(preg_replace('/([!?])\.+\s*$/Uu', '$1', $long_string))),
                    $max_chars, $separator, true))
        ),
        0, 15
    );

    return implode(PHP_EOL, $lines);
}

function is_assoc_array($array)
{
    $keys = array_keys($array);
    return $keys !== array_keys($keys);
}

function register_all_known_events($handler, &$actions)
{
    $all_events = array(
        GUI_EVENT_KEY_V_PLUS,
        GUI_EVENT_KEY_V_MINUS,
        GUI_EVENT_KEY_NEXT,
        GUI_EVENT_KEY_PREV,
        GUI_EVENT_KEY_FIP_NEXT,
        GUI_EVENT_KEY_FIP_PREV,
        GUI_EVENT_KEY_SETUP,
        GUI_EVENT_KEY_SELECT,
        GUI_EVENT_KEY_CLEAR,
        GUI_EVENT_KEY_PAUSE,
        GUI_EVENT_KEY_FWD,
        GUI_EVENT_KEY_REW,
        GUI_EVENT_KEY_SLOW,
        GUI_EVENT_KEY_TOP_MENU,
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
        GUI_EVENT_KEY_RECENT,
        GUI_EVENT_KEY_TV,
        GUI_EVENT_KEY_MOVIES,
        GUI_EVENT_KEY_MUSIC,
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
    );

    foreach ($all_events as $action) {
        if (!isset($actions[$action])) {
            hd_debug_print("register event: $action");
            $actions[$action] = User_Input_Handler_Registry::create_action($handler, $action);
        }
    }
}

function dune_params_to_array($str)
{
    if ($str === '[]') {
        $str = '';
    }

    if (empty($str)) {
        return array();
    }

    $params_array = array();
    $dune_params = explode(',', $str);
    foreach ($dune_params as $param) {
        $param_pair = explode(':', $param);
        if (empty($param_pair) || count($param_pair) < 2) continue;

        $param_pair[0] = trim($param_pair[0]);
        if (strpos($param_pair[1], ",,") !== false) {
            $param_pair[1] = str_replace(array(",,", ",", "%2C%2C"), array("%2C%2C", ",,", ",,"), $param_pair[1]);
        } else {
            $param_pair[1] = str_replace(",", ",,", $param_pair[1]);
        }

        $params_array[$param_pair[0]] = $param_pair[1];
    }
    return $params_array;
}

function dune_params_array_to_string($value)
{
    $dune_params_str = '';
    if (is_array($value)) {
        foreach ($value as $name => $param) {
            if (!empty($dune_params_str)) {
                $dune_params_str .= ',';
            }
            $dune_params_str .= "$name:$param";
        }
    }
    return $dune_params_str;
}

function send_process_signal($pid, $sig_num) {
    if (function_exists("posix_kill")) {
        return posix_kill($pid, $sig_num);
    }
    /** @var array $out */
    /** @var int $rc */
    exec("kill -s $sig_num $pid 2>&1", $out, $rc);
    return !$rc;
}

/**
 * Return true if palette is patched or not exist
 *
 * @return true
 */
function color_palette_check()
{
    global $dune_default_colors_values;

    $skin_path = get_active_skin_path();
    $skin_config = "$skin_path/dune_skin_config.xml";

    if (!file_exists($skin_config)) {
        hd_debug_print("'$skin_config' does not exist");
        return true;
    }

    $result = 1;
    $dom = new DomDocument();
    $dom->load($skin_config);
    $color = $dom->getElementsByTagName('color');
    /** @var DOMElement $item */
    foreach ($color as $item) {
        $color_index = $item->getAttribute('index');
        $color_value = $item->getAttribute('value');
        if ($color_index !== '' && $color_value !== '' && isset($dune_default_colors_values[$color_index])) {
            $result &= ($color_value === $dune_default_colors_values[$color_index]);
        }
    }

    return (bool)$result;
}

/**
 * Patch system or custom palette for default system color
 *
 * @param string $error
 * @return array|false
 */
function color_palette_patch(&$error)
{
    global $dune_default_colors_values;

    $error = '';
    clearstatcache();

    $skin_path = get_active_skin_path();
    $skin_config = "$skin_path/dune_skin_config.xml";
    if (!file_exists($skin_config)) {
        $error = "'$skin_config' does not exist";
        return false;
    }

    $origin_skin_config = file_get_contents($skin_config);

    $dom = new DomDocument();
    $dom->load($skin_config);
    $color = $dom->getElementsByTagName('color');

    foreach ($color as $item) {
        $color_index = null;
        $color_value = null;
        foreach ($item->attributes as $attrName => $attrNode) {
            if ($attrName == 'index') {
                $color_index = $attrNode->value;
            }
            else if ($attrName == 'value') {
                $color_value = $attrNode->value;
            }

            if (is_null($color_index) || is_null($color_value)) continue;

            if (isset($dune_default_colors_values[$color_index])) {
                $attrNode->ownerElement->setAttribute('value', $dune_default_colors_values[$color_index]);
            }
        }
    }

    $reboot_action = Action_Factory::restart();
    $xml = $dom->saveXML();
    // cut <?xml> tag
    $patched_skin_config = substr($xml, strpos($xml, '?>') + 2);

    if (preg_match('/\/*firmware/', $skin_path)) {
        // copy system skin to custom skin
        $custom_skin_path = preg_replace('/(.*\/(flashdata|persistfs)).*$/', "$1", get_data_path()) . '/dune_skin';
        hd_debug_print("New custom skin path: $custom_skin_path");

        // clear existing custom skin
        delete_directory($custom_skin_path);
        if (!create_path($custom_skin_path)) {
            $error = 'The directory for the custom skin in the system store is not available!';
            hd_debug_print("$error Process was terminated");
            return false;
        }

        foreach (glob("$skin_path/*") as $file) {
            $file = realpath($file);
            $basename = basename($file);

            if (is_dir($file)) {
                recursive_copy($file, "$custom_skin_path/$basename");
            } else if ($basename == 'dune_skin_config.xml') {
                if (!file_put_contents("$custom_skin_path/$basename", $patched_skin_config)) {
                    $error = "An unexpected error occurred when saving to save the 'dune_skin_config.xml'!";
                    hd_debug_print("$error The process was terminated");
                    return false;
                }
            } else if (!copy($file, "$custom_skin_path/$basename")) {
                $error = 'In the process of copying a skin file error occurred';
                hd_debug_print("$error The process was terminated");
                return false;
            }
        }

        $system_settings = get_shell_settings();
        if (!empty($system_settings)) {
            $system_settings['gui_skin'] = 'custom';
            $system_settings['appearance'] = 'custom';
            $reboot_action = Action_Factory::change_settings($system_settings, false, true);
        }
    } else if (!file_put_contents($skin_config, $patched_skin_config)) {
        $error = "An unexpected error occurred when saving to save the '$skin_config'";
        hd_debug_print("$error The process was terminated");
        return false;
    }

    create_path(get_data_path('skin_backup'));
    @file_put_contents(get_data_path('skin_backup/') . md5($patched_skin_config), $origin_skin_config);
    return Action_Factory::show_main_screen($reboot_action);
}

function color_palette_restore()
{
    $skin_config = get_active_skin_path() . '/dune_skin_config.xml';
    $hash = md5(file_get_contents($skin_config));
    $backup_storage_path = get_data_path('skin_backup');

    if (!file_exists($skin_config)) {
        hd_debug_print("Skin config file does not exist!");
        return null;
    }

    if (!file_exists($backup_storage_path)) {
        hd_debug_print("Backup storage path does not exist!");
        return null;
    }

    foreach (glob($backup_storage_path . '/*') as $file) {
        if (basename($file) !== $hash) continue;

        if (copy($file, $skin_config)) {
            safe_unlink($file);
        }

        hd_print('Skin colors restored succesfull!');
        break;
    }

    return Action_Factory::show_main_screen(Action_Factory::restart());
}

function recursive_copy($source, $target)
{
    if (!is_dir($source)) {
        copy($source, $target);
    } else {
        @mkdir($target);
        $dir = dir($source);
        while (($entry = $dir->read()) !== false) {
            if ($entry == '.' || $entry == '..') continue;

            recursive_copy("$source/$entry", "$target/$entry");
        }

        $dir->close();
    }
}

/**
 * Convert dune serial number to uuid
 * @return string
 */
function get_uuid()
{
    // convert 0000-0003-6081-4dc6-c48f-dbd4-bb02-7cba
    // to 00000003-6081-4dc6-c48f-dbd4bb027cba
    $parts = explode('-', strtolower(get_serial_number()));
    return "$parts[0]$parts[1]-$parts[2]-$parts[3]-$parts[4]-$parts[5]$parts[6]$parts[7]";
}
